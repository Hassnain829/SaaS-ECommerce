<?php

namespace App\Services;

use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentCapture;
use App\Models\PaymentIntent as LocalPaymentIntent;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
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
    ) {}

    public function handleSucceededPayment(PaymentWebhookResult $result): ?Order
    {
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
                    'amount_minor' => $this->amountMinor((float) ($result->amount ?? $paymentIntent->amount), $result->currencyCode ?? $paymentIntent->currency_code),
                    'currency_code' => $result->currencyCode ?? $paymentIntent->currency_code,
                    'response_payload' => $result->raw,
                    'captured_at' => now(),
                ]
            );

            /** @var Checkout|null $checkout */
            $checkout = Checkout::query()
                ->with(['items.variant', 'addresses', 'customer', 'store', 'fulfillmentOriginLocation', 'pickupLocation'])
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
                'shipping_tax' => 0,
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
                    ],
                ]);
            }

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

    private function amountMinor(float $amount, string $currency): int
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return (int) round($amount * ($zeroDecimal ? 1 : 100));
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
}
