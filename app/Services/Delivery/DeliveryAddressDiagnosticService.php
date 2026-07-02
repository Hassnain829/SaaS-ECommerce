<?php

namespace App\Services\Delivery;

use App\Models\CarrierAccount;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Services\Shipping\DeliveryOptionService;
use App\Services\Shipping\ShippingZoneMatcher;
use App\Support\Tax\TaxCountryCatalog;

class DeliveryAddressDiagnosticService
{
    public function __construct(
        private readonly ShippingZoneMatcher $zoneMatcher,
        private readonly DeliveryOptionService $deliveryOptions,
    ) {}

    /**
     * @return array{
     *     destination: array<string, string>,
     *     subtotal: float,
     *     currency_code: string,
     *     matched_areas: list<array{id: int, name: string}>,
     *     options: list<array<string, mixed>>,
     *     has_matching_area: bool
     * }
     */
    public function diagnose(Store $store, string $countryCode, ?string $regionCode, ?string $postalCode, float $subtotal = 0): array
    {
        $destination = [
            'country_code' => strtoupper(trim($countryCode)),
            'state' => strtoupper(trim((string) $regionCode)),
            'postal_code' => strtoupper(str_replace(' ', '', trim((string) $postalCode))),
        ];

        $currencyCode = strtoupper((string) ($store->currency ?? 'USD'));
        $matchedZones = $this->zoneMatcher->matchingZones($store, $destination);

        $availableByMethodId = collect($this->deliveryOptions->optionsFor($store, $destination, $subtotal, $currencyCode))
            ->keyBy('shipping_method_id');

        $methods = $store->shippingMethods()
            ->with(['shippingZone', 'carrierAccount'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $options = $methods->map(function (ShippingMethod $method) use ($destination, $subtotal, $availableByMethodId, $currencyCode): array {
            $available = $availableByMethodId->get($method->id);

            if ($available !== null) {
                return [
                    'shipping_method_id' => $method->id,
                    'name' => $method->name,
                    'status' => 'available',
                    'reason_code' => null,
                    'message' => 'Available for '.$currencyCode.' '.number_format((float) $available['amount'], 2),
                    'amount' => (float) $available['amount'],
                    'currency_code' => $currencyCode,
                    'delivery_area' => $method->shippingZone?->name,
                ];
            }

            [$reasonCode, $message] = $this->unavailableReason($method, $destination, $subtotal);

            return [
                'shipping_method_id' => $method->id,
                'name' => $method->name,
                'status' => 'unavailable',
                'reason_code' => $reasonCode,
                'message' => $message,
                'amount' => null,
                'currency_code' => $currencyCode,
                'delivery_area' => $method->shippingZone?->name,
            ];
        })->values()->all();

        return [
            'destination' => $destination,
            'subtotal' => round(max(0, $subtotal), 2),
            'currency_code' => $currencyCode,
            'matched_areas' => $matchedZones
                ->map(fn (ShippingZone $zone): array => ['id' => $zone->id, 'name' => $zone->name])
                ->values()
                ->all(),
            'options' => $options,
            'has_matching_area' => $matchedZones->isNotEmpty(),
        ];
    }

    /**
     * @param  array<string, string>  $destination
     * @return array{0: string, 1: string}
     */
    private function unavailableReason(ShippingMethod $method, array $destination, float $subtotal): array
    {
        if (! $method->is_active) {
            return ['option_inactive', 'This delivery option is inactive.'];
        }

        if (! $method->enabled_for_checkout) {
            return ['hidden_from_checkout', 'This delivery option is hidden from checkout.'];
        }

        $zone = $method->shippingZone;
        if ($zone === null) {
            return ['no_matching_area', 'This delivery option is not linked to a delivery area.'];
        }

        if (! $zone->is_active) {
            return ['inactive_area', 'The linked delivery area is inactive.'];
        }

        if (! $this->zoneMatcher->matches($zone, $destination)) {
            return $this->zoneMismatchReason($zone, $destination);
        }

        if ($method->min_order_amount !== null && $subtotal < (float) $method->min_order_amount) {
            return [
                'minimum_order_not_met',
                'Minimum order is '.$this->money((float) $method->min_order_amount).'.',
            ];
        }

        if ($method->max_order_amount !== null && (float) $method->max_order_amount > 0 && $subtotal > (float) $method->max_order_amount) {
            return [
                'maximum_order_exceeded',
                'Maximum order is '.$this->money((float) $method->max_order_amount).'.',
            ];
        }

        $account = $method->carrierAccount;
        if ($account !== null && ($account->status !== CarrierAccount::STATUS_ENABLED || ! $account->enabled_for_checkout)) {
            return ['provider_unavailable', 'The linked delivery provider is not available at checkout.'];
        }

        if ($account !== null) {
            $countries = collect($account->supported_countries)->filter()->map(fn ($c): string => strtoupper(trim((string) $c)))->filter();
            $country = $destination['country_code'] ?? '';
            if ($countries->isNotEmpty() && ($country === '' || ! $countries->contains($country))) {
                return ['provider_unavailable', 'The delivery provider does not support this destination country.'];
            }
        }

        $amount = $this->pricingAmount($method, $subtotal);
        if ($amount === null) {
            return ['invalid_pricing', 'Delivery price is not configured for this option.'];
        }

        return ['no_matching_area', 'This delivery option is not available for this address.'];
    }

    /**
     * @param  array<string, string>  $destination
     * @return array{0: string, 1: string}
     */
    private function zoneMismatchReason(ShippingZone $zone, array $destination): array
    {
        $countries = collect($zone->countries)->filter()->map(fn ($c): string => strtoupper(trim((string) $c)))->filter();
        $country = $destination['country_code'] ?? '';

        if ($countries->isNotEmpty() && ($country === '' || ! $countries->contains($country))) {
            $label = $country !== '' ? TaxCountryCatalog::name($country) : 'this country';

            return ['country_not_covered', 'Delivery is not offered to '.$label.' in this area.'];
        }

        $regions = collect($zone->regions)->filter()->map(fn ($r): string => strtoupper(trim((string) $r)))->filter();
        if ($regions->isNotEmpty()) {
            $state = $destination['state'] ?? '';
            if ($state === '' || ! $this->regionMatchesList($state, $regions->all(), $country)) {
                return ['region_not_covered', 'This address is outside the selected states or provinces for this delivery area.'];
            }
        }

        $patterns = collect($zone->postal_patterns)->filter();
        if ($patterns->isNotEmpty()) {
            $postal = $destination['postal_code'] ?? '';
            if ($postal === '') {
                return ['postal_code_not_covered', 'A postal code is required to match this delivery area.'];
            }

            return ['postal_code_not_covered', 'This ZIP or postal code is outside the selected coverage rules.'];
        }

        return ['no_matching_area', 'This address does not match the delivery area.'];
    }

    /**
     * @param  list<string>  $allowedRegions
     */
    private function regionMatchesList(string $state, array $allowedRegions, string $countryCode): bool
    {
        $state = strtoupper(trim($state));
        $allowed = collect($allowedRegions)->map(fn ($r): string => strtoupper(trim((string) $r)))->filter()->all();

        if (in_array($state, $allowed, true)) {
            return true;
        }

        if ($countryCode !== '') {
            $catalog = TaxCountryCatalog::regionsFor($countryCode);
            if (isset($catalog[$state])) {
                return in_array($state, $allowed, true);
            }

            foreach ($catalog as $code => $label) {
                if (strtoupper($label) === $state && in_array($code, $allowed, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pricingAmount(ShippingMethod $method, float $subtotal): ?float
    {
        $freeOver = $method->free_over_amount;
        if ($freeOver !== null && (float) $freeOver > 0 && $subtotal >= (float) $freeOver) {
            return 0.0;
        }

        return match ($method->rate_type) {
            ShippingMethod::RATE_FREE => 0.0,
            ShippingMethod::RATE_FLAT, ShippingMethod::RATE_MANUAL => (float) ($method->flat_rate ?? 0),
            ShippingMethod::RATE_CARRIER_CALCULATED_LATER => (float) ($method->flat_rate ?? 0) > 0
                ? (float) $method->flat_rate
                : null,
            default => null,
        };
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
