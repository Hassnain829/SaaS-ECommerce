<?php

namespace App\Services\Fulfillment;

use App\Models\Location;
use App\Models\Store;

class LocationServiceAreaMatcher
{
    /**
     * @param  array<string, mixed>  $address
     */
    public function matchesAddress(Location $location, array $address, Store $store): bool
    {
        return (bool) $this->scoreAddress($location, $address, $store)['matches'];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array{matches: bool, score: int, matched_by: string, service_area: array<string, mixed>}
     */
    public function scoreAddress(Location $location, array $address, Store $store): array
    {
        $score = 0;
        $matchedBy = [];
        $serviceCountries = $this->serviceCountries($location, $store);
        $serviceRegions = $this->normalizedList($location->service_regions);
        $servicePostalPatterns = $this->normalizedList($location->service_postal_patterns, preserveWildcard: true);

        $destinationCountry = $this->countryCode($address['country_code'] ?? $address['country'] ?? '');
        $regionCandidates = $this->regionCandidates($address);
        $postalCode = $this->postalCode($address['postal_code'] ?? '');

        if ($serviceCountries !== []) {
            if ($destinationCountry === '' || ! in_array($destinationCountry, $serviceCountries, true)) {
                return $this->noMatch($serviceCountries, $serviceRegions, $servicePostalPatterns);
            }

            $score += 20;
            $matchedBy[] = 'country';
        }

        if ($serviceRegions !== []) {
            if ($regionCandidates === [] || collect($regionCandidates)->intersect($serviceRegions)->isEmpty()) {
                return $this->noMatch($serviceCountries, $serviceRegions, $servicePostalPatterns);
            }

            $score += 40;
            $matchedBy[] = 'region';
        }

        if ($servicePostalPatterns !== []) {
            $postalMatch = $this->postalMatch($postalCode, $servicePostalPatterns);
            if ($postalMatch === null) {
                return $this->noMatch($serviceCountries, $serviceRegions, $servicePostalPatterns);
            }

            $score += $postalMatch === 'exact' ? 100 : 90;
            $matchedBy[] = $postalMatch === 'exact' ? 'postal_exact' : 'postal_prefix';
        }

        if ($location->is_default) {
            $score += 5;
            $matchedBy[] = 'default_location';
        }

        if ($matchedBy === []) {
            $matchedBy[] = 'store_service_area';
        }

        return [
            'matches' => true,
            'score' => $score,
            'matched_by' => implode(',', $matchedBy),
            'service_area' => [
                'countries' => $serviceCountries,
                'regions' => $serviceRegions,
                'postal_patterns' => $servicePostalPatterns,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function serviceCountries(Location $location, Store $store): array
    {
        $countries = collect($this->normalizedList($location->service_countries))
            ->map(fn (string $country): string => $this->countryCode($country))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($countries !== []) {
            return $countries;
        }

        $fallback = $this->countryCode($location->country_code ?: $this->storeCountryCode($store));

        return $fallback !== '' ? [$fallback] : [];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function normalizedList(mixed $value, bool $preserveWildcard = false): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (! is_array($value)) {
            $value = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        return collect($value)
            ->map(fn ($item): string => strtoupper(trim((string) $item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): string => $preserveWildcard ? $item : rtrim($item, '*'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $address
     * @return list<string>
     */
    private function regionCandidates(array $address): array
    {
        return collect([
            $address['province_code'] ?? null,
            $address['state'] ?? null,
            $address['region'] ?? null,
        ])
            ->map(fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function postalMatch(string $postalCode, array $patterns): ?string
    {
        if ($postalCode === '') {
            return null;
        }

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*') && $postalCode === $this->postalCode($pattern)) {
                return 'exact';
            }
        }

        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                $prefix = $this->postalCode(rtrim($pattern, '*'));
                if ($prefix !== '' && str_starts_with($postalCode, $prefix)) {
                    return 'prefix';
                }
            }
        }

        return null;
    }

    private function postalCode(mixed $value): string
    {
        return strtoupper(str_replace(' ', '', trim((string) $value)));
    }

    private function countryCode(mixed $country): string
    {
        $country = strtoupper(trim((string) $country));

        return match ($country) {
            'UNITED STATES', 'UNITED STATES OF AMERICA', 'USA' => 'US',
            'UNITED KINGDOM', 'UK' => 'GB',
            'CANADA' => 'CA',
            'PAKISTAN' => 'PK',
            'UNITED ARAB EMIRATES', 'UAE' => 'AE',
            default => strlen($country) === 2 ? $country : '',
        };
    }

    private function storeCountryCode(Store $store): string
    {
        $settings = is_array($store->settings) ? $store->settings : [];
        foreach (['country_code', 'business_country_code', 'store_country_code', 'primary_market'] as $key) {
            $country = $this->countryCode($settings[$key] ?? '');
            if ($country !== '') {
                return $country;
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $countries
     * @param  list<string>  $regions
     * @param  list<string>  $postalPatterns
     * @return array{matches: bool, score: int, matched_by: string, service_area: array<string, mixed>}
     */
    private function noMatch(array $countries, array $regions, array $postalPatterns): array
    {
        return [
            'matches' => false,
            'score' => 0,
            'matched_by' => 'none',
            'service_area' => [
                'countries' => $countries,
                'regions' => $regions,
                'postal_patterns' => $postalPatterns,
            ],
        ];
    }
}
