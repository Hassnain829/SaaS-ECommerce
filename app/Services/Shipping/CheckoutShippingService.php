<?php

namespace App\Services\Shipping;

use App\Models\Checkout;
use App\Models\PaymentIntent;
use App\Services\CheckoutEventRecorder;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutShippingService
{
    public function __construct(
        private readonly DeliveryOptionService $deliveryOptionService,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly CheckoutEventRecorder $eventRecorder,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return list<array<string, mixed>>
     */
    public function deliveryOptions(Checkout $checkout, ?array $address = null): array
    {
        $checkout->loadMissing(['store', 'addresses', 'items']);

        return $this->deliveryOptionService->optionsFor(
            $checkout->store,
            $address ?: $this->shippingAddress($checkout),
            (float) $checkout->subtotal,
            (string) $checkout->currency_code,
        );
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    public function selectShippingMethod(Checkout $checkout, int $shippingMethodId, ?array $address = null): Checkout
    {
        return DB::transaction(function () use ($checkout, $shippingMethodId, $address): Checkout {
            $checkout = Checkout::query()
                ->with(['store', 'addresses', 'items', 'paymentProviderAccount'])
                ->whereKey($checkout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($checkout->status !== Checkout::STATUS_PAYMENT_PENDING || $checkout->converted_order_id) {
                throw ValidationException::withMessages([
                    'checkout' => 'Delivery method can only be changed before payment is completed.',
                ]);
            }

            $option = $this->deliveryOptionService->optionForMethodId(
                $checkout->store,
                $shippingMethodId,
                $address ?: $this->shippingAddress($checkout),
                (float) $checkout->subtotal,
                (string) $checkout->currency_code,
            );

            if (! $option) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => 'Choose an available delivery method for this address.',
                ]);
            }

            $snapshot = $option['snapshot'];
            $snapshot['selected_at'] = now()->toISOString();

            $metadata = $checkout->metadata ?? [];
            $metadata['shipping'] = $snapshot;

            $shippingTotal = $this->money($option['amount']);
            $grandTotal = $this->money((float) $checkout->subtotal + $shippingTotal + (float) $checkout->tax_total - (float) $checkout->discount_total);

            $checkout->forceFill([
                'shipping_method_id' => $option['shipping_method_id'],
                'shipping_total' => $shippingTotal,
                'shipping_snapshot' => $snapshot,
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

            $this->refreshPaymentIntent($checkout);

            return $checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder', 'paymentProviderAccount']);
        });
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

        $result = $this->paymentProviderManager
            ->driver((string) $checkout->payment_provider)
            ->createPaymentIntent($checkout, ['provider_account' => $providerAccount]);

        $paymentIntent = PaymentIntent::query()->updateOrCreate(
            [
                'provider' => $result->provider,
                'provider_intent_id' => $result->providerIntentId,
            ],
            [
                'store_id' => $checkout->store_id,
                'checkout_id' => $checkout->id,
                'payment_provider_account_id' => $providerAccount->id,
                'mode' => (string) config('payments.stripe.mode', 'test'),
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
