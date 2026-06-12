<?php

namespace App\Support;

use App\Services\Carriers\CarrierOriginReadinessService;

final class CarrierCountryOptions
{
    /**
     * @return array<string, string> ISO-2 => merchant label
     */
    public static function fedExOptions(): array
    {
        return [
            'US' => 'United States',
        ];
    }

    public static function defaultFedExCountry(?string $originCountryCode = null): string
    {
        $normalized = app(CarrierOriginReadinessService::class)->normalizeCountryCode($originCountryCode);

        return $normalized === 'US' ? 'US' : 'US';
    }

    public static function isAllowedFedExCountry(?string $countryCode): bool
    {
        return $countryCode === 'US';
    }
}
