<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Models\Store;
use App\Support\CountryCode;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Support\Collection;

class ShippingZoneMatcher
{
    /**
     * @param  array<string, mixed>  $address
     * @return Collection<int, ShippingZone>
     */
    public function matchingZones(Store $store, array $address): Collection
    {
        $address = $this->normalizedAddress($address);

        return $store->shippingZones()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ShippingZone $zone): bool => $this->matches($zone, $address))
            ->sort(function (ShippingZone $a, ShippingZone $b): int {
                return [
                    (int) $a->sort_order,
                    -$this->specificity($a),
                    (string) $a->name,
                    (int) $a->id,
                ] <=> [
                    (int) $b->sort_order,
                    -$this->specificity($b),
                    (string) $b->name,
                    (int) $b->id,
                ];
            })
            ->values();
    }

    /**
     * True when an active delivery area covers this address for the whole
     * selected country (no state or ZIP rules).
     *
     * @param  array<string, mixed>  $address
     */
    public function hasCountryWideCoverage(Store $store, array $address): bool
    {
        return $this->matchingZones($store, $address)->contains(
            fn (ShippingZone $zone): bool => $this->isCountryWide($zone)
        );
    }

    public function isCountryWide(ShippingZone $zone): bool
    {
        return collect($zone->regions)->filter(fn ($region): bool => filled($region))->isEmpty()
            && collect($zone->postal_patterns)->filter(fn ($pattern): bool => filled($pattern))->isEmpty();
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function matches(ShippingZone $zone, array $address): bool
    {
        if (! $zone->is_active) {
            return false;
        }

        $address = $this->normalizedAddress($address);

        return $this->matchesCountry($zone, $address)
            && $this->matchesRegion($zone, $address)
            && $this->matchesPostalCode($zone, $address);
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function normalizedAddress(array $address): array
    {
        $country = CountryCode::fromAddress($address);
        if ($country !== '') {
            $address['country_code'] = $country;
            $address['country'] = $country;
        }

        if (! filled($address['state'] ?? null)) {
            $address['state'] = $address['region_code']
                ?? $address['province_code']
                ?? $address['region']
                ?? '';
        }

        return $address;
    }

    private function specificity(ShippingZone $zone): int
    {
        $score = 0;

        if (collect($zone->countries)->filter()->isNotEmpty()) {
            $score += 1;
        }

        if (collect($zone->regions)->filter()->isNotEmpty()) {
            $score += 2;
        }

        if (collect($zone->postal_patterns)->filter()->isNotEmpty()) {
            $score += 4;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function matchesCountry(ShippingZone $zone, array $address): bool
    {
        $countries = collect($zone->countries)
            ->map(fn ($country): string => CountryCode::normalize($country) ?: $this->normalized($country))
            ->filter()
            ->values();

        if ($countries->isEmpty()) {
            return true;
        }

        $country = CountryCode::fromAddress($address);

        return $country !== '' && $countries->contains($country);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function matchesRegion(ShippingZone $zone, array $address): bool
    {
        $regions = collect($zone->regions)
            ->map(fn ($region): string => $this->normalized($region))
            ->filter()
            ->values();

        if ($regions->isEmpty()) {
            return true;
        }

        $countries = collect($zone->countries)
            ->map(fn ($country): string => CountryCode::normalize($country) ?: $this->normalized($country))
            ->filter()
            ->values()
            ->all();

        $addressCountry = CountryCode::fromAddress($address);
        if ($addressCountry !== '' && ! in_array($addressCountry, $countries, true)) {
            $countries[] = $addressCountry;
        }

        $candidates = collect([
            $address['province_code'] ?? null,
            $address['state'] ?? null,
            $address['region'] ?? null,
        ])
            ->flatMap(fn ($region): array => $this->regionVariants($region, $countries))
            ->filter()
            ->unique()
            ->values();

        $zoneRegions = $regions
            ->flatMap(fn (string $region): array => $this->regionVariants($region, $countries))
            ->unique()
            ->values();

        return $candidates->contains(fn (string $candidate): bool => $zoneRegions->contains($candidate));
    }

    /**
     * @param  list<string>  $countryCodes
     * @return list<string>
     */
    private function regionVariants(mixed $region, array $countryCodes = []): array
    {
        $normalized = $this->normalized($region);
        if ($normalized === '') {
            return [];
        }

        $variants = [$normalized];

        if ($countryCodes === []) {
            $countryCodes = array_keys(TaxCountryCatalog::allRegions());
        }

        foreach ($countryCodes as $countryCode) {
            $catalog = TaxCountryCatalog::regionsFor($countryCode);
            if ($catalog === []) {
                continue;
            }

            if (isset($catalog[$normalized])) {
                $variants[] = $normalized;
                $variants[] = $this->normalized($catalog[$normalized]);
            }

            foreach ($catalog as $code => $label) {
                if ($this->normalized($label) === $normalized || $this->normalized($code) === $normalized) {
                    $variants[] = $this->normalized($code);
                    $variants[] = $this->normalized($label);
                }
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function matchesPostalCode(ShippingZone $zone, array $address): bool
    {
        $patterns = collect($zone->postal_patterns)->filter()->values();

        if ($patterns->isEmpty()) {
            return true;
        }

        $postalCode = $this->compactPostal($address['postal_code'] ?? null);
        if ($postalCode === '') {
            return false;
        }

        return $patterns->contains(fn ($pattern): bool => $this->postalPatternMatches((string) $pattern, $postalCode));
    }

    private function postalPatternMatches(string $pattern, string $postalCode): bool
    {
        $compactPattern = $this->compactPostal($pattern);

        if ($compactPattern === '') {
            return false;
        }

        if (! str_contains($compactPattern, '*')) {
            if ($postalCode === $compactPattern) {
                return true;
            }

            // US ZIP+4 (75002-1234) should match a 5-digit deliver-to rule.
            return strlen($compactPattern) === 5
                && ctype_digit($compactPattern)
                && strlen($postalCode) === 9
                && ctype_digit($postalCode)
                && str_starts_with($postalCode, $compactPattern);
        }

        $regex = '/^'.str_replace('\*', '.*', preg_quote($compactPattern, '/')).'$/';

        return (bool) preg_match($regex, $postalCode);
    }

    private function compactPostal(mixed $value): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim((string) $value)));
    }

    private function normalized(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
