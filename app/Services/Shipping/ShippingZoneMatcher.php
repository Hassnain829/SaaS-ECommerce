<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Models\Store;
use App\Support\CountryCode;
use Illuminate\Support\Collection;

class ShippingZoneMatcher
{
    /**
     * @param  array<string, mixed>  $address
     * @return Collection<int, ShippingZone>
     */
    public function matchingZones(Store $store, array $address): Collection
    {
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

        return $this->matchesCountry($zone, $address)
            && $this->matchesRegion($zone, $address)
            && $this->matchesPostalCode($zone, $address);
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

        $candidates = collect([
            $address['province_code'] ?? null,
            $address['state'] ?? null,
            $address['region'] ?? null,
        ])
            ->flatMap(fn ($region): array => $this->regionVariants($region))
            ->filter()
            ->unique()
            ->values();

        $zoneRegions = $regions
            ->flatMap(fn (string $region): array => $this->regionVariants($region))
            ->unique()
            ->values();

        return $candidates->contains(fn (string $candidate): bool => $zoneRegions->contains($candidate));
    }

    /**
     * @return list<string>
     */
    private function regionVariants(mixed $region): array
    {
        $normalized = $this->normalized($region);
        if ($normalized === '') {
            return [];
        }

        $variants = [$normalized];
        $aliases = $this->usStateAliases();

        if (isset($aliases[$normalized])) {
            $variants[] = $aliases[$normalized];
        }

        foreach ($aliases as $abbreviation => $fullName) {
            if ($fullName === $normalized) {
                $variants[] = $abbreviation;
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return array<string, string>
     */
    private function usStateAliases(): array
    {
        return [
            'AL' => 'ALABAMA',
            'AK' => 'ALASKA',
            'AZ' => 'ARIZONA',
            'AR' => 'ARKANSAS',
            'CA' => 'CALIFORNIA',
            'CO' => 'COLORADO',
            'CT' => 'CONNECTICUT',
            'DE' => 'DELAWARE',
            'FL' => 'FLORIDA',
            'GA' => 'GEORGIA',
            'HI' => 'HAWAII',
            'ID' => 'IDAHO',
            'IL' => 'ILLINOIS',
            'IN' => 'INDIANA',
            'IA' => 'IOWA',
            'KS' => 'KANSAS',
            'KY' => 'KENTUCKY',
            'LA' => 'LOUISIANA',
            'ME' => 'MAINE',
            'MD' => 'MARYLAND',
            'MA' => 'MASSACHUSETTS',
            'MI' => 'MICHIGAN',
            'MN' => 'MINNESOTA',
            'MS' => 'MISSISSIPPI',
            'MO' => 'MISSOURI',
            'MT' => 'MONTANA',
            'NE' => 'NEBRASKA',
            'NV' => 'NEVADA',
            'NH' => 'NEW HAMPSHIRE',
            'NJ' => 'NEW JERSEY',
            'NM' => 'NEW MEXICO',
            'NY' => 'NEW YORK',
            'NC' => 'NORTH CAROLINA',
            'ND' => 'NORTH DAKOTA',
            'OH' => 'OHIO',
            'OK' => 'OKLAHOMA',
            'OR' => 'OREGON',
            'PA' => 'PENNSYLVANIA',
            'RI' => 'RHODE ISLAND',
            'SC' => 'SOUTH CAROLINA',
            'SD' => 'SOUTH DAKOTA',
            'TN' => 'TENNESSEE',
            'TX' => 'TEXAS',
            'UT' => 'UTAH',
            'VT' => 'VERMONT',
            'VA' => 'VIRGINIA',
            'WA' => 'WASHINGTON',
            'WV' => 'WEST VIRGINIA',
            'WI' => 'WISCONSIN',
            'WY' => 'WYOMING',
            'DC' => 'DISTRICT OF COLUMBIA',
        ];
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

        $postalCode = $this->normalized($address['postal_code'] ?? null);
        if ($postalCode === '') {
            return false;
        }

        return $patterns->contains(fn ($pattern): bool => $this->postalPatternMatches((string) $pattern, $postalCode));
    }

    private function postalPatternMatches(string $pattern, string $postalCode): bool
    {
        $pattern = $this->normalized($pattern);

        if ($pattern === '') {
            return false;
        }

        if (! str_contains($pattern, '*')) {
            return $postalCode === $pattern;
        }

        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $postalCode);
    }

    private function normalized(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
