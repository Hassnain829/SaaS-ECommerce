<?php

namespace App\Services\Carriers\FedEx\Capabilities;

use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;

/**
 * Honest customer-facing FedEx capability disclosure registry (Package 8).
 */
final class FedExCapabilityRegistry
{
    public const VERSION = '2026-07-14-package8-v2';

    public const STATUS_PRODUCTION_ENABLED = 'production_enabled';

    public const STATUS_PRODUCTION_CONDITIONAL = 'production_conditional';

    public const STATUS_VALIDATION_ONLY = 'validation_only';

    public const STATUS_NOT_SUPPORTED = 'not_supported';

    public const STATUS_BLOCKED_ENTITLEMENT = 'blocked_entitlement';

    public function __construct(
        private readonly FedExBrandComplianceService $brandCompliance,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        $condition = 'Requires a connected FedEx integrator account and origin readiness.';

        return [
            $this->service('FEDEX_GROUND', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service('FEDEX_2_DAY', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service('FEDEX_2_DAY_AM', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service('PRIORITY_OVERNIGHT', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service('STANDARD_OVERNIGHT', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service('FEDEX_EXPRESS_SAVER', self::STATUS_PRODUCTION_CONDITIONAL, ['US'], [$condition]),
            $this->service(
                'FEDEX_INTERNATIONAL_PRIORITY',
                self::STATUS_VALIDATION_ONLY,
                ['CA'],
                ['Canada validation cases only in current release.'],
                'FedEx International Priority',
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packaging(): array
    {
        return [
            $this->packagingItem('YOUR_PACKAGING', self::STATUS_PRODUCTION_ENABLED, 'Your Packaging'),
            $this->packagingItem('FEDEX_ENVELOPE', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_PAK', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_SMALL_BOX', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_MEDIUM_BOX', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_LARGE_BOX', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_EXTRA_LARGE_BOX', self::STATUS_PRODUCTION_CONDITIONAL),
            $this->packagingItem('FEDEX_BOX', self::STATUS_VALIDATION_ONLY, 'FedEx Box'),
            $this->packagingItem('FEDEX_TUBE', self::STATUS_VALIDATION_ONLY, 'FedEx Tube'),
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
     * Registered trademark display names required on branding evidence screenshots.
     *
     * @return array{services: list<string>, packaging: list<string>}
     */
    public function brandingEvidenceDisplayNames(): array
    {
        return [
            'services' => [
                $this->display('FEDEX_GROUND'),
                $this->display('FEDEX_2_DAY'),
                $this->display('FEDEX_2_DAY_AM'),
                $this->display('PRIORITY_OVERNIGHT'),
                $this->display('STANDARD_OVERNIGHT'),
                $this->display('FEDEX_EXPRESS_SAVER'),
            ],
            'packaging' => [
                $this->display('FEDEX_ENVELOPE'),
                $this->display('FEDEX_PAK'),
                $this->display('FEDEX_SMALL_BOX'),
                $this->display('FEDEX_MEDIUM_BOX'),
                $this->display('FEDEX_LARGE_BOX'),
                $this->display('FEDEX_EXTRA_LARGE_BOX'),
            ],
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
            'branding_evidence_display_names' => $this->brandingEvidenceDisplayNames(),
        ];
    }

    /**
     * @param  list<string>  $regions
     * @param  list<string>  $conditions
     * @return array<string, mixed>
     */
    private function service(
        string $serviceType,
        string $status,
        array $regions,
        array $conditions = [],
        ?string $displayOverride = null,
    ): array {
        return [
            'service_type' => $serviceType,
            'display_name' => $displayOverride ?? $this->display($serviceType),
            'regions' => $regions,
            'status' => $status,
            'conditions' => $conditions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packagingItem(string $packagingType, string $status, ?string $displayOverride = null): array
    {
        return [
            'packaging_type' => $packagingType,
            'display_name' => $displayOverride ?? $this->display($packagingType),
            'status' => $status,
        ];
    }

    private function display(string $value): string
    {
        return (string) ($this->brandCompliance->registeredDisplayName($value) ?? $value);
    }
}
