<?php

namespace App\Services\Carriers\FedEx\Support;

/**
 * Merchant-facing FedEx service catalog for checkout configuration.
 * Curated platform-supported list only — live Service Availability / rates decide route usability.
 */
final class FedExCheckoutServiceCatalog
{
    public const SCOPE_DOMESTIC = 'domestic';

    public const SCOPE_INTERNATIONAL = 'international';

    /**
     * @return list<array{code: string, name: string, description: string, scope: string}>
     */
    public static function services(): array
    {
        return [
            [
                'code' => 'FEDEX_GROUND',
                'name' => 'FedEx Ground',
                'description' => 'Affordable ground delivery for business addresses',
                'scope' => self::SCOPE_DOMESTIC,
            ],
            [
                'code' => 'GROUND_HOME_DELIVERY',
                'name' => 'FedEx Home Delivery',
                'description' => 'Residential ground delivery',
                'scope' => self::SCOPE_DOMESTIC,
            ],
            [
                'code' => 'FEDEX_EXPRESS_SAVER',
                'name' => 'FedEx Express Saver',
                'description' => 'Economy express delivery',
                'scope' => self::SCOPE_DOMESTIC,
            ],
            [
                'code' => 'FEDEX_2_DAY',
                'name' => 'FedEx 2Day',
                'description' => 'Two-business-day delivery',
                'scope' => self::SCOPE_DOMESTIC,
            ],
            [
                'code' => 'PRIORITY_OVERNIGHT',
                'name' => 'FedEx Priority Overnight',
                'description' => 'Next-business-day morning delivery',
                'scope' => self::SCOPE_DOMESTIC,
            ],
            [
                'code' => 'FEDEX_INTERNATIONAL_PRIORITY',
                'name' => 'FedEx International Priority',
                'description' => 'Time-definite cross-border delivery (US ↔ Canada and other supported routes)',
                'scope' => self::SCOPE_INTERNATIONAL,
            ],
            [
                'code' => 'INTERNATIONAL_ECONOMY',
                'name' => 'FedEx International Economy',
                'description' => 'Cost-effective international delivery when available',
                'scope' => self::SCOPE_INTERNATIONAL,
            ],
        ];
    }

    /**
     * Preferred platform-supported return service when availability allows it.
     */
    public static function defaultReturnServiceCode(): string
    {
        return 'FEDEX_GROUND';
    }

    /**
     * @return array<string, string> code => name
     */
    public static function nameMap(): array
    {
        $map = [];
        foreach (self::services() as $service) {
            $map[$service['code']] = $service['name'];
        }

        return $map;
    }

    public static function nameFor(string $code): string
    {
        $normalized = strtoupper(trim($code));

        return self::nameMap()[$normalized] ?? str_replace('_', ' ', $normalized);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::services(), 'code');
    }

    /**
     * @return list<string>
     */
    public static function domesticCodes(): array
    {
        return array_values(array_map(
            static fn (array $service): string => $service['code'],
            array_filter(self::services(), static fn (array $service): bool => $service['scope'] === self::SCOPE_DOMESTIC),
        ));
    }

    /**
     * @return list<string>
     */
    public static function internationalCodes(): array
    {
        return array_values(array_map(
            static fn (array $service): string => $service['code'],
            array_filter(self::services(), static fn (array $service): bool => $service['scope'] === self::SCOPE_INTERNATIONAL),
        ));
    }

    public static function isKnown(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::codes(), true);
    }
}
