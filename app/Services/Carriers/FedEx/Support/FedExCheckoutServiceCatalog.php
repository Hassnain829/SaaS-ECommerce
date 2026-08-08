<?php

namespace App\Services\Carriers\FedEx\Support;

/**
 * Merchant-facing FedEx service catalog for checkout configuration.
 */
final class FedExCheckoutServiceCatalog
{
    /**
     * @return list<array{code: string, name: string, description: string}>
     */
    public static function services(): array
    {
        return [
            [
                'code' => 'FEDEX_GROUND',
                'name' => 'FedEx Ground',
                'description' => 'Affordable ground delivery for business addresses',
            ],
            [
                'code' => 'GROUND_HOME_DELIVERY',
                'name' => 'FedEx Home Delivery',
                'description' => 'Residential ground delivery',
            ],
            [
                'code' => 'FEDEX_EXPRESS_SAVER',
                'name' => 'FedEx Express Saver',
                'description' => 'Economy express delivery',
            ],
            [
                'code' => 'FEDEX_2_DAY',
                'name' => 'FedEx 2Day',
                'description' => 'Two-business-day delivery',
            ],
            [
                'code' => 'PRIORITY_OVERNIGHT',
                'name' => 'FedEx Priority Overnight',
                'description' => 'Next-business-day morning delivery',
            ],
        ];
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
        return self::nameMap()[strtoupper(trim($code))] ?? str_replace('_', ' ', strtoupper(trim($code)));
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::services(), 'code');
    }
}
