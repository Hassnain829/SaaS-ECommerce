<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * IntegratorUS08 — FedEx Freight® Priority (LTL) workbook fixture.
 *
 * Parcel Ship fixtures stay in FedExShipTestCaseFixtureService.
 */
class FedExFreightLtlFixtureService
{
    public const FIXTURE_VERSION = '2026-07-14-workbook-v2';

    public const BASELINE_SHEET = 'Americas_US_Test cases';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'IntegratorUS08' => $this->integratorUs08(),
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
    public function fixture(string $testCaseKey): array
    {
        $fixtures = $this->fixtures();
        abort_unless(isset($fixtures[$testCaseKey]), 404, 'Unknown FedEx Freight LTL test case.');

        return $fixtures[$testCaseKey];
    }

    public function lockedLabelFormat(string $testCaseKey): string
    {
        return strtoupper((string) ($this->fixture($testCaseKey)['label_format'] ?? 'ZPLII'));
    }

    /**
     * Americas_US_Test cases — FedEx Freight Priority, Your Packaging, Inside Delivery / IntegratorUS08.
     *
     * @return array<string, mixed>
     */
    private function integratorUs08(): array
    {
        $freightAccount = (string) config('carriers.fedex.validation_us08_freight_account', '');

        return [
            'key' => 'IntegratorUS08',
            'label' => 'FedEx Freight Priority · ZPLII · Inside Delivery',
            'scenario_key' => 'ship_us08_zplii',
            'api_family' => 'freight_ltl',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS08',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'FEDEX_FREIGHT_PRIORITY',
            'expected_label_format' => 'ZPLII',
            'expected_package_count' => 1,
            'label_response_options' => 'LABEL',
            'service_type' => 'FEDEX_FREIGHT_PRIORITY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'ZPLII',
            'label_format_type' => 'COMMON2D',
            'label_stock_type' => 'STOCK_4X6',
            'label_printing_orientation' => 'BOTTOM_EDGE_OF_TEXT_FIRST',
            'label_order' => 'SHIPPING_LABEL_FIRST',
            // Root accountNumber is part of the Freight LTL Ship request contract (same freight account).
            'account_number' => $freightAccount,
            'freight_account_number' => $freightAccount,
            'total_weight' => 1000,
            'total_package_count' => 1,
            'preferred_currency' => 'USD',
            'rate_request_types' => ['LIST', 'PREFERRED'],
            'transportation_payment_type' => 'RECIPIENT',
            'transportation_payment_account' => $freightAccount,
            'freight_role' => 'SHIPPER',
            'collect_terms_type' => 'NON_RECOURSE_SHIPPER_SIGNED',
            'total_handling_units' => 1,
            'client_discount_percent' => 0,
            'declared_value_per_unit' => [
                'currency' => 'USD',
                'amount' => 0,
            ],
            'declared_value_units' => 'LB',
            'special_service_types' => ['INSIDE_DELIVERY'],
            'freight_billing_contact_and_address' => [
                'person_name' => 'Shipper Contact',
                'company_name' => 'Shipper Company',
                'street_lines' => ['1202 Chalet Lane'],
                'city' => 'Harrison',
                'state' => 'AR',
                'postal_code' => '72601',
                'country_code' => 'US',
                'residential' => false,
            ],
            'shipper' => [
                'person_name' => 'QCONFIG',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['SHIPPER ADDRESS LINE 1'],
                'city' => 'CALIFORNIA',
                'state' => 'CA',
                'postal_code' => '93505',
                'country_code' => 'US',
                'residential' => false,
            ],
            'recipient' => [
                'person_name' => 'F-413404',
                'company_name' => 'IntegratorUS09',
                'phone' => '1234567890',
                'street_lines' => ['RECIPIENT ADDRESS LINE 1'],
                'city' => 'DENVER',
                'state' => 'CO',
                'postal_code' => '80204',
                'country_code' => 'US',
                'residential' => false,
            ],
            'transportation_payor' => [
                'person_name' => 'F-413404',
                'company_name' => 'Integrator',
                'phone' => '1234567890',
                'street_lines' => ['RECIPIENT ADDRESS LINE 1'],
                'city' => 'DENVER',
                'state' => 'CO',
                'postal_code' => '80204',
                'country_code' => 'US',
                'residential' => false,
            ],
            'freight_line_items' => [[
                'id' => '10',
                'freight_class' => 'CLASS_050',
                'pieces' => 10,
                'sub_packaging_type' => 'BARREL',
                'handling_units' => 1,
                // Workbook nmfcCode is null — omit from payload (do not reuse purchaseOrderNumber 54321).
                'purchase_order_number' => '54321',
                'description' => 'Axles',
                'weight' => ['units' => 'LB', 'value' => 1000],
                'dimensions' => [
                    'length' => 20,
                    'width' => 20,
                    'height' => 20,
                    'units' => 'IN',
                ],
            ]],
            'packages' => [[
                'sequence_number' => 1,
                'group_package_count' => 1,
                'weight' => 1000,
                'weight_unit' => 'LB',
                'length' => 20,
                'width' => 20,
                'height' => 20,
                'dimension_unit' => 'IN',
                'sub_packaging_type' => 'BARREL',
                'associated_freight_line_item_id' => '10',
            ]],
            'shipping_document_specification' => [
                'shipping_document_types' => [
                    'COMMERCIAL_INVOICE',
                    'FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING',
                ],
                'commercial_invoice_detail' => [
                    'provide_instructions' => true,
                    'document_format' => [
                        'stock_type' => 'PAPER_LETTER',
                        'locale' => 'en_US',
                        'doc_type' => 'PDF',
                    ],
                ],
                'freight_bill_of_lading_detail' => [
                    'provide_instructions' => true,
                    'disposition_type' => 'RETURNED',
                    'document_format' => [
                        'stock_type' => 'PAPER_LETTER',
                        'locale' => 'en_US',
                        'doc_type' => 'PDF',
                    ],
                ],
            ],
            // Workbook hazardous / alias / masterTrackingId blank — omit.
        ];
    }
}
