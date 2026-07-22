<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Checkout\FinancialTotalsInvariantService;
use App\Services\Coupons\CouponService;
use App\Services\Fulfillment\FulfillmentOriginRouter;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Shipping\CheckoutShippingService;
use App\Services\Shipping\DeliveryOptionService;
use App\Support\CheckoutMode;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use App\Support\ProductVariantLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    private const SOURCE = 'platform_checkout';

    public function __construct(
        private readonly InventorySyncService $syncService,
        private readonly InventoryReservationService $reservationService,
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly CheckoutEventRecorder $eventRecorder,
        private readonly DeliveryOptionService $deliveryOptionService,
        private readonly FulfillmentOriginRouter $originRouter,
        private readonly CheckoutTotalsService $checkoutTotalsService,
        private readonly CouponService $couponService,
        private readonly CheckoutShippingService $checkoutShippingService,
        private readonly FinancialTotalsInvariantService $financialTotalsInvariantService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Store $store, array $payload): Checkout
    {
        return DB::transaction(function () use ($store, $payload): Checkout {
            $customer = $this->upsertCustomer($store, $payload['customer']);
            $items = $this->prepareItems($store, $payload['items']);
            $taxSetting = TaxSetting::query()->where('store_id', $store->id)->first();

            if (! $taxSetting) {
                throw ValidationException::withMessages([
                    'checkout' => 'Tax settings are not available for this store right now. Please try again later or contact support.',
                ]);
            }

            $provider = (string) config('payments.default_provider', 'stripe');

            if (CheckoutMode::forStore($store) !== CheckoutMode::PLATFORM) {
                throw ValidationException::withMessages([
                    'payment' => 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.',
                ]);
            }

            $providerAccount = $this->paymentProviderManager->accountForCheckout($store);
            $paymentMode = $this->paymentProviderManager->platformPaymentModeForStore($store);
            if (! $providerAccount) {
                throw ValidationException::withMessages([
                    'payment' => 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.',
                ]);
            }
            $currencyCode = $this->resolveCurrencyCode($store, $payload['currency_code'] ?? null);
            $shippingAddress = $payload['shipping_address'];
            $shippingMethodId = filled($payload['shipping_method_id'] ?? null) ? (int) $payload['shipping_method_id'] : null;
            $pickupLocationId = filled($payload['pickup_location_id'] ?? null) ? (int) $payload['pickup_location_id'] : null;
            $shippingSnapshot = null;
            $shippingTotal = CurrencyPrecision::roundMajor('0', $currencyCode);
            $shippingMethod = null;
            $preliminarySubtotal = $this->checkoutTotalsService->itemsSubtotal($currencyCode, $items);
            $couponCode = trim((string) ($payload['coupon_code'] ?? ''));
            $couponDiscount = $couponCode !== ''
                ? $this->couponService->calculate($store, $customer, $currencyCode, $items, $couponCode)
                : null;

            if ($shippingMethodId) {
                $option = $this->deliveryOptionService->optionForMethodId(
                    $store,
                    $shippingMethodId,
                    $shippingAddress,
                    $preliminarySubtotal,
                    $currencyCode,
                );

                if (! $option) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'Choose an available delivery method for this address.',
                    ]);
                }

                $shippingTotal = $this->money($option['amount'], $currencyCode);
                $shippingSnapshot = $option['snapshot'];
                $shippingSnapshot['selected_at'] = now()->toISOString();
                $shippingMethod = ShippingMethod::query()
                    ->with('carrierAccount.carrier')
                    ->where('store_id', $store->id)
                    ->whereKey($shippingMethodId)
                    ->first();
            }

            $routingResult = $this->originRouter->routeForCheckout(
                $store,
                $items,
                $shippingAddress,
                $shippingMethod,
                $pickupLocationId,
            );
            $routingSnapshot = $routingResult->toSnapshot();

            $totalsResult = $this->checkoutTotalsService->calculate(
                $store,
                $taxSetting,
                $currencyCode,
                $items,
                $shippingTotal,
                $shippingAddress,
                couponDiscount: $couponDiscount,
            );
            $billingSameAsShipping = $this->billingSameAsShipping($payload);

            $checkout = Checkout::query()->create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'checkout_number' => $this->numberGenerator->generateCheckout($store),
                'source_channel' => (string) ($payload['source_channel'] ?? 'dev_storefront'),
                'mode' => self::SOURCE,
                'status' => Checkout::STATUS_PAYMENT_PENDING,
                'currency_code' => $currencyCode,
                'subtotal' => $this->money($totalsResult->subtotal, $currencyCode),
                'discount_total' => $this->money($totalsResult->discountTotal, $currencyCode),
                'shipping_total' => $this->money($totalsResult->shippingTotal, $currencyCode),
                'shipping_method_id' => $shippingMethodId,
                'shipping_snapshot' => $shippingSnapshot,
                'fulfillment_origin_location_id' => $routingResult->originLocation->id,
                'pickup_location_id' => $routingResult->pickupLocation?->id,
                'fulfillment_routing_snapshot' => $routingSnapshot,
                'tax_total' => $this->money($totalsResult->taxTotal, $currencyCode),
                'grand_total' => $this->money($totalsResult->grandTotal, $currencyCode),
                'payment_provider' => $provider,
                'payment_provider_account_id' => $providerAccount->id,
                'metadata' => [
                    'billing_same_as_shipping' => $billingSameAsShipping,
                    'received_at' => now()->toISOString(),
                    'server_totals' => true,
                    'shipping' => $shippingSnapshot,
                    'fulfillment_routing' => $routingSnapshot,
                    'payment_connection_type' => $providerAccount->connection_type,
                    'payment_provider_account_id' => $providerAccount->id,
                    'connected_account_id' => $providerAccount->provider_account_id,
                    'platform_payment_mode' => $paymentMode,
                    'tax_snapshot' => $totalsResult->taxSnapshot,
                    'coupon_snapshot' => $couponDiscount?->snapshot,
                ],
                'expires_at' => now()->addHours(2),
            ]);

            $this->createCheckoutAddress($checkout, 'shipping', $shippingAddress, $customer);
            $this->saveCustomerShippingAddress($customer, $shippingAddress);

            if ($billingSameAsShipping) {
                $this->createCheckoutAddress($checkout, 'billing', $shippingAddress, $customer);
            } elseif (is_array($payload['billing_address'] ?? null)) {
                $this->createCheckoutAddress($checkout, 'billing', $payload['billing_address'], $customer);
            }

            $reservationCount = 0;
            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
                $reservation = $this->reservationService->reserve(
                    $inventoryItem,
                    (int) $item['quantity'],
                    'checkout',
                    (string) $checkout->id,
                    $routingResult->originLocation,
                    $checkout->expires_at,
                    [
                        'source' => self::SOURCE,
                        'reference_type' => 'checkout',
                        'reference_id' => $checkout->id,
                        'reference_code' => $checkout->checkout_number,
                        'checkout_reference' => $checkout->checkout_number,
                        'validation_key' => 'items',
                        'metadata' => [
                            'checkout_number' => $checkout->checkout_number,
                            'variant_id' => $variant->id,
                        ],
                    ],
                );
                $reservationCount++;

                $product = $variant->product;
                $primaryImage = $product?->primaryImage();

                $lineKey = CheckoutTotalsService::lineKeyForVariant((int) $variant->id);
                $itemTotals = $totalsResult->itemTotalsFor($lineKey);

                if (! $itemTotals) {
                    throw ValidationException::withMessages([
                        'items' => 'Checkout totals could not be mapped to a catalog line.',
                    ]);
                }

                $checkout->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product?->name ?? 'Catalog item',
                    'variant_label' => $item['variant_label'],
                    'sku_snapshot' => $variant->sku,
                    'product_slug_snapshot' => $product?->slug,
                    'brand_name_snapshot' => $product?->brand?->name,
                    'product_image_snapshot' => $primaryImage?->image_path,
                    'product_type_snapshot' => $product?->product_type,
                    'variant_details' => $item['variant_details'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->money($variant->price, $currencyCode),
                    'subtotal' => $this->money($itemTotals->subtotal, $currencyCode),
                    'discount_amount' => $this->money($itemTotals->discountAmount, $currencyCode),
                    'tax_amount' => $this->money($itemTotals->taxAmount, $currencyCode),
                    'total' => $this->money($itemTotals->total, $currencyCode),
                    'metadata' => [
                        'reservation_id' => $reservation->id,
                        'tax' => [
                            'is_taxable' => $itemTotals->isTaxable,
                            'prices_include_tax' => $totalsResult->pricesIncludeTax,
                            'settings_version' => $taxSetting->settings_version,
                        ],
                        'coupon' => $couponDiscount ? [
                            'code' => $couponDiscount->coupon->code,
                            'discount_amount' => $itemTotals->discountAmount,
                        ] : null,
                    ],
                ]);
            }

            $this->checkoutTotalsService->replaceTaxLines($checkout, $totalsResult);
            if ($couponDiscount) {
                $this->couponService->reserve($checkout, $customer, $couponDiscount);
                $this->eventRecorder->record(
                    $checkout,
                    'coupon.applied',
                    'Coupon applied',
                    'Coupon '.$couponDiscount->coupon->code.' was applied to this checkout.',
                    [
                        'coupon_id' => $couponDiscount->coupon->id,
                        'code' => $couponDiscount->coupon->code,
                        'discount_total' => $couponDiscount->discountTotal,
                    ],
                );
            }

            $this->eventRecorder->record(
                $checkout,
                'checkout.created',
                'Checkout created',
                'A platform checkout was created from the storefront.',
                ['source_channel' => $checkout->source_channel]
            );
            $this->eventRecorder->record(
                $checkout,
                'fulfillment.origin_selected',
                'Fulfillment origin selected',
                'Best eligible origin selected: '.$routingResult->originLocation->name.'.',
                $routingSnapshot
            );
            $this->eventRecorder->record(
                $checkout,
                'inventory.reserved',
                'Inventory reserved',
                'Stock was reserved while the customer completes payment.',
                ['reservation_count' => $reservationCount]
            );

            if ($shippingSnapshot) {
                $this->eventRecorder->record(
                    $checkout,
                    'shipping.method_selected',
                    'Delivery method selected',
                    'Customer selected '.$shippingSnapshot['method_name'].' for this checkout.',
                    [
                        'shipping_method_id' => $shippingMethodId,
                        'shipping_total' => $this->money($totalsResult->shippingTotal, $currencyCode),
                        'currency_code' => $checkout->currency_code,
                    ]
                );
            }

            $checkout->load(['items', 'taxLines']);
            $this->financialTotalsInvariantService->assertCheckoutConsistent($checkout);

            $result = $this->paymentProviderManager
                ->driver($provider)
                ->createPaymentIntent($checkout, [
                    'provider_account' => $providerAccount,
                    'mode' => $paymentMode,
                ]);

            $paymentIntent = PaymentIntent::query()->create([
                'store_id' => $store->id,
                'checkout_id' => $checkout->id,
                'payment_provider_account_id' => $providerAccount->id,
                'provider' => $result->provider,
                'mode' => $result->mode ?? $paymentMode,
                'provider_intent_id' => $result->providerIntentId,
                'provider_account_id' => $result->providerAccountId ?? $providerAccount->provider_account_id,
                'client_secret' => $result->clientSecret,
                'status' => $result->status,
                'currency_code' => $result->currencyCode,
                'amount' => $result->amount,
                'amount_minor' => CurrencyPrecision::toMinorUnits((string) $result->amount, $result->currencyCode),
                'request_payload' => [
                    'checkout_id' => $checkout->id,
                    'checkout_number' => $checkout->checkout_number,
                    'amount' => $result->amount,
                    'currency_code' => $result->currencyCode,
                    'connection_type' => $providerAccount->connection_type,
                    'provider_account_id' => $providerAccount->provider_account_id,
                ],
                'response_payload' => $result->raw,
            ]);

            $paymentIntent->attempts()->create([
                'store_id' => $store->id,
                'provider' => $result->provider,
                'status' => $result->status,
                'response_payload' => $result->raw,
            ]);

            $checkout->forceFill([
                'stripe_payment_intent_id' => $result->providerIntentId,
            ])->save();

            $this->eventRecorder->record(
                $checkout,
                'payment.intent_created',
                'Payment started',
                $providerAccount->connection_type === 'connect'
                    ? 'Stripe payment was prepared through the connected account.'
                    : 'Stripe sandbox payment was prepared for this checkout.',
                [
                    'payment_intent_id' => $result->providerIntentId,
                    'connection_type' => $providerAccount->connection_type,
                    'provider_account_id' => $providerAccount->provider_account_id,
                ]
            );

            return $checkout->load(['items', 'addresses', 'events', 'paymentIntents', 'fulfillmentOriginLocation', 'pickupLocation', 'taxLines']);
        });
    }

    public function applyCoupon(Checkout $checkout, string $code): Checkout
    {
        return DB::transaction(function () use ($checkout, $code): Checkout {
            $checkout = $this->lockMutableCheckout($checkout);
            $this->couponService->release($checkout);

            $couponDiscount = $this->couponService->calculate(
                $checkout->store,
                $checkout->customer,
                (string) $checkout->currency_code,
                $this->preparedItemsFromCheckout($checkout),
                $code,
            );

            $this->recalculateCheckoutWithCoupon($checkout, $couponDiscount);
            $this->couponService->reserve($checkout, $checkout->customer, $couponDiscount);
            $this->eventRecorder->record(
                $checkout,
                'coupon.applied',
                'Coupon applied',
                'Coupon '.$couponDiscount->coupon->code.' was applied to this checkout.',
                [
                    'coupon_id' => $couponDiscount->coupon->id,
                    'code' => $couponDiscount->coupon->code,
                    'discount_total' => $couponDiscount->discountTotal,
                ],
            );
            $checkout->load(['items', 'taxLines']);
            $this->financialTotalsInvariantService->assertCheckoutConsistent($checkout);
            $this->checkoutShippingService->syncPaymentIntent($checkout, 'coupon');

            return $checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder', 'paymentProviderAccount', 'taxLines']);
        });
    }

    public function removeCoupon(Checkout $checkout): Checkout
    {
        return DB::transaction(function () use ($checkout): Checkout {
            $checkout = $this->lockMutableCheckout($checkout);
            $previousCode = data_get($checkout->metadata, 'coupon_snapshot.code');
            $this->couponService->release($checkout);
            $this->recalculateCheckoutWithCoupon($checkout, null);

            if (filled($previousCode)) {
                $this->eventRecorder->record(
                    $checkout,
                    'coupon.removed',
                    'Coupon removed',
                    'Coupon '.$previousCode.' was removed from this checkout.',
                    ['code' => $previousCode],
                );
            }

            $checkout->load(['items', 'taxLines']);
            $this->financialTotalsInvariantService->assertCheckoutConsistent($checkout);
            $this->checkoutShippingService->syncPaymentIntent($checkout, 'coupon');

            return $checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder', 'paymentProviderAccount', 'taxLines']);
        });
    }

    /**
     * @param  list<array{variant_id: int, quantity?: int}>  $rows
     */
    public function updateItems(Checkout $checkout, array $rows): Checkout
    {
        return DB::transaction(function () use ($checkout, $rows): Checkout {
            $checkout = $this->lockMutableCheckout($checkout);
            $currencyCode = (string) $checkout->currency_code;
            $store = $checkout->store;
            $prepared = $this->prepareItems($store, $rows);
            $previousCouponCode = data_get($checkout->metadata, 'coupon_snapshot.code');

            $this->couponService->release($checkout);
            $this->releaseCheckoutReservations($checkout);

            $couponDiscount = null;
            $couponCleared = false;
            if (filled($previousCouponCode)) {
                try {
                    $couponDiscount = $this->couponService->calculate(
                        $store,
                        $checkout->customer,
                        (string) $checkout->currency_code,
                        $prepared,
                        (string) $previousCouponCode,
                    );
                } catch (ValidationException $exception) {
                    $couponCleared = true;
                    $couponDiscount = null;
                }
            }

            $taxSetting = TaxSetting::query()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();

            if (! $taxSetting) {
                throw ValidationException::withMessages([
                    'checkout' => 'Tax settings are not available for this store right now. Please try again later or contact support.',
                ]);
            }

            $shippingAddress = $this->shippingAddressFromCheckout($checkout);
            $totalsResult = $this->checkoutTotalsService->calculate(
                $store,
                $taxSetting,
                (string) $checkout->currency_code,
                $prepared,
                (string) $checkout->shipping_total,
                $shippingAddress,
                couponDiscount: $couponDiscount,
            );

            $checkout->items()->delete();

            $origin = $checkout->fulfillmentOriginLocation
                ?: \App\Models\Location::query()->whereKey($checkout->fulfillment_origin_location_id)->first();

            $reservationCount = 0;
            foreach ($prepared as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
                $reservation = $this->reservationService->reserve(
                    $inventoryItem,
                    (int) $item['quantity'],
                    'checkout',
                    (string) $checkout->id,
                    $origin,
                    $checkout->expires_at,
                    [
                        'source' => self::SOURCE,
                        'reference_type' => 'checkout',
                        'reference_id' => $checkout->id,
                        'reference_code' => $checkout->checkout_number,
                        'checkout_reference' => $checkout->checkout_number,
                        'validation_key' => 'items',
                        'metadata' => [
                            'checkout_number' => $checkout->checkout_number,
                            'variant_id' => $variant->id,
                        ],
                    ],
                );
                $reservationCount++;

                $product = $variant->product;
                $primaryImage = $product?->primaryImage();
                $lineKey = CheckoutTotalsService::lineKeyForVariant((int) $variant->id);
                $itemTotals = $totalsResult->itemTotalsFor($lineKey);
                if (! $itemTotals) {
                    throw ValidationException::withMessages([
                        'items' => 'Checkout totals could not be mapped to a catalog line.',
                    ]);
                }

                $checkout->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product?->name ?? 'Catalog item',
                    'variant_label' => $item['variant_label'],
                    'sku_snapshot' => $variant->sku,
                    'product_slug_snapshot' => $product?->slug,
                    'brand_name_snapshot' => $product?->brand?->name,
                    'product_image_snapshot' => $primaryImage?->image_path,
                    'product_type_snapshot' => $product?->product_type,
                    'variant_details' => $item['variant_details'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $this->money($variant->price, $currencyCode),
                    'subtotal' => $this->money($itemTotals->subtotal, $currencyCode),
                    'discount_amount' => $this->money($itemTotals->discountAmount, $currencyCode),
                    'tax_amount' => $this->money($itemTotals->taxAmount, $currencyCode),
                    'total' => $this->money($itemTotals->total, $currencyCode),
                    'metadata' => [
                        'reservation_id' => $reservation->id,
                        'tax' => [
                            'is_taxable' => $itemTotals->isTaxable,
                            'prices_include_tax' => $totalsResult->pricesIncludeTax,
                            'settings_version' => $taxSetting->settings_version,
                        ],
                        'coupon' => $couponDiscount ? [
                            'code' => $couponDiscount->coupon->code,
                            'discount_amount' => $itemTotals->discountAmount,
                        ] : null,
                    ],
                ]);
            }

            $this->checkoutTotalsService->replaceTaxLines($checkout, $totalsResult);

            $metadata = $checkout->metadata ?? [];
            $metadata['tax_snapshot'] = $totalsResult->taxSnapshot;
            $metadata['coupon_snapshot'] = $couponDiscount?->snapshot;

            $checkout->forceFill([
                'subtotal' => $this->money($totalsResult->subtotal, $currencyCode),
                'discount_total' => $this->money($totalsResult->discountTotal, $currencyCode),
                'shipping_total' => $this->money($totalsResult->shippingTotal, $currencyCode),
                'tax_total' => $this->money($totalsResult->taxTotal, $currencyCode),
                'grand_total' => $this->money($totalsResult->grandTotal, $currencyCode),
                'metadata' => $metadata,
            ])->save();

            if ($couponDiscount) {
                $this->couponService->reserve($checkout, $checkout->customer, $couponDiscount);
            }

            $this->eventRecorder->record(
                $checkout,
                'checkout.items_updated',
                'Cart updated',
                'Checkout line items were updated and totals were recalculated.',
                ['reservation_count' => $reservationCount, 'item_count' => count($prepared)],
            );

            if ($couponCleared && filled($previousCouponCode)) {
                $this->eventRecorder->record(
                    $checkout,
                    'coupon.removed',
                    'Coupon removed',
                    'Coupon '.$previousCouponCode.' no longer applied after the cart changed.',
                    ['code' => $previousCouponCode, 'reason' => 'cart_changed'],
                );
            }

            $checkout->load(['items', 'taxLines']);
            $this->financialTotalsInvariantService->assertCheckoutConsistent($checkout);
            $this->checkoutShippingService->syncPaymentIntent($checkout, 'items');

            return $checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder', 'paymentProviderAccount', 'taxLines']);
        });
    }

    /**
     * Re-run coupon math for an existing checkout cart, or clear it when no longer valid.
     *
     * @return array{0: ?\App\Data\Coupons\CouponDiscountResult, 1: bool}
     */
    public function resolveCouponDiscountForCheckout(Checkout $checkout): array
    {
        $code = data_get($checkout->metadata, 'coupon_snapshot.code');
        if (! filled($code) || ! $checkout->customer) {
            return [null, false];
        }

        try {
            $result = $this->couponService->calculate(
                $checkout->store,
                $checkout->customer,
                (string) $checkout->currency_code,
                $this->preparedItemsFromCheckout($checkout),
                (string) $code,
            );

            return [$result, false];
        } catch (ValidationException) {
            $this->couponService->release($checkout);

            return [null, true];
        }
    }

    private function releaseCheckoutReservations(Checkout $checkout): void
    {
        $reservations = \App\Models\InventoryReservation::query()
            ->where('store_id', $checkout->store_id)
            ->where('reference_type', 'checkout')
            ->where('reference_id', (string) $checkout->id)
            ->whereIn('status', [
                \App\Models\InventoryReservation::STATUS_ACTIVE,
                \App\Models\InventoryReservation::STATUS_COMMITTED,
            ])
            ->get();

        foreach ($reservations as $reservation) {
            $this->reservationService->release($reservation, [
                'source' => self::SOURCE,
                'reference_type' => 'checkout',
                'reference_id' => $checkout->id,
                'reference_code' => $checkout->checkout_number,
            ]);
        }
    }

    private function lockMutableCheckout(Checkout $checkout): Checkout
    {
        $locked = Checkout::query()
            ->with([
                'store',
                'customer',
                'addresses',
                'items.variant.product.categories:id',
                'paymentProviderAccount',
                'paymentIntents',
            ])
            ->whereKey($checkout->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->status !== Checkout::STATUS_PAYMENT_PENDING || $locked->converted_order_id) {
            throw ValidationException::withMessages([
                'checkout' => 'This checkout can only be changed before payment is completed.',
            ]);
        }

        if (! $locked->customer) {
            throw ValidationException::withMessages([
                'checkout' => 'This checkout is missing customer data needed for coupons.',
            ]);
        }

        return $locked;
    }

    /**
     * @return list<array{variant: ProductVariant, quantity: int}>
     */
    private function preparedItemsFromCheckout(Checkout $checkout): array
    {
        $items = [];

        foreach ($checkout->items as $item) {
            $variant = $item->variant;
            if (! $variant || ! $variant->product) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item is missing catalog data needed for coupon calculation.',
                ]);
            }

            $items[] = [
                'variant' => $variant,
                'quantity' => (int) $item->quantity,
            ];
        }

        return $items;
    }

    private function recalculateCheckoutWithCoupon(Checkout $checkout, ?\App\Data\Coupons\CouponDiscountResult $couponDiscount): void
    {
        $currencyCode = (string) $checkout->currency_code;
        $taxSetting = TaxSetting::query()
            ->where('store_id', $checkout->store_id)
            ->lockForUpdate()
            ->first();

        if (! $taxSetting) {
            throw ValidationException::withMessages([
                'checkout' => 'Tax settings are not available for this store right now. Please try again later or contact support.',
            ]);
        }

        $shippingAddress = $this->shippingAddressFromCheckout($checkout);
        $totalsResult = $this->checkoutTotalsService->calculate(
            $checkout->store,
            $taxSetting,
            (string) $checkout->currency_code,
            $this->preparedItemsFromCheckout($checkout),
            (string) $checkout->shipping_total,
            $shippingAddress,
            couponDiscount: $couponDiscount,
        );

        foreach ($checkout->items as $item) {
            $lineKey = CheckoutTotalsService::lineKeyForVariant((int) $item->product_variant_id);
            $itemTotals = $totalsResult->itemTotalsFor($lineKey);
            if (! $itemTotals) {
                throw ValidationException::withMessages([
                    'items' => 'Checkout totals could not be mapped to a catalog line.',
                ]);
            }

            $metadata = $item->metadata ?? [];
            $metadata['tax'] = [
                'is_taxable' => $itemTotals->isTaxable,
                'prices_include_tax' => $totalsResult->pricesIncludeTax,
                'settings_version' => $taxSetting->settings_version,
            ];
            $metadata['coupon'] = $couponDiscount ? [
                'code' => $couponDiscount->coupon->code,
                'discount_amount' => $itemTotals->discountAmount,
            ] : null;

            $item->forceFill([
                'subtotal' => $this->money($itemTotals->subtotal, $currencyCode),
                'discount_amount' => $this->money($itemTotals->discountAmount, $currencyCode),
                'tax_amount' => $this->money($itemTotals->taxAmount, $currencyCode),
                'total' => $this->money($itemTotals->total, $currencyCode),
                'metadata' => $metadata,
            ])->save();
        }

        $this->checkoutTotalsService->replaceTaxLines($checkout, $totalsResult);

        $metadata = $checkout->metadata ?? [];
        $metadata['tax_snapshot'] = $totalsResult->taxSnapshot;
        $metadata['coupon_snapshot'] = $couponDiscount?->snapshot;

        $checkout->forceFill([
            'subtotal' => $this->money($totalsResult->subtotal, $currencyCode),
            'discount_total' => $this->money($totalsResult->discountTotal, $currencyCode),
            'shipping_total' => $this->money($totalsResult->shippingTotal, $currencyCode),
            'tax_total' => $this->money($totalsResult->taxTotal, $currencyCode),
            'grand_total' => $this->money($totalsResult->grandTotal, $currencyCode),
            'metadata' => $metadata,
        ])->save();

        $this->eventRecorder->record(
            $checkout,
            'checkout.totals_recalculated',
            'Checkout total updated',
            'Taxes and totals were recalculated after the coupon changed.',
            [
                'subtotal' => $this->money($totalsResult->subtotal, $currencyCode),
                'discount_total' => $this->money($totalsResult->discountTotal, $currencyCode),
                'shipping_total' => $this->money($totalsResult->shippingTotal, $currencyCode),
                'tax_total' => $this->money($totalsResult->taxTotal, $currencyCode),
                'grand_total' => $this->money($totalsResult->grandTotal, $currencyCode),
                'settings_version' => $taxSetting->settings_version,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddressFromCheckout(Checkout $checkout): array
    {
        $address = $checkout->addresses->firstWhere('type', 'shipping');
        if (! $address) {
            return [];
        }

        return [
            'name' => $address->name,
            'email' => $address->email,
            'company' => $address->company,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'city' => $address->city,
            'state' => $address->state,
            'province_code' => $address->province_code,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'country_code' => $address->country_code,
            'phone' => $address->phone,
            'delivery_notes' => $address->delivery_notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    private function upsertCustomer(Store $store, array $customerData): Customer
    {
        $email = strtolower(trim((string) $customerData['email']));
        $fullName = trim((string) ($customerData['full_name'] ?? ''));
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

        $existing = Customer::query()
            ->where('store_id', $store->id)
            ->where('email', $email)
            ->first();

        if ($existing?->status === 'blocked') {
            throw ValidationException::withMessages([
                'customer.email' => 'This customer is blocked and cannot place a new platform checkout.',
            ]);
        }

        $customer = Customer::query()->updateOrCreate(
            ['store_id' => $store->id, 'email' => $email],
            [
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'full_name' => $fullName,
                'phone' => $customerData['phone'] ?? null,
                'source' => self::SOURCE,
                'preferred_currency' => $store->currency ?? 'USD',
                'meta' => [
                    'last_platform_checkout_at' => now()->toISOString(),
                ],
            ]
        );

        return $customer;
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
            ->with(['product.brand', 'product.images', 'product.categories:id', 'options.variationType'])
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
            $variantCount = $variant->product?->variants()->count() ?? 1;
            $variantLabel = ProductVariantLabel::forVariant($variant, 0, $variantCount);

            if (isset($items[$variant->id])) {
                $items[$variant->id]['quantity'] += $quantity;

                continue;
            }

            $items[$variant->id] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'variant_label' => $variantLabel,
                'variant_details' => [
                    'options' => $variant->options
                        ->map(fn ($option): array => [
                            'group' => $option->variationType?->name ?? 'Option',
                            'value' => $option->value,
                        ])
                        ->values()
                        ->all(),
                ],
            ];
        }

        return array_values($items);
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

        return (bool) ($billing['same_as_shipping'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function createCheckoutAddress(Checkout $checkout, string $type, array $address, Customer $customer): void
    {
        $checkout->addresses()->create([
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

    private function resolveCurrencyCode(Store $store, mixed $supplied): string
    {
        $storeCurrency = strtoupper(trim((string) ($store->currency ?: 'USD')));
        if ($storeCurrency === '' || preg_match('/^[A-Z]{3}$/', $storeCurrency) !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => 'This store does not have a valid base currency configured.',
            ]);
        }

        if ($supplied === null || trim((string) $supplied) === '') {
            return $storeCurrency;
        }

        $currency = strtoupper(trim((string) $supplied));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => 'Currency must be a three-letter ISO code.',
            ]);
        }

        if ($currency !== $storeCurrency) {
            throw ValidationException::withMessages([
                'currency_code' => 'Checkout currency must match the store currency ('.$storeCurrency.').',
            ]);
        }

        return $currency;
    }

    private function money(mixed $value, string $currencyCode): string
    {
        if ($value === null || trim((string) $value) === '') {
            return CurrencyPrecision::roundMajor('0', $currencyCode);
        }

        $rounded = CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) $value),
            $currencyCode,
        );

        return bccomp($rounded, '0', 6) < 0
            ? CurrencyPrecision::roundMajor('0', $currencyCode)
            : $rounded;
    }
}
