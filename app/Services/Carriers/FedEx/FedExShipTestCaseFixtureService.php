<?php

namespace App\Services\Carriers\FedEx;

class FedExShipTestCaseFixtureService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'IntegratorUS02' => $this->integratorUs02(),
            'IntegratorUS04' => $this->integratorUs04(),
            'IntegratorUS05' => $this->integratorUs05(),
        ];
    }

    /**
     * @return list<string>
     */
    public function testCaseKeys(): array
    {
        return array_keys($this->fixtures());
    }

    /**
     * @return array<string, mixed>
     */
    public function fixture(string $key): array
    {
        $fixtures = $this->fixtures();

        abort_unless(isset($fixtures[$key]), 404, 'Unknown FedEx ship test case.');

        return $fixtures[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs02(): array
    {
        return [
            'key' => 'IntegratorUS02',
            'label' => 'Integrator US02 — Priority Overnight single package',
            'account_number' => '700257037',
            'service_type' => 'PRIORITY_OVERNIGHT',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'shipper' => [
                'person_name' => 'James Weston',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => 'Recipient Test',
                'company_name' => 'Validation Recipient',
                'phone' => '9015550100',
                'street_lines' => ['15 W 18TH ST FL 7'],
                'city' => 'NEW YORK',
                'state' => 'NY',
                'postal_code' => '100114624',
                'country_code' => 'US',
                'residential' => false,
            ],
            'packages' => [
                [
                    'weight' => 10.0,
                    'weight_unit' => 'LB',
                    'length' => 12.0,
                    'width' => 10.0,
                    'height' => 8.0,
                    'dimension_unit' => 'IN',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs04(): array
    {
        return [
            'key' => 'IntegratorUS04',
            'label' => 'Integrator US04 — Ground Home Delivery residential',
            'account_number' => '700257037',
            'service_type' => 'GROUND_HOME_DELIVERY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'shipper' => [
                'person_name' => 'James Weston',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => 'Residential Recipient',
                'company_name' => null,
                'phone' => '9015550101',
                'street_lines' => ['1200 S LAKE SHORE DR'],
                'city' => 'CHICAGO',
                'state' => 'IL',
                'postal_code' => '60601',
                'country_code' => 'US',
                'residential' => true,
            ],
            'packages' => [
                [
                    'weight' => 5.0,
                    'weight_unit' => 'LB',
                    'length' => 10.0,
                    'width' => 8.0,
                    'height' => 6.0,
                    'dimension_unit' => 'IN',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs05(): array
    {
        return [
            'key' => 'IntegratorUS05',
            'label' => 'Integrator US05 — FedEx Ground multi-piece',
            'account_number' => '700257037',
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'shipper' => [
                'person_name' => 'James Weston',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => 'MPS Recipient',
                'company_name' => 'MPS Validation',
                'phone' => '9015550102',
                'street_lines' => ['100 MAIN ST'],
                'city' => 'DALLAS',
                'state' => 'TX',
                'postal_code' => '75201',
                'country_code' => 'US',
                'residential' => false,
            ],
            'packages' => [
                [
                    'weight' => 8.0,
                    'weight_unit' => 'LB',
                    'length' => 12.0,
                    'width' => 10.0,
                    'height' => 8.0,
                    'dimension_unit' => 'IN',
                ],
                [
                    'weight' => 6.0,
                    'weight_unit' => 'LB',
                    'length' => 10.0,
                    'width' => 8.0,
                    'height' => 6.0,
                    'dimension_unit' => 'IN',
                ],
            ],
        ];
    }
}
