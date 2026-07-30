<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlResponseParser;
use App\Services\Carriers\FedEx\Operations\FedExShipResponseParser;
use App\Services\Carriers\FedEx\Support\FedExConfig;

final class FedExShipEvidenceRules
{
    public function __construct(
        private readonly FedExShipFixtureResolver $fixtureResolver,
        private readonly FedExShipResponseParser $responseParser,
        private readonly FedExFreightLtlResponseParser $freightResponseParser,
        private readonly FedExConfig $config,
        private readonly FedExFreightLtlEvidenceRules $freightLtlEvidenceRules,
        private readonly FedExUs09EvidenceRules $us09EvidenceRules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function expectedMetadata(string $testCaseKey): array
    {
        $fixture = $this->fixtureResolver->fixture($testCaseKey);
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey]
            ?? FedExValidationScenarioCatalog::globalShipScenarios()[$testCaseKey]
            ?? [];

        return [
            'test_case_key' => $testCaseKey,
            'scenario_key' => (string) ($fixture['scenario_key'] ?? $meta['scenario_key'] ?? ''),
            'expected_service_type' => (string) ($fixture['expected_service_type'] ?? $fixture['service_type'] ?? ''),
            'expected_label_format' => strtoupper((string) ($fixture['expected_label_format'] ?? $fixture['label_format'] ?? '')),
            'label_stock_type' => (string) ($fixture['label_stock_type'] ?? $meta['label_stock_type'] ?? ''),
            'expected_package_count' => (int) ($fixture['expected_package_count'] ?? count($fixture['packages'] ?? [])),
            'payment_type' => strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER')),
            'fixture_version' => (string) ($fixture['fixture_version'] ?? ''),
        ];
    }

    public function isValidEventForTestCase(CarrierApiEvent $event, string $testCaseKey): bool
    {
        if ($this->isLegacyGrandfathered($event)) {
            return $this->validateLegacyGrandfathered($event, $testCaseKey)['valid'];
        }

        return $this->validateRequest($event, $testCaseKey)['valid']
            && $this->validateResponse($event, $testCaseKey)['valid'];
    }

    public function isLegacyGrandfathered(CarrierApiEvent $event): bool
    {
        return (bool) data_get($event->response_summary, 'legacy_grandfathered', false);
    }

    /**
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateLegacyGrandfathered(CarrierApiEvent $event, string $testCaseKey): array
    {
        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];

        if ($event->action !== CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL) {
            $reasons[] = 'action_not_create_label';
        }

        if (! $event->isSuccessfulHttp() || $event->status !== CarrierApiEvent::STATUS_SUCCEEDED) {
            $reasons[] = 'http_not_successful';
        }

        if ((string) $event->scenario_key !== (string) $expected['scenario_key']) {
            $reasons[] = 'scenario_key_mismatch';
        }

        if ((string) $event->test_case_key !== $testCaseKey) {
            $reasons[] = 'test_case_key_mismatch';
        }

        if ((string) ($event->validation_region ?? 'US') !== 'US') {
            $reasons[] = 'validation_region_not_us';
        }

        $parsed = $this->responseParser->parse(is_array($event->response_body_encrypted) ? $event->response_body_encrypted : []);
        $responseService = strtoupper((string) ($parsed['service_type'] ?? ''));
        if ($responseService === '' || $responseService !== (string) $expected['expected_service_type']) {
            $reasons[] = 'response_service_mismatch';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{valid: bool, reasons: list<string>, metadata: array<string, mixed>}
     */
    public function validateRequest(CarrierApiEvent $event, string $testCaseKey): array
    {
        if ($this->isLegacyGrandfathered($event)) {
            $legacy = $this->validateLegacyGrandfathered($event, $testCaseKey);

            return [
                'valid' => $legacy['valid'],
                'reasons' => $legacy['reasons'],
                'metadata' => $this->expectedMetadata($testCaseKey),
            ];
        }

        if ($testCaseKey === 'IntegratorUS08') {
            return $this->validateFreightUs08Request($event);
        }

        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];
        $request = is_array($event->request_body_encrypted) ? $event->request_body_encrypted : [];

        if ($event->action !== CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL) {
            $reasons[] = 'action_not_create_label';
        }

        if (strtoupper((string) $event->http_method) !== 'POST') {
            $reasons[] = 'http_method_not_post';
        }

        $endpoint = (string) ($event->endpoint ?? data_get($event->request_summary, 'endpoint', ''));
        if (! str_contains($endpoint, $this->config->shipCreatePath($event->environment))) {
            $reasons[] = 'endpoint_not_ship_create';
        }

        if (! $event->hasCompleteEvidence()) {
            $reasons[] = 'incomplete_evidence';
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED || ! $event->isSuccessfulHttp()) {
            $reasons[] = 'http_not_successful';
        }

        if ((string) $event->scenario_key !== (string) $expected['scenario_key']) {
            $reasons[] = 'scenario_key_mismatch';
        }

        if ((string) $event->test_case_key !== $testCaseKey) {
            $reasons[] = 'test_case_key_mismatch';
        }

        $payment = data_get($request, 'requestedShipment.shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || $payment === []) {
            $reasons[] = 'shipping_charges_payment_missing';
        } elseif (strtoupper((string) data_get($payment, 'paymentType')) !== (string) $expected['payment_type']) {
            $reasons[] = 'payment_type_mismatch';
        }

        if (
            $testCaseKey === 'IntegratorUS05'
            && is_array($payment)
            && strtoupper((string) data_get($payment, 'paymentType')) === 'RECIPIENT'
            && ! filled(data_get($payment, 'payor.responsibleParty.accountNumber.value'))
        ) {
            $reasons[] = 'us05_recipient_payor_account_missing';
        }

        $requestService = strtoupper((string) data_get($request, 'requestedShipment.serviceType', ''));
        if ($requestService !== (string) $expected['expected_service_type']) {
            $reasons[] = 'request_service_mismatch';
        }

        $imageType = strtoupper((string) data_get($request, 'requestedShipment.labelSpecification.imageType', ''));
        if ($imageType !== (string) $expected['expected_label_format']) {
            $reasons[] = 'request_label_format_mismatch';
        }

        $stockType = (string) data_get($request, 'requestedShipment.labelSpecification.labelStockType', '');
        if ($stockType !== (string) $expected['label_stock_type']) {
            $reasons[] = 'label_stock_mismatch';
        }

        $packageCount = count((array) data_get($request, 'requestedShipment.requestedPackageLineItems', []));
        if ($packageCount !== (int) $expected['expected_package_count']) {
            $reasons[] = 'package_count_mismatch';
        }

        if ($testCaseKey === 'IntegratorUS02' && ! $this->isFridayShipDate((string) data_get($request, 'requestedShipment.shipDatestamp', ''))) {
            $reasons[] = 'us02_ship_date_not_friday';
        }

        if ($testCaseKey === 'IntegratorUS02' && ! $this->containsSpecialServices($request, ['EVENT_NOTIFICATION', 'SATURDAY_DELIVERY'])) {
            $reasons[] = 'us02_special_services_missing';
        }

        if ($testCaseKey === 'IntegratorCA03') {
            if (! $this->isFridayShipDate((string) data_get($request, 'requestedShipment.shipDatestamp', ''))) {
                $reasons[] = 'ca03_ship_date_not_friday';
            }

            if (! $this->containsSpecialServices($request, ['SATURDAY_DELIVERY'])) {
                $reasons[] = 'ca03_saturday_delivery_missing';
            }

            if (! data_get($request, 'requestedShipment.recipients.0.address.residential')) {
                $reasons[] = 'ca03_residential_missing';
            }
        }

        if ($testCaseKey === 'IntegratorUS04') {
            if (! data_get($request, 'requestedShipment.recipients.0.address.residential')) {
                $reasons[] = 'us04_residential_missing';
            }

            if (! filled(data_get($request, 'requestedShipment.recipients.0.contact.phoneNumber'))) {
                $reasons[] = 'us04_recipient_phone_missing';
            }

            if (! $this->containsSpecialServices($request, ['HOME_DELIVERY_PREMIUM'])) {
                $reasons[] = 'us04_home_delivery_premium_missing';
            }

            $premiumDetail = data_get($request, 'requestedShipment.shipmentSpecialServices.homeDeliveryPremiumDetail');
            if (! is_array($premiumDetail)) {
                $reasons[] = 'us04_home_delivery_premium_detail_missing';
            } else {
                if (array_key_exists('homeDeliveryPremiumType', $premiumDetail)) {
                    $reasons[] = 'us04_wrong_home_delivery_premium_type_key';
                }

                if (strtoupper((string) data_get($premiumDetail, 'homedeliveryPremiumType', '')) !== 'EVENING') {
                    $reasons[] = 'us04_homedelivery_premium_type_mismatch';
                }

                if (array_key_exists('deliveryDate', $premiumDetail)) {
                    $reasons[] = 'us04_delivery_date_present';
                }
            }

            $totalDeclared = data_get($request, 'requestedShipment.totalDeclaredValue');
            if (
                ! is_array($totalDeclared)
                || (float) data_get($totalDeclared, 'amount') !== 300.0
                || strtoupper((string) data_get($totalDeclared, 'currency', '')) !== 'USD'
            ) {
                $reasons[] = 'us04_total_declared_value_mismatch';
            }

            if (data_get($request, 'requestedShipment.requestedPackageLineItems.0.declaredValue') !== null) {
                $reasons[] = 'us04_package_declared_value_present';
            }

            if (data_get($request, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices') !== null) {
                $reasons[] = 'us04_package_special_services_present';
            }

            $packageTypes = array_map(
                strtoupper(...),
                (array) data_get($request, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices.specialServiceTypes', []),
            );
            if (in_array('NON_STANDARD_CONTAINER', $packageTypes, true)) {
                $reasons[] = 'us04_non_standard_container_present';
            }
        }

        if ($testCaseKey === 'IntegratorUS01') {
            $packageServices = data_get($request, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices');
            $packageTypes = array_map(
                strtoupper(...),
                (array) data_get($packageServices, 'specialServiceTypes', []),
            );

            if (! in_array('ALCOHOL', $packageTypes, true)) {
                $reasons[] = 'us01_alcohol_special_service_missing';
            }

            if (strtoupper((string) data_get($packageServices, 'alcoholDetail.alcoholRecipientType', '')) !== 'LICENSEE') {
                $reasons[] = 'us01_alcohol_recipient_type_mismatch';
            }

            $declared = data_get($request, 'requestedShipment.requestedPackageLineItems.0.declaredValue');
            if (
                ! is_array($declared)
                || (float) data_get($declared, 'amount') !== 250.0
                || strtoupper((string) data_get($declared, 'currency', '')) !== 'USD'
            ) {
                $reasons[] = 'us01_package_declared_value_mismatch';
            }

            if (! filled(data_get($request, 'requestedShipment.recipients.0.contact.phoneNumber'))) {
                $reasons[] = 'us01_recipient_phone_missing';
            }
        }

        if ($testCaseKey === 'IntegratorUS03') {
            $customs = data_get($request, 'requestedShipment.customsClearanceDetail');
            if (! is_array($customs) || $customs === [] || $customs === '[REDACTED]') {
                $reasons[] = 'us03_customs_clearance_missing';
            } else {
                if (strtoupper((string) data_get($customs, 'dutiesPayment.paymentType', '')) !== 'SENDER') {
                    $reasons[] = 'us03_duties_payment_type_mismatch';
                }

                if ((float) data_get($customs, 'totalCustomsValue.amount') !== 55.0) {
                    $reasons[] = 'us03_total_customs_value_mismatch';
                }

                if (array_key_exists('insuranceCharge', $customs)) {
                    $reasons[] = 'us03_top_level_insurance_charge_present';
                }

                if ((string) data_get($customs, 'commercialInvoice.comments.0') !== 'FEDEX BUSINESS') {
                    $reasons[] = 'us03_commercial_invoice_comments_mismatch';
                }

                if (
                    (float) data_get($customs, 'commercialInvoice.insuranceCharge.amount') !== 50.0
                    || strtoupper((string) data_get($customs, 'commercialInvoice.insuranceCharge.currency', '')) !== 'USD'
                ) {
                    $reasons[] = 'us03_commercial_invoice_insurance_charge_mismatch';
                }

                if (strtoupper((string) data_get($customs, 'commercialInvoice.shipmentPurpose', '')) !== 'SAMPLE') {
                    $reasons[] = 'us03_shipment_purpose_mismatch';
                }

                if ((string) data_get($customs, 'commodities.0.description') !== 'Dictionaries ') {
                    $reasons[] = 'us03_commodity_description_mismatch';
                }

                if ((string) data_get($customs, 'exportDetail.exportComplianceStatement') !== 'NO EEI 30.37(f)') {
                    $reasons[] = 'us03_export_compliance_mismatch';
                }
            }

            if (strtoupper((string) data_get($request, 'requestedShipment.shipper.tins.0.tinType', '')) !== 'PERSONAL_STATE') {
                $reasons[] = 'us03_shipper_tin_type_mismatch';
            }

            if (! filled(data_get($request, 'requestedShipment.shipper.tins.0.number'))) {
                $reasons[] = 'us03_shipper_tin_number_missing';
            }

            if (strtoupper((string) data_get($request, 'requestedShipment.recipients.0.address.countryCode', '')) !== 'GB') {
                $reasons[] = 'us03_recipient_country_mismatch';
            }
        }

        if ($testCaseKey === 'IntegratorUS05') {
            if ((int) data_get($request, 'requestedShipment.totalPackageCount') !== 2) {
                $reasons[] = 'us05_total_package_count_mismatch';
            }
        }

        if ($testCaseKey === 'IntegratorUS06') {
            if (! $this->containsSpecialServices($request, ['RETURN_SHIPMENT'])) {
                $reasons[] = 'us06_return_shipment_special_service_missing';
            }

            $returnDetail = data_get($request, 'requestedShipment.shipmentSpecialServices.returnShipmentDetail');
            if (! is_array($returnDetail) || $returnDetail === [] || $returnDetail === '[REDACTED]') {
                $reasons[] = 'us06_return_shipment_detail_missing';
            } elseif (strtoupper((string) data_get($returnDetail, 'returnType', '')) !== 'PRINT_RETURN_LABEL') {
                $reasons[] = 'us06_return_type_mismatch';
            }

            if (data_get($request, 'requestedShipment.blockInsightVisibility') !== false) {
                $reasons[] = 'us06_block_insight_visibility_mismatch';
            }

            if (! data_get($request, 'requestedShipment.recipients.0.address.residential')) {
                $reasons[] = 'us06_recipient_residential_missing';
            }

            if (strtoupper((string) data_get($request, 'requestedShipment.recipients.0.address.countryCode', '')) !== 'CA') {
                $reasons[] = 'us06_recipient_country_mismatch';
            }

            if ((float) data_get($request, 'requestedShipment.requestedPackageLineItems.0.weight.value') !== 10.0) {
                $reasons[] = 'us06_package_weight_mismatch';
            }

            $customs = data_get($request, 'requestedShipment.customsClearanceDetail');
            if (! is_array($customs) || $customs === [] || $customs === '[REDACTED]') {
                $reasons[] = 'us06_customs_clearance_missing';
            } else {
                if ((string) data_get($customs, 'commercialInvoice.specialInstructions') !== 'GSNE.  IOR equals Duties/Taxes payer') {
                    $reasons[] = 'us06_special_instructions_mismatch';
                }
                if (strtoupper((string) data_get($customs, 'commercialInvoice.shipmentPurpose', '')) !== 'SAMPLE') {
                    $reasons[] = 'us06_shipment_purpose_mismatch';
                }
                if ((string) data_get($customs, 'customsOption.type') !== 'EXHIBITION_TRADE_SHOW') {
                    $reasons[] = 'us06_customs_option_type_mismatch';
                }
                if ((string) data_get($customs, 'commodities.0.description') !== 'Dictionaries ') {
                    $reasons[] = 'us06_commodity_description_mismatch';
                }

                $reasons = array_merge($reasons, $this->validateUs06CustomsValues($customs));
            }
        }

        if ($testCaseKey === 'IntegratorUS07') {
            $reasons = array_merge($reasons, $this->validateUs07Request($request));
        }

        if (in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true)) {
            $us09 = $this->us09EvidenceRules->validateShipRequest($request, $testCaseKey);
            $reasons = array_merge($reasons, $us09['reasons']);
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
            'metadata' => $expected,
        ];
    }

    /**
     * @return array{valid: bool, reasons: list<string>, parsed: array<string, mixed>}
     */
    public function validateResponse(CarrierApiEvent $event, string $testCaseKey): array
    {
        $expected = $this->expectedMetadata($testCaseKey);
        $response = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : [];

        if ($testCaseKey === 'IntegratorUS08') {
            $parsed = $this->freightResponseParser->parse($response);
            $freight = $this->freightLtlEvidenceRules->validateResponse($parsed, $event);

            return [
                'valid' => $freight['valid'],
                'reasons' => $freight['reasons'],
                'parsed' => $freight['parsed'],
            ];
        }

        $parsed = $this->responseParser->parse($response);

        if ($this->isLegacyGrandfathered($event)) {
            $legacy = $this->validateLegacyGrandfathered($event, $testCaseKey);

            return [
                'valid' => $legacy['valid'],
                'reasons' => $legacy['reasons'],
                'parsed' => $parsed,
            ];
        }

        $reasons = [];

        $responseService = strtoupper((string) ($parsed['service_type'] ?? data_get($event->response_summary, 'response_service_type', '')));
        $expectedService = (string) $expected['expected_service_type'];

        if ($responseService === '' || $responseService !== $expectedService) {
            $reasons[] = 'response_service_mismatch';
        }

        if ($testCaseKey === 'IntegratorUS02' && in_array($responseService, ['GROUND_HOME_DELIVERY', 'FEDEX_GROUND'], true)) {
            $reasons[] = 'us02_wrong_home_delivery_or_ground';
        }

        $labelCount = count($parsed['labels']);
        if ($labelCount !== (int) $expected['expected_package_count']) {
            $reasons[] = 'response_label_count_mismatch';
        }

        $sequences = array_keys($parsed['labels']);
        $expectedSequences = range(1, (int) $expected['expected_package_count']);
        if ($sequences !== $expectedSequences) {
            $reasons[] = 'response_package_sequence_mismatch';
        }

        foreach ($parsed['labels'] as $label) {
            $imageType = strtoupper((string) ($label['image_type'] ?? ''));
            if ($imageType !== '' && $imageType !== (string) $expected['expected_label_format']) {
                $reasons[] = 'response_label_format_mismatch';
                break;
            }
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
            'parsed' => $parsed,
        ];
    }

    /**
     * Validate sanitized export JSON for locked US ship cases.
     *
     * @param  array<string, mixed>  $sanitizedRequestBody
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateSanitizedExport(array $sanitizedRequestBody, string $testCaseKey): array
    {
        if ($testCaseKey === 'IntegratorUS08') {
            return $this->freightLtlEvidenceRules->validateSanitizedExport($sanitizedRequestBody);
        }

        if (in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true)) {
            return $this->us09EvidenceRules->validateSanitizedExport($sanitizedRequestBody, $testCaseKey);
        }

        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];
        $shipment = data_get($sanitizedRequestBody, 'requestedShipment');
        if (! is_array($shipment)) {
            $shipment = $sanitizedRequestBody;
        }

        $payment = data_get($shipment, 'shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || $payment === []) {
            $reasons[] = 'shipping_charges_payment_missing';
        } else {
            $paymentType = strtoupper((string) data_get($payment, 'paymentType', ''));
            if ($paymentType === '' || $paymentType !== (string) $expected['payment_type']) {
                $reasons[] = 'payment_type_mismatch';
            }
        }

        $imageType = strtoupper((string) data_get($shipment, 'labelSpecification.imageType', ''));
        if ($imageType !== (string) $expected['expected_label_format']) {
            $reasons[] = 'request_label_format_mismatch';
        }

        if ($testCaseKey === 'IntegratorUS02') {
            $types = array_map(
                strtoupper(...),
                (array) data_get($shipment, 'shipmentSpecialServices.specialServiceTypes', []),
            );

            if (! in_array('EVENT_NOTIFICATION', $types, true)) {
                $reasons[] = 'us02_event_notification_missing';
            }

            if (! in_array('SATURDAY_DELIVERY', $types, true)) {
                $reasons[] = 'us02_saturday_delivery_missing';
            }
        }

        if ($testCaseKey === 'IntegratorUS01') {
            $packageServices = data_get($shipment, 'requestedPackageLineItems.0.packageSpecialServices');
            if ($packageServices === '[REDACTED]') {
                $reasons[] = 'us01_package_special_services_wholly_redacted';
            } else {
                $types = array_map(
                    strtoupper(...),
                    (array) data_get($packageServices, 'specialServiceTypes', []),
                );
                if (! in_array('ALCOHOL', $types, true)) {
                    $reasons[] = 'us01_alcohol_special_service_missing';
                }
                if (strtoupper((string) data_get($packageServices, 'alcoholDetail.alcoholRecipientType', '')) !== 'LICENSEE') {
                    $reasons[] = 'us01_alcohol_recipient_type_mismatch';
                }
            }
        }

        if ($testCaseKey === 'IntegratorUS03') {
            $customs = data_get($shipment, 'customsClearanceDetail');
            if ($customs === '[REDACTED]') {
                $reasons[] = 'us03_customs_clearance_wholly_redacted';
            } elseif (! is_array($customs) || $customs === []) {
                $reasons[] = 'us03_customs_clearance_missing';
            } else {
                if (strtoupper((string) data_get($customs, 'dutiesPayment.paymentType', '')) !== 'SENDER') {
                    $reasons[] = 'us03_duties_payment_type_mismatch';
                }
                if ((string) data_get($customs, 'commodities.0.description') !== 'Dictionaries ') {
                    $reasons[] = 'us03_commodity_description_mismatch';
                }
                if ((string) data_get($customs, 'commercialInvoice.comments.0') !== 'FEDEX BUSINESS') {
                    $reasons[] = 'us03_commercial_invoice_comments_mismatch';
                }
                if (array_key_exists('insuranceCharge', $customs)) {
                    $reasons[] = 'us03_top_level_insurance_charge_present';
                }
                if (
                    (float) data_get($customs, 'commercialInvoice.insuranceCharge.amount') !== 50.0
                    || strtoupper((string) data_get($customs, 'commercialInvoice.insuranceCharge.currency', '')) !== 'USD'
                ) {
                    $reasons[] = 'us03_commercial_invoice_insurance_charge_mismatch';
                }
            }

            $tinNumber = data_get($shipment, 'shipper.tins.0.number');
            if ($tinNumber !== null && $tinNumber !== '[REDACTED]') {
                $reasons[] = 'us03_shipper_tin_unredacted';
            }
        }

        if ($testCaseKey === 'IntegratorUS05') {
            $payorAccount = data_get($payment, 'payor.responsibleParty.accountNumber');
            $payorValue = is_array($payorAccount) ? data_get($payorAccount, 'value') : null;

            if (
                ! is_array($payment)
                || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'RECIPIENT'
                || ! is_array($payorAccount)
                || $payorAccount === []
                || $payorValue !== '[REDACTED]'
            ) {
                $reasons[] = 'us05_recipient_payor_account_missing_or_not_redacted';
            }
        }

        if ($testCaseKey === 'IntegratorUS06') {
            $types = array_map(
                strtoupper(...),
                (array) data_get($shipment, 'shipmentSpecialServices.specialServiceTypes', []),
            );
            if (! in_array('RETURN_SHIPMENT', $types, true)) {
                $reasons[] = 'us06_return_shipment_special_service_missing';
            }

            $returnDetail = data_get($shipment, 'shipmentSpecialServices.returnShipmentDetail');
            if ($returnDetail === '[REDACTED]') {
                $reasons[] = 'us06_return_shipment_detail_wholly_redacted';
            } elseif (! is_array($returnDetail) || strtoupper((string) data_get($returnDetail, 'returnType', '')) !== 'PRINT_RETURN_LABEL') {
                $reasons[] = 'us06_return_type_mismatch';
            }

            $customs = data_get($shipment, 'customsClearanceDetail');
            if ($customs === '[REDACTED]') {
                $reasons[] = 'us06_customs_clearance_wholly_redacted';
            } elseif (! is_array($customs) || $customs === []) {
                $reasons[] = 'us06_customs_clearance_missing';
            } else {
                if ((string) data_get($customs, 'customsOption.type') !== 'EXHIBITION_TRADE_SHOW') {
                    $reasons[] = 'us06_customs_option_type_mismatch';
                }
                if ((string) data_get($customs, 'commodities.0.description') !== 'Dictionaries ') {
                    $reasons[] = 'us06_commodity_description_mismatch';
                }

                $reasons = array_merge($reasons, $this->validateUs06CustomsValues($customs));
            }
        }

        if ($testCaseKey === 'IntegratorUS07') {
            $reasons = array_merge($reasons, $this->validateUs07SanitizedExport($sanitizedRequestBody, $shipment, $payment));
        }

        if ($this->containsUnredactedAccountNumber($sanitizedRequestBody)) {
            $reasons[] = 'account_number_unredacted';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  list<FedExValidationArtifact>  $artifacts
     * @param  list<FedExValidationArtifact>  $documentArtifacts
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateGeneratedArtifacts(
        CarrierApiEvent $event,
        string $testCaseKey,
        array $artifacts,
        array $documentArtifacts = [],
    ): array {
        if ($testCaseKey === 'IntegratorUS08') {
            return $this->freightLtlEvidenceRules->validateGeneratedArtifacts($event, $artifacts, $documentArtifacts);
        }

        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];
        $labelsBySequence = [];

        foreach ($artifacts as $artifact) {
            if ($artifact->artifact_role !== FedExValidationArtifact::ROLE_GENERATED_LABEL) {
                continue;
            }

            $sequence = (int) $artifact->package_sequence;
            if (isset($labelsBySequence[$sequence])) {
                $reasons[] = 'duplicate_generated_label_sequence_'.$sequence;
            }
            $labelsBySequence[$sequence] = $artifact;

            $path = $artifact->absolutePath();
            if ($path === null || ! FedExLabelArtifactValidator::isValid($path, (string) $expected['expected_label_format'])) {
                $reasons[] = 'invalid_generated_label_binary_'.$sequence;
            }
        }

        for ($sequence = 1; $sequence <= (int) $expected['expected_package_count']; $sequence++) {
            if (! isset($labelsBySequence[$sequence])) {
                $reasons[] = 'missing_generated_label_'.$sequence;
            }
        }

        if ((int) $event->id !== (int) ($labelsBySequence[1]?->carrier_api_event_id ?? $event->id)) {
            $reasons[] = 'artifact_event_mismatch';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{valid: bool, reasons: list<string>, metadata: array<string, mixed>}
     */
    private function validateFreightUs08Request(CarrierApiEvent $event): array
    {
        $expected = $this->expectedMetadata('IntegratorUS08');
        $reasons = [];
        $request = is_array($event->request_body_encrypted) ? $event->request_body_encrypted : [];

        if ($event->action !== CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL) {
            $reasons[] = 'action_not_create_label';
        }

        if (strtoupper((string) $event->http_method) !== 'POST') {
            $reasons[] = 'http_method_not_post';
        }

        $endpoint = (string) ($event->endpoint ?? data_get($event->request_summary, 'endpoint', ''));
        if (! str_contains($endpoint, $this->config->freightLtlShipPath($event->environment))) {
            $reasons[] = 'endpoint_not_freight_ltl_ship';
        }

        if (! $event->hasCompleteEvidence()) {
            $reasons[] = 'incomplete_evidence';
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED || ! $event->isSuccessfulHttp()) {
            $reasons[] = 'http_not_successful';
        }

        if ((string) $event->scenario_key !== (string) $expected['scenario_key']) {
            $reasons[] = 'scenario_key_mismatch';
        }

        if ((string) $event->test_case_key !== 'IntegratorUS08') {
            $reasons[] = 'test_case_key_mismatch';
        }

        $appearsSanitized = data_get($request, 'accountNumber.value') === '[REDACTED]'
            || data_get($request, 'freightRequestedShipment.freightShipmentDetail.fedExFreightAccountNumber.value') === '[REDACTED]'
            || data_get($request, 'freightRequestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value') === '[REDACTED]';

        $freight = $appearsSanitized
            ? $this->freightLtlEvidenceRules->validateSanitizedExport($request)
            : $this->freightLtlEvidenceRules->validateRequest($request);
        $reasons = array_merge($reasons, $freight['reasons']);

        return [
            'valid' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
            'metadata' => $expected,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  list<string>  $required
     */
    private function containsSpecialServices(array $request, array $required): bool
    {
        $types = array_map(
            strtoupper(...),
            (array) data_get($request, 'requestedShipment.shipmentSpecialServices.specialServiceTypes', []),
        );

        foreach ($required as $service) {
            if (! in_array(strtoupper($service), $types, true)) {
                return false;
            }
        }

        return true;
    }

    private function isFridayShipDate(string $shipDate): bool
    {
        if ($shipDate === '') {
            return false;
        }

        try {
            return now()->parse($shipDate)->dayOfWeek === 5;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $customs
     * @return list<string>
     */
    private function validateUs06CustomsValues(array $customs): array
    {
        $reasons = [];

        if (
            (float) data_get($customs, 'totalCustomsValue.amount') !== 1.0
            || strtoupper((string) data_get($customs, 'totalCustomsValue.currency', '')) !== 'USD'
        ) {
            $reasons[] = 'us06_total_customs_value_mismatch';
        }

        if (
            (float) data_get($customs, 'commodities.0.unitPrice.amount') !== 0.0
            || strtoupper((string) data_get($customs, 'commodities.0.unitPrice.currency', '')) !== 'USD'
        ) {
            $reasons[] = 'us06_workbook_unit_price_mismatch';
        }

        if (
            (float) data_get($customs, 'commodities.0.customsValue.amount') !== 1.0
            || strtoupper((string) data_get($customs, 'commodities.0.customsValue.currency', '')) !== 'USD'
        ) {
            $reasons[] = 'us06_commodity_customs_value_mismatch';
        }

        $commodityTotal = 0.0;
        foreach ((array) data_get($customs, 'commodities', []) as $commodity) {
            if (! is_array($commodity)) {
                continue;
            }
            $commodityTotal += (float) data_get($commodity, 'customsValue.amount', 0);
        }

        if ($commodityTotal !== (float) data_get($customs, 'totalCustomsValue.amount')) {
            $reasons[] = 'us06_customs_value_total_mismatch';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function validateUs07Request(array $request): array
    {
        $reasons = [];
        $shipment = data_get($request, 'requestedShipment');
        if (! is_array($shipment)) {
            return ['us07_service_type_mismatch'];
        }

        if (strtoupper((string) data_get($shipment, 'serviceType', '')) !== 'SMART_POST') {
            $reasons[] = 'us07_service_type_mismatch';
        }
        if ((string) data_get($shipment, 'packagingType') !== 'YOUR_PACKAGING') {
            $reasons[] = 'us07_packaging_type_mismatch';
        }
        if ((string) data_get($shipment, 'pickupType') !== 'USE_SCHEDULED_PICKUP') {
            $reasons[] = 'us07_pickup_type_mismatch';
        }

        $payment = data_get($shipment, 'shippingChargesPayment');
        if (! is_array($payment) || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'SENDER') {
            $reasons[] = 'us07_sender_payment_missing';
        } else {
            $payorAccount = data_get($payment, 'payor.responsibleParty.accountNumber');
            if (! is_array($payorAccount) || $payorAccount === [] || ! filled(data_get($payorAccount, 'value'))) {
                $reasons[] = 'us07_sender_payor_missing';
            }
            if (strtoupper((string) data_get($payment, 'payor.responsibleParty.address.countryCode', '')) !== 'US') {
                $reasons[] = 'us07_sender_payor_country_mismatch';
            }
        }

        if (
            strtoupper((string) data_get($shipment, 'labelSpecification.imageType', '')) !== 'PDF'
            || (string) data_get($shipment, 'labelSpecification.labelStockType') !== 'PAPER_85X11_TOP_HALF_LABEL'
        ) {
            $reasons[] = 'us07_label_format_mismatch';
        }

        if ((int) data_get($shipment, 'totalPackageCount') !== 1) {
            $reasons[] = 'us07_total_package_count_mismatch';
        }

        if ((int) data_get($shipment, 'requestedPackageLineItems.0.sequenceNumber') !== 1) {
            $reasons[] = 'us07_package_sequence_mismatch';
        }

        if (
            strtoupper((string) data_get($shipment, 'requestedPackageLineItems.0.weight.units', '')) !== 'LB'
            || (float) data_get($shipment, 'requestedPackageLineItems.0.weight.value') !== 2.3
        ) {
            $reasons[] = 'us07_package_weight_mismatch';
        }

        $smartPost = data_get($shipment, 'smartPostInfoDetail');
        if (! is_array($smartPost) || $smartPost === []) {
            $reasons[] = 'us07_smart_post_detail_missing';
        } else {
            if ((string) data_get($smartPost, 'indicia') !== 'PARCEL_SELECT') {
                $reasons[] = 'us07_indicia_mismatch';
            }
            if ((string) data_get($smartPost, 'hubId') !== '5531') {
                $reasons[] = 'us07_hub_id_mismatch';
            }
            if (array_key_exists('ancillaryEndorsement', $smartPost)) {
                $reasons[] = 'us07_unexpected_ancillary_endorsement';
            }
        }

        if (! is_array(data_get($request, 'accountNumber')) || ! filled(data_get($request, 'accountNumber.value'))) {
            $reasons[] = 'us07_root_account_number_missing';
        }

        $recipientAddress = data_get($shipment, 'recipients.0.address');
        if (is_array($recipientAddress) && array_key_exists('residential', $recipientAddress)) {
            $reasons[] = 'us07_unexpected_residential_flag';
        }

        if (data_get($shipment, 'requestedPackageLineItems.0.customerReferences') !== null) {
            $reasons[] = 'us07_unexpected_customer_reference';
        }

        if (data_get($shipment, 'requestedPackageLineItems.0.dimensions') !== null) {
            $reasons[] = 'us07_unexpected_dimensions';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $sanitizedRequestBody
     * @param  array<string, mixed>|mixed  $shipment
     * @param  array<string, mixed>|mixed  $payment
     * @return list<string>
     */
    private function validateUs07SanitizedExport(array $sanitizedRequestBody, mixed $shipment, mixed $payment): array
    {
        $reasons = [];

        if (! is_array($shipment)) {
            return ['us07_service_type_mismatch'];
        }

        $reasons = array_merge($reasons, $this->validateUs07Request([
            'accountNumber' => data_get($sanitizedRequestBody, 'accountNumber'),
            'requestedShipment' => $shipment,
        ]));

        // Replace raw-account checks with redaction checks for sanitized export.
        $reasons = array_values(array_filter(
            $reasons,
            static fn (string $reason): bool => ! in_array($reason, [
                'us07_sender_payor_missing',
                'us07_root_account_number_missing',
            ], true),
        ));

        if ($payment === '[REDACTED]') {
            $reasons[] = 'us07_shipping_charges_payment_wholly_redacted';
        }

        $smartPost = data_get($shipment, 'smartPostInfoDetail');
        if ($smartPost === '[REDACTED]') {
            $reasons[] = 'us07_smart_post_detail_wholly_redacted';
        }

        $rootAccount = data_get($sanitizedRequestBody, 'accountNumber');
        if ($rootAccount === '[REDACTED]') {
            $reasons[] = 'us07_root_account_number_wholly_redacted';
        } elseif (! is_array($rootAccount) || data_get($rootAccount, 'value') !== '[REDACTED]') {
            $reasons[] = 'us07_root_account_number_missing_or_not_redacted';
        }

        $payorAccount = data_get($payment, 'payor.responsibleParty.accountNumber');
        $payorValue = is_array($payorAccount) ? data_get($payorAccount, 'value') : null;
        if (
            ! is_array($payment)
            || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'SENDER'
            || ! is_array($payorAccount)
            || $payorValue !== '[REDACTED]'
        ) {
            $reasons[] = 'us07_sender_payor_missing_or_not_redacted';
        }

        return array_values(array_unique($reasons));
    }

    private function containsUnredactedAccountNumber(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (FedExSensitiveFieldClassifier::isAccountNumberKey((string) $key)) {
                $accountValue = is_array($item) ? data_get($item, 'value') : $item;
                if (is_string($accountValue) && $accountValue !== '' && $accountValue !== '[REDACTED]') {
                    return true;
                }
            }

            if ($this->containsUnredactedAccountNumber($item)) {
                return true;
            }
        }

        return false;
    }
}
