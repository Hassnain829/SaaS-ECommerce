<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;

class FedExShipPayloadFactory
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
        $accountNumber = (string) ($account->provider_account_number ?: ($fixture['account_number'] ?? ''));
        $shipDate = (string) ($overrides['ship_date'] ?? now()->toDateString());

        $payload = [
            'labelResponseOptions' => filled($labelFormat) ? 'LABEL' : 'URL_ONLY',
            'requestedShipment' => [
                'shipDatestamp' => $shipDate,
                'pickupType' => (string) ($fixture['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
                'serviceType' => (string) ($fixture['service_type'] ?? 'FEDEX_GROUND'),
                'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
                'shipper' => $this->party($fixture['shipper'] ?? []),
                'recipients' => [$this->party($fixture['recipient'] ?? [], true)],
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                ],
                'requestedPackageLineItems' => $this->packageLineItems($fixture['packages'] ?? []),
            ],
            'accountNumber' => [
                'value' => $accountNumber,
            ],
        ];

        if (filled($labelFormat)) {
            $payload['requestedShipment']['labelSpecification'] = $this->labelSpecification($labelFormat);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function party(array $party, bool $recipient = false): array
    {
        $contact = array_filter([
            'personName' => $party['person_name'] ?? null,
            'companyName' => $party['company_name'] ?? null,
            'phoneNumber' => $party['phone'] ?? null,
        ]);

        $address = array_filter([
            'streetLines' => array_values(array_filter($party['street_lines'] ?? [])),
            'city' => $party['city'] ?? null,
            'stateOrProvinceCode' => $party['state'] ?? null,
            'postalCode' => $party['postal_code'] ?? null,
            'countryCode' => strtoupper((string) ($party['country_code'] ?? 'US')),
            'residential' => $recipient ? (bool) ($party['residential'] ?? false) : null,
        ], static fn (mixed $value): bool => $value !== null);

        return array_filter([
            'contact' => $contact !== [] ? $contact : null,
            'address' => $address,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function packageLineItems(array $packages): array
    {
        $items = [];

        foreach ($packages as $index => $package) {
            $items[] = [
                'sequenceNumber' => $index + 1,
                'weight' => [
                    'units' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                    'value' => max(0.01, (float) ($package['weight'] ?? 1)),
                ],
                'dimensions' => [
                    'length' => max(0.01, (float) ($package['length'] ?? 9)),
                    'width' => max(0.01, (float) ($package['width'] ?? 6)),
                    'height' => max(0.01, (float) ($package['height'] ?? 2)),
                    'units' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
                ],
            ];
        }

        return $items !== [] ? $items : [[
            'sequenceNumber' => 1,
            'weight' => ['units' => 'LB', 'value' => 1.0],
            'dimensions' => ['length' => 9, 'width' => 6, 'height' => 2, 'units' => 'IN'],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function labelSpecification(string $format): array
    {
        $format = strtoupper(trim($format));

        return match ($format) {
            'PNG' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PNG',
                'labelStockType' => 'PAPER_4X6',
            ],
            'ZPL', 'ZPLII' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'ZPLII',
                'labelStockType' => 'STOCK_4X6',
            ],
            default => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PDF',
                'labelStockType' => 'PAPER_4X6',
            ],
        };
    }
}
