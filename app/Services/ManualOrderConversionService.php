<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\DraftOrder;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Coupons\CouponService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
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
        private readonly CouponService $couponService,
    ) {}

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

        $draft->loadMissing(['customer.addresses', 'items.variant.product.images', 'items.variant.product.categories:id', 'taxLines']);

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

            $draft->load(['customer', 'items.variant.product.images', 'taxLines']);
            $billing = $draft->billingSameAsShipping() ? [] : $draft->billingAddress();
            $isCalculatedTax = $draft->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED;
            $draftMetadata = is_array($draft->metadata) ? $draft->metadata : [];
            $orderMeta = [
                'draft_order_id' => $draft->id,
                'draft_number' => $draft->draft_number,
            ];

            if ($isCalculatedTax && isset($draftMetadata['tax_snapshot'])) {
                $orderMeta['tax_snapshot'] = $draftMetadata['tax_snapshot'];
            }

            $couponDiscount = null;
            $couponCode = trim((string) data_get($draftMetadata, 'coupon_snapshot.code', ''));
            if ($couponCode !== '' && $draft->customer) {
                $prepared = [];
                foreach ($draft->items as $draftItem) {
                    if (! $draftItem->variant) {
                        continue;
                    }
                    $prepared[] = [
                        'variant' => $draftItem->variant,
                        'quantity' => (int) $draftItem->quantity,
                    ];
                }
                $couponDiscount = $this->couponService->calculate(
                    $store,
                    $draft->customer,
                    (string) $draft->currency,
                    $prepared,
                    $couponCode,
                );
                $this->assertDraftCouponMatchesStoredSnapshot(
                    $draft,
                    $draftMetadata['coupon_snapshot'] ?? [],
                    $couponDiscount,
                );
                $orderMeta['coupon_snapshot'] = $draftMetadata['coupon_snapshot'];
            }

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
                'shipping_tax' => $isCalculatedTax ? $this->shippingTaxTotal($draft) : 0,
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
                'meta' => $orderMeta,
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

                $lineKey = CheckoutTotalsService::lineKeyForVariant((int) $item->product_variant_id);
                $lineDiscount = $couponDiscount
                    ? CurrencyPrecision::roundMajor(
                        DecimalString::normalizeNonNegative((string) ($couponDiscount->itemDiscounts[$lineKey] ?? '0')),
                        (string) $draft->currency,
                    )
                    : CurrencyPrecision::roundMajor('0', (string) $draft->currency);

                $itemTotal = CurrencyPrecision::roundMajor(
                    bcsub($this->orderItemTotal($item, $isCalculatedTax, (string) $draft->currency), $lineDiscount, 6),
                    (string) $draft->currency,
                );
                if (bccomp($itemTotal, '0', 6) < 0) {
                    $itemTotal = CurrencyPrecision::roundMajor('0', (string) $draft->currency);
                }

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
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $isCalculatedTax ? $item->tax_amount : 0,
                    'total' => $itemTotal,
                    'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                    'meta' => $couponDiscount ? [
                        'coupon' => [
                            'code' => $couponDiscount->coupon->code,
                            'discount_amount' => $lineDiscount,
                        ],
                    ] : null,
                ]);
            }

            if ($couponDiscount && $draft->customer) {
                $this->couponService->recordRedeemedForOrder($order, $draft->customer, $couponDiscount);
            }

            if ($isCalculatedTax) {
                foreach ($draft->taxLines as $taxLine) {
                    $order->taxLines()->create([
                        'store_id' => $taxLine->store_id,
                        'tax_rate_id' => $taxLine->tax_rate_id,
                        'jurisdiction_country_code' => $taxLine->jurisdiction_country_code,
                        'jurisdiction_region_code' => $taxLine->jurisdiction_region_code,
                        'rate_percent' => $taxLine->rate_percent,
                        'taxable_amount' => $taxLine->taxable_amount,
                        'tax_amount' => $taxLine->tax_amount,
                        'applies_to' => $taxLine->applies_to,
                        'settings_version' => $taxLine->settings_version,
                        'calculated_at' => $taxLine->calculated_at,
                    ]);
                }
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

    private function shippingTaxTotal(DraftOrder $draft): string
    {
        $currency = strtoupper((string) ($draft->currency ?: 'USD'));
        $total = CurrencyPrecision::roundMajor('0', $currency);

        foreach ($draft->taxLines->where('applies_to', \App\Models\DraftTaxLine::APPLIES_TO_SHIPPING) as $line) {
            $total = CurrencyPrecision::roundMajor(
                bcadd($total, DecimalString::normalizeNonNegative((string) $line->tax_amount), 6),
                $currency,
            );
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $storedSnapshot
     */
    private function assertDraftCouponMatchesStoredSnapshot(
        DraftOrder $draft,
        array $storedSnapshot,
        \App\Data\Coupons\CouponDiscountResult $fresh,
    ): void {
        $currencyCode = strtoupper((string) ($draft->currency ?: 'USD'));
        $headerDiscount = CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) $draft->discount_total),
            $currencyCode,
        );
        $storedDiscount = CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) ($storedSnapshot['discount_amount'] ?? '0')),
            $currencyCode,
        );
        $freshDiscount = CurrencyPrecision::roundMajor($fresh->discountTotal, $currencyCode);

        $storedItems = is_array($storedSnapshot['item_discounts'] ?? null) ? $storedSnapshot['item_discounts'] : [];
        $allocationSum = CurrencyPrecision::roundMajor('0', $currencyCode);
        foreach ($storedItems as $amount) {
            $allocationSum = CurrencyPrecision::roundMajor(
                bcadd($allocationSum, DecimalString::normalizeNonNegative((string) $amount), 6),
                $currencyCode,
            );
        }

        $headerMinor = CurrencyPrecision::toMinorUnits($headerDiscount, $currencyCode);
        $storedMinor = CurrencyPrecision::toMinorUnits($storedDiscount, $currencyCode);
        $allocationMinor = CurrencyPrecision::toMinorUnits($allocationSum, $currencyCode);
        $freshMinor = CurrencyPrecision::toMinorUnits($freshDiscount, $currencyCode);

        if (
            strtoupper((string) ($storedSnapshot['code'] ?? '')) !== strtoupper($fresh->coupon->code)
            || $headerMinor !== $storedMinor
            || $headerMinor !== $allocationMinor
            || $headerMinor !== $freshMinor
        ) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon no longer matches the approved draft discount. Update the draft before creating the order.',
            ]);
        }

        $freshItems = $fresh->itemDiscounts;
        $keys = collect(array_unique(array_merge(array_keys($storedItems), array_keys($freshItems))))->sort()->values();

        foreach ($keys as $key) {
            $storedLine = CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($storedItems[$key] ?? '0')),
                $currencyCode,
            );
            $freshLine = CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($freshItems[$key] ?? '0')),
                $currencyCode,
            );

            if (
                CurrencyPrecision::toMinorUnits($storedLine, $currencyCode)
                !== CurrencyPrecision::toMinorUnits($freshLine, $currencyCode)
            ) {
                throw ValidationException::withMessages([
                    'coupon_code' => 'This coupon no longer matches the approved draft discount. Update the draft before creating the order.',
                ]);
            }
        }
    }

    private function orderItemTotal($item, bool $isCalculatedTax, string $currencyCode): string
    {
        if (! $isCalculatedTax) {
            return CurrencyPrecision::roundMajor((string) $item->line_total, $currencyCode);
        }

        if ((bool) data_get($item->metadata, 'tax.prices_include_tax', false)) {
            return CurrencyPrecision::roundMajor((string) $item->line_total, $currencyCode);
        }

        return CurrencyPrecision::roundMajor(
            bcadd((string) $item->line_total, (string) $item->tax_amount, 6),
            $currencyCode,
        );
    }
}
