<?php

namespace App\Support\Tax;

final class TaxCountryCatalog
{
    /** @var array<string, string>|null */
    private static ?array $countries = null;

    /** @var array<string, array<string, string>>|null */
    private static ?array $regions = null;

    /**
     * @return array<string, string> ISO-2 code => English country name, sorted by name
     */
    public static function all(): array
    {
        $countries = self::countries();
        asort($countries, SORT_STRING | SORT_FLAG_CASE);

        return $countries;
    }

    public static function name(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $countries = self::countries();

        return $countries[$normalized] ?? $normalized;
    }

    /**
     * @return array<string, string> region code => label
     */
    public static function regionsFor(string $countryCode): array
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $regions = self::regionCatalog()[$normalizedCountry] ?? [];

        asort($regions, SORT_STRING | SORT_FLAG_CASE);

        return $regions;
    }

    public static function regionLabel(string $countryCode, string $regionCode): ?string
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $normalizedRegion = strtoupper(trim($regionCode));

        if ($normalizedRegion === '') {
            return null;
        }

        return self::regionCatalog()[$normalizedCountry][$normalizedRegion] ?? null;
    }

    /**
     * @return array{
     *     country_name: string,
     *     scope: 'country-wide'|'region-specific',
     *     region_label: ?string,
     *     display: string,
     * }
     */
    public static function jurisdictionSummary(string $countryCode, ?string $regionCode = null): array
    {
        $normalizedCountry = strtoupper(trim($countryCode));
        $normalizedRegion = $regionCode === null ? '' : strtoupper(trim($regionCode));
        $countryName = self::name($normalizedCountry);

        if ($normalizedRegion === '') {
            return [
                'country_name' => $countryName,
                'scope' => 'country-wide',
                'region_label' => null,
                'display' => $countryName,
            ];
        }

        $regionLabel = self::regionLabel($normalizedCountry, $normalizedRegion);

        return [
            'country_name' => $countryName,
            'scope' => 'region-specific',
            'region_label' => $regionLabel,
            'display' => $regionLabel !== null
                ? "{$regionLabel}, {$countryName}"
                : "{$normalizedRegion}, {$countryName}",
        ];
    }

    /**
     * @return array<string, array<string, string>> country => [region code => label]
     */
    public static function allRegions(): array
    {
        if (self::$regions === null) {
            /** @var array<string, array<string, string>> $regions */
            $regions = require __DIR__.'/data/regions.php';
            self::$regions = $regions;
        }

        return self::$regions;
    }

    /**
     * @return array<string, string>
     */
    private static function countries(): array
    {
        if (self::$countries === null) {
            /** @var array<string, string> $countries */
            $countries = require __DIR__.'/data/countries.php';
            self::$countries = $countries;
        }

        return self::$countries;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function regionCatalog(): array
    {
        return self::allRegions();
    }
}
