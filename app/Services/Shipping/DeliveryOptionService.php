<?php

namespace App\Services\Shipping;

use App\Models\CarrierAccount;
use App\Models\ShippingMethod;
use App\Models\Store;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;

class DeliveryOptionService
{
    public function __construct(
        private readonly ShippingZoneMatcher $zoneMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $destination
     * @return list<array<string, mixed>>
     */
    public function optionsFor(Store $store, array $destination, string $subtotal, string $currencyCode): array
    {
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
            ->with(['shippingZone', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->whereIn('shipping_zone_id', $zones->pluck('id'))
            ->get()
            ->map(fn (ShippingMethod $method): ?array => $this->optionForMethod($method, $destination, $subtotal, $currencyCode))
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
    public function optionForMethodId(Store $store, int $methodId, array $destination, string $subtotal, string $currencyCode): ?array
    {
        $method = ShippingMethod::query()
            ->with(['shippingZone', 'carrierAccount.carrier'])
            ->where('store_id', $store->id)
            ->whereKey($methodId)
            ->first();

        if (! $method) {
            return null;
        }

        return $this->optionForMethod($method, $destination, $this->money($subtotal, $currencyCode), $currencyCode);
    }

    /**
     * @param  array<string, mixed>  $destination
     * @return array<string, mixed>|null
     */
    public function optionForMethod(ShippingMethod $method, array $destination, string $subtotal, string $currencyCode): ?array
    {
        $method->loadMissing(['shippingZone', 'carrierAccount.carrier']);
        $zone = $method->shippingZone;
        $currencyCode = strtoupper($currencyCode);
        $subtotal = $this->money($subtotal, $currencyCode);

        if (! $method->is_active || ! $method->enabled_for_checkout || ! $zone) {
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

        $amount = $this->amountFor($method, $subtotal, $currencyCode);
        if ($amount === null) {
            return null;
        }

        $amount = $this->money($amount, $currencyCode);

        return [
            'id' => $method->id,
            'shipping_method_id' => $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'delivery_speed_label' => $method->delivery_speed_label,
            'amount' => $amount,
            'amount_formatted' => $amount,
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
            ->map(fn ($country): string => $this->countryCode($country))
            ->filter();

        if ($countries->isEmpty()) {
            return true;
        }

        $country = $this->countryCode($destination['country_code'] ?? $destination['country'] ?? '');

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
     * @return array<string, mixed>
     */
    private function snapshot(ShippingMethod $method, string $amount, string $currencyCode): array
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
