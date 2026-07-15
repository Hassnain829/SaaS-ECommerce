<?php

namespace App\Services\Carriers\FedEx\Validation;

use Carbon\Carbon;

class FedExShipTestCaseFixtureService
{
    public const FIXTURE_VERSION = '2026-06-30-workbook-v1';

    public const BASELINE_SHEET = 'Americas_US_Test cases';

    public function __construct(
        private readonly FedExFreightLtlFixtureService $freightLtlFixtures,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return array_merge([
            'IntegratorUS01' => $this->integratorUs01(),
            'IntegratorUS02' => $this->integratorUs02(),
            'IntegratorUS03' => $this->integratorUs03(),
            'IntegratorUS04' => $this->integratorUs04(),
            'IntegratorUS05' => $this->integratorUs05(),
            'IntegratorUS06' => $this->integratorUs06(),
            'IntegratorUS07' => $this->integratorUs07(),
        ], $this->freightLtlFixtures->fixtures());
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
        if ($this->freightLtlFixtures->fixtures()[$key] ?? null) {
            return $this->freightLtlFixtures->fixture($key);
        }

        $fixtures = [
            'IntegratorUS01' => $this->integratorUs01(),
            'IntegratorUS02' => $this->integratorUs02(),
            'IntegratorUS03' => $this->integratorUs03(),
            'IntegratorUS04' => $this->integratorUs04(),
            'IntegratorUS05' => $this->integratorUs05(),
            'IntegratorUS06' => $this->integratorUs06(),
            'IntegratorUS07' => $this->integratorUs07(),
        ];

        abort_unless(isset($fixtures[$key]), 404, 'Unknown FedEx ship test case.');

        return $fixtures[$key];
    }

    public function lockedLabelFormat(string $testCaseKey): string
    {
        return (string) (FedExValidationScenarioCatalog::lockedLabelFormat($testCaseKey)
            ?? abort(422, 'Unknown locked ship test case.'));
    }

    /**
     * Saturday Delivery requires a valid Friday ship date that is not in the past.
     */
    public function nextValidFriday(?Carbon $now = null): string
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $candidate = $now->copy();

        if ($candidate->dayOfWeek !== Carbon::FRIDAY) {
            $candidate = $candidate->next(Carbon::FRIDAY);
        }

        return $candidate->toDateString();
    }

    /**
     * Heuristic Friday for Saturday Delivery when live validate probing is unavailable.
     *
     * FedEx sandbox typically accepts a Friday 7–10 calendar days out, not the immediate next Friday.
     */
    public function nextSaturdayDeliveryFriday(?Carbon $now = null): string
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $windowStart = $now->copy()->addDays(7);
        $windowEnd = $now->copy()->addDays(10);

        $candidate = $windowStart->dayOfWeek === Carbon::FRIDAY
            ? $windowStart->copy()
            : $windowStart->copy()->next(Carbon::FRIDAY);

        if ($candidate->lte($windowEnd)) {
            return $candidate->toDateString();
        }

        $fallback = $windowEnd->copy()->previous(Carbon::FRIDAY);
        if ($fallback->gte($windowStart)) {
            return $fallback->toDateString();
        }

        return Carbon::parse($this->nextValidFriday($now))->addWeek()->toDateString();
    }

    /**
     * @return list<string>
     */
    public function saturdayDeliveryFridayCandidates(?Carbon $now = null, int $weeks = 4): array
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $dates = [];
        $candidate = Carbon::parse($this->nextValidFriday($now))->startOfDay();

        for ($week = 0; $week < $weeks; $week++) {
            $dates[] = $candidate->toDateString();
            $candidate = $candidate->copy()->addWeek();
        }

        return $dates;
    }

    /**
     * Home Delivery Premium delivery date — approximately one week after ship date.
     */
    public function homeDeliveryPremiumDeliveryDate(string $shipDate): string
    {
        return Carbon::parse($shipDate)->addWeek()->toDateString();
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
     * Americas_US_Test cases — US_Exp_Dom-Alcohol / IntegratorUS01.
     *
     * @return array<string, mixed>
     */
    private function integratorUs01(): array
    {
        return [
            'key' => 'IntegratorUS01',
            'label' => 'Express Saver · PDF · Alcohol',
            'scenario_key' => 'ship_us01_pdf',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS01',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'FEDEX_EXPRESS_SAVER',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_EXPRESS_SAVER',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'SENDER',
            'total_weight' => 36,
            'rate_request_types' => ['LIST'],
            'total_package_count' => 1,
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
                'person_name' => '323401',
                'company_name' => 'Integrator',
                'phone' => '9012633035',
                'street_lines' => ['110 Fedex parkway'],
                'city' => 'NEW ORLEANS',
                'state' => 'LA',
                'postal_code' => '70119',
                'country_code' => 'US',
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 18.0,
                'weight_unit' => 'LB',
                'length' => 20.0,
                'width' => 15.0,
                'height' => 20.0,
                'dimension_unit' => 'IN',
                'declared_value' => ['amount' => 250, 'currency' => 'USD'],
                'package_special_services' => [
                    'specialServiceTypes' => ['ALCOHOL'],
                    'alcoholDetail' => [
                        'alcoholRecipientType' => 'LICENSEE',
                    ],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs02(): array
    {
        return [
            'key' => 'IntegratorUS02',
            'label' => 'Priority Overnight · ZPLII · 1 package',
            'scenario_key' => 'ship_us02_zplii',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS02',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'PRIORITY_OVERNIGHT',
            'expected_label_format' => 'ZPLII',
            'expected_package_count' => 1,
            'service_type' => 'PRIORITY_OVERNIGHT',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'ZPLII',
            'label_stock_type' => 'STOCK_4X6',
            'transportation_payment_type' => 'SENDER',
            'ship_date_strategy' => 'saturday_delivery_friday',
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
                'sequence_number' => 1,
                'weight' => 20.0,
                'weight_unit' => 'LB',
                'length' => 10.0,
                'width' => 10.0,
                'height' => 10.0,
                'dimension_unit' => 'IN',
            ]],
            'shipment_special_services' => [
                'specialServiceTypes' => ['EVENT_NOTIFICATION', 'SATURDAY_DELIVERY'],
            ],
            'email_notification_detail' => [
                'aggregationType' => 'PER_PACKAGE',
                'emailNotificationRecipients' => [[
                    'name' => 'FedEx Validation Recipient',
                    'emailNotificationRecipientType' => 'RECIPIENT',
                    'emailAddress' => 'test001@fedex.com',
                    'notificationFormatType' => 'HTML',
                    'notificationType' => 'EMAIL',
                    'locale' => 'en_US',
                    'notificationEventType' => ['ON_SHIPMENT'],
                ]],
                'personalMessage' => 'FedEx integrator validation shipment notification.',
            ],
        ];
    }

    /**
     * Americas_US_Test cases — US_Exp_Intl / IntegratorUS03.
     *
     * @return array<string, mixed>
     */
    private function integratorUs03(): array
    {
        return [
            'key' => 'IntegratorUS03',
            'label' => 'International Priority · PDF · Customs',
            'scenario_key' => 'ship_us03_pdf',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS03',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'SENDER',
            'total_weight' => 30,
            'rate_request_types' => ['LIST'],
            'total_package_count' => 1,
            'shipper' => [
                'person_name' => 'James Weston',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
                'tins' => [[
                    'tinType' => 'PERSONAL_STATE',
                    'number' => '123456789',
                ]],
            ],
            'recipient' => [
                'person_name' => '413250',
                'company_name' => 'Integrator',
                'phone' => '9012633035',
                'street_lines' => ['14 TOTTENHAM COURT ROAD'],
                'city' => 'LONDON',
                'postal_code' => 'W1T1JY',
                'country_code' => 'GB',
            ],
            'customs_clearance' => [
                'is_document_only' => false,
                'total_customs_value' => ['amount' => 55, 'currency' => 'USD'],
                'duties_payment_type' => 'SENDER',
                'duties_payor' => [
                    'person_name' => 'Integrator',
                    'country_code' => 'US',
                ],
                'commercial_invoice' => [
                    'comments' => ['FEDEX BUSINESS'],
                    'insurance_charge' => ['amount' => 50, 'currency' => 'USD'],
                    'taxes_or_miscellaneous_charge' => ['amount' => 25, 'currency' => 'USD'],
                    'taxes_or_miscellaneous_charge_type' => 'OTHER',
                    'shipment_purpose' => 'SAMPLE',
                    'customer_references' => [[
                        'customerReferenceType' => 'CUSTOMER_REFERENCE',
                        'value' => '123456789',
                    ]],
                ],
                'commodities' => [[
                    'number_of_pieces' => 1,
                    'description' => 'Dictionaries ',
                    'country_of_manufacture' => 'US',
                    'weight' => ['units' => 'LB', 'value' => 30],
                    'quantity' => 1,
                    'quantity_units' => 'EA',
                    'unit_price' => ['amount' => 100, 'currency' => 'USD'],
                    'customs_value' => ['amount' => 55, 'currency' => 'USD'],
                ]],
                'export_detail' => [
                    'b13AFilingOption' => 'NOT_REQUIRED',
                    'exportComplianceStatement' => 'NO EEI 30.37(f)',
                ],
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 30.0,
                'weight_unit' => 'LB',
                'length' => 20.0,
                'width' => 20.0,
                'height' => 20.0,
                'dimension_unit' => 'IN',
                'declared_value' => ['amount' => 55, 'currency' => 'USD'],
                'customer_references' => [[
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => 'Integrator',
                ]],
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
            'label' => 'Ground Home Delivery · PNG · 1 package',
            'scenario_key' => 'ship_us04_png',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS04',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'GROUND_HOME_DELIVERY',
            'expected_label_format' => 'PNG',
            'expected_package_count' => 1,
            'service_type' => 'GROUND_HOME_DELIVERY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PNG',
            'label_stock_type' => 'PAPER_4X6',
            'transportation_payment_type' => 'SENDER',
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
                'sequence_number' => 1,
                'weight' => 30.0,
                'weight_unit' => 'LB',
                'length' => 20.0,
                'width' => 15.0,
                'height' => 20.0,
                'dimension_unit' => 'IN',
            ]],
            'shipment_special_services' => [
                'specialServiceTypes' => ['HOME_DELIVERY_PREMIUM'],
                'homeDeliveryPremiumDetail' => [
                    'homedeliveryPremiumType' => 'EVENING',
                ],
            ],
            'total_declared_value' => [
                'amount' => 300,
                'currency' => 'USD',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs05(): array
    {
        $recipientPayorAccount = (string) config(
            'carriers.fedex.validation_us05_recipient_payor_account',
            config('carriers.fedex.validation_sandbox_account_number', '700257037'),
        );

        return [
            'key' => 'IntegratorUS05',
            'label' => 'FedEx Ground · PDF · 2-package MPS',
            'scenario_key' => 'ship_us05_pdf_mps',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS05',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'FEDEX_GROUND',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 2,
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'total_package_count' => 2,
            'transportation_payment_type' => 'RECIPIENT',
            'transportation_payment_account' => $recipientPayorAccount,
            'processing_option' => [
                'processingOptionType' => 'SYNCHRONOUS_ONLY',
                'oneLabelAtATime' => false,
            ],
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
                    'sequence_number' => 1,
                    'weight' => 8.0,
                    'weight_unit' => 'LB',
                    'length' => 12.0,
                    'width' => 10.0,
                    'height' => 8.0,
                    'dimension_unit' => 'IN',
                ],
                [
                    'sequence_number' => 2,
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

    /**
     * Americas_US_Test cases — US_Grn_Dom,_Intl_&_Home_Del / IntegratorUS06.
     *
     * @return array<string, mixed>
     */
    private function integratorUs06(): array
    {
        return [
            'key' => 'IntegratorUS06',
            'label' => 'International Ground · PDF · Return Manager Printed Label',
            'scenario_key' => 'ship_us06_pdf',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS06',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'FEDEX_GROUND',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'FEDEX_GROUND',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'transportation_payment_type' => 'SENDER',
            'block_insight_visibility' => false,
            'shipper' => [
                'person_name' => 'James Weston',
                'company_name' => 'Integrator',
                'phone' => '9012633035',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => '141',
                'company_name' => 'Integrator',
                'phone' => '9012633035',
                'street_lines' => ['80 FEDEX PRKWY'],
                'city' => 'Mississauga',
                'state' => 'ON',
                'postal_code' => 'L4W5K6',
                'country_code' => 'CA',
                'residential' => true,
            ],
            'shipment_special_services' => [
                'specialServiceTypes' => ['RETURN_SHIPMENT'],
                'returnShipmentDetail' => [
                    'returnType' => 'PRINT_RETURN_LABEL',
                ],
            ],
            'customs_clearance' => [
                // The FedEx workbook leaves the customs-value amount blank, but the current
                // Ship REST API rejects zero/null customs value for the US-to-Canada shipment.
                // USD 1.00 is the minimal nominal compatibility value; unitPrice remains the
                // workbook-defined USD 0.00.
                'total_customs_value' => [
                    'amount' => 1,
                    'currency' => 'USD',
                ],
                'commercial_invoice' => [
                    'special_instructions' => 'GSNE.  IOR equals Duties/Taxes payer',
                    'shipment_purpose' => 'SAMPLE',
                ],
                'customs_option' => [
                    'type' => 'EXHIBITION_TRADE_SHOW',
                ],
                'commodities' => [[
                    'description' => 'Dictionaries ',
                    'country_of_manufacture' => 'US',
                    'weight' => ['units' => 'LB', 'value' => 10],
                    'quantity' => 1,
                    'quantity_units' => 'PC',
                    'unit_price' => ['amount' => 0, 'currency' => 'USD'],
                    'customs_value' => [
                        'amount' => 1,
                        'currency' => 'USD',
                    ],
                ]],
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 10.0,
                'weight_unit' => 'LB',
            ]],
        ];
    }

    /**
     * Americas_US_Test cases — FedEx Ground® Economy / IntegratorUS07.
     *
     * @return array<string, mixed>
     */
    private function integratorUs07(): array
    {
        $groundEconomyAccount = (string) config('carriers.fedex.validation_us07_ground_economy_account', '');

        return [
            'key' => 'IntegratorUS07',
            'label' => 'FedEx Ground® Economy · PDF · SMART_POST',
            'scenario_key' => 'ship_us07_pdf',
            'baseline_sheet' => self::BASELINE_SHEET,
            'baseline_case' => 'IntegratorUS07',
            'fixture_version' => self::FIXTURE_VERSION,
            'expected_service_type' => 'SMART_POST',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'service_type' => 'SMART_POST',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'account_number' => $groundEconomyAccount,
            'transportation_payment_type' => 'SENDER',
            'transportation_payment_account' => $groundEconomyAccount,
            'include_transportation_payor' => true,
            'transportation_payor' => [
                'country_code' => 'US',
            ],
            'total_package_count' => 1,
            'omit_recipient_residential' => true,
            'smart_post_info_detail' => [
                'indicia' => 'PARCEL_SELECT',
                'hub_id' => '5531',
            ],
            'shipper' => [
                'person_name' => 'ANTHONY JAMES',
                'company_name' => 'RTC',
                'phone' => '9012633035',
                'street_lines' => ['10 FedEx Pkwy'],
                'city' => 'Collierville',
                'state' => 'TN',
                'postal_code' => '38017',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => 'ANTHONY JAMES',
                'company_name' => 'IntegratorUS08',
                'phone' => '9012633035',
                'street_lines' => ['62 Ford St'],
                'city' => 'Brulington',
                'state' => 'CT',
                'postal_code' => '06013',
                'country_code' => 'US',
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 2.3,
                'weight_unit' => 'LB',
            ]],
        ];
    }
}
