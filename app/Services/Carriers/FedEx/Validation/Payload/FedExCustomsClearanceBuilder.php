<?php

namespace App\Services\Carriers\FedEx\Validation\Payload;

/**
 * Builds customsClearanceDetail from normalized regional ship fixtures.
 */
final class FedExCustomsClearanceBuilder
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    public function build(array $fixture, string $defaultAccountNumber): ?array
    {
        $customs = $fixture['customs_clearance'] ?? null;
        if (! is_array($customs) || $customs === []) {
            return null;
        }

        $payload = [];

        if (array_key_exists('is_document_only', $customs)) {
            $payload['isDocumentOnly'] = (bool) $customs['is_document_only'];
        }

        if ($total = $this->money($customs['total_customs_value'] ?? null)) {
            $payload['totalCustomsValue'] = $total;
        }

        if ($dutiesPayment = $this->buildDutiesPayment($customs, $defaultAccountNumber)) {
            $payload['dutiesPayment'] = $dutiesPayment;
        }

        if ($commercialInvoice = $this->buildCommercialInvoice($customs['commercial_invoice'] ?? null)) {
            $payload['commercialInvoice'] = $commercialInvoice;
        }

        if ($customsOption = $this->buildCustomsOption($customs['customs_option'] ?? null)) {
            $payload['customsOption'] = $customsOption;
        }

        if ($commodities = $this->buildCommodities($customs['commodities'] ?? [])) {
            $payload['commodities'] = $commodities;
        }

        if ($exportDetail = $customs['export_detail'] ?? null) {
            if (is_array($exportDetail) && $exportDetail !== []) {
                $payload['exportDetail'] = $exportDetail;
            }
        }

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string, mixed>|null  $invoice
     * @return array<string, mixed>|null
     */
    private function buildCommercialInvoice(?array $invoice): ?array
    {
        if (! is_array($invoice) || $invoice === []) {
            return null;
        }

        $comments = array_values(array_filter(
            array_map(
                static fn (mixed $comment): string => trim((string) $comment),
                is_array($invoice['comments'] ?? null)
                    ? $invoice['comments']
                    : (filled($invoice['comments'] ?? null) ? [(string) $invoice['comments']] : []),
            ),
            static fn (string $comment): bool => $comment !== '',
        ));

        $payload = array_filter([
            'comments' => $comments !== [] ? $comments : null,
            'specialInstructions' => $invoice['special_instructions'] ?? null,
            'taxesOrMiscellaneousChargeType' => $invoice['taxes_or_miscellaneous_charge_type'] ?? null,
            'shipmentPurpose' => $invoice['shipment_purpose'] ?? null,
            'termsOfSale' => $invoice['terms_of_sale'] ?? null,
            'declarationStatement' => $invoice['declaration_statement'] ?? null,
            'originatorName' => $invoice['originator_name'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($insurance = $this->money($invoice['insurance_charge'] ?? null)) {
            $payload['insuranceCharge'] = $insurance;
        }

        if ($taxes = $this->money($invoice['taxes_or_miscellaneous_charge'] ?? null)) {
            $payload['taxesOrMiscellaneousCharge'] = $taxes;
        }

        if ($freight = $this->money($invoice['freight_charge'] ?? null)) {
            $payload['freightCharge'] = $freight;
        }

        $references = [];
        foreach ((array) ($invoice['customer_references'] ?? []) as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $type = $reference['customerReferenceType'] ?? $reference['customer_reference_type'] ?? null;
            $value = $reference['value'] ?? null;
            if (filled($type) && filled($value)) {
                $references[] = [
                    'customerReferenceType' => (string) $type,
                    'value' => (string) $value,
                ];
            }
        }

        if ($references !== []) {
            $payload['customerReferences'] = $references;
        }

        return $payload !== [] ? $payload : null;
    }

    /**
     * @param  array<string, mixed>|null  $option
     * @return array<string, mixed>|null
     */
    private function buildCustomsOption(?array $option): ?array
    {
        if (! is_array($option) || $option === []) {
            return null;
        }

        $type = $option['type'] ?? null;
        if (! filled($type)) {
            return null;
        }

        return ['type' => (string) $type];
    }

    /**
     * @param  array<string, mixed>  $customs
     * @return array<string, mixed>|null
     */
    private function buildDutiesPayment(array $customs, string $defaultAccountNumber): ?array
    {
        $paymentType = strtoupper((string) ($customs['duties_payment_type'] ?? ''));
        if ($paymentType === '') {
            return null;
        }

        $payment = ['paymentType' => $paymentType];

        if (in_array($paymentType, ['SENDER', 'THIRD_PARTY', 'RECIPIENT'], true)) {
            $accountNumber = (string) ($customs['duties_payment_account'] ?? $defaultAccountNumber);
            $payor = [
                'responsibleParty' => [
                    'accountNumber' => ['value' => $accountNumber],
                ],
            ];

            if ($contact = $this->payorContact($customs['duties_payor'] ?? null)) {
                $payor['responsibleParty']['contact'] = $contact;
            }

            if ($address = $this->payorAddress($customs['duties_payor'] ?? null)) {
                $payor['responsibleParty']['address'] = $address;
            }

            $payment['payor'] = $payor;
        }

        return $payment;
    }

    /**
     * @param  list<array<string, mixed>>  $commodities
     * @return list<array<string, mixed>>
     */
    private function buildCommodities(array $commodities): array
    {
        $items = [];

        foreach ($commodities as $commodity) {
            if (! is_array($commodity)) {
                continue;
            }

            $item = array_filter([
                'numberOfPieces' => isset($commodity['number_of_pieces']) ? (int) $commodity['number_of_pieces'] : null,
                'description' => $commodity['description'] ?? null,
                'countryOfManufacture' => $commodity['country_of_manufacture'] ?? null,
                'harmonizedCode' => $commodity['harmonized_code'] ?? null,
                'quantity' => isset($commodity['quantity']) ? (int) $commodity['quantity'] : null,
                'quantityUnits' => $commodity['quantity_units'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($weight = $commodity['weight'] ?? null) {
                if (is_array($weight)) {
                    $item['weight'] = [
                        'units' => strtoupper((string) ($weight['units'] ?? 'LB')),
                        'value' => max(0.01, (float) ($weight['value'] ?? 1)),
                    ];
                }
            }

            if ($unitPrice = $this->money($commodity['unit_price'] ?? null)) {
                $item['unitPrice'] = $unitPrice;
            }

            if ($customsValue = $this->money($commodity['customs_value'] ?? null)) {
                $item['customsValue'] = $customsValue;
            }

            if ($item !== []) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $payor
     * @return array<string, mixed>|null
     */
    private function payorContact(?array $payor): ?array
    {
        if (! is_array($payor)) {
            return null;
        }

        $contact = array_filter([
            'personName' => $payor['person_name'] ?? null,
            'companyName' => $payor['company_name'] ?? null,
        ]);

        return $contact !== [] ? $contact : null;
    }

    /**
     * @param  array<string, mixed>|null  $payor
     * @return array<string, mixed>|null
     */
    private function payorAddress(?array $payor): ?array
    {
        if (! is_array($payor) || ! filled($payor['country_code'] ?? null)) {
            return null;
        }

        $address = array_filter([
            'streetLines' => array_values(array_filter($payor['street_lines'] ?? [])),
            'city' => $payor['city'] ?? null,
            'stateOrProvinceCode' => $payor['state'] ?? null,
            'postalCode' => $payor['postal_code'] ?? null,
            'countryCode' => strtoupper((string) $payor['country_code']),
            'residential' => array_key_exists('residential', $payor) ? (bool) $payor['residential'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return $address !== [] ? $address : null;
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
            'amount' => (float) ($money['amount'] ?? 0),
            'currency' => strtoupper((string) ($money['currency'] ?? 'USD')),
        ];
    }
}
