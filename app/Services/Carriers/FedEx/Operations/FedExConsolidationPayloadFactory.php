<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;

/**
 * Builds FedEx Consolidation API V1 request bodies (not Parcel Ship / Open Ship / Freight LTL).
 */
class FedExConsolidationPayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function buildCreateConsolidation(array $fixture): array
    {
        $account = (string) ($fixture['account_number'] ?? '');
        $thirdPartyAccount = (string) ($fixture['third_party_account_number'] ?? FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT);
        $shipper = is_array($fixture['shipper'] ?? null) ? $fixture['shipper'] : [];
        $origin = is_array($fixture['origin'] ?? null) ? $fixture['origin'] : [];
        $soldTo = is_array($fixture['sold_to'] ?? null) ? $fixture['sold_to'] : [];
        $ior = is_array($fixture['importer_of_record'] ?? null) ? $fixture['importer_of_record'] : [];
        $ice = is_array($fixture['international_controlled_export_detail'] ?? null)
            ? $fixture['international_controlled_export_detail']
            : [];
        $dataSource = is_array($fixture['consolidation_data_source'] ?? null)
            ? $fixture['consolidation_data_source']
            : [];
        $docSpec = is_array($fixture['consolidation_document_specification'] ?? null)
            ? $fixture['consolidation_document_specification']
            : [];
        $label = is_array($fixture['label_specification'] ?? null) ? $fixture['label_specification'] : [];
        $customs = is_array($fixture['customs_clearance'] ?? null) ? $fixture['customs_clearance'] : [];
        $distribution = is_array($fixture['international_distribution_detail'] ?? null)
            ? $fixture['international_distribution_detail']
            : [];

        $requestedConsolidation = array_filter([
            'consolidationType' => (string) ($fixture['consolidation_type'] ?? FedExConsolidationFixtureService::CONSOLIDATION_TYPE),
            'shipper' => $this->party($shipper, includeTins: true),
            'origin' => $this->party($origin),
            'soldTo' => $this->party($soldTo, includeAccount: true),
            'specialServicesRequested' => [
                'specialServiceTypes' => array_values((array) ($fixture['special_service_types'] ?? [])),
                'internationalControlledExportDetail' => [
                    'licenseOrPermitExpirationDate' => (string) ($ice['license_or_permit_expiration_date'] ?? ''),
                    'licenseOrPermitNumber' => (string) ($ice['license_or_permit_number'] ?? ''),
                    'type' => (string) ($ice['type'] ?? ''),
                ],
            ],
            'consolidationDataSources' => [[
                'consolidationDataType' => (string) ($dataSource['consolidation_data_type'] ?? ''),
                'consolidationDataSourceType' => (string) ($dataSource['consolidation_data_source_type'] ?? ''),
            ]],
            'consolidationDocumentSpecification' => [
                'consolidationDocumentTypes' => array_values((array) ($docSpec['consolidation_document_types'] ?? [])),
                'consolidatedCommercialInvoiceDetail' => [
                    'documentFormat' => [
                        'stockType' => (string) data_get($docSpec, 'consolidated_commercial_invoice_detail.document_format.stock_type'),
                        'docType' => (string) data_get($docSpec, 'consolidated_commercial_invoice_detail.document_format.doc_type'),
                    ],
                ],
            ],
            'labelSpecification' => $this->labelSpecification($label),
            'customsClearanceDetail' => [
                'customsValue' => $this->money($customs['customs_value'] ?? null),
                'dutiesPayment' => $this->thirdPartyPayment(
                    (string) ($customs['duties_payment_type'] ?? 'THIRD_PARTY'),
                    $thirdPartyAccount,
                    (string) ($customs['duties_payor_country_code'] ?? 'US'),
                    includeBillingDetails: true,
                ),
                'documentContent' => (string) ($customs['document_content'] ?? ''),
                'recipientCustomsId' => [
                    'type' => (string) data_get($customs, 'recipient_customs_id.type'),
                    'value' => (string) data_get($customs, 'recipient_customs_id.value'),
                ],
                'importerOfRecord' => $this->party($ior),
            ],
            'internationalDistributionDetail' => [
                'declarationCurrencies' => array_map(static function (array $row): array {
                    return [
                        'currency' => (string) ($row['currency'] ?? ''),
                        'value' => (string) ($row['value'] ?? ''),
                    ];
                }, (array) ($distribution['declaration_currencies'] ?? [])),
                'totalDimensions' => [
                    'length' => (int) data_get($distribution, 'total_dimensions.length'),
                    'width' => (int) data_get($distribution, 'total_dimensions.width'),
                    'height' => (int) data_get($distribution, 'total_dimensions.height'),
                    'units' => (string) data_get($distribution, 'total_dimensions.units'),
                ],
                'clearanceFacilityLocationId' => (string) ($distribution['clearance_facility_location_id'] ?? ''),
                'dropOffType' => (string) ($distribution['drop_off_type'] ?? ''),
                'totalInsuredValue' => $this->money($distribution['total_insured_value'] ?? null),
                'unitSystem' => (string) ($distribution['unit_system'] ?? ''),
            ],
            'shippingChargesPayment' => $this->thirdPartyPayment(
                (string) ($fixture['transportation_payment_type'] ?? 'THIRD_PARTY'),
                $thirdPartyAccount,
                (string) ($fixture['transportation_payor_country_code'] ?? 'US'),
            ),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return [
            'accountNumber' => ['value' => $account],
            'requestedConsolidation' => $requestedConsolidation,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function buildAddShipment(array $fixture, ?string $shipTimestamp = null): array
    {
        $account = (string) ($fixture['account_number'] ?? '');
        $thirdPartyAccount = (string) ($fixture['third_party_account_number'] ?? FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT);
        $key = is_array($fixture['consolidation_key'] ?? null) ? $fixture['consolidation_key'] : [];
        $ice = is_array($fixture['international_controlled_export_detail'] ?? null)
            ? $fixture['international_controlled_export_detail']
            : [];
        $customs = is_array($fixture['customs_clearance'] ?? null) ? $fixture['customs_clearance'] : [];
        $label = is_array($fixture['label_specification'] ?? null) ? $fixture['label_specification'] : [];
        $printedOrigin = is_array($fixture['printed_label_origin'] ?? null) ? $fixture['printed_label_origin'] : [];

        $requestedShipment = array_filter([
            'shipTimestamp' => $shipTimestamp ?? $this->defaultShipTimestamp(),
            'serviceType' => (string) ($fixture['service_type'] ?? FedExConsolidationFixtureService::CONSOLIDATION_TYPE),
            'pickupType' => (string) ($fixture['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
            'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
            'dropoffType' => filled($fixture['dropoff_type'] ?? null) ? (string) $fixture['dropoff_type'] : null,
            'shipper' => $this->party($fixture['shipper'] ?? []),
            'origin' => $this->party($fixture['origin'] ?? []),
            'recipients' => [$this->party($fixture['recipient'] ?? [])],
            'printedLabelOrigin' => $this->party($printedOrigin, includeEmail: true, includePhoneExtension: true),
            'shippingChargesPayment' => $this->thirdPartyPayment(
                (string) ($fixture['transportation_payment_type'] ?? 'THIRD_PARTY'),
                $thirdPartyAccount,
                (string) ($fixture['transportation_payor_country_code'] ?? 'US'),
            ),
            'specialServicesRequested' => [
                'specialServiceTypes' => array_values((array) ($fixture['special_service_types'] ?? [])),
                'internationalControlledExportDetail' => [
                    'licenseOrPermitExpirationDate' => (string) ($ice['license_or_permit_expiration_date'] ?? ''),
                    'licenseOrPermitNumber' => (string) ($ice['license_or_permit_number'] ?? ''),
                    'type' => (string) ($ice['type'] ?? ''),
                ],
            ],
            'labelSpecification' => $this->labelSpecification($label),
            'customsClearanceDetail' => [
                'totalCustomsValue' => $this->money($customs['total_customs_value'] ?? null),
                'isDocumentOnly' => (bool) ($customs['is_document_only'] ?? false),
                'dutiesPayment' => $this->thirdPartyPayment(
                    (string) ($customs['duties_payment_type'] ?? 'THIRD_PARTY'),
                    $thirdPartyAccount,
                    (string) ($customs['duties_payor_country_code'] ?? 'US'),
                    includeBillingDetails: true,
                ),
                'commodities' => $this->commodities($fixture['commodities'] ?? []),
            ],
            'requestedPackageLineItems' => $this->packages($fixture['packages'] ?? []),
            'customerReferences' => array_map(static function (array $ref): array {
                return [
                    'customerReferenceType' => (string) ($ref['customer_reference_type'] ?? ''),
                    'value' => (string) ($ref['value'] ?? ''),
                ];
            }, (array) ($fixture['customer_references'] ?? [])),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return array_filter([
            'accountNumber' => ['value' => $account],
            'consolidationKey' => [
                'type' => (string) ($key['type'] ?? FedExConsolidationFixtureService::CONSOLIDATION_TYPE),
                'index' => (string) ($key['index'] ?? ''),
                'date' => (string) ($key['date'] ?? ''),
            ],
            'labelResponseOptions' => (string) ($fixture['label_response_options'] ?? 'LABEL'),
            'openShipmentAction' => (string) ($fixture['open_shipment_action'] ?? 'CONFIRM'),
            'processingOptions' => array_values((array) ($fixture['processing_options'] ?? [])),
            'requestedShipment' => $requestedShipment,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function buildConfirmConsolidation(array $fixture): array
    {
        $account = (string) ($fixture['account_number'] ?? '');
        $key = is_array($fixture['consolidation_key'] ?? null) ? $fixture['consolidation_key'] : [];
        $label = is_array($fixture['label_specification'] ?? null) ? $fixture['label_specification'] : [];

        return [
            'accountNumber' => ['value' => $account],
            'consolidationKey' => [
                'type' => (string) ($key['type'] ?? FedExConsolidationFixtureService::CONSOLIDATION_TYPE),
                'index' => (string) ($key['index'] ?? ''),
                'date' => (string) ($key['date'] ?? ''),
            ],
            'processingOptionType' => (string) ($fixture['processing_option_type'] ?? 'ALLOW_ASYNCHRONOUS'),
            'edtRequestType' => (string) ($fixture['edt_request_type'] ?? 'ALL'),
            'labelSpecification' => $this->labelSpecification($label),
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function buildConfirmResults(array $fixture): array
    {
        return [
            'accountNumber' => ['value' => (string) ($fixture['account_number'] ?? '')],
            'jobId' => (string) ($fixture['job_id'] ?? ''),
        ];
    }

    public function defaultShipTimestamp(): string
    {
        // Preserve workbook timezone intent (US Central) while using a current final-run stamp.
        return now('America/Chicago')->format('Y-m-d\TH:i:s');
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function party(
        array $party,
        bool $includeTins = false,
        bool $includeAccount = false,
        bool $includeEmail = false,
        bool $includePhoneExtension = false,
    ): array {
        $contact = array_filter([
            'personName' => filled($party['person_name'] ?? null) ? (string) $party['person_name'] : null,
            'phoneNumber' => filled($party['phone'] ?? null) ? (string) $party['phone'] : null,
            'phoneExtension' => $includePhoneExtension && filled($party['phone_extension'] ?? null)
                ? (string) $party['phone_extension']
                : null,
            'companyName' => filled($party['company_name'] ?? null) ? (string) $party['company_name'] : null,
            'emailAddress' => $includeEmail && filled($party['email'] ?? null) ? (string) $party['email'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $address = array_filter([
            'streetLines' => array_values((array) ($party['street_lines'] ?? [])),
            'city' => $party['city'] ?? null,
            'stateOrProvinceCode' => $party['state'] ?? null,
            'postalCode' => $party['postal_code'] ?? null,
            'countryCode' => isset($party['country_code']) ? strtoupper((string) $party['country_code']) : null,
            'residential' => array_key_exists('residential', $party) ? (bool) $party['residential'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $payload = array_filter([
            'contact' => $contact !== [] ? $contact : null,
            'address' => $address !== [] ? $address : null,
        ]);

        if ($includeAccount && filled($party['account_number'] ?? null)) {
            $payload['accountNumber'] = ['value' => (string) $party['account_number']];
        }

        if ($includeTins && isset($party['tins']) && is_array($party['tins'])) {
            $payload['tins'] = array_map(static function (array $tin): array {
                return [
                    'number' => (string) ($tin['number'] ?? ''),
                    'tinType' => (string) ($tin['tin_type'] ?? ''),
                ];
            }, $party['tins']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $label
     * @return array<string, mixed>
     */
    private function labelSpecification(array $label): array
    {
        return array_filter([
            'labelFormatType' => (string) ($label['label_format_type'] ?? 'COMMON2D'),
            'labelStockType' => (string) ($label['label_stock_type'] ?? 'PAPER_4X6'),
            'imageType' => (string) ($label['image_type'] ?? 'PNG'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function thirdPartyPayment(
        string $paymentType,
        string $account,
        string $countryCode,
        bool $includeBillingDetails = false,
    ): array {
        $responsible = [
            'accountNumber' => ['value' => $account],
            'address' => [
                'countryCode' => strtoupper($countryCode),
            ],
        ];

        if ($includeBillingDetails) {
            $responsible['accountNumber'] = ['value' => $account];
        }

        $payment = [
            'paymentType' => $paymentType,
            'payor' => [
                'responsibleParty' => $responsible,
            ],
        ];

        if ($includeBillingDetails) {
            $payment['payor']['responsibleParty']['accountNumber'] = ['value' => $account];
            // Workbook billingDetails.accountNumber on duties payor.
            $payment['billingDetails'] = [
                'accountNumber' => ['value' => $account],
            ];
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>|null  $money
     * @return array<string, mixed>|null
     */
    private function money(?array $money): ?array
    {
        if (! is_array($money)) {
            return null;
        }

        return [
            'amount' => $money['amount'] ?? null,
            'currency' => (string) ($money['currency'] ?? 'USD'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function packages(array $packages): array
    {
        $items = [];

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $item = array_filter([
                'groupPackageCount' => isset($package['group_package_count'])
                    ? (int) $package['group_package_count']
                    : null,
                'declaredValue' => $this->money($package['declared_value'] ?? null),
                'weight' => isset($package['weight']) && is_array($package['weight']) ? [
                    'units' => (string) ($package['weight']['units'] ?? 'LB'),
                    'value' => (float) ($package['weight']['value'] ?? 0),
                ] : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);

            // Workbook physicalPackaging text "null" → omit entirely (never send string "null").
            if (! ($package['omit_physical_packaging'] ?? false) && filled($package['physical_packaging'] ?? null)) {
                $physical = (string) $package['physical_packaging'];
                if (strtolower($physical) !== 'null') {
                    $item['physicalPackaging'] = $physical;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $commodities
     * @return list<array<string, mixed>>
     */
    private function commodities(array $commodities): array
    {
        $items = [];

        foreach ($commodities as $commodity) {
            if (! is_array($commodity)) {
                continue;
            }

            $item = array_filter([
                'unitPrice' => $this->money($commodity['unit_price'] ?? null),
                'numberOfPieces' => isset($commodity['number_of_pieces']) ? (int) $commodity['number_of_pieces'] : null,
                'quantity' => isset($commodity['quantity']) ? (int) $commodity['quantity'] : null,
                'customsValue' => $this->money($commodity['customs_value'] ?? null),
                'countryOfManufacture' => filled($commodity['country_of_manufacture'] ?? null)
                    ? (string) $commodity['country_of_manufacture']
                    : null,
                'harmonizedCode' => filled($commodity['harmonized_code'] ?? null)
                    ? (string) $commodity['harmonized_code']
                    : null,
                'description' => filled($commodity['description'] ?? null)
                    ? (string) $commodity['description']
                    : null,
                'weight' => isset($commodity['weight']) && is_array($commodity['weight']) ? [
                    'units' => (string) ($commodity['weight']['units'] ?? 'LB'),
                    'value' => (float) ($commodity['weight']['value'] ?? 0),
                ] : null,
                'quantityUnits' => filled($commodity['quantity_units'] ?? null)
                    ? (string) $commodity['quantity_units']
                    : null,
                'commodityId' => filled($commodity['commodity_id'] ?? null)
                    ? (string) $commodity['commodity_id']
                    : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $items[] = $item;
        }

        return $items;
    }
}
