<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use Illuminate\Validation\ValidationException;

class FedExValidationQuickTestPresets
{
    public function __construct(
        private readonly FedExTestCaseFixtureService $baselineFixtures,
        private readonly FedExShipTestCaseFixtureService $shipFixtures,
        private readonly CarrierOriginReadinessService $originReadiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function addressCheck(): array
    {
        $account = $this->baselineFixtures->usValidationAccount();

        return [
            'address_line1' => (string) ($account['address_line1'] ?? '15 W 18TH ST FL 7'),
            'address_line2' => (string) ($account['address_line2'] ?? ''),
            'city' => (string) ($account['city'] ?? 'NEW YORK'),
            'state' => (string) ($account['state'] ?? 'NY'),
            'postal_code' => (string) ($account['postal_code'] ?? '100114624'),
            'country_code' => strtoupper((string) ($account['country_code'] ?? 'US')),
            'residential' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceAvailability(Store $store): array
    {
        $us02 = $this->shipFixtures->fixture('IntegratorUS02');
        $recipient = is_array($us02['recipient'] ?? null) ? $us02['recipient'] : [];

        return [
            'origin_location_id' => $this->resolveOriginLocationId($store),
            'destination_country' => strtoupper((string) ($recipient['country_code'] ?? 'US')),
            'destination_postal_code' => (string) ($recipient['postal_code'] ?? '38017'),
            'destination_state' => strtoupper((string) ($recipient['state'] ?? 'TN')),
            'destination_city' => strtoupper((string) ($recipient['city'] ?? 'Collierville')),
            'packaging_type' => (string) ($us02['packaging_type'] ?? 'YOUR_PACKAGING'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rateQuote(Store $store): array
    {
        $us02 = $this->shipFixtures->fixture('IntegratorUS02');
        $package = is_array($us02['packages'][0] ?? null) ? $us02['packages'][0] : [];

        return [
            'origin_location_id' => $this->resolveOriginLocationId($store),
            'destination_country' => 'US',
            'destination_postal_code' => '60601',
            'destination_state' => 'IL',
            'destination_city' => 'CHICAGO',
            'weight_value' => (float) ($package['weight'] ?? 10),
            'length' => (float) ($package['length'] ?? 12),
            'width' => (float) ($package['width'] ?? 10),
            'height' => (float) ($package['height'] ?? 8),
            'service_type' => (string) ($us02['service_type'] ?? 'PRIORITY_OVERNIGHT'),
            'packaging_type' => (string) ($us02['packaging_type'] ?? 'YOUR_PACKAGING'),
            'residential' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shipValidate(string $testCaseKey = 'IntegratorUS02'): array
    {
        $this->shipFixtures->fixture($testCaseKey);

        return [
            'test_case' => $testCaseKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shipLabel(string $testCaseKey = 'IntegratorUS02'): array
    {
        $fixture = $this->shipFixtures->fixture($testCaseKey);

        return [
            'test_case' => $testCaseKey,
            'label_format' => strtoupper((string) ($fixture['label_format'] ?? 'PDF')),
        ];
    }

    /**
     * @return list<array{key: string, label: string, action: string}>
     */
    public function quickActions(): array
    {
        return [
            ['key' => 'address', 'label' => 'Run address check (baseline US account)', 'action' => 'address'],
            ['key' => 'service', 'label' => 'Check service availability (US02 route)', 'action' => 'service'],
            ['key' => 'rate', 'label' => 'Get comprehensive rate quote (baseline)', 'action' => 'rate'],
            ['key' => 'ship_us02', 'label' => 'Ship validate — IntegratorUS02', 'action' => 'ship_validate', 'test_case' => 'IntegratorUS02'],
            ['key' => 'ship_us04', 'label' => 'Ship validate — IntegratorUS04', 'action' => 'ship_validate', 'test_case' => 'IntegratorUS04'],
            ['key' => 'ship_us05', 'label' => 'Ship validate — IntegratorUS05', 'action' => 'ship_validate', 'test_case' => 'IntegratorUS05'],
            ['key' => 'label_us02', 'label' => 'Create label — IntegratorUS02 (ZPLII)', 'action' => 'ship_label', 'test_case' => 'IntegratorUS02'],
            ['key' => 'label_us04', 'label' => 'Create label — IntegratorUS04 (PNG)', 'action' => 'ship_label', 'test_case' => 'IntegratorUS04'],
            ['key' => 'label_us05', 'label' => 'Create label — IntegratorUS05 (PDF MPS)', 'action' => 'ship_label', 'test_case' => 'IntegratorUS05'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function presetSummary(string $action, ?string $testCaseKey = null): array
    {
        return match ($action) {
            'address' => [
                'preset' => 'FedEx baseline US validation account address',
                'source' => $this->baselineFixtures->baselineAvailable() ? 'baseline workbook' : 'fallback fixture',
            ],
            'service' => [
                'preset' => 'IntegratorUS02 shipper origin → Collierville TN destination',
                'source' => 'FedEx ship test case baseline',
            ],
            'rate' => [
                'preset' => 'Baseline origin → Chicago IL · PRIORITY_OVERNIGHT · US02 package',
                'source' => 'FedEx comprehensive rate validation preset',
            ],
            'ship_validate', 'ship_label' => [
                'preset' => 'FedEx integrator ship test case '.($testCaseKey ?? 'IntegratorUS02'),
                'source' => 'FedEx ship test case baseline',
            ],
            default => [
                'preset' => 'FedEx baseline validation preset',
                'source' => 'baseline',
            ],
        };
    }

    private function resolveOriginLocationId(Store $store): int
    {
        $locations = $store->locations()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        foreach ($locations as $location) {
            if ($this->originReadiness->assess($location, CarrierOriginReadinessService::CARRIER_GENERIC)->ready) {
                return (int) $location->id;
            }
        }

        $fallback = $locations->first();
        if ($fallback instanceof Location) {
            return (int) $fallback->id;
        }

        throw ValidationException::withMessages([
            'origin_location_id' => 'Add an active fulfillment location before running FedEx validation checks.',
        ]);
    }
}
