<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Evidence rules for IntegratorUS08 Freight LTL requests and responses.
 */
final class FedExFreightLtlEvidenceRules
{
    /**
     * @param  array<string, mixed>  $request
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateRequest(array $request): array
    {
        return [
            'valid' => ($reasons = $this->collectReasons($request, redactedAccounts: false)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $sanitizedRequestBody
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateSanitizedExport(array $sanitizedRequestBody): array
    {
        return [
            'valid' => ($reasons = $this->collectReasons($sanitizedRequestBody, redactedAccounts: true)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{valid: bool, reasons: list<string>, parsed: array<string, mixed>}
     */
    public function validateResponse(array $parsed, ?\App\Models\CarrierApiEvent $event = null): array
    {
        $reasons = [];

        if ($event !== null) {
            if (! $event->isSuccessfulHttp() || $event->status !== \App\Models\CarrierApiEvent::STATUS_SUCCEEDED) {
                $reasons[] = 'us08_http_not_successful';
            }
            if ((string) $event->scenario_key !== 'ship_us08_zplii') {
                $reasons[] = 'us08_scenario_key_mismatch';
            }
            if ((string) $event->test_case_key !== 'IntegratorUS08') {
                $reasons[] = 'us08_test_case_key_mismatch';
            }
        }

        if (strtoupper((string) ($parsed['service_type'] ?? '')) !== 'FEDEX_FREIGHT_PRIORITY') {
            $reasons[] = 'us08_response_service_mismatch';
        }

        $labels = is_array($parsed['labels'] ?? null) ? $parsed['labels'] : [];
        if (count($labels) !== 1 || ! isset($labels[1])) {
            $reasons[] = 'us08_handling_unit_label_count_mismatch';
        } else {
            $label = $labels[1];
            $imageType = strtoupper((string) ($label['image_type'] ?? ''));
            if ($imageType !== '' && $imageType !== 'ZPLII') {
                $reasons[] = 'us08_label_format_mismatch';
            }
            $encoded = $label['encoded_label'] ?? null;
            $binary = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (! is_string($encoded) || $encoded === '' || $binary === false || $binary === '') {
                $reasons[] = 'us08_label_empty_or_corrupt';
            }
        }

        if (! ($parsed['bol_present'] ?? false)) {
            $reasons[] = 'us08_bol_missing';
        } else {
            $bol = collect($parsed['documents'] ?? [])->firstWhere('is_bol', true);
            $encoded = is_array($bol) ? ($bol['encoded_label'] ?? null) : null;
            $binary = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (! is_string($encoded) || $binary === false || $binary === '' || ! str_starts_with((string) $binary, '%PDF')) {
                $reasons[] = 'us08_bol_empty_or_corrupt';
            }
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
            'parsed' => $parsed,
        ];
    }

    /**
     * @param  list<\App\Models\FedExValidationArtifact>  $labelArtifacts
     * @param  list<\App\Models\FedExValidationArtifact>  $documentArtifacts
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateGeneratedArtifacts(
        \App\Models\CarrierApiEvent $event,
        array $labelArtifacts,
        array $documentArtifacts = [],
    ): array {
        $reasons = [];
        $labelsBySequence = [];

        foreach ($labelArtifacts as $artifact) {
            if ($artifact->artifact_role !== \App\Models\FedExValidationArtifact::ROLE_GENERATED_LABEL) {
                continue;
            }

            $sequence = (int) $artifact->package_sequence;
            if (isset($labelsBySequence[$sequence])) {
                $reasons[] = 'us08_duplicate_generated_label_sequence_'.$sequence;
            }
            $labelsBySequence[$sequence] = $artifact;

            $path = $artifact->absolutePath();
            if ($path === null || ! \App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator::isValid($path, 'ZPLII')) {
                $reasons[] = 'us08_invalid_generated_label_binary_'.$sequence;
            }
        }

        if (! isset($labelsBySequence[1])) {
            $reasons[] = 'us08_missing_generated_label_1';
        }

        $bol = collect($documentArtifacts)->first(
            fn ($artifact): bool => $artifact->artifact_type === 'freight_bill_of_lading'
                && $artifact->artifact_role === \App\Models\FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT
        );

        if ($bol === null) {
            $reasons[] = 'us08_bol_artifact_missing';
        } else {
            $path = $bol->absolutePath();
            if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                $reasons[] = 'us08_bol_artifact_empty_or_corrupt';
            } else {
                $contents = (string) file_get_contents($path);
                if (! str_starts_with($contents, '%PDF')) {
                    $reasons[] = 'us08_bol_artifact_empty_or_corrupt';
                }
            }
        }

        if ((int) $event->id !== (int) ($labelsBySequence[1]?->carrier_api_event_id ?? $event->id)) {
            $reasons[] = 'us08_artifact_event_mismatch';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectReasons(array $request, bool $redactedAccounts): array
    {
        $reasons = [];

        if (array_key_exists('requestedShipment', $request) && ! array_key_exists('freightRequestedShipment', $request)) {
            $reasons[] = 'us08_parcel_ship_envelope_used';
        }

        $shipment = data_get($request, 'freightRequestedShipment');
        if ($shipment === '[REDACTED]') {
            $reasons[] = 'us08_freight_requested_shipment_wholly_redacted';

            return $reasons;
        }
        if (! is_array($shipment) || $shipment === []) {
            $reasons[] = 'us08_freight_requested_shipment_missing';

            return $reasons;
        }

        if (strtoupper((string) data_get($shipment, 'serviceType', '')) !== 'FEDEX_FREIGHT_PRIORITY') {
            $reasons[] = 'us08_service_type_mismatch';
        }
        if ((string) data_get($shipment, 'packagingType') !== 'YOUR_PACKAGING') {
            $reasons[] = 'us08_packaging_type_mismatch';
        }
        if ((string) data_get($shipment, 'pickupType') !== 'USE_SCHEDULED_PICKUP') {
            $reasons[] = 'us08_pickup_type_mismatch';
        }
        if ((int) data_get($shipment, 'totalWeight') !== 1000) {
            $reasons[] = 'us08_total_weight_mismatch';
        }
        if ((int) data_get($shipment, 'totalPackageCount') !== 1) {
            $reasons[] = 'us08_total_package_count_mismatch';
        }

        $payment = data_get($shipment, 'shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'us08_shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'RECIPIENT') {
            $reasons[] = 'us08_payment_type_mismatch';
        } else {
            $payorAccount = data_get($payment, 'payor.responsibleParty.accountNumber');
            $payorValue = is_array($payorAccount) ? data_get($payorAccount, 'value') : null;
            if ($redactedAccounts) {
                if (! is_array($payorAccount) || $payorValue !== '[REDACTED]') {
                    $reasons[] = 'us08_payor_account_missing_or_not_redacted';
                }
            } elseif (! is_array($payorAccount) || ! filled($payorValue) || $payorValue === '[REDACTED]') {
                $reasons[] = 'us08_payor_account_missing';
            }
        }

        $detail = data_get($shipment, 'freightShipmentDetail');
        if ($detail === '[REDACTED]') {
            $reasons[] = 'us08_freight_shipment_detail_wholly_redacted';
        } elseif (! is_array($detail) || $detail === []) {
            $reasons[] = 'us08_freight_shipment_detail_missing';
        } else {
            if ((string) data_get($detail, 'role') !== 'SHIPPER') {
                $reasons[] = 'us08_freight_role_mismatch';
            }
            if ((string) data_get($detail, 'collectTermsType') !== 'NON_RECOURSE_SHIPPER_SIGNED') {
                $reasons[] = 'us08_collect_terms_mismatch';
            }
            if (array_key_exists('carrierCode', $detail) && filled(data_get($detail, 'carrierCode'))) {
                $reasons[] = 'us08_unexpected_carrier_code';
            }
            if ((int) data_get($detail, 'totalHandlingUnits') !== 1) {
                $reasons[] = 'us08_handling_unit_count_mismatch';
            }
            if ((float) data_get($detail, 'clientDiscountPercent') !== 0.0) {
                $reasons[] = 'us08_client_discount_percent_mismatch';
            }
            if ((string) data_get($detail, 'declaredValuePerUnit.currency') !== 'USD'
                || (float) data_get($detail, 'declaredValuePerUnit.amount') !== 0.0
                || (string) data_get($detail, 'declaredValueUnits') !== 'LB'
            ) {
                $reasons[] = 'us08_declared_value_mismatch';
            }
            if ((string) data_get($detail, 'lineItem.0.id') !== '10') {
                $reasons[] = 'us08_line_item_id_mismatch';
            }
            if ((string) data_get($detail, 'lineItem.0.freightClass') !== 'CLASS_050') {
                $reasons[] = 'us08_freight_class_mismatch';
            }
            if ((string) data_get($detail, 'lineItem.0.description') !== 'Axles') {
                $reasons[] = 'us08_commodity_description_mismatch';
            }
            if (array_key_exists('nmfcCode', (array) data_get($detail, 'lineItem.0', []))) {
                $reasons[] = 'us08_nmfc_code_should_be_omitted';
            }
            if ((string) data_get($detail, 'lineItem.0.purchaseOrderNumber') !== '54321') {
                $reasons[] = 'us08_purchase_order_number_mismatch';
            }
            if ((float) data_get($detail, 'lineItem.0.weight.value') !== 1000.0) {
                $reasons[] = 'us08_line_item_weight_mismatch';
            }
            if ((int) data_get($detail, 'lineItem.0.handlingUnits') !== 1) {
                $reasons[] = 'us08_line_item_handling_units_mismatch';
            }
            if ((int) data_get($detail, 'lineItem.0.pieces') !== 10) {
                $reasons[] = 'us08_line_item_pieces_mismatch';
            }
            if ((string) data_get($detail, 'lineItem.0.subPackagingType') !== 'BARREL') {
                $reasons[] = 'us08_sub_packaging_type_mismatch';
            }

            $billing = data_get($detail, 'fedExFreightBillingContactAndAddress');
            if (! is_array($billing) || $billing === []) {
                $reasons[] = 'us08_freight_billing_address_missing';
            } elseif ((string) data_get($billing, 'address.postalCode') !== '72601') {
                $reasons[] = 'us08_freight_billing_address_mismatch';
            }

            $freightAccount = data_get($detail, 'fedExFreightAccountNumber');
            $freightValue = is_array($freightAccount) ? data_get($freightAccount, 'value') : null;
            if ($redactedAccounts) {
                if (! is_array($freightAccount) || $freightValue !== '[REDACTED]') {
                    $reasons[] = 'us08_freight_account_missing_or_not_redacted';
                }
            } elseif (! is_array($freightAccount) || ! filled($freightValue)) {
                $reasons[] = 'us08_freight_account_missing';
            }
        }

        $label = data_get($shipment, 'labelSpecification');
        if (
            strtoupper((string) data_get($label, 'imageType', '')) !== 'ZPLII'
            || (string) data_get($label, 'labelStockType') !== 'STOCK_4X6'
            || (string) data_get($label, 'labelOrder') !== 'SHIPPING_LABEL_FIRST'
        ) {
            $reasons[] = 'us08_label_format_mismatch';
        }

        $docTypes = array_map('strtoupper', (array) data_get($shipment, 'shippingDocumentSpecification.shippingDocumentTypes', []));
        if (
            ! in_array('FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING', $docTypes, true)
            || ! in_array('COMMERCIAL_INVOICE', $docTypes, true)
        ) {
            $reasons[] = 'us08_bill_of_lading_requirements_mismatch';
        }
        if (data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.provideInstructions') !== true) {
            $reasons[] = 'us08_commercial_invoice_instructions_missing';
        }
        $bolFormat = data_get($shipment, 'shippingDocumentSpecification.freightBillOfLadingDetail.format');
        if (
            ! is_array($bolFormat)
            || data_get($bolFormat, 'provideInstructions') !== true
            || strtoupper((string) data_get($bolFormat, 'dispositions.0.dispositionType')) !== 'RETURNED'
            || (string) data_get($bolFormat, 'stockType') !== 'PAPER_LETTER'
            || (string) data_get($bolFormat, 'docType') !== 'PDF'
        ) {
            $reasons[] = 'us08_bol_format_requirements_mismatch';
        }

        $rateTypes = array_map('strtoupper', (array) data_get($shipment, 'rateRequestType', []));
        if ($rateTypes !== ['LIST', 'PREFERRED']) {
            $reasons[] = 'us08_rate_request_types_mismatch';
        }

        $specialTypes = array_map('strtoupper', (array) data_get($shipment, 'freightShipmentSpecialServices.specialServiceTypes', []));
        if (! in_array('INSIDE_DELIVERY', $specialTypes, true)) {
            $reasons[] = 'us08_inside_delivery_missing';
        }

        if ((float) data_get($shipment, 'requestedPackageLineItems.0.weight.value') !== 1000.0) {
            $reasons[] = 'us08_package_weight_mismatch';
        }
        if (! is_array(data_get($shipment, 'requestedPackageLineItems.0.dimensions'))) {
            $reasons[] = 'us08_package_dimensions_missing';
        }
        if ((string) data_get($shipment, 'requestedPackageLineItems.0.associatedFreightLineItems.0.id') !== '10') {
            $reasons[] = 'us08_associated_freight_line_item_id_mismatch';
        }

        $rootAccount = data_get($request, 'accountNumber');
        if ($rootAccount === '[REDACTED]') {
            $reasons[] = 'us08_root_account_wholly_redacted';
        } elseif ($redactedAccounts) {
            if (! is_array($rootAccount) || data_get($rootAccount, 'value') !== '[REDACTED]') {
                $reasons[] = 'us08_root_account_missing_or_not_redacted';
            }
        } elseif (! is_array($rootAccount) || ! filled(data_get($rootAccount, 'value'))) {
            $reasons[] = 'us08_root_account_missing';
        }

        if (data_get($shipment, 'shipper.contact.personName') !== 'QCONFIG'
            || data_get($shipment, 'shipper.contact.companyName') !== 'RTC'
        ) {
            $reasons[] = 'us08_shipper_mismatch';
        }
        if (data_get($shipment, 'recipient.contact.personName') !== 'F-413404'
            || data_get($shipment, 'recipient.contact.companyName') !== 'IntegratorUS09'
        ) {
            $reasons[] = 'us08_recipient_mismatch';
        }

        return array_values(array_unique($reasons));
    }
}
