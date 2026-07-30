<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * Evidence rules for IntegratorUS09 ETD Image and Document ship + upload requests.
 */
final class FedExUs09EvidenceRules
{
    /**
     * @param  array<string, mixed>  $request
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateShipRequest(array $request, string $testCaseKey): array
    {
        return [
            'valid' => ($reasons = $this->collectShipReasons($request, $testCaseKey, redactedAccounts: false)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $sanitizedRequestBody
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateSanitizedExport(array $sanitizedRequestBody, string $testCaseKey): array
    {
        return [
            'valid' => ($reasons = $this->collectShipReasons($sanitizedRequestBody, $testCaseKey, redactedAccounts: true)) === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Validate one independent upload evidence payload by upload scenario key.
     *
     * @param  array<string, mixed>  $uploadEvidence
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateUploadEvidence(array $uploadEvidence, string $uploadScenarioKey): array
    {
        $reasons = [];
        $mode = strtolower((string) ($uploadEvidence['upload_mode'] ?? data_get($uploadEvidence, 'request_summary.upload_mode', '')));
        $path = (string) ($uploadEvidence['endpoint_path'] ?? data_get($uploadEvidence, 'request_summary.endpoint_path', ''));
        $imageType = strtoupper((string) ($uploadEvidence['image_type'] ?? data_get($uploadEvidence, 'request_summary.image_type', '')));
        $imageIndex = strtoupper((string) ($uploadEvidence['image_index']
            ?? $uploadEvidence['returned_image_index']
            ?? data_get($uploadEvidence, 'request_summary.image_index')
            ?? data_get($uploadEvidence, 'request_summary.returned_image_index')
            ?? ''));
        $requestedIndex = strtoupper((string) ($uploadEvidence['image_index'] ?? data_get($uploadEvidence, 'request_summary.image_index', '')));
        $returnedIndex = strtoupper((string) ($uploadEvidence['returned_image_index'] ?? data_get($uploadEvidence, 'request_summary.returned_image_index', '')));

        if ($uploadScenarioKey === FedExUs09EtdFixtureService::UPLOAD_SCENARIO_LETTERHEAD) {
            if ($mode !== 'image') {
                $reasons[] = 'us09_letterhead_upload_mode_mismatch';
            }
            if ($path !== '/documents/v1/lhsimages/upload') {
                $reasons[] = 'us09_letterhead_upload_endpoint_mismatch';
            }
            if ($imageType !== 'LETTERHEAD') {
                $reasons[] = 'us09_letterhead_image_type_mismatch';
            }
            if ($requestedIndex !== 'IMAGE_1' && $returnedIndex !== 'IMAGE_1' && $imageIndex !== 'IMAGE_1') {
                $reasons[] = 'us09_letterhead_image_index_mismatch';
            }
            $returnedId = $uploadEvidence['returned_image_index']
                ?? $uploadEvidence['returned_image_id']
                ?? data_get($uploadEvidence, 'request_summary.returned_image_index')
                ?? data_get($uploadEvidence, 'request_summary.returned_image_id');
            if (! filled($returnedId)) {
                $reasons[] = 'us09_letterhead_upload_id_missing';
            }
        } elseif ($uploadScenarioKey === FedExUs09EtdFixtureService::UPLOAD_SCENARIO_SIGNATURE) {
            if ($mode !== 'image') {
                $reasons[] = 'us09_signature_upload_mode_mismatch';
            }
            if ($path !== '/documents/v1/lhsimages/upload') {
                $reasons[] = 'us09_signature_upload_endpoint_mismatch';
            }
            if ($imageType !== 'SIGNATURE') {
                $reasons[] = 'us09_signature_image_type_mismatch';
            }
            if ($requestedIndex !== 'IMAGE_2' && $returnedIndex !== 'IMAGE_2' && $imageIndex !== 'IMAGE_2') {
                $reasons[] = 'us09_signature_image_index_mismatch';
            }
            $returnedId = $uploadEvidence['returned_image_index']
                ?? $uploadEvidence['returned_image_id']
                ?? data_get($uploadEvidence, 'request_summary.returned_image_index')
                ?? data_get($uploadEvidence, 'request_summary.returned_image_id');
            if (! filled($returnedId)) {
                $reasons[] = 'us09_signature_upload_id_missing';
            }
        } elseif ($uploadScenarioKey === FedExUs09EtdFixtureService::UPLOAD_SCENARIO_DOCUMENT) {
            if ($mode !== 'document') {
                $reasons[] = 'us09_document_upload_mode_mismatch';
            }
            if ($path !== '/documents/v1/etds/upload') {
                $reasons[] = 'us09_document_upload_endpoint_mismatch';
            }
            $docType = strtoupper((string) ($uploadEvidence['document_type'] ?? data_get($uploadEvidence, 'request_summary.document_type', '')));
            if ($docType !== 'COMMERCIAL_INVOICE') {
                $reasons[] = 'us09_document_type_mismatch';
            }
            $returnedId = $uploadEvidence['returned_document_id']
                ?? data_get($uploadEvidence, 'request_summary.returned_document_id');
            $idPresent = (bool) ($uploadEvidence['returned_document_id_present']
                ?? data_get($uploadEvidence, 'request_summary.returned_document_id_present', false));
            $looksRedacted = is_string($returnedId)
                && (str_contains($returnedId, 'REDACTED') || $returnedId === '[REDACTED]');
            if ((! filled($returnedId) && ! $idPresent) || $returnedId === '{{US09_DOCUMENT_ID}}') {
                $reasons[] = 'us09_document_upload_id_missing';
            }
            if (is_string($returnedId) && $returnedId !== '' && ! $looksRedacted && strlen($returnedId) > 12) {
                $reasons[] = 'us09_document_id_not_redacted';
            }
        } else {
            $reasons[] = 'us09_unknown_upload_scenario';
        }

        $encoded = json_encode($uploadEvidence) ?: '';
        if (str_contains($encoded, 'OMITTED_BINARY') === false
            && preg_match('/"bytes"\s*:\s*"[A-Za-z0-9+\/=]{80,}"/', $encoded)) {
            $reasons[] = 'us09_raw_file_content_exported';
        }
        if (preg_match('/Bearer\s+[A-Za-z0-9\-._~+\/=]{8,}/', $encoded)) {
            $reasons[] = 'us09_bearer_token_exported';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return list<string>
     */
    private function collectShipReasons(array $request, string $testCaseKey, bool $redactedAccounts): array
    {
        $reasons = [];
        $isImage = $testCaseKey === 'IntegratorUS09_IMAGE';
        $isDocument = $testCaseKey === 'IntegratorUS09_DOCUMENT';

        if (! $isImage && ! $isDocument) {
            return ['us09_unknown_case_key'];
        }

        $shipment = data_get($request, 'requestedShipment');
        if ($shipment === '[REDACTED]') {
            $reasons[] = 'us09_requested_shipment_wholly_redacted';

            return $reasons;
        }
        if (! is_array($shipment) || $shipment === []) {
            $reasons[] = 'us09_requested_shipment_missing';

            return $reasons;
        }

        if (strtoupper((string) data_get($shipment, 'serviceType', '')) !== 'FEDEX_INTERNATIONAL_PRIORITY') {
            $reasons[] = 'us09_service_type_mismatch';
        }
        if ((string) data_get($shipment, 'packagingType') !== 'YOUR_PACKAGING') {
            $reasons[] = 'us09_packaging_type_mismatch';
        }
        if ((string) data_get($shipment, 'pickupType') !== 'USE_SCHEDULED_PICKUP') {
            $reasons[] = 'us09_pickup_type_mismatch';
        }
        if ((float) data_get($shipment, 'totalWeight') !== 10.0) {
            $reasons[] = 'us09_total_weight_mismatch';
        }

        $payment = data_get($shipment, 'shippingChargesPayment');
        if ($payment === '[REDACTED]') {
            $reasons[] = 'us09_shipping_charges_payment_wholly_redacted';
        } elseif (! is_array($payment) || strtoupper((string) data_get($payment, 'paymentType', '')) !== 'SENDER') {
            $reasons[] = 'us09_payment_type_mismatch';
        }

        $specialTypes = array_map('strtoupper', (array) data_get($shipment, 'shipmentSpecialServices.specialServiceTypes', []));
        if (! in_array('ELECTRONIC_TRADE_DOCUMENTS', $specialTypes, true)) {
            $reasons[] = 'us09_electronic_trade_documents_missing';
        }

        $etdDetail = data_get($shipment, 'shipmentSpecialServices.etdDetail');
        if ($etdDetail === '[REDACTED]') {
            $reasons[] = 'us09_etd_detail_wholly_redacted';
        } elseif (! is_array($etdDetail) || $etdDetail === []) {
            $reasons[] = 'us09_etd_detail_missing';
        } elseif ($isImage) {
            $requestedTypes = array_map('strtoupper', (array) data_get($etdDetail, 'requestedDocumentTypes', []));
            if (! in_array('COMMERCIAL_INVOICE', $requestedTypes, true)) {
                $reasons[] = 'us09_image_requested_document_types_missing';
            }
            if (array_key_exists('attachedDocuments', $etdDetail)) {
                $reasons[] = 'us09_image_unexpected_attached_documents';
            }
        } elseif ($isDocument) {
            $attached = data_get($etdDetail, 'attachedDocuments.0');
            if (! is_array($attached) || strtoupper((string) data_get($attached, 'documentType', '')) !== 'COMMERCIAL_INVOICE') {
                $reasons[] = 'us09_etd_attached_document_type_mismatch';
            } else {
                $documentId = data_get($attached, 'documentId');
                if (! filled($documentId) || $documentId === '{{US09_DOCUMENT_ID}}') {
                    $reasons[] = 'us09_etd_document_id_missing';
                } elseif ($redactedAccounts && $documentId !== '[REDACTED]') {
                    $reasons[] = 'us09_etd_document_id_unredacted';
                }
                if ((string) data_get($attached, 'description') !== 'CommercialInvoice') {
                    $reasons[] = 'us09_etd_document_description_mismatch';
                }
            }
            if (array_key_exists('requestedDocumentTypes', $etdDetail)) {
                $reasons[] = 'us09_document_unexpected_requested_document_types';
            }
        }

        if ($isImage) {
            $usages = (array) data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages', []);
            $letterhead = collect($usages)->first(static fn (mixed $row): bool => strtoupper((string) data_get($row, 'id', '')) === 'IMAGE_1');
            $signature = collect($usages)->first(static fn (mixed $row): bool => strtoupper((string) data_get($row, 'id', '')) === 'IMAGE_2');

            if (
                ! is_array($letterhead)
                || strtoupper((string) data_get($letterhead, 'type', '')) !== 'LETTER_HEAD'
                || strtoupper((string) data_get($letterhead, 'providedImageType', '')) !== 'LETTER_HEAD'
            ) {
                $reasons[] = 'us09_image_letterhead_reference_missing';
            }
            if (
                ! is_array($signature)
                || strtoupper((string) data_get($signature, 'type', '')) !== 'SIGNATURE'
                || strtoupper((string) data_get($signature, 'providedImageType', '')) !== 'SIGNATURE'
            ) {
                $reasons[] = 'us09_image_signature_reference_missing';
            }
        }

        if ($isDocument) {
            $usages = data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages');
            if (is_array($usages) && $usages !== []) {
                $reasons[] = 'us09_document_case_unexpected_customer_images';
            }
        }

        $customs = data_get($shipment, 'customsClearanceDetail');
        if ($customs === '[REDACTED]') {
            $reasons[] = 'us09_customs_wholly_redacted';
        } elseif (! is_array($customs) || $customs === []) {
            $reasons[] = 'us09_customs_missing';
        } else {
            if (strtoupper((string) data_get($customs, 'dutiesPayment.paymentType', '')) !== 'SENDER') {
                $reasons[] = 'us09_duties_payment_type_mismatch';
            }
            if ((string) data_get($customs, 'commodities.0.description') !== 'Computer Keyboard') {
                $reasons[] = 'us09_commodity_description_mismatch';
            }
            if ((float) data_get($customs, 'totalCustomsValue.amount') !== 25.0) {
                $reasons[] = 'us09_customs_value_mismatch';
            }
            if ((string) data_get($customs, 'commercialInvoice.termsOfSale') !== 'DDP') {
                $reasons[] = 'us09_terms_of_sale_mismatch';
            }
            if ((string) data_get($customs, 'commercialInvoice.originatorName') !== 'originatorName') {
                $reasons[] = 'us09_originator_name_mismatch';
            }
            if ((string) data_get($customs, 'dutiesPayment.payor.responsibleParty.address.postalCode') !== '10011-4624') {
                $reasons[] = 'us09_duties_payor_address_mismatch';
            }
        }

        $label = data_get($shipment, 'labelSpecification');
        if (
            strtoupper((string) data_get($label, 'imageType', '')) !== 'PDF'
            || (string) data_get($label, 'labelStockType') !== 'PAPER_85X11_TOP_HALF_LABEL'
            || (string) data_get($label, 'labelFormatType') !== 'COMMON2D'
        ) {
            $reasons[] = 'us09_label_format_mismatch';
        }

        if ((float) data_get($shipment, 'requestedPackageLineItems.0.weight.value') !== 10.0) {
            $reasons[] = 'us09_package_weight_mismatch';
        }
        if ((string) data_get($shipment, 'requestedPackageLineItems.0.customerReferences.0.value') !== 'ref1234') {
            $reasons[] = 'us09_package_reference_mismatch';
        }

        $expectedRecipient = $isImage ? 'IntegratorUS10' : 'Integrator';
        if ((string) data_get($shipment, 'recipients.0.contact.personName') !== $expectedRecipient) {
            $reasons[] = 'us09_recipient_person_mismatch';
        }

        $expectedOrigin = $isImage ? 'Integrator' : 'IntegratorUS13';
        if ((string) data_get($shipment, 'origin.contact.companyName') !== $expectedOrigin) {
            $reasons[] = 'us09_origin_company_mismatch';
        }

        $rootAccount = data_get($request, 'accountNumber');
        if ($rootAccount === '[REDACTED]') {
            $reasons[] = 'us09_root_account_wholly_redacted';
        } elseif ($redactedAccounts) {
            if (! is_array($rootAccount) || data_get($rootAccount, 'value') !== '[REDACTED]') {
                $reasons[] = 'us09_root_account_missing_or_not_redacted';
            }
        } elseif (! is_array($rootAccount) || ! filled(data_get($rootAccount, 'value'))) {
            $reasons[] = 'us09_root_account_missing';
        }

        return array_values(array_unique($reasons));
    }
}
