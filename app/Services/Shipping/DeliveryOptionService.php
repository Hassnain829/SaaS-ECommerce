<?php

namespace App\Services\Shipping;

use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutPackageBuilder;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutRateResolver;
use App\Services\Fulfillment\FulfillmentOriginRouter;
use App\Support\CountryCode;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use Illuminate\Validation\ValidationException;

class DeliveryOptionService
{
    public function __construct(
        private readonly ShippingZoneMatcher $zoneMatcher,
        private readonly FedExCheckoutRateResolver $fedExCheckoutRates,
        private readonly FedExCheckoutPackageBuilder $fedExCheckoutPackages,
        private readonly FulfillmentOriginRouter $originRouter,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @return list<array<string, mixed>>
     */
    public function optionsFor(
        Store $store,
        array $destination,
        string $subtotal,
        string $currencyCode,
        ?Checkout $checkout = null,
    ): array {
        $zones = $this->zoneMatcher->matchingZones($store, $destination);

        if ($zones->isEmpty()) {
            return [];
        }

        $zoneRanks = $zones->pluck('id')
            ->values()
            ->flip()
            ->all();

        $subtotal = $this->money($subtotal, $currencyCode);

        return ShippingMethod::query()
            ->with(['shippingZone', 'carrierAccount.defaultOriginLocation', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->whereIn('shipping_zone_id', $zones->pluck('id'))
            ->get()
            ->map(fn (ShippingMethod $method): ?array => $this->optionForMethod(
                $method,
                $destination,
                $subtotal,
                $currencyCode,
                $store,
                $checkout,
            ))
            ->filter()
            ->sort(function (array $a, array $b) use ($zoneRanks): int {
                $zoneCompare = ((int) ($zoneRanks[$a['shipping_zone_id']] ?? 999999))
                    <=> ((int) ($zoneRanks[$b['shipping_zone_id']] ?? 999999));
                if ($zoneCompare !== 0) {
                    return $zoneCompare;
                }

                $sortCompare = ((int) $a['sort_order']) <=> ((int) $b['sort_order']);
                if ($sortCompare !== 0) {
                    return $sortCompare;
                }

                $amountCompare = bccomp((string) $a['amount'], (string) $b['amount'], 6);
                if ($amountCompare !== 0) {
                    return $amountCompare;
                }

                return strcmp((string) $a['name'], (string) $b['name']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    public function optionForMethodId(
        Store $store,
        int $methodId,
        array $destination,
        string $subtotal,
        string $currencyCode,
        ?Checkout $checkout = null,
    ): ?array {
        $method = ShippingMethod::query()
            ->with(['shippingZone', 'carrierAccount.defaultOriginLocation', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->whereKey($methodId)
            ->first();

        if (! $method) {
            return null;
        }

        return $this->optionForMethod(
            $method,
            $destination,
            $this->money($subtotal, $currencyCode),
            $currencyCode,
            $store,
            $checkout,
        );
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>|null
     */
    public function optionForMethod(
        ShippingMethod $method,
        array $destination,
        string $subtotal,
        string $currencyCode,
        ?Store $store = null,
        ?Checkout $checkout = null,
    ): ?array {
        $method->loadMissing(['shippingZone', 'carrierAccount.defaultOriginLocation', 'carrierAccount.carrier']);
        $zone = $method->shippingZone;
        $currencyCode = strtoupper($currencyCode);
        $subtotal = $this->money($subtotal, $currencyCode);
        $store ??= $method->store ?? Store::query()->find($method->store_id);

        if (! $method->is_active || ! $method->enabled_for_checkout || ! $zone || ! $store) {
            return null;
        }

        if (! $this->zoneMatcher->matches($zone, $destination)) {
            return null;
        }

        if (! $this->orderAmountAllowed($method, $subtotal, $currencyCode)) {
            return null;
        }

        if (! $this->carrierAccountAllowed($method, $destination)) {
            return null;
        }

        $liveFedEx = null;
        if ($this->shouldUseFedExLiveRates($method)) {
            if ($checkout === null) {
                // Live FedEx quotes require a checkout cart + routed origin. Never invent packages.
                return null;
            }

            $liveFedEx = $this->resolveFedExLiveRate($checkout, $method, $destination, $currencyCode);
            if ($liveFedEx === null) {
                return null;
            }
        }

        $amount = $liveFedEx['amount'] ?? $this->amountFor($method, $subtotal, $currencyCode);
        if ($amount === null) {
            return null;
        }

        $amount = $this->money($amount, $liveFedEx['currency'] ?? $currencyCode);
        $optionCurrency = strtoupper((string) ($liveFedEx['currency'] ?? $currencyCode));
        if ($optionCurrency !== $currencyCode) {
            return null;
        }

        $option = [
            'id' => $method->id,
            'shipping_method_id' => $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'delivery_speed_label' => $method->delivery_speed_label,
            'amount' => $amount,
            'amount_formatted' => $amount,
            'currency_code' => $optionCurrency,
            'estimated_min_days' => $liveFedEx['transit_days'] ?? $method->estimated_min_days,
            'estimated_max_days' => $liveFedEx['transit_days'] ?? $method->estimated_max_days,
            'shipping_zone_id' => $zone->id,
            'shipping_zone_name' => $zone->name,
            'carrier_account_id' => $method->carrier_account_id,
            'carrier_name' => $method->carrierAccount?->display_name,
            'carrier_code' => $method->carrierAccount?->carrier?->code,
            'rate_type' => $method->rate_type,
            'sort_order' => (int) $method->sort_order,
            'snapshot' => $this->snapshot($method, $amount, $optionCurrency, $liveFedEx),
        ];

        if ($liveFedEx !== null) {
            $option['fedex_service_type'] = $liveFedEx['service_type'] ?? null;
            $option['fedex_transaction_id'] = $liveFedEx['transaction_id'] ?? null;
            $option['rate_quote_ids'] = $liveFedEx['quote_ids'] ?? [];
            $option['fulfillment_origin_location_id'] = $liveFedEx['origin_location_id'] ?? null;
        }

        return $option;
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>|null
     */
    private function resolveFedExLiveRate(
        Checkout $checkout,
        ShippingMethod $method,
        array $destination,
        string $currencyCode,
    ): ?array {
        $checkout->loadMissing(['store', 'items.variant.product']);

        try {
            $routing = $this->originRouter->routeForCheckout(
                $checkout->store,
                $checkout->items,
                $destination,
                $method,
                null,
                'checkout',
                (string) $checkout->id,
            );
        } catch (ValidationException) {
            return null;
        }

        $packageBuild = $this->fedExCheckoutPackages->buildFromCheckout($checkout);
        if (! ($packageBuild['ready'] ?? false) || ($packageBuild['packages'] ?? []) === []) {
            // Missing product weights or store package defaults — hide FedEx live rates safely.
            return null;
        }

        return $this->fedExCheckoutRates->resolve(
            store: $checkout->store,
            method: $method,
            destination: $destination,
            origin: $routing->originLocation,
            packages: $packageBuild['packages'],
            checkoutCurrency: $currencyCode,
            cartFingerprint: $packageBuild['fingerprint'].'|checkout:'.$checkout->id,
            residential: array_key_exists('residential', $destination) ? (bool) $destination['residential'] : null,
            shipDate: now()->toDateString(),
        );
    }

    private function shouldUseFedExLiveRates(ShippingMethod $method): bool
    {
        if ($method->rate_type !== ShippingMethod::RATE_CARRIER_CALCULATED_LATER) {
            return false;
        }

        if (! filter_var(config('carriers.fedex.checkout_rates_enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        if (! filter_var(config('carriers.fedex.ops_negotiated_rates_enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $account = $method->carrierAccount;

        return $account instanceof CarrierAccount
            && $account->isFedEx()
            && $account->usesFedExIntegratorProvider();
    }

    private function orderAmountAllowed(ShippingMethod $method, string $subtotal, string $currencyCode): bool
    {
        if ($method->min_order_amount !== null
            && bccomp($subtotal, $this->money($method->min_order_amount, $currencyCode), 6) < 0
        ) {
            return false;
        }

        if (
            $method->max_order_amount !== null
            && bccomp($this->money($method->max_order_amount, $currencyCode), '0', 6) > 0
            && bccomp($subtotal, $this->money($method->max_order_amount, $currencyCode), 6) > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    private function carrierAccountAllowed(ShippingMethod $method, array $destination): bool
    {
        $account = $method->carrierAccount;

        if (! $account) {
            return true;
        }

        if ($account->status !== CarrierAccount::STATUS_ENABLED || ! $account->enabled_for_checkout) {
            return false;
        }

        $countries = collect($account->supported_countries)
            ->map(fn ($country): string => CountryCode::normalize($country) ?: strtoupper(trim((string) $country)))
            ->filter();

        if ($countries->isEmpty()) {
            return true;
        }

        $country = CountryCode::fromAddress($destination);

        return $country !== '' && $countries->contains($country);
    }

    private function amountFor(ShippingMethod $method, string $subtotal, string $currencyCode): ?string
    {
        $freeOverAmount = $method->free_over_amount;
        if (
            $freeOverAmount !== null
            && bccomp($this->money($freeOverAmount, $currencyCode), '0', 6) > 0
            && bccomp($subtotal, $this->money($freeOverAmount, $currencyCode), 6) >= 0
        ) {
            return $this->zero($currencyCode);
        }

        return match ($method->rate_type) {
            ShippingMethod::RATE_FREE => $this->zero($currencyCode),
            ShippingMethod::RATE_FLAT, ShippingMethod::RATE_MANUAL => $this->money($method->flat_rate ?? 0, $currencyCode),
            ShippingMethod::RATE_CARRIER_CALCULATED_LATER => bccomp($this->money($method->flat_rate ?? 0, $currencyCode), '0', 6) > 0
                ? $this->money($method->flat_rate, $currencyCode)
                : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $liveFedEx
     * @return array<string, mixed>
     */
    private function snapshot(ShippingMethod $method, string $amount, string $currencyCode, ?array $liveFedEx = null): array
    {
        $zone = $method->shippingZone;
        $account = $method->carrierAccount;

        return [
            'source' => $liveFedEx ? 'fedex_negotiated_rates' : 'shipping_settings',
            'shipping_method_id' => $method->id,
            'method_name' => $method->name,
            'description' => $method->description,
            'delivery_speed_label' => $method->delivery_speed_label,
            'rate_type' => $method->rate_type,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'estimated_min_days' => $liveFedEx['transit_days'] ?? $method->estimated_min_days,
            'estimated_max_days' => $liveFedEx['transit_days'] ?? $method->estimated_max_days,
            'shipping_zone_id' => $zone?->id,
            'shipping_zone_name' => $zone?->name,
            'carrier_account_id' => $account?->id,
            'carrier_name' => $account?->display_name,
            'carrier_code' => $account?->carrier?->code,
            'fedex_service_type' => $liveFedEx['service_type'] ?? null,
            'fedex_transaction_id' => $liveFedEx['transaction_id'] ?? null,
            'fulfillment_origin_location_id' => $liveFedEx['origin_location_id'] ?? null,
            'platform_fallback_used' => false,
        ];
    }

    private function money(mixed $value, string $currencyCode): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $this->zero($currencyCode);
        }

        return CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) $value),
            $currencyCode,
        );
    }

    private function zero(string $currencyCode): string
    {
        return CurrencyPrecision::roundMajor('0', $currencyCode);
    }
}
