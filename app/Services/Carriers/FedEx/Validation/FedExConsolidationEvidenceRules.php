<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Evidence rules for IntegratorUS10 Consolidation / IPD requests.
 */
final class FedExConsolidationEvidenceRules
{
    /**
     * @param  array<string, mixed>  $request
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateCreateRequest(array $request): array
    {
        return [
            'valid' => ($reasons = $this->collectCreateReasons($request, redactedAccounts: false)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateAddShipmentRequest(array $request, int $sequence): array
    {
        return [
            'valid' => ($reasons = $this->collectAddShipmentReasons($request, $sequence, redactedAccounts: false)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateConfirmRequest(array $request): array
    {
        return [
            'valid' => ($reasons = $this->collectConfirmReasons($request, redactedAccounts: false)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $sanitizedRequestBody
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateSanitizedExport(array $sanitizedRequestBody, string $operation): array
    {
        $reasons = match ($operation) {
            'create' => $this->collectCreateReasons($sanitizedRequestBody, redactedAccounts: true),
            'add_shipment' => $this->collectAddShipmentReasons($sanitizedRequestBody, 0, redactedAccounts: true),
            'confirm' => $this->collectConfirmReasons($sanitizedRequestBody, redactedAccounts: true),
            'confirm_results' => $this->collectConfirmResultsReasons($sanitizedRequestBody, redactedAccounts: true),
            default => ['us10_unknown_operation'],
        };

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectCreateReasons(array $request, bool $redactedAccounts): array
    {
        $reasons = [];

        if (array_key_exists('requestedShipment', $request) && ! array_key_exists('requestedConsolidation', $request)) {
            $reasons[] = 'us10_parcel_ship_envelope_used';
        }
        if (array_key_exists('freightRequestedShipment', $request)) {
            $reasons[] = 'us10_freight_envelope_used';
        }

        $requested = data_get($request, 'requestedConsolidation');
        if ($requested === '[REDACTED]') {
            $reasons[] = 'us10_requested_consolidation_wholly_redacted';

            return $reasons;
        }
        if (! is_array($requested) || $requested === []) {
            $reasons[] = 'us10_requested_consolidation_missing';

            return $reasons;
        }

        if ((string) data_get($requested, 'consolidationType') !== FedExConsolidationFixtureService::CONSOLIDATION_TYPE) {
            $reasons[] = 'us10_consolidation_type_mismatch';
        }

        $specialTypes = array_map('strtoupper', (array) data_get($requested, 'specialServicesRequested.specialServiceTypes', []));
        if (! in_array('INTERNATIONAL_CONTROLLED_EXPORT_SERVICE', $specialTypes, true)) {
            $reasons[] = 'us10_ice_special_service_missing';
        }

        $ice = data_get($requested, 'specialServicesRequested.internationalControlledExportDetail');
        if (! is_array($ice) || (string) data_get($ice, 'type') !== 'DEA_486') {
            $reasons[] = 'us10_ice_detail_mismatch';
        }

        $payment = data_get($requested, 'shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'us10_shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'THIRD_PARTY') {
            $reasons[] = 'us10_transportation_payment_mismatch';
        }

        $duties = data_get($requested, 'customsClearanceDetail.dutiesPayment');
        if ($duties === '[REDACTED]') {
            $reasons[] = 'us10_duties_payment_wholly_redacted';
        } elseif (! is_array($duties) || strtoupper((string) data_get($duties, 'paymentType', '')) !== 'THIRD_PARTY') {
            $reasons[] = 'us10_duties_payment_mismatch';
        }

        $docTypes = array_map('strtoupper', (array) data_get($requested, 'consolidationDocumentSpecification.consolidationDocumentTypes', []));
        if (! in_array('CONSOLIDATED_COMMERCIAL_INVOICE', $docTypes, true)) {
            $reasons[] = 'us10_cci_document_type_missing';
        }

        $reasons = array_merge($reasons, $this->accountReasons($request, 'accountNumber', 'us10_root_account', $redactedAccounts));
        $reasons = array_merge($reasons, $this->accountReasons(
            $request,
            'requestedConsolidation.shippingChargesPayment.payor.responsibleParty.accountNumber',
            'us10_transport_payor_account',
            $redactedAccounts,
        ));

        $tinNumber = data_get($requested, 'shipper.tins.0.number');
        if ($redactedAccounts && $tinNumber !== null && $tinNumber !== '[REDACTED]') {
            $reasons[] = 'us10_shipper_tin_unredacted';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectAddShipmentReasons(array $request, int $sequence, bool $redactedAccounts): array
    {
        $reasons = [];

        if (array_key_exists('freightRequestedShipment', $request)) {
            $reasons[] = 'us10_freight_envelope_used';
        }

        $shipment = data_get($request, 'requestedShipment');
        if ($shipment === '[REDACTED]') {
            $reasons[] = 'us10_requested_shipment_wholly_redacted';

            return $reasons;
        }
        if (! is_array($shipment) || $shipment === []) {
            $reasons[] = 'us10_requested_shipment_missing';

            return $reasons;
        }

        $key = data_get($request, 'consolidationKey');
        if ($key === '[REDACTED]') {
            $reasons[] = 'us10_consolidation_key_wholly_redacted';
        } elseif (! is_array($key)) {
            $reasons[] = 'us10_consolidation_key_missing';
        } else {
            if ((string) data_get($key, 'type') !== FedExConsolidationFixtureService::CONSOLIDATION_TYPE) {
                $reasons[] = 'us10_consolidation_key_type_mismatch';
            }
            if ($redactedAccounts && data_get($key, 'index') !== '[REDACTED]') {
                $reasons[] = 'us10_consolidation_index_unredacted';
            }
        }

        if ((string) data_get($shipment, 'serviceType') !== FedExConsolidationFixtureService::CONSOLIDATION_TYPE) {
            $reasons[] = 'us10_service_type_mismatch';
        }

        $payment = data_get($shipment, 'shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'us10_shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'THIRD_PARTY') {
            $reasons[] = 'us10_transportation_payment_mismatch';
        }

        $commodities = data_get($shipment, 'customsClearanceDetail.commodities');
        if ($commodities === '[REDACTED]') {
            $reasons[] = 'us10_commodities_wholly_redacted';
        } elseif (! is_array($commodities) || $commodities === []) {
            $reasons[] = 'us10_commodities_missing';
        } else {
            if ((string) data_get($commodities, '0.description') !== 'Textbooks') {
                $reasons[] = 'us10_commodity_description_mismatch';
            }
            if ((string) data_get($commodities, '0.harmonizedCode') !== '4901990010') {
                $reasons[] = 'us10_harmonized_code_mismatch';
            }
        }

        $package = data_get($shipment, 'requestedPackageLineItems.0');
        if (! is_array($package)) {
            $reasons[] = 'us10_package_missing';
        } else {
            if ((int) data_get($package, 'groupPackageCount') !== 35) {
                $reasons[] = 'us10_group_package_count_mismatch';
            }
            if (array_key_exists('physicalPackaging', $package) && (string) data_get($package, 'physicalPackaging') === 'null') {
                $reasons[] = 'us10_physical_packaging_string_null';
            }
        }

        if ($sequence === 3) {
            $street2 = data_get($shipment, 'shipper.address.streetLines.1');
            if ($street2 !== ' Suite 101') {
                $reasons[] = 'us10_shipment3_leading_space_street_normalized';
            }
        }

        if ($sequence === 2 || $sequence === 3 || $sequence === 6) {
            if ((string) data_get($shipment, 'customsClearanceDetail.commodities.0.commodityId') !== 'commodity Id') {
                $reasons[] = 'us10_commodity_id_missing_for_shipment_'.$sequence;
            }
        }

        if (in_array($sequence, [3, 5, 6], true)) {
            if ((string) data_get($shipment, 'dropoffType') !== 'REGULAR_PICKUP') {
                $reasons[] = 'us10_dropoff_type_missing_for_shipment_'.$sequence;
            }
        } elseif ($sequence > 0 && array_key_exists('dropoffType', $shipment)) {
            $reasons[] = 'us10_unexpected_dropoff_type_for_shipment_'.$sequence;
        }

        $reasons = array_merge($reasons, $this->accountReasons($request, 'accountNumber', 'us10_root_account', $redactedAccounts));

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectConfirmReasons(array $request, bool $redactedAccounts): array
    {
        $reasons = [];

        $key = data_get($request, 'consolidationKey');
        if ($key === '[REDACTED]') {
            $reasons[] = 'us10_consolidation_key_wholly_redacted';
        } elseif (! is_array($key)) {
            $reasons[] = 'us10_consolidation_key_missing';
        }

        if ((string) data_get($request, 'processingOptionType') !== 'ALLOW_ASYNCHRONOUS') {
            $reasons[] = 'us10_processing_option_mismatch';
        }
        if ((string) data_get($request, 'edtRequestType') !== 'ALL') {
            $reasons[] = 'us10_edt_request_type_mismatch';
        }

        $label = data_get($request, 'labelSpecification');
        if ($label === '[REDACTED]') {
            $reasons[] = 'us10_label_specification_wholly_redacted';
        } elseif (
            ! is_array($label)
            || (string) data_get($label, 'imageType') !== 'PDF'
            || (string) data_get($label, 'labelStockType') !== 'PAPER_4X6'
        ) {
            $reasons[] = 'us10_confirm_label_mismatch';
        }

        $reasons = array_merge($reasons, $this->accountReasons($request, 'accountNumber', 'us10_root_account', $redactedAccounts));

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectConfirmResultsReasons(array $request, bool $redactedAccounts): array
    {
        $reasons = [];

        $jobId = data_get($request, 'jobId');
        if ($redactedAccounts) {
            if ($jobId !== '[REDACTED]') {
                $reasons[] = 'us10_job_id_unredacted';
            }
        } elseif (! filled($jobId) || $jobId === FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID) {
            $reasons[] = 'us10_job_id_missing_or_historical';
        }

        $reasons = array_merge($reasons, $this->accountReasons($request, 'accountNumber', 'us10_root_account', $redactedAccounts));

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function accountReasons(array $request, string $path, string $prefix, bool $redactedAccounts): array
    {
        $account = data_get($request, $path);
        if ($account === '[REDACTED]') {
            return [$prefix.'_wholly_redacted'];
        }

        if ($redactedAccounts) {
            if (! is_array($account) || data_get($account, 'value') !== '[REDACTED]') {
                return [$prefix.'_missing_or_not_redacted'];
            }

            return [];
        }

        if (! is_array($account) || ! filled(data_get($account, 'value'))) {
            return [$prefix.'_missing'];
        }

        return [];
    }
}
