<?php

namespace App\Support;

use App\Support\Tax\TaxCountryCatalog;

final class CountryCode
{
    public static function normalize(mixed $country): string
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '') {
            return '';
        }

        if (preg_match('/^([A-Z]{2})\s*\(\s*\)\s*$/', $country, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/\(([A-Z]{2})\)\s*$/', $country, $matches) === 1) {
            return self::normalize($matches[1]);
        }

        $mapped = match ($country) {
            'UNITED STATES', 'UNITED STATES OF AMERICA', 'USA', 'U.S.', 'U.S.A.', 'US' => 'US',
            'UNITED KINGDOM', 'UK', 'GREAT BRITAIN', 'GB' => 'GB',
            'CANADA', 'CA' => 'CA',
            'PAKISTAN', 'PK' => 'PK',
            'UNITED ARAB EMIRATES', 'UAE', 'AE' => 'AE',
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        if (strlen($country) === 2) {
            return $country;
        }

        return self::fromCatalogName($country);
    }

    private static function fromCatalogName(string $normalizedName): string
    {
        foreach (TaxCountryCatalog::all() as $code => $name) {
            if (strtoupper(trim((string) $name)) === $normalizedName) {
                return strtoupper((string) $code);
            }
        }

        return '';
    }

    /**
     * Prefer a full country name over a conflicting 2-letter code.
     * WordPress and other storefronts often send "United States" with a truncated code like "UN".
     *
     * @param  array<string, mixed>  $address
     */
    public static function fromAddress(array $address): string
    {
        $rawName = strtoupper(trim((string) ($address['country'] ?? '')));
        $fromName = self::normalize($address['country'] ?? '');
        $fromCode = self::normalize($address['country_code'] ?? '');

        if ($fromName !== '' && strlen($rawName) > 2) {
            return $fromName;
        }

        return $fromCode !== '' ? $fromCode : $fromName;
    }
}
