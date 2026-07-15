<?php

namespace App\Services\Carriers\FedEx\Validation;

/**
 * IntegratorUS09 ETD Image + Document workbook fixtures.
 */
class FedExUs09EtdFixtureService
{
    public const FIXTURE_VERSION = '2026-06-30-workbook-v1';

    public const BASELINE_SHEET = 'Americas_US_Test cases';

    public const UPLOAD_SCENARIO_LETTERHEAD = 'upload_us09_image_letterhead';

    public const UPLOAD_SCENARIO_SIGNATURE = 'upload_us09_image_signature';

    public const UPLOAD_SCENARIO_DOCUMENT = 'upload_us09_document';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'IntegratorUS09_IMAGE' => $this->integratorUs09Image(),
            'IntegratorUS09_DOCUMENT' => $this->integratorUs09Document(),
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
        abort_unless(isset($fixtures[$testCaseKey]), 404, 'Unknown FedEx US09 ETD test case.');

        return $fixtures[$testCaseKey];
    }

    public function lockedLabelFormat(string $testCaseKey): string
    {
        return (string) (FedExValidationScenarioCatalog::lockedLabelFormat($testCaseKey)
            ?? abort(422, 'Unknown locked US09 ship test case.'));
    }

    /**
     * Absolute path to the workbook Commercial Invoice PDF used for IntegratorUS09_DOCUMENT ETD upload.
     */
    public function documentCommercialInvoiceAbsolutePath(): ?string
    {
        $fixture = $this->fixture('IntegratorUS09_DOCUMENT');
        $relativePath = str_replace('\\', '/', ltrim((string) data_get($fixture, 'upload.relative_path', ''), '/'));
        if ($relativePath === '' || ! str_starts_with($relativePath, 'resources/fedex-validation/us09/')) {
            return null;
        }

        $absolute = base_path($relativePath);
        if (is_file($absolute) && filesize($absolute) > 0) {
            return $absolute;
        }

        // Tolerate a common workbook drop that left a trailing underscore in the filename.
        $alt = dirname($absolute).DIRECTORY_SEPARATOR.'commercial_invoice_.pdf';
        if (is_file($alt) && filesize($alt) > 0) {
            return $alt;
        }

        return null;
    }

    /**
     * Attach a Trade Documents Upload documentId to the Document ship fixture.
     *
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function withUploadedDocumentId(array $fixture, string $documentId): array
    {
        $fixture['shipment_special_services']['etdDetail']['attachedDocuments'][0]['documentId'] = $documentId;

        return $fixture;
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs09Image(): array
    {
        return array_merge($this->sharedShipmentBase(
            recipientPerson: 'IntegratorUS10',
            originCompany: 'Integrator',
            etdMode: 'image',
        ), [
            'key' => 'IntegratorUS09_IMAGE',
            'label' => 'ETD Process Shipment using Image',
            'scenario_key' => 'ship_us09_image_pdf',
            'baseline_case' => 'IntegratorUS09',
            'etd_mode' => 'image',
            'upload_scenario_keys' => [
                self::UPLOAD_SCENARIO_LETTERHEAD,
                self::UPLOAD_SCENARIO_SIGNATURE,
            ],
            'upload' => [
                'mode' => 'image',
                'relative_path' => 'resources/fedex-validation/us09/signature3.png',
                'filename' => 'signature3.png',
                'content_type' => 'image/png',
                'image_type' => 'LETTERHEAD',
                'image_index' => 'IMAGE_1',
                'workflow_name' => 'LetterheadSignature',
                'reference_id' => 'IntegratorUS09_IMAGE_LETTERHEAD',
                'upload_scenario_key' => self::UPLOAD_SCENARIO_LETTERHEAD,
                'secondary' => [
                    'relative_path' => 'resources/fedex-validation/us09/signature2.png',
                    'filename' => 'signature2.png',
                    'content_type' => 'image/png',
                    'image_type' => 'SIGNATURE',
                    'image_index' => 'IMAGE_2',
                    'workflow_name' => 'LetterheadSignature',
                    'reference_id' => 'IntegratorUS09_IMAGE_SIGNATURE',
                    'upload_scenario_key' => self::UPLOAD_SCENARIO_SIGNATURE,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function integratorUs09Document(): array
    {
        return array_merge($this->sharedShipmentBase(
            recipientPerson: 'Integrator',
            originCompany: 'IntegratorUS13',
            etdMode: 'document',
        ), [
            'key' => 'IntegratorUS09_DOCUMENT',
            'label' => 'ETD Process Shipment using Document',
            'scenario_key' => 'ship_us09_document_pdf',
            'baseline_case' => 'IntegratorUS09',
            'etd_mode' => 'document',
            'upload_scenario_keys' => [self::UPLOAD_SCENARIO_DOCUMENT],
            'upload' => [
                'mode' => 'document',
                'relative_path' => 'resources/fedex-validation/us09/commercial_invoice.pdf',
                'filename' => 'commercial_invoice.pdf',
                'content_type' => 'application/pdf',
                'workflow_name' => 'ETDPreshipment',
                'ship_document_type' => 'COMMERCIAL_INVOICE',
                'origin_country_code' => 'US',
                'destination_country_code' => 'IT',
                'carrier_code' => 'FDXE',
                'upload_scenario_key' => self::UPLOAD_SCENARIO_DOCUMENT,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedShipmentBase(
        string $recipientPerson,
        string $originCompany,
        string $etdMode,
    ): array {
        $etdDetail = $etdMode === 'image'
            ? [
                'requestedDocumentTypes' => ['COMMERCIAL_INVOICE'],
            ]
            : [
                'attachedDocuments' => [[
                    'documentType' => 'COMMERCIAL_INVOICE',
                    'documentId' => '{{US09_DOCUMENT_ID}}',
                    'description' => 'CommercialInvoice',
                ]],
            ];

        $shippingDocumentSpecification = [
            'shippingDocumentTypes' => ['COMMERCIAL_INVOICE'],
            'commercialInvoiceDetail' => [
                'documentFormat' => [
                    'docType' => 'PDF',
                    'stockType' => 'PAPER_LETTER',
                ],
            ],
        ];

        if ($etdMode === 'image') {
            $shippingDocumentSpecification['commercialInvoiceDetail']['customerImageUsages'] = [
                [
                    'id' => 'IMAGE_1',
                    'type' => 'LETTER_HEAD',
                    'providedImageType' => 'LETTER_HEAD',
                ],
                [
                    'id' => 'IMAGE_2',
                    'type' => 'SIGNATURE',
                    'providedImageType' => 'SIGNATURE',
                ],
            ];
        }

        return [
            'fixture_version' => self::FIXTURE_VERSION,
            'baseline_sheet' => self::BASELINE_SHEET,
            'expected_service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'expected_label_format' => 'PDF',
            'expected_package_count' => 1,
            'api_family' => 'parcel_etd',
            'service_type' => 'FEDEX_INTERNATIONAL_PRIORITY',
            'packaging_type' => 'YOUR_PACKAGING',
            'pickup_type' => 'USE_SCHEDULED_PICKUP',
            'label_format' => 'PDF',
            'label_stock_type' => 'PAPER_85X11_TOP_HALF_LABEL',
            'label_format_type' => 'COMMON2D',
            'transportation_payment_type' => 'SENDER',
            'total_weight' => 10,
            'total_package_count' => 1,
            'preferred_currency' => 'USD',
            'rate_request_types' => ['LIST'],
            'omit_recipient_residential' => true,
            'shipper' => [
                'person_name' => 'PERSONAL_STATE',
                'company_name' => 'Integrator',
                'phone' => '9015551234',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'recipient' => [
                'person_name' => $recipientPerson,
                'company_name' => 'ABC Widget Co',
                'phone' => '9561324887',
                'street_lines' => ['80 Fedex Prkwy'],
                'city' => 'London',
                'state' => 'GB',
                'postal_code' => 'W1T1JY',
                'country_code' => 'GB',
            ],
            'origin' => [
                'person_name' => 'PERSONAL_STATE',
                'company_name' => $originCompany,
                'phone' => '9015551234',
                'street_lines' => ['1751 THOMPSON ST'],
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
            ],
            'shipment_special_services' => [
                'specialServiceTypes' => ['ELECTRONIC_TRADE_DOCUMENTS'],
                'etdDetail' => $etdDetail,
            ],
            'shipping_document_specification' => $shippingDocumentSpecification,
            'customs_clearance' => [
                'is_document_only' => false,
                'total_customs_value' => ['amount' => 25, 'currency' => 'USD'],
                'duties_payment_type' => 'SENDER',
                'duties_payment_account' => null,
                'duties_payor' => [
                    'street_lines' => ['15 W 18TH ST FL 7'],
                    'city' => 'NEW YORK',
                    'state' => 'NY',
                    'postal_code' => '10011-4624',
                    'country_code' => 'US',
                    'residential' => false,
                ],
                'commercial_invoice' => [
                    'terms_of_sale' => 'DDP',
                    'originator_name' => 'originatorName',
                ],
                'commodities' => [[
                    'description' => 'Computer Keyboard',
                    'country_of_manufacture' => 'US',
                    'weight' => ['units' => 'LB', 'value' => 10],
                    'quantity' => 1,
                    'quantity_units' => 'pcs',
                    'unit_price' => ['amount' => 25, 'currency' => 'USD'],
                    'customs_value' => ['amount' => 25, 'currency' => 'USD'],
                ]],
            ],
            'packages' => [[
                'sequence_number' => 1,
                'weight' => 10.0,
                'weight_unit' => 'LB',
                'length' => 5.0,
                'width' => 5.0,
                'height' => 5.0,
                'dimension_unit' => 'IN',
                'customer_references' => [[
                    'customer_reference_type' => 'CUSTOMER_REFERENCE',
                    'value' => 'ref1234',
                ]],
            ]],
        ];
    }
}
