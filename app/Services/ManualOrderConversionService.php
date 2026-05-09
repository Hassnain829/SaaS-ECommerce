<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\DraftOrder;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualOrderConversionService
{
    public function __construct(
        private readonly InventoryReservationService $reservationService,
        private readonly InventorySyncService $syncService,
        private readonly OrderEventRecorder $eventRecorder,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CustomerMetricsService $customerMetricsService,
    ) {
    }

    public function convert(DraftOrder $draft, Store $store, User $actor): Order
    {
        if ((int) $draft->store_id !== (int) $store->id) {
            abort(404);
        }

        if ($draft->status !== DraftOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'draft_order' => 'This draft order has already been converted or cancelled.',
            ]);
        }

        $draft->loadMissing(['customer.addresses', 'items.variant.product.images']);

        if (! $draft->customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Add a customer before creating the order.',
            ]);
        }

        if ($draft->customer->status === 'blocked') {
            throw ValidationException::withMessages([
                'customer_id' => 'This customer is blocked. Unblock the customer before creating a new order.',
            ]);
        }

        if ($draft->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product before creating the order.',
            ]);
        }

        $shipping = $this->shippingAddressFor($draft);
        $addressErrors = [];
        if (! filled($shipping['address_line1'] ?? null)) {
            $addressErrors['shipping_address_line1'] = 'Add a shipping address before creating the order.';
        }
        if (! filled($shipping['city'] ?? null)) {
            $addressErrors['shipping_city'] = 'Add a shipping city before creating the order.';
        }
        if (! filled($shipping['country'] ?? null)) {
            $addressErrors['shipping_country'] = 'Add a shipping country before creating the order.';
        }

        if ($addressErrors !== []) {
            $addressErrors['shipping_address'] = 'Add a complete shipping address before creating the order.';

            throw ValidationException::withMessages($addressErrors);
        }

        return DB::transaction(function () use ($draft, $store, $actor, $shipping): Order {
            $draft = DraftOrder::query()
                ->where('store_id', $store->id)
                ->whereKey($draft->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($draft->status !== DraftOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'draft_order' => 'This draft order has already been converted or cancelled.',
                ]);
            }

            $draft->load(['customer', 'items.variant.product.images']);
            $billing = $draft->billingSameAsShipping() ? [] : $draft->billingAddress();

            $order = Order::query()->create([
                'store_id' => $store->id,
                'customer_id' => $draft->customer_id,
                'order_number' => $this->orderNumberGenerator->generate($store),
                'status' => OrderLifecycle::ORDER_CONFIRMED,
                'payment_status' => OrderLifecycle::PAYMENT_PENDING,
                'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                'order_source' => 'manual',
                'channel' => 'dashboard',
                'currency_code' => $draft->currency,
                'item_count' => $draft->items->count(),
                'total_quantity' => $draft->items->sum('quantity'),
                'subtotal' => $draft->subtotal,
                'discount' => $draft->discount_total,
                'shipping' => $draft->shipping_total,
                'tax' => $draft->tax_total,
                'total' => $draft->total,
                'grand_total' => $draft->total,
                'outstanding_total' => $draft->total,
                'customer_email' => $draft->customer?->email,
                'customer_phone' => $draft->customer?->phone,
                'billing_same_as_shipping' => $draft->billingSameAsShipping(),
                'notes' => $draft->notes,
                'placed_at' => now(),
                'confirmed_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'meta' => [
                    'draft_order_id' => $draft->id,
                    'draft_number' => $draft->draft_number,
                ],
            ]);

            $this->createOrderAddress($order, 'shipping', $shipping);
            if (! $draft->billingSameAsShipping()) {
                $this->createOrderAddress($order, 'billing', $billing);
            }

            $reservations = [];
            foreach ($draft->items as $item) {
                $variant = $item->variant;
                if (! $variant || ! $variant->product || (int) $variant->store_id !== (int) $store->id) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the draft products is no longer available for this store.',
                    ]);
                }

                $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
                $reservation = $this->reservationService->reserve(
                    $inventoryItem,
                    (int) $item->quantity,
                    'manual_order',
                    (string) $order->id,
                    null,
                    null,
                    [
                        'order' => $order,
                        'source' => 'manual_order',
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reference_code' => $order->order_number,
                        'metadata' => [
                            'draft_order_id' => $draft->id,
                            'draft_order_item_id' => $item->id,
                        ],
                    ]
                );

                $this->reservationService->commit($reservation, [
                    'source' => 'manual_order',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reference_code' => $order->order_number,
                ]);
                $this->reservationService->deductCommitted($reservation, [
                    'source' => 'manual_order',
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reference_code' => $order->order_number,
                ]);
                $reservations[] = $reservation->fresh();

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name,
                    'sku_snapshot' => $item->sku,
                    'product_slug_snapshot' => $variant->product->slug,
                    'product_image_snapshot' => $item->metadata['product_image'] ?? null,
                    'product_type_snapshot' => $item->metadata['product_type'] ?? $variant->product->product_type,
                    'variant_label' => $item->variant_title,
                    'variant_details' => $item->metadata,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->line_total,
                    'total' => $item->line_total,
                    'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                ]);
            }

            $draft->update([
                'status' => DraftOrder::STATUS_CONVERTED,
                'converted_order_id' => $order->id,
                'converted_at' => now(),
            ]);

            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_ORDER_CREATED,
                'Order created',
                'Order was created manually from a draft order.',
                [
                    'source' => 'manual_order',
                    'draft_order_id' => $draft->id,
                    'draft_number' => $draft->draft_number,
                ],
                $actor,
                $order->placed_at,
            );
            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_INVENTORY_RESERVED,
                'Inventory reserved',
                'Stock was reserved for the manual order.',
                [
                    'reservation_count' => count($reservations),
                    'total_quantity' => $order->total_quantity,
                ],
                $actor,
            );
            $this->eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
                'Inventory deducted',
                'Stock was deducted for the manual order.',
                [
                    'item_count' => $order->item_count,
                    'total_quantity' => $order->total_quantity,
                ],
                $actor,
            );

            $this->customerMetricsService->recalculate($draft->customer);

            return $order->load(['items', 'addresses', 'customer', 'events']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressFor(DraftOrder $draft): array
    {
        $shipping = $draft->shippingAddress();
        if (filled($shipping['address_line1'] ?? null)) {
            return $shipping;
        }

        $address = $draft->customer?->addresses
            ->where('type', 'shipping')
            ->where('is_default', true)
            ->first()
            ?? $draft->customer?->addresses->first();

        if (! $address instanceof CustomerAddress) {
            return $shipping;
        }

        return [
            'name' => $address->name,
            'phone' => $address->phone,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'country_code' => $address->country_code,
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function createOrderAddress(Order $order, string $type, array $address): void
    {
        $order->addresses()->create([
            'type' => $type,
            'name' => $address['name'] ?? $order->customer?->full_name,
            'email' => $address['email'] ?? $order->customer_email,
            'address_line1' => $address['address_line1'] ?? null,
            'address_line2' => $address['address_line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
            'country_code' => $address['country_code'] ?? null,
            'phone' => $address['phone'] ?? null,
        ]);
    }
}
