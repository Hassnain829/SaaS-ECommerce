<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use App\Models\Store;
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
            ->map(fn ($country): string => $this->countryCode($country))
            ->filter()
            ->values();

        if ($countries->isEmpty()) {
            return true;
        }

        $country = $this->countryCode($address['country_code'] ?? $address['country'] ?? null);

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
            $address['city'] ?? null,
        ])
            ->map(fn ($region): string => $this->normalized($region))
            ->filter();

        return $candidates->contains(fn (string $candidate): bool => $regions->contains($candidate));
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

    private function countryCode(mixed $country): string
    {
        $country = $this->normalized($country);

        return match ($country) {
            'UNITED STATES', 'UNITED STATES OF AMERICA', 'USA' => 'US',
            'UNITED KINGDOM', 'UK' => 'GB',
            'CANADA' => 'CA',
            'PAKISTAN' => 'PK',
            default => $country,
        };
    }

    private function normalized(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
