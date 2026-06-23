<?php

namespace App\Services\Carriers\FedEx\Validation;

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

    public function lockedLabelFormat(string $testCaseKey): string
    {
        return (string) (FedExValidationScenarioCatalog::lockedLabelFormat($testCaseKey)
            ?? abort(422, 'Unknown locked ship test case.'));
    }

    /**
     * FedEx integrator sandbox invoice MFA test values for validation workspace prefill.
     *
     * @return array{number: string, date: string, currency: string, amount: string}
     */
    public function mfaInvoice(): array
    {
        return [
            'number' => '234562278',
            'date' => now()->subMonths(1)->format('Y-m-d'),
            'currency' => 'USD',
            'amount' => '234.00',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs02(): array
    {
        return [
            'key' => 'IntegratorUS02',
            'label' => 'Integrator US02 — Priority Overnight · ZPLII · STOCK_4X6',
            'scenario_key' => 'ship_us02_zplii',
            'account_number' => '700257037',
            'service_type' => 'PRIORITY_OVERNIGHT',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'ZPLII',
            'label_stock_type' => 'STOCK_4X6',
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
                'person_name' => 'FedEx Validation Recipient',
                'company_name' => 'FedEx Validation',
                'phone' => '9015550100',
                'street_lines' => ['20 FedEx Pkwy'],
                'city' => 'Collierville',
                'state' => 'TN',
                'postal_code' => '38017',
                'country_code' => 'US',
                'residential' => false,
            ],
            'packages' => [[
                'weight' => 10.0,
                'weight_unit' => 'LB',
                'length' => 12.0,
                'width' => 10.0,
                'height' => 8.0,
                'dimension_unit' => 'IN',
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs04(): array
    {
        return [
            'key' => 'IntegratorUS04',
            'label' => 'Integrator US04 — Ground Home Delivery · PNG · PAPER_4X6',
            'scenario_key' => 'ship_us04_png',
            'account_number' => '700257037',
            'service_type' => 'GROUND_HOME_DELIVERY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PNG',
            'label_stock_type' => 'PAPER_4X6',
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
                'street_lines' => ['109 FEDEX PRKWY'],
                'city' => 'Collierville',
                'state' => 'TN',
                'postal_code' => '38017',
                'country_code' => 'US',
                'residential' => true,
            ],
            'packages' => [[
                'weight' => 30.0,
                'weight_unit' => 'LB',
                'length' => 20.0,
                'width' => 15.0,
                'height' => 20.0,
                'dimension_unit' => 'IN',
            ]],
            'special_services' => [[
                'specialServiceTypes' => ['HOME_DELIVERY_PREMIUM'],
                'homeDeliveryPremiumDetail' => ['homedeliveryPremiumType' => 'EVENING'],
            ]],
            'declared_value' => ['amount' => 300, 'currency' => 'USD'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs05(): array
    {
        return [
            'key' => 'IntegratorUS05',
            'label' => 'Integrator US05 — FedEx Ground MPS · PDF · PAPER_85X11_TOP_HALF_LABEL',
            'scenario_key' => 'ship_us05_pdf_mps',
            'account_number' => '700257037',
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'total_package_count' => 2,
            'transportation_payment_type' => 'RECIPIENT',
            'transportation_payment_account' => '700257037',
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
                'street_lines' => ['80 FEDEX PRKWY'],
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postal_code' => '90013',
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
