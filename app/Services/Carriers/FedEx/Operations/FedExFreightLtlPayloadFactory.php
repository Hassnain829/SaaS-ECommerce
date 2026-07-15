<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;

/**
 * Builds FedEx Freight LTL Ship requests (POST /ship/v1/freight/shipments).
 *
 * Not used for parcel Ship API cases (US01–US07).
 */
class FedExFreightLtlPayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildShipmentPayload(
        CarrierAccount $account,
        array $fixture,
        ?string $labelFormat = null,
        array $overrides = [],
    ): array {
        $fixture = array_replace_recursive($fixture, $overrides);
        $freightAccount = (string) ($fixture['freight_account_number']
            ?? $fixture['account_number']
            ?? '');
        $labelFormat = strtoupper(trim((string) ($labelFormat ?? $fixture['label_format'] ?? 'ZPLII')));

        $freightRequestedShipment = array_filter([
            'shipDatestamp' => (string) ($overrides['ship_date'] ?? now()->toDateString()),
            'pickupType' => (string) ($fixture['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
            'serviceType' => (string) ($fixture['service_type'] ?? ''),
            'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
            'totalWeight' => isset($fixture['total_weight']) ? (int) $fixture['total_weight'] : null,
            'totalPackageCount' => isset($fixture['total_package_count']) ? (int) $fixture['total_package_count'] : null,
            'preferredCurrency' => filled($fixture['preferred_currency'] ?? null)
                ? (string) $fixture['preferred_currency']
                : null,
            // Freight LTL REST contract uses rateRequestType (array). Workbook columns are rateRequestTypes.
            'rateRequestType' => isset($fixture['rate_request_types']) && is_array($fixture['rate_request_types'])
                ? array_values(array_map(static fn (mixed $t): string => strtoupper((string) $t), $fixture['rate_request_types']))
                : null,
            'shipper' => $this->party($fixture['shipper'] ?? []),
            'recipient' => $this->party($fixture['recipient'] ?? []),
            'shippingChargesPayment' => $this->buildShippingChargesPayment($fixture, $freightAccount),
            'freightShipmentDetail' => $this->buildFreightShipmentDetail($fixture, $freightAccount),
            'freightShipmentSpecialServices' => $this->buildSpecialServices($fixture),
            'labelSpecification' => $this->buildLabelSpecification($fixture, $labelFormat),
            'shippingDocumentSpecification' => $this->buildShippingDocumentSpecification($fixture),
            'requestedPackageLineItems' => $this->buildPackageLineItems($fixture['packages'] ?? []),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return [
            'labelResponseOptions' => (string) ($fixture['label_response_options'] ?? 'LABEL'),
            // Freight LTL Ship contract includes root accountNumber (Freight account, not parcel Integrator account).
            'accountNumber' => ['value' => $freightAccount],
            'freightRequestedShipment' => $freightRequestedShipment,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function buildFreightShipmentDetail(array $fixture, string $freightAccount): array
    {
        $declared = is_array($fixture['declared_value_per_unit'] ?? null)
            ? $fixture['declared_value_per_unit']
            : null;

        $detail = array_filter([
            'role' => (string) ($fixture['freight_role'] ?? 'SHIPPER'),
            'collectTermsType' => filled($fixture['collect_terms_type'] ?? null)
                ? (string) $fixture['collect_terms_type']
                : null,
            'totalHandlingUnits' => isset($fixture['total_handling_units'])
                ? (int) $fixture['total_handling_units']
                : null,
            'clientDiscountPercent' => array_key_exists('client_discount_percent', $fixture)
                ? (float) $fixture['client_discount_percent']
                : null,
            'declaredValueUnits' => filled($fixture['declared_value_units'] ?? null)
                ? (string) $fixture['declared_value_units']
                : null,
            'declaredValuePerUnit' => is_array($declared) ? array_filter([
                'currency' => filled($declared['currency'] ?? null) ? (string) $declared['currency'] : null,
                'amount' => array_key_exists('amount', $declared) ? (float) $declared['amount'] : null,
            ], static fn (mixed $value): bool => $value !== null) : null,
            'fedExFreightAccountNumber' => ['value' => $freightAccount],
            'fedExFreightBillingContactAndAddress' => $this->billingContactAndAddress(
                $fixture['freight_billing_contact_and_address'] ?? []
            ),
            'lineItem' => $this->buildLineItems($fixture['freight_line_items'] ?? []),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $detail;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function buildLineItems(array $items): array
    {
        $lineItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = array_filter([
                'id' => filled($item['id'] ?? null) ? (string) $item['id'] : null,
                'freightClass' => (string) ($item['freight_class'] ?? ''),
                'pieces' => isset($item['pieces']) ? (int) $item['pieces'] : null,
                'subPackagingType' => filled($item['sub_packaging_type'] ?? null)
                    ? (string) $item['sub_packaging_type']
                    : null,
                'handlingUnits' => isset($item['handling_units']) ? (int) $item['handling_units'] : null,
                // Omit nmfcCode when blank — never map purchaseOrderNumber into nmfcCode.
                'nmfcCode' => filled($item['nmfc_code'] ?? null) ? (string) $item['nmfc_code'] : null,
                'purchaseOrderNumber' => filled($item['purchase_order_number'] ?? null)
                    ? (string) $item['purchase_order_number']
                    : null,
                'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
                'weight' => isset($item['weight']) && is_array($item['weight']) ? [
                    'units' => strtoupper((string) ($item['weight']['units'] ?? 'LB')),
                    'value' => (float) ($item['weight']['value'] ?? 0),
                ] : null,
                'dimensions' => $this->dimensions($item['dimensions'] ?? null),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($line !== []) {
                $lineItems[] = $line;
            }
        }

        return $lineItems;
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function buildPackageLineItems(array $packages): array
    {
        $items = [];

        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                continue;
            }

            $item = array_filter([
                'sequenceNumber' => (int) ($package['sequence_number'] ?? ($index + 1)),
                'groupPackageCount' => isset($package['group_package_count'])
                    ? (int) $package['group_package_count']
                    : null,
                'weight' => [
                    'units' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                    'value' => (float) ($package['weight'] ?? 0),
                ],
                'dimensions' => $this->dimensions([
                    'length' => $package['length'] ?? null,
                    'width' => $package['width'] ?? null,
                    'height' => $package['height'] ?? null,
                    'units' => $package['dimension_unit'] ?? null,
                ]),
                'subPackagingType' => filled($package['sub_packaging_type'] ?? null)
                    ? (string) $package['sub_packaging_type']
                    : null,
                'associatedFreightLineItems' => filled($package['associated_freight_line_item_id'] ?? null)
                    ? [['id' => (string) $package['associated_freight_line_item_id']]]
                    : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function buildShippingChargesPayment(array $fixture, string $freightAccount): array
    {
        $paymentType = strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER'));
        $payment = ['paymentType' => $paymentType];

        if (in_array($paymentType, ['RECIPIENT', 'THIRD_PARTY'], true)) {
            $payorAccount = (string) ($fixture['transportation_payment_account'] ?? $freightAccount);
            $payorParty = is_array($fixture['transportation_payor'] ?? null) ? $fixture['transportation_payor'] : [];
            $responsible = array_filter([
                'accountNumber' => ['value' => $payorAccount],
                'contact' => $this->contact($payorParty),
                'address' => $this->address($payorParty),
            ]);
            $payment['payor'] = ['responsibleParty' => $responsible];
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    private function buildSpecialServices(array $fixture): ?array
    {
        $types = array_values(array_filter(array_map(
            static fn (mixed $type): string => strtoupper((string) $type),
            (array) ($fixture['special_service_types'] ?? []),
        )));

        if ($types === []) {
            return null;
        }

        return ['specialServiceTypes' => $types];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function buildLabelSpecification(array $fixture, string $labelFormat): array
    {
        return array_filter([
            'labelFormatType' => (string) ($fixture['label_format_type'] ?? 'COMMON2D'),
            'imageType' => $labelFormat,
            'labelStockType' => (string) ($fixture['label_stock_type'] ?? 'STOCK_4X6'),
            'labelPrintingOrientation' => filled($fixture['label_printing_orientation'] ?? null)
                ? (string) $fixture['label_printing_orientation']
                : null,
            'labelOrder' => filled($fixture['label_order'] ?? null)
                ? (string) $fixture['label_order']
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    private function buildShippingDocumentSpecification(array $fixture): ?array
    {
        $spec = $fixture['shipping_document_specification'] ?? null;
        if (! is_array($spec) || $spec === []) {
            return null;
        }

        $payload = array_filter([
            'shippingDocumentTypes' => array_values(array_filter((array) ($spec['shipping_document_types'] ?? []))),
            'commercialInvoiceDetail' => $this->commercialInvoiceDetail($spec['commercial_invoice_detail'] ?? null),
            'freightBillOfLadingDetail' => $this->freightBillOfLadingDetail($spec['freight_bill_of_lading_detail'] ?? null),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string, mixed>|null  $detail
     * @return array<string, mixed>|null
     */
    private function commercialInvoiceDetail(?array $detail): ?array
    {
        if (! is_array($detail)) {
            return null;
        }

        $format = $this->documentFormat($detail['document_format'] ?? null);
        $payload = array_filter([
            'provideInstructions' => array_key_exists('provide_instructions', $detail)
                ? (bool) $detail['provide_instructions']
                : null,
            'documentFormat' => $format,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string, mixed>|null  $detail
     * @return array<string, mixed>|null
     */
    private function freightBillOfLadingDetail(?array $detail): ?array
    {
        if (! is_array($detail)) {
            return null;
        }

        $format = $this->documentFormat($detail['document_format'] ?? null);
        if ($format === null) {
            return null;
        }

        if (array_key_exists('provide_instructions', $detail)) {
            $format['provideInstructions'] = (bool) $detail['provide_instructions'];
        }

        if (filled($detail['disposition_type'] ?? null)) {
            $format['dispositions'] = [[
                'dispositionType' => strtoupper((string) $detail['disposition_type']),
            ]];
        }

        return ['format' => $format];
    }

    /**
     * @param  array<string, mixed>|null  $format
     * @return array<string, mixed>|null
     */
    private function documentFormat(?array $format): ?array
    {
        if (! is_array($format)) {
            return null;
        }

        $documentFormat = array_filter([
            'stockType' => $format['stock_type'] ?? null,
            'docType' => $format['doc_type'] ?? null,
            'locale' => $format['locale'] ?? null,
        ], static fn (mixed $value): bool => filled($value));

        return $documentFormat !== [] ? $documentFormat : null;
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function party(array $party): array
    {
        return array_filter([
            'contact' => $this->contact($party),
            'address' => $this->address($party),
        ]);
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function billingContactAndAddress(array $party): array
    {
        return array_filter([
            'contact' => $this->contact($party),
            'address' => $this->address($party),
        ]);
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>|null
     */
    private function contact(array $party): ?array
    {
        $contact = array_filter([
            'personName' => filled($party['person_name'] ?? null) ? (string) $party['person_name'] : null,
            'companyName' => filled($party['company_name'] ?? null) ? (string) $party['company_name'] : null,
            'phoneNumber' => filled($party['phone'] ?? null) ? (string) $party['phone'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $contact !== [] ? $contact : null;
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>|null
     */
    private function address(array $party): ?array
    {
        if ($party === []) {
            return null;
        }

        $address = array_filter([
            'streetLines' => array_values(array_filter($party['street_lines'] ?? [])),
            'city' => $party['city'] ?? null,
            'stateOrProvinceCode' => $party['state'] ?? null,
            'postalCode' => $party['postal_code'] ?? null,
            'countryCode' => strtoupper((string) ($party['country_code'] ?? 'US')),
            'residential' => array_key_exists('residential', $party) ? (bool) $party['residential'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $address !== [] ? $address : null;
    }

    /**
     * @param  array<string, mixed>|null  $dimensions
     * @return array<string, mixed>|null
     */
    private function dimensions(?array $dimensions): ?array
    {
        if (! is_array($dimensions)) {
            return null;
        }

        if (
            ! filled($dimensions['length'] ?? null)
            || ! filled($dimensions['width'] ?? null)
            || ! filled($dimensions['height'] ?? null)
        ) {
            return null;
        }

        return [
            'length' => (float) $dimensions['length'],
            'width' => (float) $dimensions['width'],
            'height' => (float) $dimensions['height'],
            'units' => strtoupper((string) ($dimensions['units'] ?? 'IN')),
        ];
    }
}
