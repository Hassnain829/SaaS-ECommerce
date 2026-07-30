<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * IntegratorUS10 — International Priority DirectDistribution Consolidation fixtures.
 *
 * Historical workbook index/date/jobId are baseline examples only and must never
 * be used by the live Consolidation chain.
 */
class FedExConsolidationFixtureService
{
    public const FIXTURE_VERSION = '2026-06-30-workbook-v1';

    public const BASELINE_SHEET = 'Americas_US_Test cases';

    /** Workbook historical examples — never inject into live requests. */
    public const HISTORICAL_WORKBOOK_INDEX = '794904933293';

    public const HISTORICAL_WORKBOOK_DATE = '2024-01-03';

    public const HISTORICAL_WORKBOOK_JOB_ID = '41u120371133650224874999733';

    /**
     * Americas_US_Test cases IntegratorUS10 third-party / soldTo billing account value.
     * Not the OAuth-linked US Test Account (700257037).
     */
    public const WORKBOOK_THIRD_PARTY_ACCOUNT = '123456789';

    public const PLACEHOLDER_INDEX = '{{US10_CONSOLIDATION_INDEX}}';

    public const PLACEHOLDER_DATE = '{{US10_CONSOLIDATION_DATE}}';

    public const PLACEHOLDER_JOB_ID = '{{US10_JOB_ID}}';

    public const CONSOLIDATION_TYPE = 'INTERNATIONAL_PRIORITY_DISTRIBUTION';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'IntegratorUS10_CREATE_CONSOLIDATION' => $this->createConsolidation(),
            'IntegratorUS10_ADD_SHIPMENT_1' => $this->addShipment(1, dropoffType: null, commodityId: null, leadingSpaceStreet2: false),
            'IntegratorUS10_ADD_SHIPMENT_2' => $this->addShipment(2, dropoffType: null, commodityId: 'commodity Id', leadingSpaceStreet2: false),
            'IntegratorUS10_ADD_SHIPMENT_3' => $this->addShipment(3, dropoffType: 'REGULAR_PICKUP', commodityId: 'commodity Id', leadingSpaceStreet2: true),
            'IntegratorUS10_ADD_SHIPMENT_4' => $this->addShipment(4, dropoffType: null, commodityId: null, leadingSpaceStreet2: false),
            'IntegratorUS10_ADD_SHIPMENT_5' => $this->addShipment(5, dropoffType: 'REGULAR_PICKUP', commodityId: null, leadingSpaceStreet2: false),
            'IntegratorUS10_ADD_SHIPMENT_6' => $this->addShipment(6, dropoffType: 'REGULAR_PICKUP', commodityId: 'commodity Id', leadingSpaceStreet2: false),
            'IntegratorUS10_CONFIRM_CONSOLIDATION' => $this->confirmConsolidation(),
            'IntegratorUS10_CONFIRM_RESULTS' => $this->confirmResults(),
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
     * @return list<string>
     */
    public function addShipmentKeys(): array
    {
        return [
            'IntegratorUS10_ADD_SHIPMENT_1',
            'IntegratorUS10_ADD_SHIPMENT_2',
            'IntegratorUS10_ADD_SHIPMENT_3',
            'IntegratorUS10_ADD_SHIPMENT_4',
            'IntegratorUS10_ADD_SHIPMENT_5',
            'IntegratorUS10_ADD_SHIPMENT_6',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fixture(string $testCaseKey): array
    {
        $fixtures = $this->fixtures();
        abort_unless(isset($fixtures[$testCaseKey]), 404, 'Unknown FedEx US10 consolidation test case.');

        return $fixtures[$testCaseKey];
    }

    public function consolidationAccountNumber(): string
    {
        return trim((string) config('carriers.fedex.validation_us10_consolidation_account', ''));
    }

    /**
     * Workbook soldTo / THIRD_PARTY payor account (not the credential-linked root account).
     */
    public function workbookThirdPartyAccountNumber(): string
    {
        return self::WORKBOOK_THIRD_PARTY_ACCOUNT;
    }

    /**
     * @param  array{type?: string, index?: string, date?: string}  $key
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function withConsolidationKey(array $fixture, array $key): array
    {
        $fixture['consolidation_key'] = [
            'type' => (string) ($key['type'] ?? self::CONSOLIDATION_TYPE),
            'index' => (string) ($key['index'] ?? self::PLACEHOLDER_INDEX),
            'date' => (string) ($key['date'] ?? self::PLACEHOLDER_DATE),
        ];

        return $fixture;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function withJobId(array $fixture, string $jobId): array
    {
        $fixture['job_id'] = $jobId;

        return $fixture;
    }

    /**
     * @return array<string, mixed>
     */
    private function createConsolidation(): array
    {
        $account = $this->consolidationAccountNumber();
        $tin = trim((string) config('carriers.fedex.validation_us10_shipper_tin', ''));

        return [
            'key' => 'IntegratorUS10_CREATE_CONSOLIDATION',
            'label' => 'Create Consolidation — IPD',
            'scenario_key' => 'consolidation_us10_create',
            'baseline_case' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'operation' => 'create',
            'endpoint_path_config' => 'consolidation_create_path',
            'customer_transaction_id' => 'IntegratorUS10_Create consolidation',
            'fixture_version' => self::FIXTURE_VERSION,
            'baseline_sheet' => self::BASELINE_SHEET,
            'account_number' => $account,
            'third_party_account_number' => $this->workbookThirdPartyAccountNumber(),
            'consolidation_type' => self::CONSOLIDATION_TYPE,
            'special_service_types' => ['INTERNATIONAL_CONTROLLED_EXPORT_SERVICE'],
            'international_controlled_export_detail' => [
                'license_or_permit_expiration_date' => '2024-12-18',
                'license_or_permit_number' => '123',
                'type' => 'DEA_486',
            ],
            'shipper' => [
                'person_name' => 'XIAO LI',
                'phone' => '12124567890',
                'company_name' => 'ABC COMPANY',
                'street_lines' => [
                    'GRT TEST ACCOUNT- DO NOT TOUCH',
                    'DONG SAN HUAN BEI ROAD',
                ],
                'city' => 'MEMPHIS',
                'state' => 'TN',
                'postal_code' => '38125',
                'country_code' => 'US',
                'residential' => false,
                'tins' => [[
                    'number' => $tin,
                    'tin_type' => 'PERSONAL_NATIONAL',
                ]],
            ],
            'origin' => [
                'person_name' => 'XIAO LI',
                'phone' => '12124567890',
                'company_name' => 'ABC COMPANY',
                'street_lines' => [
                    'GRT TEST ACCOUNT- DO NOT TOUCH',
                    'DONG SAN HUAN BEI ROAD',
                ],
                'city' => 'MEMPHIS',
                'state' => 'TN',
                'postal_code' => '38125',
                'country_code' => 'US',
                'residential' => false,
            ],
            'sold_to' => $this->torontoParty('SHPC-440836-CL2203C8', 'GRT', '9012633035', includeAccount: true),
            'importer_of_record' => $this->torontoParty('SHPC-440836-CL2203C8', 'GRT', '9012633035', includeAccount: false),
            'consolidation_data_source' => [
                'consolidation_data_type' => 'TOTAL_FREIGHT_CHARGES',
                'consolidation_data_source_type' => 'ACCUMULATED',
            ],
            'consolidation_document_specification' => [
                'consolidation_document_types' => ['CONSOLIDATED_COMMERCIAL_INVOICE'],
                'consolidated_commercial_invoice_detail' => [
                    'document_format' => [
                        'stock_type' => 'PAPER_LETTER',
                        'doc_type' => 'PDF',
                    ],
                ],
            ],
            'label_specification' => [
                'label_format_type' => 'COMMON2D',
                'label_stock_type' => 'PAPER_4X6',
                'image_type' => 'PNG',
            ],
            'customs_clearance' => [
                'customs_value' => ['amount' => 200, 'currency' => 'USD'],
                'duties_payment_type' => 'THIRD_PARTY',
                'duties_payor_country_code' => 'US',
                'document_content' => 'NON_DOCUMENTS',
                'recipient_customs_id' => [
                    'type' => 'COMPANY',
                    'value' => '125',
                ],
            ],
            'international_distribution_detail' => [
                'declaration_currencies' => [[
                    'currency' => 'USD',
                    'value' => 'CUSTOMS_VALUE',
                ]],
                'total_dimensions' => [
                    'length' => 10,
                    'width' => 10,
                    'height' => 10,
                    'units' => 'IN',
                ],
                'clearance_facility_location_id' => 'YWGI',
                'drop_off_type' => 'REGULAR_PICKUP',
                'total_insured_value' => ['amount' => 200, 'currency' => 'USD'],
                'unit_system' => 'ENGLISH',
            ],
            'transportation_payment_type' => 'THIRD_PARTY',
            'transportation_payor_country_code' => 'US',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addShipment(
        int $sequence,
        ?string $dropoffType,
        ?string $commodityId,
        bool $leadingSpaceStreet2,
    ): array {
        $street2 = $leadingSpaceStreet2 ? ' Suite 101' : 'Suite 101';

        return [
            'key' => 'IntegratorUS10_ADD_SHIPMENT_'.$sequence,
            'label' => 'Add Consolidation Shipment '.$sequence,
            'scenario_key' => 'consolidation_us10_add_shipment_'.$sequence,
            'baseline_case' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'operation' => 'add_shipment',
            'endpoint_path_config' => 'consolidation_shipment_path',
            'customer_transaction_id' => 'IntegratorUS10_Add shipment '.$sequence,
            'fixture_version' => self::FIXTURE_VERSION,
            'shipment_sequence' => $sequence,
            'account_number' => $this->consolidationAccountNumber(),
            'third_party_account_number' => $this->workbookThirdPartyAccountNumber(),
            'consolidation_key' => [
                'type' => self::CONSOLIDATION_TYPE,
                'index' => self::PLACEHOLDER_INDEX,
                'date' => self::PLACEHOLDER_DATE,
            ],
            'service_type' => self::CONSOLIDATION_TYPE,
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'packaging_type' => 'YOUR_PACKAGING',
            'label_response_options' => 'LABEL',
            'open_shipment_action' => 'CONFIRM',
            'dropoff_type' => $dropoffType,
            'special_service_types' => ['INTERNATIONAL_CONTROLLED_EXPORT_SERVICE'],
            'international_controlled_export_detail' => [
                'license_or_permit_expiration_date' => '2024-12-18',
                'license_or_permit_number' => '123',
                'type' => 'DEA_486',
            ],
            'shipper' => [
                'person_name' => 'Shipper name',
                'phone' => '12124567890',
                'company_name' => 'Shipper company',
                'street_lines' => ['80 FedEx Parkway', $street2],
                'city' => 'MEMPHIS',
                'state' => 'TN',
                'postal_code' => '38125',
                'country_code' => 'US',
                'residential' => false,
            ],
            'origin' => [
                'person_name' => 'Origin name',
                'phone' => '12124567890',
                'company_name' => 'Origin COMPANY',
                'street_lines' => ['80 FedEx Parkway', $street2],
                'city' => 'MEMPHIS',
                'state' => 'TN',
                'postal_code' => '38125',
                'country_code' => 'US',
                'residential' => false,
            ],
            'recipient' => [
                'person_name' => 'TEst name',
                'phone' => '19512390523',
                'company_name' => 'Test company',
                'street_lines' => ['RTC', '4011 MALDEN RD'],
                'city' => 'TORONTO',
                'state' => 'ON',
                'postal_code' => 'M1M1M1',
                'country_code' => 'CA',
                'residential' => false,
            ],
            'printed_label_origin' => [
                'person_name' => 'TestAutomation',
                'phone' => '19512390523',
                'phone_extension' => '10',
                'company_name' => 'COMPANYA',
                'email' => 'abc123456@fedex.com',
                'street_lines' => ['RTC', '4011 MALDEN RD'],
                'city' => 'TORONTO',
                'state' => 'ON',
                'postal_code' => 'M1M1M1',
                'country_code' => 'CA',
                'residential' => false,
            ],
            'label_specification' => [
                'label_format_type' => 'COMMON2D',
                'label_stock_type' => 'PAPER_4X6',
                'image_type' => 'PNG',
            ],
            'customer_references' => [
                ['customer_reference_type' => 'CUSTOMER_REFERENCE', 'value' => 'i-f89999-tc0203-c1'],
                ['customer_reference_type' => 'INVOICE_NUMBER', 'value' => 'i-f89999-tc0203-c1'],
            ],
            'transportation_payment_type' => 'THIRD_PARTY',
            'transportation_payor_country_code' => 'US',
            'customs_clearance' => [
                'total_customs_value' => ['amount' => 500, 'currency' => 'USD'],
                'is_document_only' => false,
                'duties_payment_type' => 'THIRD_PARTY',
                'duties_payor_country_code' => 'US',
            ],
            'processing_options' => ['PACKAGE_LEVEL_COMMODITIES'],
            'packages' => [[
                'group_package_count' => 35,
                // Workbook physicalPackaging text "null" → omit property entirely.
                'omit_physical_packaging' => true,
                'declared_value' => ['amount' => 14.28, 'currency' => 'USD'],
                'weight' => ['units' => 'LB', 'value' => 120],
            ]],
            'commodities' => [[
                'unit_price' => ['amount' => 500, 'currency' => 'USD'],
                'number_of_pieces' => 1,
                'quantity' => 1,
                'customs_value' => ['amount' => 500, 'currency' => 'USD'],
                'country_of_manufacture' => 'CA',
                'harmonized_code' => '4901990010',
                'description' => 'Textbooks',
                'weight' => ['units' => 'LB', 'value' => 100],
                'quantity_units' => 'EA',
                'commodity_id' => $commodityId,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmConsolidation(): array
    {
        return [
            'key' => 'IntegratorUS10_CONFIRM_CONSOLIDATION',
            'label' => 'Confirm Consolidation — IPD',
            'scenario_key' => 'consolidation_us10_confirm',
            'baseline_case' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'operation' => 'confirm',
            'endpoint_path_config' => 'consolidation_confirm_path',
            'customer_transaction_id' => 'IntegratorUS10_Confirm Consolidation',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => $this->consolidationAccountNumber(),
            'consolidation_key' => [
                'type' => self::CONSOLIDATION_TYPE,
                'index' => self::PLACEHOLDER_INDEX,
                'date' => self::PLACEHOLDER_DATE,
            ],
            'processing_option_type' => 'ALLOW_ASYNCHRONOUS',
            'edt_request_type' => 'ALL',
            'label_specification' => [
                'label_format_type' => 'COMMON2D',
                'label_stock_type' => 'PAPER_4X6',
                'image_type' => 'PDF',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmResults(): array
    {
        return [
            'key' => 'IntegratorUS10_CONFIRM_RESULTS',
            'label' => 'Confirm Consolidation Results — IPD',
            'scenario_key' => 'consolidation_us10_confirm_results',
            'baseline_case' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'operation' => 'confirm_results',
            'endpoint_path_config' => 'consolidation_confirm_results_path',
            'customer_transaction_id' => 'IntegratorUS10_Confirm Results',
            'fixture_version' => self::FIXTURE_VERSION,
            'account_number' => $this->consolidationAccountNumber(),
            'job_id' => self::PLACEHOLDER_JOB_ID,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function torontoParty(string $personName, string $companyName, string $phone, bool $includeAccount): array
    {
        $party = [
            'person_name' => $personName,
            'phone' => $phone,
            'company_name' => $companyName,
            'street_lines' => [
                'MORELOS 417 COL CENTRO',
                'Suite 101',
            ],
            'city' => 'TORONTO',
            'state' => 'ON',
            'postal_code' => 'M1M1M1',
            'country_code' => 'CA',
            'residential' => false,
        ];

        if ($includeAccount) {
            $party['account_number'] = $this->workbookThirdPartyAccountNumber();
        }

        return $party;
    }
}
