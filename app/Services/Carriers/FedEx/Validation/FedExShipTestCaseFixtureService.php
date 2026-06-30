<?php

namespace App\Services\Carriers\FedEx\Validation;

use Carbon\Carbon;

class FedExShipTestCaseFixtureService
{
    public const FIXTURE_VERSION = '2026-06-30-workbook-v1';

    public const BASELINE_SHEET = 'Americas_US_Test cases';

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
                'phone' => '9012633035',
                'phone_extension' => '200',
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
                'declared_value' => ['amount' => 300, 'currency' => 'USD'],
                'package_special_services' => [
                    'specialServiceTypes' => ['NON_STANDARD_CONTAINER'],
                ],
            ]],
            'shipment_special_services' => [
                'specialServiceTypes' => ['HOME_DELIVERY_PREMIUM'],
                'homeDeliveryPremiumDetail' => [
                    'homeDeliveryPremiumType' => 'EVENING',
                ],
            ],
            'home_delivery_premium_delivery_date_strategy' => 'one_week_after_ship_date',
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
}
