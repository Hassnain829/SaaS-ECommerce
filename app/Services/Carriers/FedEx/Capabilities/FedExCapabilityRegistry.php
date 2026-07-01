<?php

namespace App\Services\Carriers\FedEx\Capabilities;

/**
 * Honest customer-facing FedEx capability disclosure registry (Package 8).
 */
final class FedExCapabilityRegistry
{
    public const VERSION = '2026-07-01-package8-v1';

    public const STATUS_PRODUCTION_ENABLED = 'production_enabled';

    public const STATUS_PRODUCTION_CONDITIONAL = 'production_conditional';

    public const STATUS_VALIDATION_ONLY = 'validation_only';

    public const STATUS_NOT_SUPPORTED = 'not_supported';

    public const STATUS_BLOCKED_ENTITLEMENT = 'blocked_entitlement';

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return [
            [
                'service_type' => 'FEDEX_GROUND',
                'display_name' => 'FedEx Ground',
                'regions' => ['US'],
                'status' => self::STATUS_PRODUCTION_CONDITIONAL,
                'conditions' => ['Requires connected FedEx integrator account and origin readiness.'],
            ],
            [
                'service_type' => 'PRIORITY_OVERNIGHT',
                'display_name' => 'FedEx Priority Overnight',
                'regions' => ['US'],
                'status' => self::STATUS_VALIDATION_ONLY,
                'conditions' => ['Available in FedEx validation workspace; production checkout enablement follows entitlement.'],
            ],
            [
                'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
                'display_name' => 'FedEx International Priority',
                'regions' => ['CA'],
                'status' => self::STATUS_VALIDATION_ONLY,
                'conditions' => ['Canada validation cases only in current release.'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packaging(): array
    {
        return [
            ['packaging_type' => 'YOUR_PACKAGING', 'display_name' => 'Your Packaging', 'status' => self::STATUS_PRODUCTION_ENABLED],
            ['packaging_type' => 'FEDEX_BOX', 'display_name' => 'FedEx Box', 'status' => self::STATUS_VALIDATION_ONLY],
            ['packaging_type' => 'FEDEX_TUBE', 'display_name' => 'FedEx Tube', 'status' => self::STATUS_VALIDATION_ONLY],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function shipmentLevelSpecialServices(): array
    {
        return [
            ['enum' => 'SATURDAY_DELIVERY', 'display_name' => 'Saturday Delivery', 'status' => self::STATUS_VALIDATION_ONLY, 'regions' => ['US', 'CA']],
            ['enum' => 'EVENT_NOTIFICATION', 'display_name' => 'Shipment notification', 'status' => self::STATUS_VALIDATION_ONLY, 'regions' => ['US']],
            ['enum' => 'HOME_DELIVERY_PREMIUM', 'display_name' => 'Home Delivery Premium', 'status' => self::STATUS_VALIDATION_ONLY, 'regions' => ['US']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packageLevelSpecialServices(): array
    {
        return [
            ['enum' => 'SIGNATURE_OPTION', 'display_name' => 'Signature options', 'status' => self::STATUS_VALIDATION_ONLY, 'regions' => ['CA', 'US']],
            ['enum' => 'NON_STANDARD_CONTAINER', 'display_name' => 'Non-standard container', 'status' => self::STATUS_VALIDATION_ONLY, 'regions' => ['US']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerFacingCapabilities(): array
    {
        $filter = fn (array $item): bool => in_array(
            (string) ($item['status'] ?? ''),
            [self::STATUS_PRODUCTION_ENABLED, self::STATUS_PRODUCTION_CONDITIONAL],
            true,
        );

        return [
            'services' => array_values(array_filter($this->services(), $filter)),
            'packaging' => array_values(array_filter($this->packaging(), $filter)),
            'shipment_special_services' => array_values(array_filter($this->shipmentLevelSpecialServices(), $filter)),
            'package_special_services' => array_values(array_filter($this->packageLevelSpecialServices(), $filter)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportSummary(): array
    {
        return [
            'registry_version' => self::VERSION,
            'services' => $this->services(),
            'packaging' => $this->packaging(),
            'shipment_level_special_services' => $this->shipmentLevelSpecialServices(),
            'package_level_special_services' => $this->packageLevelSpecialServices(),
            'customer_facing' => $this->customerFacingCapabilities(),
        ];
    }
}
