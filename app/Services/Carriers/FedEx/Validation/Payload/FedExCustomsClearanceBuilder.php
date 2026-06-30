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

        return [
            'countryCode' => strtoupper((string) $payor['country_code']),
        ];
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
