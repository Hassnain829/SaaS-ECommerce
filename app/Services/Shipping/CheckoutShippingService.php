<?php

namespace App\Services\Shipping;

use App\Models\Checkout;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\PaymentIntent;
use App\Services\CheckoutEventRecorder;
use App\Services\Fulfillment\FulfillmentOriginRouter;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutShippingService
{
    public function __construct(
        private readonly DeliveryOptionService $deliveryOptionService,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly CheckoutEventRecorder $eventRecorder,
        private readonly FulfillmentOriginRouter $originRouter,
        private readonly InventorySyncService $syncService,
        private readonly InventoryReservationService $reservationService,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return list<array<string, mixed>>
     */
    public function deliveryOptions(Checkout $checkout, ?array $address = null): array
    {
        $checkout->loadMissing(['store', 'addresses', 'items.variant']);
        $destination = $address ?: $this->shippingAddress($checkout);

        return collect($this->deliveryOptionService->optionsFor(
            $checkout->store,
            $destination,
            (float) $checkout->subtotal,
            (string) $checkout->currency_code,
        ))
            ->map(fn (array $option): ?array => $this->withFulfillmentRouting($checkout, $option, $destination))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    public function selectShippingMethod(Checkout $checkout, int $shippingMethodId, ?array $address = null, ?int $pickupLocationId = null): Checkout
    {
        return DB::transaction(function () use ($checkout, $shippingMethodId, $address, $pickupLocationId): Checkout {
            $checkout = Checkout::query()
                ->with(['store', 'addresses', 'items.variant', 'paymentProviderAccount'])
                ->whereKey($checkout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($checkout->status !== Checkout::STATUS_PAYMENT_PENDING || $checkout->converted_order_id) {
                throw ValidationException::withMessages([
                    'checkout' => 'Delivery method can only be changed before payment is completed.',
                ]);
            }

            $destination = $address ?: $this->shippingAddress($checkout);
            $option = $this->deliveryOptionService->optionForMethodId(
                $checkout->store,
                $shippingMethodId,
                $destination,
                (float) $checkout->subtotal,
                (string) $checkout->currency_code,
            );

            if (! $option) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => 'Choose an available delivery method for this address.',
                ]);
            }

            $shippingMethod = ShippingMethod::query()
                ->with('carrierAccount.carrier')
                ->where('store_id', $checkout->store_id)
                ->whereKey($shippingMethodId)
                ->firstOrFail();
            $routingResult = $this->originRouter->routeForCheckout(
                $checkout->store,
                $checkout->items,
                $destination,
                $shippingMethod,
                $pickupLocationId,
                'checkout',
                (string) $checkout->id,
            );
            $routingSnapshot = $routingResult->toSnapshot();
            $originChanged = (int) $checkout->fulfillment_origin_location_id !== (int) $routingResult->originLocation->id;
            $pickupChanged = (int) ($checkout->pickup_location_id ?? 0) !== (int) ($routingResult->pickupLocation?->id ?? 0);

            $this->retargetReservations($checkout, $routingResult->originLocation);

            $snapshot = $option['snapshot'];
            $snapshot['selected_at'] = now()->toISOString();

            $metadata = $checkout->metadata ?? [];
            $metadata['shipping'] = $snapshot;
            $metadata['fulfillment_routing'] = $routingSnapshot;

            $shippingTotal = $this->money($option['amount']);
            $grandTotal = $this->money((float) $checkout->subtotal + $shippingTotal + (float) $checkout->tax_total - (float) $checkout->discount_total);

            $checkout->forceFill([
                'shipping_method_id' => $option['shipping_method_id'],
                'shipping_total' => $shippingTotal,
                'shipping_snapshot' => $snapshot,
                'fulfillment_origin_location_id' => $routingResult->originLocation->id,
                'pickup_location_id' => $routingResult->pickupLocation?->id,
                'fulfillment_routing_snapshot' => $routingSnapshot,
                'grand_total' => $grandTotal,
                'metadata' => $metadata,
            ])->save();

            $this->eventRecorder->record(
                $checkout,
                'shipping.method_selected',
                'Delivery method selected',
                'Customer selected '.$option['name'].' for this checkout.',
                [
                    'shipping_method_id' => $option['shipping_method_id'],
                    'shipping_total' => $shippingTotal,
                    'currency_code' => $checkout->currency_code,
                ]
            );

            if ($originChanged || $pickupChanged) {
                $this->eventRecorder->record(
                    $checkout,
                    'fulfillment.origin_selected',
                    'Fulfillment origin selected',
                    'Best eligible origin selected: '.$routingResult->originLocation->name.'.',
                    $routingSnapshot
                );
            }

            $this->refreshPaymentIntent($checkout);

            return $checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder', 'paymentProviderAccount', 'fulfillmentOriginLocation', 'pickupLocation']);
        });
    }

    /**
     * @param  array<string, mixed>  $option
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>|null
     */
    private function withFulfillmentRouting(Checkout $checkout, array $option, array $destination): ?array
    {
        $method = ShippingMethod::query()
            ->with('carrierAccount.carrier')
            ->where('store_id', $checkout->store_id)
            ->whereKey($option['shipping_method_id'])
            ->first();

        if (! $method) {
            return null;
        }

        if ($this->originRouter->isPickupMethod($method)) {
            $pickupLocations = $this->originRouter->eligiblePickupLocations($checkout->store, $checkout->items, 'checkout', (string) $checkout->id);
            if ($pickupLocations === []) {
                return null;
            }

            $option['pickup_required'] = true;
            $option['pickup_locations'] = $pickupLocations;
            if (count($pickupLocations) === 1) {
                $first = $pickupLocations[0];
                $option['fulfillment_origin'] = [
                    'location_id' => $first['id'],
                    'name' => $first['name'],
                    'type' => $first['type'],
                ];
            }

            return $option;
        }

        try {
            $routing = $this->originRouter->routeForCheckout($checkout->store, $checkout->items, $destination, $method, null, 'checkout', (string) $checkout->id);
        } catch (ValidationException) {
            return null;
        }

        $option['fulfillment_origin'] = $routing->publicOrigin();

        return $option;
    }

    private function retargetReservations(Checkout $checkout, \App\Models\Location $origin): void
    {
        $reservations = InventoryReservation::query()
            ->where('store_id', $checkout->store_id)
            ->where('reference_type', 'checkout')
            ->where('reference_id', (string) $checkout->id)
            ->whereIn('status', [InventoryReservation::STATUS_ACTIVE, InventoryReservation::STATUS_COMMITTED])
            ->get();

        $needsReservation = $reservations->isEmpty()
            || $reservations->contains(fn (InventoryReservation $reservation): bool => (int) $reservation->location_id !== (int) $origin->id);

        if (! $needsReservation) {
            return;
        }

        foreach ($reservations as $reservation) {
            $this->reservationService->release($reservation, [
                'source' => 'platform_checkout',
                'reference_type' => 'checkout',
                'reference_id' => $checkout->id,
                'reference_code' => $checkout->checkout_number,
            ]);
        }

        foreach ($checkout->items as $checkoutItem) {
            /** @var ProductVariant|null $variant */
            $variant = $checkoutItem->variant;
            if (! $variant) {
                continue;
            }

            $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
            $reservation = $this->reservationService->reserve(
                $inventoryItem,
                (int) $checkoutItem->quantity,
                'checkout',
                (string) $checkout->id,
                $origin,
                $checkout->expires_at,
                [
                    'source' => 'platform_checkout',
                    'reference_type' => 'checkout',
                    'reference_id' => $checkout->id,
                    'reference_code' => $checkout->checkout_number,
                    'checkout_reference' => $checkout->checkout_number,
                    'validation_key' => 'items',
                    'metadata' => [
                        'checkout_number' => $checkout->checkout_number,
                        'variant_id' => $variant->id,
                        'rerouted' => true,
                    ],
                ]
            );

            $metadata = $checkoutItem->metadata ?? [];
            $metadata['reservation_id'] = $reservation->id;
            $checkoutItem->forceFill(['metadata' => $metadata])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingAddress(Checkout $checkout): array
    {
        $address = $checkout->addresses->firstWhere('type', 'shipping');

        if (! $address) {
            return [];
        }

        return [
            'address_line1' => $address->address_line1,
            'city' => $address->city,
            'state' => $address->state,
            'province_code' => $address->province_code,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'country_code' => $address->country_code,
        ];
    }

    private function refreshPaymentIntent(Checkout $checkout): void
    {
        if (! $checkout->payment_provider || ! $checkout->payment_provider_account_id) {
            return;
        }

        $providerAccount = $checkout->paymentProviderAccount
            ?: $this->paymentProviderManager->accountForCheckout($checkout->store);

        if (! $providerAccount) {
            return;
        }

        $checkout->paymentIntents()
            ->whereNull('order_id')
            ->whereNotIn('status', ['succeeded', 'failed', 'canceled', 'superseded'])
            ->update(['status' => 'superseded']);

        $paymentMode = $this->paymentProviderManager->platformPaymentModeForStore($checkout->store);

        $result = $this->paymentProviderManager
            ->driver((string) $checkout->payment_provider)
            ->createPaymentIntent($checkout, [
                'provider_account' => $providerAccount,
                'mode' => $paymentMode,
            ]);

        $paymentIntent = PaymentIntent::query()->updateOrCreate(
            [
                'provider' => $result->provider,
                'provider_intent_id' => $result->providerIntentId,
            ],
            [
                'store_id' => $checkout->store_id,
                'checkout_id' => $checkout->id,
                'payment_provider_account_id' => $providerAccount->id,
                'mode' => $result->mode ?? $paymentMode,
                'provider_account_id' => $result->providerAccountId ?? $providerAccount->provider_account_id,
                'client_secret' => $result->clientSecret,
                'status' => $result->status,
                'currency_code' => $result->currencyCode,
                'amount' => $result->amount,
                'amount_minor' => $this->amountMinor($result->amount, $result->currencyCode),
                'request_payload' => [
                    'checkout_id' => $checkout->id,
                    'checkout_number' => $checkout->checkout_number,
                    'amount' => $result->amount,
                    'currency_code' => $result->currencyCode,
                    'shipping_method_id' => $checkout->shipping_method_id,
                    'connection_type' => $providerAccount->connection_type,
                    'provider_account_id' => $providerAccount->provider_account_id,
                    'refreshed_after_shipping_selection' => true,
                ],
                'response_payload' => $result->raw,
                'confirmed_at' => null,
                'failed_at' => null,
            ]
        );

        $paymentIntent->attempts()->create([
            'store_id' => $checkout->store_id,
            'provider' => $result->provider,
            'status' => $result->status,
            'response_payload' => $result->raw,
        ]);

        $checkout->forceFill([
            'stripe_payment_intent_id' => $result->providerIntentId,
        ])->save();

        $this->eventRecorder->record(
            $checkout,
            'payment.intent_refreshed',
            'Payment total updated',
            'Payment was refreshed after the delivery method was selected.',
            [
                'payment_intent_id' => $result->providerIntentId,
                'shipping_total' => $checkout->shipping_total,
                'grand_total' => $checkout->grand_total,
            ]
        );
    }

    private function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    private function amountMinor(float $amount, string $currency): int
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return (int) round($amount * ($zeroDecimal ? 1 : 100));
    }
}
