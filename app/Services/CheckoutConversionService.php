<?php

namespace App\Services;

use App\Data\Payments\PaymentWebhookResult;
use App\Exceptions\CheckoutPaymentAmountMismatchException;
use App\Exceptions\CheckoutTotalsMismatchException;
use App\Models\Checkout;
use App\Models\CheckoutTaxLine;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentCapture;
use App\Models\PaymentIntent as LocalPaymentIntent;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Checkout\FinancialTotalsInvariantService;
use App\Services\Coupons\CouponService;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;

class CheckoutConversionService
{
    private const SOURCE = 'platform_checkout';

    public function __construct(
        private readonly InventoryReservationService $reservationService,
        private readonly InventorySyncService $syncService,
        private readonly OrderEventRecorder $orderEventRecorder,
        private readonly CheckoutEventRecorder $checkoutEventRecorder,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CustomerMetricsService $customerMetricsService,
        private readonly CouponService $couponService,
        private readonly FinancialTotalsInvariantService $financialTotalsInvariantService,
    ) {}

    public function handleSucceededPayment(PaymentWebhookResult $result): ?Order
    {
        try {
            return DB::transaction(function () use ($result): ?Order {
                $paymentIntent = LocalPaymentIntent::query()
                    ->where('provider', 'stripe')
                    ->where('provider_intent_id', $result->providerIntentId)
                    ->where('status', '!=', 'superseded')
                    ->when($result->mode, fn ($query) => $query->where('mode', $result->mode))
                    ->when($result->providerAccountId, function ($query) use ($result): void {
                        $query->where(function ($inner) use ($result): void {
                            $inner->where('provider_account_id', $result->providerAccountId)
                                ->orWhereHas('paymentProviderAccount', fn ($accountQuery) => $accountQuery->where('provider_account_id', $result->providerAccountId));
                        });
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $paymentIntent) {
                    return null;
                }

                /** @var Checkout|null $checkout */
                $checkout = Checkout::query()
                    ->with(['items.variant', 'addresses', 'customer', 'store', 'fulfillmentOriginLocation', 'pickupLocation', 'taxLines'])
                    ->with('paymentProviderAccount')
                    ->whereKey($paymentIntent->checkout_id)
                    ->lockForUpdate()
                    ->first();

                if (! $checkout) {
                    return null;
                }

                if ($checkout->converted_order_id) {
                    $existing = Order::query()->find($checkout->converted_order_id);
                    if ($existing) {
                        $paymentIntent->forceFill(['order_id' => $existing->id])->save();

                        return $existing;
                    }
                }

                $this->assertPaymentAmountsMatch($checkout, $paymentIntent, $result);
                $this->financialTotalsInvariantService->assertCheckoutConsistent($checkout);

                $paymentIntent->forceFill([
                    'status' => $result->status ?: 'succeeded',
                    'response_payload' => $result->raw,
                    'confirmed_at' => $paymentIntent->confirmed_at ?: now(),
                    'failed_at' => null,
                ])->save();

                $paymentIntent->attempts()->create([
                    'store_id' => $paymentIntent->store_id,
                    'provider' => 'stripe',
                    'status' => $result->status ?: 'succeeded',
                    'response_payload' => $result->raw,
                ]);

                PaymentCapture::query()->firstOrCreate(
                    [
                        'payment_intent_id' => $paymentIntent->id,
                        'provider_capture_id' => $result->providerIntentId,
                    ],
                    [
                        'store_id' => $paymentIntent->store_id,
                        'provider' => 'stripe',
                        'status' => 'captured',
                        'amount' => $result->amount ?? $paymentIntent->amount,
                        'amount_minor' => CurrencyPrecision::toMinorUnits((string) ($result->amount ?? $paymentIntent->amount), $result->currencyCode ?? $paymentIntent->currency_code),
                        'currency_code' => $result->currencyCode ?? $paymentIntent->currency_code,
                        'response_payload' => $result->raw,
                        'captured_at' => now(),
                    ]
                );

                $customer = $checkout->customer;
                $providerAccount = $checkout->paymentProviderAccount;
                $connectionType = $providerAccount?->connection_type ?? data_get($checkout->metadata, 'payment_connection_type', 'platform');
                $paymentMode = (string) ($providerAccount?->mode ?? data_get($checkout->metadata, 'platform_payment_mode', 'test'));
                $connectionLabel = $connectionType === 'connect'
                    ? ($paymentMode === 'live' ? 'Stripe live account connected for this store' : 'Stripe test account connected for this store')
                    : 'Platform test mode';
                $routingSnapshot = $checkout->fulfillment_routing_snapshot
                    ?: data_get($checkout->metadata, 'fulfillment_routing');
                $order = Order::query()->create([
                    'store_id' => $checkout->store_id,
                    'customer_id' => $customer?->id,
                    'order_number' => $this->orderNumberGenerator->generate($checkout->store),
                    'status' => OrderLifecycle::ORDER_CONFIRMED,
                    'payment_status' => OrderLifecycle::PAYMENT_PAID,
                    'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                    'order_source' => self::SOURCE,
                    'channel' => $checkout->source_channel,
                    'currency_code' => $checkout->currency_code,
                    'exchange_rate' => 1,
                    'item_count' => $checkout->items->count(),
                    'total_quantity' => $checkout->items->sum('quantity'),
                    'subtotal' => $checkout->subtotal,
                    'discount' => $checkout->discount_total,
                    'discount_tax' => 0,
                    'shipping' => $checkout->shipping_total,
                    'shipping_tax' => $this->shippingTaxTotal($checkout),
                    'tax' => $checkout->tax_total,
                    'total' => $checkout->grand_total,
                    'grand_total' => $checkout->grand_total,
                    'refunded_total' => 0,
                    'outstanding_total' => 0,
                    'payment_method' => 'card',
                    'payment_gateway' => 'stripe',
                    'payment_reference' => $result->providerIntentId,
                    'customer_email' => $customer?->email,
                    'customer_phone' => $customer?->phone,
                    'billing_same_as_shipping' => (bool) data_get($checkout->metadata, 'billing_same_as_shipping', true),
                    'placed_at' => now(),
                    'confirmed_at' => now(),
                    'meta' => [
                        'shipping' => $checkout->shipping_snapshot,
                        'fulfillment_routing' => $routingSnapshot,
                        'tax_snapshot' => data_get($checkout->metadata, 'tax_snapshot'),
                        'coupon_snapshot' => data_get($checkout->metadata, 'coupon_snapshot'),
                        'platform_checkout' => [
                            'checkout_id' => $checkout->id,
                            'checkout_number' => $checkout->checkout_number,
                            'payment_intent_id' => $result->providerIntentId,
                            'payment_provider_account_id' => $providerAccount?->id,
                            'provider_account_id' => $providerAccount?->provider_account_id,
                            'connection_type' => $connectionType,
                            'connection_label' => $connectionLabel,
                        ],
                    ],
                ]);

                foreach ($checkout->addresses as $address) {
                    $order->addresses()->create([
                        'type' => $address->type,
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
                    ]);
                }

                foreach ($checkout->items as $item) {
                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_name' => $item->product_name,
                        'variant_label' => $item->variant_label,
                        'quantity' => $item->quantity,
                        'refunded_quantity' => 0,
                        'returned_quantity' => 0,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'discount_amount' => $item->discount_amount,
                        'tax_amount' => $item->tax_amount,
                        'total' => $item->total,
                        'sku_snapshot' => $item->sku_snapshot,
                        'product_slug_snapshot' => $item->product_slug_snapshot,
                        'brand_name_snapshot' => $item->brand_name_snapshot,
                        'product_image_snapshot' => $item->product_image_snapshot,
                        'product_type_snapshot' => $item->product_type_snapshot,
                        'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                        'variant_details' => $item->variant_details,
                        'meta' => [
                            'source' => self::SOURCE,
                            'checkout_item_id' => $item->id,
                            'coupon' => data_get($item->metadata, 'coupon'),
                        ],
                    ]);
                }

                foreach ($checkout->taxLines as $taxLine) {
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

                $order->load(['items', 'taxLines']);
                $this->financialTotalsInvariantService->assertOrderMatchesCheckout($order, $checkout);

                $this->couponService->redeem($checkout, $order);

                $reservations = InventoryReservation::query()
                    ->where('store_id', $checkout->store_id)
                    ->where('reference_type', 'checkout')
                    ->where('reference_id', (string) $checkout->id)
                    ->whereIn('status', [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED])
                    ->get();

                if ($reservations->isEmpty()) {
                    $reservations = collect();
                    $originLocation = $this->checkoutOriginLocation($checkout, $routingSnapshot);
                    foreach ($checkout->items as $checkoutItem) {
                        if (! $checkoutItem->variant) {
                            continue;
                        }

                        $inventoryItem = $this->syncService->ensureInventoryItemForVariant($checkoutItem->variant);
                        $reservations->push($this->reservationService->reserve(
                            $inventoryItem,
                            (int) $checkoutItem->quantity,
                            'checkout',
                            (string) $checkout->id,
                            $originLocation,
                            null,
                            [
                                'order' => $order,
                                'source' => self::SOURCE,
                                'reference_type' => 'checkout',
                                'reference_id' => $checkout->id,
                                'reference_code' => $checkout->checkout_number,
                                'checkout_reference' => $checkout->checkout_number,
                                'validation_key' => 'items',
                                'metadata' => [
                                    'checkout_number' => $checkout->checkout_number,
                                    're_reserved_after_payment' => true,
                                ],
                            ]
                        ));
                    }
                }

                foreach ($reservations as $reservation) {
                    $reservation->forceFill(['order_id' => $order->id])->save();
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
                }

                $checkout->forceFill([
                    'status' => Checkout::STATUS_CONVERTED,
                    'completed_at' => now(),
                    'converted_order_id' => $order->id,
                ])->save();

                $paymentIntent->forceFill(['order_id' => $order->id])->save();

                $this->checkoutEventRecorder->record(
                    $checkout,
                    'checkout.completed',
                    'Checkout completed',
                    'Stripe confirmed payment and the checkout became an order.',
                    ['order_id' => $order->id, 'order_number' => $order->order_number]
                );

                $this->orderEventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_ORDER_CREATED,
                    'Order created',
                    'Order was created from platform checkout after Stripe payment succeeded.',
                    ['source' => self::SOURCE, 'checkout_number' => $checkout->checkout_number]
                );
                $this->orderEventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_PAYMENT_SUCCEEDED,
                    'Payment succeeded',
                    $connectionType === 'connect'
                        ? 'Stripe confirmed the payment through the connected account.'
                        : 'Stripe test mode confirmed the payment for this order.',
                    [
                        'payment_reference' => $result->providerIntentId,
                        'gateway' => 'stripe',
                        'connection_type' => $connectionType,
                        'provider_account_id' => $providerAccount?->provider_account_id,
                    ]
                );
                if (is_array($routingSnapshot) && $routingSnapshot !== []) {
                    $this->orderEventRecorder->record(
                        $order,
                        OrderLifecycle::EVENT_FULFILLMENT_ORIGIN_SELECTED,
                        'Fulfillment origin selected',
                        'This order will be fulfilled from '.(data_get($routingSnapshot, 'origin_name') ?: 'the selected fulfillment location').'.',
                        $routingSnapshot
                    );
                }
                $this->orderEventRecorder->record(
                    $order,
                    OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
                    'Inventory deducted',
                    'Reserved stock was deducted after platform checkout payment succeeded.',
                    ['reservation_count' => $reservations->count()]
                );

                if ($customer) {
                    $this->customerMetricsService->recalculate($customer);
                }

                return $order->load(['items', 'addresses', 'customer', 'events']);
            });
        } catch (CheckoutPaymentAmountMismatchException $exception) {
            $this->recordAuditAfterConversionRollback(
                fn () => $this->recordPaymentMismatchEvent($exception)
            );

            throw $exception;
        } catch (CheckoutTotalsMismatchException $exception) {
            $this->recordAuditAfterConversionRollback(
                fn () => $this->recordTotalsMismatchEvent($exception)
            );

            throw $exception;
        }
    }

    public function handleFailedPayment(PaymentWebhookResult $result): void
    {
        DB::transaction(function () use ($result): void {
            $failedStatus = $result->eventType === 'payment_intent.canceled' ? 'canceled' : 'failed';
            $paymentIntent = LocalPaymentIntent::query()
                ->where('provider', 'stripe')
                ->where('provider_intent_id', $result->providerIntentId)
                ->where('status', '!=', 'superseded')
                ->when($result->mode, fn ($query) => $query->where('mode', $result->mode))
                ->when($result->providerAccountId, function ($query) use ($result): void {
                    $query->where(function ($inner) use ($result): void {
                        $inner->where('provider_account_id', $result->providerAccountId)
                            ->orWhereHas('paymentProviderAccount', fn ($accountQuery) => $accountQuery->where('provider_account_id', $result->providerAccountId));
                    });
                })
                ->lockForUpdate()
                ->first();

            if (! $paymentIntent) {
                return;
            }

            $paymentIntent->forceFill([
                'status' => $failedStatus,
                'response_payload' => $result->raw,
                'failed_at' => now(),
            ])->save();

            $paymentIntent->attempts()->create([
                'store_id' => $paymentIntent->store_id,
                'provider' => 'stripe',
                'status' => $failedStatus,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
                'response_payload' => $result->raw,
            ]);

            $checkout = Checkout::query()
                ->whereKey($paymentIntent->checkout_id)
                ->lockForUpdate()
                ->first();

            if (! $checkout || $checkout->converted_order_id) {
                return;
            }

            $reservations = InventoryReservation::query()
                ->where('store_id', $checkout->store_id)
                ->where('reference_type', 'checkout')
                ->where('reference_id', (string) $checkout->id)
                ->whereIn('status', [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED])
                ->get();

            foreach ($reservations as $reservation) {
                $this->reservationService->release($reservation, [
                    'source' => self::SOURCE,
                    'reference_type' => 'checkout',
                    'reference_id' => $checkout->id,
                    'reference_code' => $checkout->checkout_number,
                ]);
            }

            $this->couponService->release($checkout);

            $checkout->forceFill(['status' => Checkout::STATUS_FAILED])->save();
            $this->checkoutEventRecorder->record(
                $checkout,
                'payment.failed',
                'Payment failed',
                'Stripe reported that payment did not complete, so reserved stock was released.',
                [
                    'payment_reference' => $result->providerIntentId,
                    'failure_code' => $result->failureCode,
                ]
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $routingSnapshot
     */
    private function checkoutOriginLocation(Checkout $checkout, ?array $routingSnapshot): ?Location
    {
        if ($checkout->fulfillmentOriginLocation) {
            return $checkout->fulfillmentOriginLocation;
        }

        $locationId = (int) data_get($routingSnapshot, 'origin_location_id');
        if ($locationId <= 0) {
            return null;
        }

        return Location::query()
            ->where('store_id', $checkout->store_id)
            ->whereKey($locationId)
            ->first();
    }

    private function assertPaymentAmountsMatch(
        Checkout $checkout,
        LocalPaymentIntent $paymentIntent,
        PaymentWebhookResult $result,
    ): void {
        $expectedCurrency = strtoupper((string) $checkout->currency_code);
        $providerCurrency = $result->currencyCode !== null ? strtoupper($result->currencyCode) : null;
        $localCurrency = strtoupper((string) $paymentIntent->currency_code);
        $expectedMinor = CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, $expectedCurrency);
        $localMinor = (int) $paymentIntent->amount_minor;
        $localAmountAsMinor = CurrencyPrecision::toMinorUnits((string) $paymentIntent->amount, $localCurrency);
        $providerActualMinor = $this->providerActualMinor($result, $providerCurrency ?? $expectedCurrency);

        if (
            $localMinor !== $expectedMinor
            || $localAmountAsMinor !== $expectedMinor
            || $providerActualMinor !== $expectedMinor
            || $localCurrency !== $expectedCurrency
            || $providerCurrency !== $expectedCurrency
        ) {
            throw new CheckoutPaymentAmountMismatchException(
                checkoutId: (int) $checkout->id,
                expectedMinor: $expectedMinor,
                providerActualMinor: $providerActualMinor,
                localPaymentIntentMinor: $localMinor,
                expectedCurrency: $expectedCurrency,
                providerCurrency: $providerCurrency,
                providerIntentId: $result->providerIntentId,
                localPaymentIntentAmountAsMinor: $localAmountAsMinor,
            );
        }
    }

    private function providerActualMinor(PaymentWebhookResult $result, string $currency): ?int
    {
        $rawAmount = data_get($result->raw, 'object.amount');

        if (is_numeric($rawAmount)) {
            return (int) $rawAmount;
        }

        if ($result->amount === null) {
            return null;
        }

        return CurrencyPrecision::toMinorUnits((string) $result->amount, $currency);
    }

    private function shippingTaxTotal(Checkout $checkout): string
    {
        $currency = strtoupper((string) $checkout->currency_code);
        $total = CurrencyPrecision::roundMajor('0', $currency);

        foreach ($checkout->taxLines->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING) as $line) {
            $total = CurrencyPrecision::roundMajor(
                bcadd($total, DecimalString::normalizeNonNegative((string) $line->tax_amount), 6),
                $currency,
            );
        }

        return $total;
    }

    /**
     * Audit events must survive conversion rollback.
     * Record only after the conversion DB::transaction() callback has exited (and rolled back).
     * An outer test transaction (RefreshDatabase) may still be open; that is expected.
     */
    private function recordAuditAfterConversionRollback(callable $recorder): void
    {
        $recorder();
    }

    private function recordPaymentMismatchEvent(CheckoutPaymentAmountMismatchException $exception): void
    {
        $checkout = Checkout::query()->find($exception->checkoutId);

        if (! $checkout) {
            return;
        }

        $this->checkoutEventRecorder->record(
            $checkout,
            'payment.amount_mismatch',
            'Payment total mismatch',
            'Payment confirmation did not match the stored checkout total, so no order was created.',
            $exception->context(),
        );
    }

    private function recordTotalsMismatchEvent(CheckoutTotalsMismatchException $exception): void
    {
        $checkout = Checkout::query()->find($exception->checkoutId);

        if (! $checkout) {
            return;
        }

        $this->checkoutEventRecorder->record(
            $checkout,
            'checkout.totals_mismatch',
            'Checkout totals mismatch',
            'Stored checkout totals were inconsistent, so payment capture and order creation were blocked.',
            $exception->context(),
        );
    }
}
