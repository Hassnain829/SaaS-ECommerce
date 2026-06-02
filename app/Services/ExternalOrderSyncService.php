<?php

namespace App\Services;

use App\Exceptions\ExternalOrderConflictException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\Fulfillment\FulfillmentOriginRouter;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Support\OrderLifecycle;
use App\Support\ProductVariantLabel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalOrderSyncService
{
    private const SOURCE = 'external_checkout';

    private const CHANNEL = 'api';

    private const PAYMENT_STATUS_MAP = [
        'paid' => OrderLifecycle::PAYMENT_PAID,
        'pending' => OrderLifecycle::PAYMENT_PENDING,
        'authorized' => OrderLifecycle::PAYMENT_AUTHORIZED,
        'cod_pending' => OrderLifecycle::PAYMENT_PENDING,
        'bank_transfer_pending' => OrderLifecycle::PAYMENT_PENDING,
    ];

    private const EXTERNAL_FULFILLMENT_STATUS_MAP = [
        'pending' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        'open' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        'unfulfilled' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        'processing' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        'partial' => OrderLifecycle::FULFILLMENT_PARTIAL,
        'partially_shipped' => OrderLifecycle::FULFILLMENT_PARTIAL,
        'shipped' => OrderLifecycle::FULFILLMENT_PARTIAL,
        'delivered' => OrderLifecycle::FULFILLMENT_FULFILLED,
        'fulfilled' => OrderLifecycle::FULFILLMENT_FULFILLED,
    ];

    public function __construct(
        private readonly InventorySyncService $syncService,
        private readonly InventoryReservationService $reservationService,
        private readonly OrderEventRecorder $eventRecorder,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CustomerMetricsService $customerMetricsService,
        private readonly ChannelOwnershipService $channelOwnership,
        private readonly FulfillmentOriginRouter $originRouter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{order: Order, created: bool}
     */
    public function sync(Store $store, array $payload, string $requestHash): array
    {
        $existingResult = $this->resolveExistingExternalOrder($store, $payload, $requestHash);
        if ($existingResult !== null) {
            return $existingResult;
        }

        $rawPaymentStatus = (string) ($payload['payment_status'] ?? '');
        if (! array_key_exists($rawPaymentStatus, self::PAYMENT_STATUS_MAP)) {
            throw ValidationException::withMessages([
                'payment_status' => 'Only paid, authorized, pending, cash on delivery pending, or bank transfer pending external orders can be synced now.',
            ]);
        }

        return DB::transaction(function () use ($store, $payload, $requestHash, $rawPaymentStatus): array {
            $customer = $this->upsertCustomer($store, $payload['customer']);
            $items = $this->prepareItems($store, $payload['items']);
            $totals = $this->totals($items, $payload);
            $paymentStatus = self::PAYMENT_STATUS_MAP[$rawPaymentStatus];
            $orderStatus = in_array($paymentStatus, [OrderLifecycle::PAYMENT_PAID, OrderLifecycle::PAYMENT_AUTHORIZED], true)
                ? OrderLifecycle::ORDER_CONFIRMED
                : OrderLifecycle::ORDER_PENDING;
            $placedAt = filled($payload['placed_at'] ?? null)
                ? Carbon::parse($payload['placed_at'])
                : now();
            $shippingSnapshot = $this->shippingSnapshot($payload, $totals, $store);
            $fulfillmentSnapshot = $this->fulfillmentSnapshot($payload);
            $fulfillmentStatus = $this->mapFulfillmentStatus($fulfillmentSnapshot);
            $ownershipSnapshot = $this->channelOwnership->externalCheckoutConfig($store);
            $inventoryOwner = $this->channelOwnership->inventoryOwner($store, ChannelOwnershipService::CHANNEL_EXTERNAL);
            $usesPlatformInventory = $inventoryOwner === ChannelOwnershipService::OWNER_PLATFORM;
            $routingResult = $usesPlatformInventory
                ? $this->originRouter->routeForCheckout($store, $items, $payload['shipping_address'])
                : null;
            $routingSnapshot = $routingResult?->toSnapshot();

            $order = Order::query()->create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'order_number' => $this->orderNumberGenerator->generate($store),
                'external_order_number' => $payload['external_order_number'] ?? null,
                'external_order_id' => $payload['external_order_id'] ?? null,
                'external_checkout_reference' => $payload['external_checkout_reference'] ?? null,
                'status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => $fulfillmentStatus,
                'order_source' => self::SOURCE,
                'channel' => self::CHANNEL,
                'currency_code' => strtoupper((string) ($payload['currency_code'] ?? $store->currency ?? 'USD')),
                'exchange_rate' => 1,
                'item_count' => count($items),
                'total_quantity' => array_sum(array_map(fn (array $item): int => (int) $item['quantity'], $items)),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping' => $totals['shipping'],
                'tax' => $totals['tax'],
                'total' => $totals['grand_total'],
                'grand_total' => $totals['grand_total'],
                'outstanding_total' => $paymentStatus === OrderLifecycle::PAYMENT_PAID ? 0 : $totals['grand_total'],
                'payment_method' => $payload['payment_method'] ?? null,
                'payment_gateway' => $payload['payment_gateway'] ?? null,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'billing_same_as_shipping' => $this->billingSameAsShipping($payload),
                'notes' => $payload['notes'] ?? null,
                'placed_at' => $placedAt,
                'confirmed_at' => $orderStatus === OrderLifecycle::ORDER_CONFIRMED ? $placedAt : null,
                'meta' => array_filter([
                    'shipping' => $shippingSnapshot !== [] ? $shippingSnapshot : null,
                    'fulfillment' => $fulfillmentSnapshot !== [] ? $fulfillmentSnapshot : null,
                    'fulfillment_routing' => $routingSnapshot,
                    'channel_ownership' => [
                        'checkout_owner' => $ownershipSnapshot['checkout_owner'] ?? ChannelOwnershipService::OWNER_EXTERNAL,
                        'payment_owner' => $ownershipSnapshot['payment_owner'] ?? ChannelOwnershipService::OWNER_EXTERNAL,
                        'shipping_owner' => $ownershipSnapshot['shipping_owner'] ?? ChannelOwnershipService::OWNER_EXTERNAL,
                        'fulfillment_owner' => $ownershipSnapshot['fulfillment_owner'] ?? ChannelOwnershipService::OWNER_EXTERNAL,
                        'inventory_owner' => $inventoryOwner,
                    ],
                    'external_checkout' => [
                        'request_hash' => $requestHash,
                        'received_at' => now()->toISOString(),
                        'raw_payment_status' => $rawPaymentStatus,
                        'external_order_id' => $payload['external_order_id'] ?? null,
                        'external_checkout_reference' => $payload['external_checkout_reference'] ?? null,
                        'discounts' => $payload['discounts'] ?? [],
                    ],
                ], fn ($value): bool => $value !== null),
            ]);

            $this->createOrderAddress($order, 'shipping', $payload['shipping_address'], $customer);
            $this->saveCustomerShippingAddress($customer, $payload['shipping_address']);

            if (! $order->billing_same_as_shipping && is_array($payload['billing_address'] ?? null)) {
                $this->createOrderAddress($order, 'billing', $payload['billing_address'], $customer);
            }

            $reservationCount = 0;
            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];

                if ($usesPlatformInventory) {
                    $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
                    $reservation = $this->reservationService->reserve(
                        $inventoryItem,
                        (int) $item['quantity'],
                        'external_order',
                        (string) $order->id,
                        $routingResult?->originLocation,
                        null,
                        [
                            'order' => $order,
                            'source' => self::SOURCE,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'reference_code' => $order->order_number,
                            'checkout_reference' => $payload['external_checkout_reference'] ?? null,
                            'metadata' => [
                                'external_order_number' => $order->external_order_number,
                                'external_line_id' => $item['external_line_id'],
                            ],
                        ]
                    );

                    $this->reservationService->commit($reservation, [
                        'source' => self::SOURCE,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reference_code' => $order->order_number,
                    ]);
                    $this->reservationService->deductCommitted($reservation, [
                        'source' => self::SOURCE,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reference_code' => $order->order_number,
                    ]);
                    $reservationCount++;
                }

                $product = $variant->product;
                $primaryImage = $product?->primaryImage();

                $order->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product?->name ?? 'Catalog item',
                    'sku_snapshot' => $variant->sku,
                    'product_slug_snapshot' => $product?->slug,
                    'brand_name_snapshot' => $product?->brand?->name,
                    'product_image_snapshot' => $primaryImage?->image_path,
                    'product_type_snapshot' => $product?->product_type,
                    'variant_label' => $item['variant_label'],
                    'variant_details' => $item['variant_details'],
                    'meta' => [
                        'external_line_id' => $item['external_line_id'],
                        'source' => self::SOURCE,
                    ],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total' => $item['subtotal'],
                    'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                ]);
            }

            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_EXTERNAL_ORDER_RECEIVED,
                'External order received',
                'Order was synced from an external checkout.',
                [
                    'source' => self::SOURCE,
                    'external_order_number' => $order->external_order_number,
                    'external_checkout_reference' => $order->external_checkout_reference,
                ],
                null,
                $placedAt,
            );
            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_ORDER_CREATED,
                'Order created',
                'Order was created from external checkout sync.',
                ['source' => self::SOURCE],
                null,
                $placedAt,
            );
            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_PAYMENT_STATUS_RECORDED,
                'Payment status recorded',
                'Payment status was recorded from the external checkout.',
                [
                    'payment_status' => $order->payment_status,
                    'payment_gateway' => $order->payment_gateway,
                    'payment_reference' => $order->payment_reference,
                ],
            );
            if ($usesPlatformInventory) {
                if (is_array($routingSnapshot) && $routingSnapshot !== []) {
                    $this->eventRecorder->record(
                        $order,
                        OrderLifecycle::EVENT_FULFILLMENT_ORIGIN_SELECTED,
                        'Fulfillment origin selected',
                        'This order will be fulfilled from '.(data_get($routingSnapshot, 'origin_name') ?: 'the selected fulfillment location').'.',
                        $routingSnapshot,
                    );
                }
                $this->eventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_INVENTORY_RESERVED,
                    'Inventory reserved',
                    'Stock was reserved because this store uses dashboard inventory for external orders.',
                    ['reservation_count' => $reservationCount, 'total_quantity' => $order->total_quantity],
                );
                $this->eventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
                    'Inventory deducted',
                    'Stock was deducted because this store uses dashboard inventory for external orders.',
                    ['item_count' => $order->item_count, 'total_quantity' => $order->total_quantity],
                );
            } else {
                $this->eventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_INVENTORY_EXTERNAL_MANAGED,
                    'Inventory managed externally',
                    'Dashboard stock was not changed for this external order.',
                    ['inventory_owner' => $inventoryOwner],
                );
            }

            if ($shippingSnapshot !== []) {
                $this->eventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_EXTERNAL_SHIPPING_RECORDED,
                    'External shipping recorded',
                    'Shipping details were recorded from the external storefront.',
                    $shippingSnapshot,
                );
            }

            if ($fulfillmentSnapshot !== []) {
                $this->eventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_EXTERNAL_FULFILLMENT_RECORDED,
                    'External fulfillment recorded',
                    'Fulfillment details were recorded from the external storefront.',
                    $fulfillmentSnapshot,
                );
            }

            $this->customerMetricsService->recalculate($customer);

            return [
                'order' => $order->load(['items', 'addresses', 'customer', 'events']),
                'created' => true,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{order: Order, created: bool}|null
     */
    private function resolveExistingExternalOrder(Store $store, array $payload, string $requestHash): ?array
    {
        $existing = null;

        if (filled($payload['external_order_id'] ?? null)) {
            $existing = Order::query()
                ->where('store_id', $store->id)
                ->where('order_source', self::SOURCE)
                ->where('channel', self::CHANNEL)
                ->where('external_order_id', $payload['external_order_id'])
                ->first();
        } elseif (filled($payload['external_order_number'] ?? null)) {
            $existing = Order::query()
                ->where('store_id', $store->id)
                ->where('order_source', self::SOURCE)
                ->where('channel', self::CHANNEL)
                ->where('external_order_number', $payload['external_order_number'])
                ->first();
        }

        if (! $existing) {
            return null;
        }

        $existingHash = data_get($existing->meta, 'external_checkout.request_hash');
        if (is_string($existingHash) && hash_equals($existingHash, $requestHash)) {
            return [
                'order' => $existing->load(['items', 'addresses', 'customer', 'events']),
                'created' => false,
            ];
        }

        $reference = filled($payload['external_order_id'] ?? null)
            ? 'external order id'
            : 'external order number';

        throw new ExternalOrderConflictException("An order with this {$reference} already exists for a different request.");
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    private function upsertCustomer(Store $store, array $customerData): Customer
    {
        $email = strtolower(trim((string) $customerData['email']));
        $fullName = trim((string) ($customerData['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = trim((string) ($customerData['name'] ?? ''));
        }
        $firstName = trim((string) ($customerData['first_name'] ?? ''));
        $lastName = trim((string) ($customerData['last_name'] ?? ''));

        if ($fullName === '') {
            $fullName = trim($firstName.' '.$lastName);
        }

        if ($fullName === '') {
            $fullName = $email;
        }

        if ($firstName === '' && $lastName === '') {
            [$firstName, $lastName] = $this->splitName($fullName);
        }

        return Customer::query()->updateOrCreate(
            ['store_id' => $store->id, 'email' => $email],
            [
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'full_name' => $fullName,
                'phone' => $customerData['phone'] ?? null,
                'source' => self::SOURCE,
                'preferred_currency' => $store->currency ?? 'USD',
                'meta' => [
                    'last_external_checkout_sync_at' => now()->toISOString(),
                ],
            ]
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function prepareItems(Store $store, array $rows): array
    {
        $variantIds = collect($rows)
            ->pluck('variant_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->with(['product.brand', 'product.images', 'options.variationType'])
            ->where('store_id', $store->id)
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($rows as $index => $row) {
            $variant = $variants->get((int) ($row['variant_id'] ?? 0));

            if (! $variant || ! $variant->product || (int) $variant->product->store_id !== (int) $store->id) {
                throw ValidationException::withMessages([
                    'items.'.($index).'.variant_id' => 'Choose a product variant that belongs to this store.',
                ]);
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $unitPrice = $this->money($row['unit_price'] ?? $variant->price);
            $variantCount = $variant->product?->variants()->count() ?? 1;
            $variantLabel = ProductVariantLabel::forVariant($variant, 0, $variantCount);

            $items[] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $this->money($unitPrice * $quantity),
                'external_line_id' => $row['external_line_id'] ?? null,
                'variant_label' => $variantLabel,
                'variant_details' => [
                    'options' => $variant->options
                        ->map(fn ($option): array => [
                            'group' => $option->variationType?->name ?? 'Option',
                            'value' => $option->value,
                        ])
                        ->values()
                        ->all(),
                    'external_line_id' => $row['external_line_id'] ?? null,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $payload
     * @return array{subtotal: float, shipping: float, tax: float, discount: float, grand_total: float}
     */
    private function totals(array $items, array $payload): array
    {
        $totalsBlock = is_array($payload['totals'] ?? null) ? $payload['totals'] : [];
        $subtotal = $this->money($totalsBlock['subtotal'] ?? array_sum(array_map(fn (array $item): float => (float) $item['subtotal'], $items)));
        $shipping = $this->money($totalsBlock['shipping'] ?? $payload['shipping_total'] ?? 0);
        $tax = $this->money($totalsBlock['tax'] ?? $payload['tax_total'] ?? 0);
        $discount = $this->money($totalsBlock['discount'] ?? $payload['discount_total'] ?? 0);
        $grandTotal = $this->money($totalsBlock['total'] ?? $totalsBlock['grand_total'] ?? null);

        if ($grandTotal === 0.0 && ! isset($totalsBlock['total'], $totalsBlock['grand_total'])) {
            $grandTotal = $this->money(max(0, $subtotal + $shipping + $tax - $discount));
        }

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'discount' => $discount,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasExplicitShippingData(array $payload): bool
    {
        $shipping = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : [];
        if ($shipping !== [] && $this->hasShippingObjectData($shipping)) {
            return true;
        }

        foreach (['shipping_method_name', 'shipping_carrier_name', 'shipping_delivery_speed_label'] as $field) {
            if (filled($payload[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $shipping
     */
    private function hasShippingObjectData(array $shipping): bool
    {
        foreach (['method_name', 'carrier_name', 'delivery_speed_label'] as $field) {
            if (filled($shipping[$field] ?? null)) {
                return true;
            }
        }

        return isset($shipping['amount']) && $this->money($shipping['amount']) > 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{subtotal: float, shipping: float, tax: float, discount: float, grand_total: float}  $totals
     * @return array<string, mixed>
     */
    private function shippingSnapshot(array $payload, array $totals, Store $store): array
    {
        if (! $this->hasExplicitShippingData($payload)) {
            return [];
        }

        $shipping = is_array($payload['shipping'] ?? null) ? $payload['shipping'] : [];
        $currency = strtoupper((string) ($shipping['currency'] ?? $shipping['currency_code'] ?? $payload['currency_code'] ?? $store->currency ?? 'USD'));

        $snapshot = [
            'source' => (string) ($shipping['source'] ?? 'external'),
            'method_name' => $shipping['method_name'] ?? $payload['shipping_method_name'] ?? null,
            'carrier_name' => $shipping['carrier_name'] ?? $payload['shipping_carrier_name'] ?? null,
            'delivery_speed_label' => $shipping['delivery_speed_label'] ?? $payload['shipping_delivery_speed_label'] ?? null,
            'amount' => $this->money($shipping['amount'] ?? $totals['shipping']),
            'currency_code' => $currency,
            'external_checkout_reference' => $payload['external_checkout_reference'] ?? null,
            'synced_at' => now()->toISOString(),
        ];

        return collect($snapshot)
            ->reject(fn ($value, string $key): bool => $key !== 'amount' && ($value === null || $value === ''))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fulfillmentSnapshot(array $payload): array
    {
        $fulfillment = is_array($payload['fulfillment'] ?? null) ? $payload['fulfillment'] : [];
        if ($fulfillment === []) {
            return [];
        }

        return collect([
            'managed_by' => (string) ($fulfillment['managed_by'] ?? ChannelOwnershipService::OWNER_EXTERNAL),
            'status' => isset($fulfillment['status']) ? strtolower(trim((string) $fulfillment['status'])) : null,
            'external_fulfillment_id' => $fulfillment['external_fulfillment_id'] ?? null,
            'external_shipment_id' => $fulfillment['external_shipment_id'] ?? null,
            'carrier_name' => $fulfillment['carrier_name'] ?? null,
            'tracking_number' => $fulfillment['tracking_number'] ?? null,
            'tracking_url' => $fulfillment['tracking_url'] ?? null,
            'shipped_at' => $fulfillment['shipped_at'] ?? null,
            'delivered_at' => $fulfillment['delivered_at'] ?? null,
            'synced_at' => now()->toISOString(),
        ])
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $fulfillmentSnapshot
     */
    private function mapFulfillmentStatus(array $fulfillmentSnapshot): string
    {
        if ($fulfillmentSnapshot === []) {
            return OrderLifecycle::FULFILLMENT_UNFULFILLED;
        }

        // External fulfillment snapshots are informational until item-level shipment sync arrives.
        return OrderLifecycle::FULFILLMENT_UNFULFILLED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function billingSameAsShipping(array $payload): bool
    {
        $billing = $payload['billing_address'] ?? null;

        if (! is_array($billing)) {
            return true;
        }

        return (bool) ($billing['same_as_shipping'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function createOrderAddress(Order $order, string $type, array $address, Customer $customer): void
    {
        $order->addresses()->create([
            'type' => $type,
            'name' => $address['name'] ?? $customer->full_name,
            'email' => $customer->email,
            'company' => $address['company'] ?? null,
            'address_line1' => $address['address_line1'] ?? null,
            'address_line2' => $address['address_line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'province_code' => $address['province_code'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
            'country_code' => $address['country_code'] ?? null,
            'phone' => $address['phone'] ?? $customer->phone,
            'delivery_notes' => $address['delivery_notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function saveCustomerShippingAddress(Customer $customer, array $address): void
    {
        $isFirstAddress = ! $customer->addresses()->exists();

        $customer->addresses()->updateOrCreate(
            [
                'type' => 'shipping',
                'address_line1' => $address['address_line1'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? null,
            ],
            [
                'name' => $address['name'] ?? $customer->full_name,
                'company' => $address['company'] ?? null,
                'address_line2' => $address['address_line2'] ?? null,
                'state' => $address['state'] ?? null,
                'province_code' => $address['province_code'] ?? null,
                'country_code' => $address['country_code'] ?? null,
                'phone' => $address['phone'] ?? $customer->phone,
                'is_default' => $isFirstAddress,
                'delivery_instructions' => $address['delivery_notes'] ?? null,
            ]
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function money(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        return round(max(0, (float) $value), 2);
    }
}
