<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Package 7B — locked Canada ship fixtures from Americas_CA_Test cases (workbook V7.0).
 */
class FedExCanadaShipTestCaseFixtureService
{
    public const FIXTURE_VERSION = '2026-06-30-workbook-v1';

    public const BASELINE_SHEET = 'Americas_CA_Test cases';

    public const CA_TEST_ACCOUNT = '614365501';

    public const CA_THIRD_PARTY_ACCOUNT = '150067600';

    public const CA_DUTIES_THIRD_PARTY_ACCOUNT = '198823520';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'IntegratorCA01' => $this->integratorCa01(),
            'IntegratorCA02' => $this->integratorCa02(),
            'IntegratorCA03' => $this->integratorCa03(),
            'IntegratorCA04' => $this->integratorCa04(),
            'IntegratorCA05' => $this->integratorCa05(),
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

        abort_unless(isset($fixtures[$key]), 404, 'Unknown FedEx Canada ship test case.');

        return $fixtures[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorCa01(): array
    {
        return [
            'key' => 'IntegratorCA01',
            'label' => 'FedEx Express Saver · PDF · domestic Canada',
            'scenario_key' => 'ship_ca01_pdf',
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorCA01',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => self::CA_TEST_ACCOUNT,
            'expected_service_type' => 'FEDEX_EXPRESS_SAVER',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_EXPRESS_SAVER',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'SENDER',
            'shipper' => [
                'person_name' => 'Integrator',
                'company_name' => 'RTC',
                'phone' => '9052125456',
                'street_lines' => ['5985 EXPLORER DR'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
            ],
            'recipient' => [
                'person_name' => 'FES-1001',
                'company_name' => 'Recipient Company',
                'phone' => '9052125251',
                'street_lines' => ['Recipient address Line 1'],
                'city' => 'Winnipeg',
                'state' => 'MB',
                'postal_code' => 'R2G0A1',
                'country_code' => 'CA',
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 15.0,
                'weight_unit' => 'LB',
                'length' => 25.0,
                'width' => 25.0,
                'height' => 25.0,
                'dimension_unit' => 'IN',
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'CUSTOMER_REFERENCE',
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorCa02(): array
    {
        return [
            'key' => 'IntegratorCA02',
            'label' => 'Priority Overnight · FedEx Tube · PNG',
            'scenario_key' => 'ship_ca02_png',
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorCA02',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => self::CA_TEST_ACCOUNT,
            'expected_service_type' => 'PRIORITY_OVERNIGHT',
            'expected_label_format' => 'PNG',
            'expected_package_count' => 1,
            'service_type' => 'PRIORITY_OVERNIGHT',
            'packaging_type' => 'FEDEX_TUBE',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PNG',
            'label_stock_type' => 'PAPER_4X6',
            'transportation_payment_type' => 'SENDER',
            'shipper' => [
                'person_name' => 'Integrator',
                'company_name' => 'RTC',
                'phone' => '9052125456',
                'street_lines' => ['5985 EXPLORER DR'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
            ],
            'recipient' => [
                'person_name' => 'PO-1007',
                'company_name' => 'Recipient Company',
                'phone' => '9012367890',
                'street_lines' => ['Recipient address Line 1'],
                'city' => 'St-Laurent',
                'state' => 'PQ',
                'postal_code' => 'H4S1A1',
                'country_code' => 'CA',
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 15.0,
                'weight_unit' => 'LB',
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'CUSTOMER_REFERENCE',
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorCa03(): array
    {
        return [
            'key' => 'IntegratorCA03',
            'label' => 'International Priority · PDF · Friday + Saturday Delivery',
            'scenario_key' => 'ship_ca03_pdf',
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorCA03',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => self::CA_TEST_ACCOUNT,
            'expected_service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'packaging_type' => 'FEDEX_TUBE',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'SENDER',
            'ship_date_strategy' => 'saturday_delivery_friday',
            'shipper' => [
                'person_name' => 'integrator',
                'company_name' => 'RTC',
                'phone' => '9052125456',
                'street_lines' => ['5985 EXPLORER DR'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
            ],
            'recipient' => [
                'person_name' => 'IP-1007',
                'company_name' => 'Recipient Company',
                'phone' => '9012367890',
                'street_lines' => ['Intergrator Testing'],
                'city' => 'Lancaster',
                'state' => 'PA',
                'postal_code' => '17601',
                'country_code' => 'US',
                'residential' => true,
            ],
            'shipment_special_services' => [
                'specialServiceTypes' => ['SATURDAY_DELIVERY'],
            ],
            'customs_clearance' => [
                'is_document_only' => true,
                'total_customs_value' => ['amount' => 15.0, 'currency' => 'CAD'],
                'duties_payment_type' => 'THIRD_PARTY',
                'duties_payment_account' => self::CA_DUTIES_THIRD_PARTY_ACCOUNT,
                'duties_payor' => [
                    'person_name' => 'Integrator Testing',
                    'country_code' => 'CA',
                ],
                'commodities' => [[
                    'number_of_pieces' => 1,
                    'description' => 'Dictionaries',
                    'country_of_manufacture' => 'CA',
                    'weight' => ['units' => 'LB', 'value' => 4.0],
                    'quantity' => 1,
                    'quantity_units' => 'EA',
                    'unit_price' => ['amount' => 15.0, 'currency' => 'CAD'],
                    'customs_value' => ['amount' => 15.0, 'currency' => 'CAD'],
                ]],
                'export_detail' => [
                    'b13AFilingOption' => 'NOT_REQUIRED',
                ],
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 4.0,
                'weight_unit' => 'LB',
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'CUSTOMER_REFERENCE',
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorCa04(): array
    {
        return [
            'key' => 'IntegratorCA04',
            'label' => 'FedEx Ground · PDF · cross-border third-party payment',
            'scenario_key' => 'ship_ca04_pdf',
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorCA04',
            'fixture_version' => self::FIXTURE_VERSION,
            // Sandbox child OAuth authorizes the primary CA test account for label creation.
            // Workbook third-party billing remains on transportation and duties payors below.
            'account_number' => self::CA_TEST_ACCOUNT,
            'workbook_third_party_account' => self::CA_THIRD_PARTY_ACCOUNT,
            'expected_service_type' => 'FEDEX_GROUND',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'THIRD_PARTY',
            'transportation_payment_account' => self::CA_THIRD_PARTY_ACCOUNT,
            'transportation_payor' => [
                'person_name' => 'Integrator Testing',
                'country_code' => 'CA',
            ],
            'shipper' => [
                'person_name' => '2509',
                'company_name' => 'RTC',
                'phone' => '9052125000',
                'street_lines' => ['5985 EXPLORER DR'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
            ],
            'recipient' => [
                'person_name' => '2509',
                'company_name' => 'Recipient Company',
                'phone' => '8009887652',
                'street_lines' => ['Recipient Address Line 10'],
                'city' => 'Anchorage',
                'state' => 'AK',
                'postal_code' => '99502',
                'country_code' => 'US',
            ],
            'customs_clearance' => [
                'is_document_only' => false,
                'total_customs_value' => ['amount' => 100.0, 'currency' => 'CAD'],
                'duties_payment_type' => 'THIRD_PARTY',
                'duties_payment_account' => self::CA_THIRD_PARTY_ACCOUNT,
                'duties_payor' => [
                    'person_name' => 'Integrator Testing',
                    'country_code' => 'CA',
                ],
                'commodities' => [[
                    'number_of_pieces' => 1,
                    'description' => 'Dictionaries',
                    'country_of_manufacture' => 'CA',
                    'weight' => ['units' => 'LB', 'value' => 60.0],
                    'quantity' => 1,
                    'quantity_units' => 'EA',
                    'unit_price' => ['amount' => 100.0, 'currency' => 'CAD'],
                    'customs_value' => ['amount' => 100.0, 'currency' => 'CAD'],
                ]],
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 60.0,
                'weight_unit' => 'LB',
                'length' => 25.0,
                'width' => 25.0,
                'height' => 25.0,
                'dimension_unit' => 'IN',
                'declared_value' => ['amount' => 100.0, 'currency' => 'CAD'],
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'CUSTOMER_REFERENCE',
                ]],
                'package_special_services' => [
                    'specialServiceTypes' => ['SIGNATURE_OPTION'],
                    'signatureOptionType' => 'DIRECT',
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorCa05(): array
    {
        return [
            'key' => 'IntegratorCA05',
            'label' => 'FedEx Ground · ZPLII · domestic Canada',
            'scenario_key' => 'ship_ca05_zplii',
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorCA05',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => self::CA_TEST_ACCOUNT,
            'expected_service_type' => 'FEDEX_GROUND',
            'expected_label_format' => 'ZPLII',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'ZPLII',
            'label_stock_type' => 'STOCK_4X6',
            'transportation_payment_type' => 'SENDER',
            'shipper' => [
                'person_name' => '1502',
                'company_name' => 'RTC',
                'phone' => '9052125456',
                'street_lines' => ['5985 EXPLORER DR'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
            ],
            'recipient' => [
                'person_name' => '1502',
                'company_name' => 'Recipient Company',
                'phone' => '8889708898',
                'street_lines' => ['Recipient Address Line 1'],
                'city' => 'Burnaby',
                'state' => 'BC',
                'postal_code' => 'V5H4K7',
                'country_code' => 'CA',
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 23.0,
                'weight_unit' => 'LB',
                'length' => 20.0,
                'width' => 20.0,
                'height' => 20.0,
                'dimension_unit' => 'IN',
                'declared_value' => ['amount' => 1200.0, 'currency' => 'CAD'],
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'CUSTOMER_REFERENCE',
                ]],
            ]],
        ];
    }
}
