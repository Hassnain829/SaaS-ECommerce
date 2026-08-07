<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Checkout live FedEx rates using the merchant Model A account only.
 * Caller must supply the routed fulfillment origin and cart-derived packages.
 * Never falls back to platform credentials. Returns null when unavailable.
 */
final class FedExCheckoutRateResolver
{
    public function __construct(
        private readonly FedExOperationGuard $guard,
        private readonly FedExNegotiatedRateService $negotiatedRates,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @param  list<array<string, mixed>>  $packages
     * @return array{amount: string, currency: string, service_type: ?string, service_name: ?string, transit_days: ?int, delivery_date: ?string, transaction_id: ?string, quote_ids: list<int>, origin_location_id: int}|null
     */
    public function resolve(
        Store $store,
        ShippingMethod $method,
        array $destination,
        Location $origin,
        array $packages,
        string $checkoutCurrency,
        string $cartFingerprint,
        ?bool $residential = null,
        ?string $shipDate = null,
    ): ?array {
        if (! $this->guard->capabilityEnabled(FedExOperationGuard::CAPABILITY_CHECKOUT_RATES)) {
            return null;
        }

        $account = $method->carrierAccount;
        if (! $account instanceof CarrierAccount || ! $account->isFedEx()) {
            return null;
        }

        if ($account->usesSandboxPlatformFallback() || $account->isSandboxPlatformFallback()) {
            return null;
        }

        if (! $account->usesFedExIntegratorProvider()) {
            return null;
        }

        if ((int) $origin->store_id !== (int) $store->id) {
            return null;
        }

        try {
            $this->guard->assertAccountForOperation(
                $store,
                $account,
                FedExOperationGuard::CAPABILITY_CHECKOUT_RATES,
            );
        } catch (Throwable) {
            return null;
        }

        $destinationCountry = strtoupper((string) ($destination['country_code'] ?? $destination['country'] ?? ''));
        if ($destinationCountry === '') {
            return null;
        }

        $checkoutCurrency = strtoupper(trim($checkoutCurrency));
        $shipDate = $shipDate ?: now()->toDateString();
        $residentialFlag = $residential ?? (array_key_exists('residential', $destination) ? (bool) $destination['residential'] : null);
        $serviceType = data_get($method->meta, 'fedex_service_type')
            ?? $method->getAttribute('carrier_service_code');

        $packageFingerprint = hash('sha256', json_encode($packages) ?: '');
        $cacheSeconds = max(30, (int) config('carriers.fedex.checkout_rate_cache_seconds', 120));
        $cacheKey = 'fedex.checkout.rate.'.hash('sha256', implode('|', [
            'v2',
            (string) $store->id,
            (string) $account->id,
            (string) $method->id,
            (string) $origin->id,
            $cartFingerprint,
            $packageFingerprint,
            $destinationCountry,
            (string) ($destination['postal_code'] ?? ''),
            (string) ($destination['state'] ?? $destination['province_code'] ?? ''),
            (string) ($destination['city'] ?? ''),
            $residentialFlag === null ? 'res:null' : ('res:'.($residentialFlag ? '1' : '0')),
            (string) ($serviceType ?: 'ALL'),
            $checkoutCurrency,
            $shipDate,
        ]));

        /** @var array{amount: string, currency: string, expires_at?: int}|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && filled($cached['amount'] ?? null)) {
            $expiresAt = (int) ($cached['expires_at'] ?? 0);
            if ($expiresAt > time()
                && strtoupper((string) ($cached['currency'] ?? '')) === $checkoutCurrency
            ) {
                return $cached;
            }
            Cache::forget($cacheKey);
        }

        try {
            $outcome = $this->negotiatedRates->quoteForOriginDestination(
                store: $store,
                account: $account,
                originLocation: $origin,
                destinationInput: [
                    'country_code' => $destinationCountry,
                    'postal_code' => $destination['postal_code'] ?? null,
                    'state' => $destination['state'] ?? $destination['province_code'] ?? null,
                    'city' => $destination['city'] ?? null,
                    'address_line1' => $destination['address_line1'] ?? $destination['line1'] ?? null,
                    'address_line2' => $destination['address_line2'] ?? $destination['line2'] ?? null,
                    'residential' => $residentialFlag,
                ],
                packageInput: $packages,
                shipDate: $shipDate,
                serviceType: filled($serviceType) ? (string) $serviceType : null,
                residential: $residentialFlag,
                forCheckout: true,
            );
        } catch (Throwable) {
            return null;
        }

        if (! $outcome['result']->successful
            || ! filled($outcome['result']->amount)
            || ! filled($outcome['result']->currency)
            || strtoupper((string) $outcome['result']->rateType) !== 'ACCOUNT'
        ) {
            return null;
        }

        $rateCurrency = strtoupper((string) $outcome['result']->currency);
        if ($rateCurrency !== $checkoutCurrency) {
            return null;
        }

        $payload = [
            'amount' => (string) $outcome['result']->amount,
            'currency' => $rateCurrency,
            'service_type' => $outcome['result']->serviceType,
            'service_name' => $outcome['result']->serviceName,
            'transit_days' => $outcome['result']->transitDays,
            'delivery_date' => $outcome['result']->deliveryDate,
            'transaction_id' => $outcome['result']->transactionId,
            'quote_ids' => $outcome['quote_ids'],
            'origin_location_id' => (int) $origin->id,
            'expires_at' => time() + $cacheSeconds,
            'platform_fallback_used' => false,
        ];

        Cache::put($cacheKey, $payload, $cacheSeconds);

        return $payload;
    }
}
