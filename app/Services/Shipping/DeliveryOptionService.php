<?php

namespace App\Services\Shipping;

use App\Models\CarrierAccount;
use App\Models\ShippingMethod;
use App\Models\Store;

class DeliveryOptionService
{
    public function __construct(
        private readonly ShippingZoneMatcher $zoneMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @return list<array<string, mixed>>
     */
    public function optionsFor(Store $store, array $destination, float $subtotal, string $currencyCode): array
    {
        $zones = $this->zoneMatcher->matchingZones($store, $destination);

        if ($zones->isEmpty()) {
            return [];
        }

        $zoneRanks = $zones->pluck('id')
            ->values()
            ->flip()
            ->all();

        return ShippingMethod::query()
            ->with(['shippingZone', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->whereIn('shipping_zone_id', $zones->pluck('id'))
            ->get()
            ->map(fn (ShippingMethod $method): ?array => $this->optionForMethod($method, $destination, $subtotal, $currencyCode))
            ->filter()
            ->sort(function (array $a, array $b) use ($zoneRanks): int {
                return [
                    (int) ($zoneRanks[$a['shipping_zone_id']] ?? 999999),
                    (int) $a['sort_order'],
                    (float) $a['amount'],
                    (string) $a['name'],
                ] <=> [
                    (int) ($zoneRanks[$b['shipping_zone_id']] ?? 999999),
                    (int) $b['sort_order'],
                    (float) $b['amount'],
                    (string) $b['name'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    public function optionForMethodId(Store $store, int $methodId, array $destination, float $subtotal, string $currencyCode): ?array
    {
        $method = ShippingMethod::query()
            ->with(['shippingZone', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->whereKey($methodId)
            ->first();

        if (! $method) {
            return null;
        }

        return $this->optionForMethod($method, $destination, $subtotal, $currencyCode);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>|null
     */
    public function optionForMethod(ShippingMethod $method, array $destination, float $subtotal, string $currencyCode): ?array
    {
        $method->loadMissing(['shippingZone', 'carrierAccount.carrier']);
        $zone = $method->shippingZone;

        if (! $method->is_active || ! $method->enabled_for_checkout || ! $zone) {
            return null;
        }

        if (! $this->zoneMatcher->matches($zone, $destination)) {
            return null;
        }

        if (! $this->orderAmountAllowed($method, $subtotal)) {
            return null;
        }

        if (! $this->carrierAccountAllowed($method, $destination)) {
            return null;
        }

        $amount = $this->amountFor($method, $subtotal);
        if ($amount === null) {
            return null;
        }

        $amount = $this->money($amount);
        $currencyCode = strtoupper($currencyCode);

        return [
            'id' => $method->id,
            'shipping_method_id' => $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'delivery_speed_label' => $method->delivery_speed_label,
            'amount' => $amount,
            'amount_formatted' => number_format($amount, 2, '.', ''),
            'currency_code' => $currencyCode,
            'estimated_min_days' => $method->estimated_min_days,
            'estimated_max_days' => $method->estimated_max_days,
            'shipping_zone_id' => $zone->id,
            'shipping_zone_name' => $zone->name,
            'carrier_account_id' => $method->carrier_account_id,
            'carrier_name' => $method->carrierAccount?->display_name,
            'carrier_code' => $method->carrierAccount?->carrier?->code,
            'rate_type' => $method->rate_type,
            'sort_order' => (int) $method->sort_order,
            'snapshot' => $this->snapshot($method, $amount, $currencyCode),
        ];
    }

    private function orderAmountAllowed(ShippingMethod $method, float $subtotal): bool
    {
        if ($method->min_order_amount !== null && $subtotal < (float) $method->min_order_amount) {
            return false;
        }

        if ($method->max_order_amount !== null && (float) $method->max_order_amount > 0 && $subtotal > (float) $method->max_order_amount) {
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
            ->map(fn ($country): string => $this->countryCode($country))
            ->filter();

        if ($countries->isEmpty()) {
            return true;
        }

        $country = $this->countryCode($destination['country_code'] ?? $destination['country'] ?? '');

        return $country !== '' && $countries->contains($country);
    }

    private function amountFor(ShippingMethod $method, float $subtotal): ?float
    {
        $freeOverAmount = $method->free_over_amount;
        if ($freeOverAmount !== null && (float) $freeOverAmount > 0 && $subtotal >= (float) $freeOverAmount) {
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

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ShippingMethod $method, float $amount, string $currencyCode): array
    {
        $zone = $method->shippingZone;
        $account = $method->carrierAccount;

        return [
            'source' => 'shipping_settings',
            'shipping_method_id' => $method->id,
            'method_name' => $method->name,
            'description' => $method->description,
            'delivery_speed_label' => $method->delivery_speed_label,
            'rate_type' => $method->rate_type,
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'estimated_min_days' => $method->estimated_min_days,
            'estimated_max_days' => $method->estimated_max_days,
            'shipping_zone_id' => $zone?->id,
            'shipping_zone_name' => $zone?->name,
            'carrier_account_id' => $account?->id,
            'carrier_name' => $account?->display_name,
            'carrier_code' => $account?->carrier?->code,
        ];
    }

    private function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    private function countryCode(mixed $country): string
    {
        $country = strtoupper(trim((string) $country));

        return match ($country) {
            'UNITED STATES', 'UNITED STATES OF AMERICA', 'USA' => 'US',
            'UNITED KINGDOM', 'UK' => 'GB',
            'CANADA' => 'CA',
            'PAKISTAN' => 'PK',
            default => $country,
        };
    }
}
