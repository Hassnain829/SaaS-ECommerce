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
        $labelFormat = strtoupper(trim((string) ($labelFormat ?? $fixture['label_format'] ?? 'PDF')));
        $labelStockType = (string) ($fixture['label_stock_type'] ?? 'PAPER_4X6');

        $requestedShipment = [
            'shipDatestamp' => $shipDate,
            'pickupType' => (string) ($fixture['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
            'serviceType' => (string) ($fixture['service_type'] ?? 'FEDEX_GROUND'),
            'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
            'shipper' => $this->party($fixture['shipper'] ?? []),
            'recipients' => [$this->party($fixture['recipient'] ?? [], true)],
            'shippingChargesPayment' => $this->shippingChargesPayment($fixture, $accountNumber),
            'requestedPackageLineItems' => $this->packageLineItems($fixture['packages'] ?? []),
        ];

        if (isset($fixture['total_package_count'])) {
            $requestedShipment['totalPackageCount'] = (int) $fixture['total_package_count'];
        }

        if (! empty($fixture['special_services'])) {
            $servicePayload = [];
            $serviceTypes = [];

            foreach ($fixture['special_services'] as $service) {
                if (! is_array($service)) {
                    continue;
                }

                foreach ((array) ($service['specialServiceTypes'] ?? []) as $type) {
                    $serviceTypes[] = $type;
                }

                $detail = $service;
                unset($detail['specialServiceTypes']);
                $servicePayload = array_replace_recursive($servicePayload, $detail);
            }

            if ($serviceTypes !== []) {
                $servicePayload['specialServiceTypes'] = array_values(array_unique($serviceTypes));
            }

            if ($servicePayload !== []) {
                $requestedShipment['shipmentSpecialServices'] = $servicePayload;
            }
        }

        if (filled($fixture['email_notification'] ?? null)) {
            $requestedShipment['emailNotificationDetail'] = [
                'recipients' => [[
                    'emailAddress' => (string) $fixture['email_notification'],
                    'notificationEventType' => ['ON_SHIPMENT'],
                    'notificationFormatType' => 'HTML',
                ]],
            ];
        }

        if (isset($fixture['declared_value']) && is_array($fixture['declared_value'])) {
            $requestedShipment['totalDeclaredValue'] = [
                'amount' => (float) ($fixture['declared_value']['amount'] ?? 0),
                'currency' => (string) ($fixture['declared_value']['currency'] ?? 'USD'),
            ];
        }

        if (filled($labelFormat)) {
            $requestedShipment['labelSpecification'] = $this->labelSpecification($labelFormat, $labelStockType);
        }

        return [
            'labelResponseOptions' => filled($labelFormat) ? 'LABEL' : 'URL_ONLY',
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $accountNumber],
        ];
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
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function shippingChargesPayment(array $fixture, string $accountNumber): array
    {
        $paymentType = strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER'));
        $payment = ['paymentType' => $paymentType];

        if ($paymentType === 'RECIPIENT') {
            $payment['payor'] = [
                'responsibleParty' => [
                    'accountNumber' => [
                        'value' => (string) ($fixture['transportation_payment_account'] ?? $accountNumber),
                    ],
                ],
            ];
        }

        return $payment;
    }

    private function labelSpecification(string $format, string $stockType): array
    {
        $format = strtoupper(trim($format));

        return match ($format) {
            'PNG' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PNG',
                'labelStockType' => $stockType,
            ],
            'ZPL', 'ZPLII' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'ZPLII',
                'labelStockType' => $stockType,
            ],
            default => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PDF',
                'labelStockType' => $stockType,
            ],
        };
    }
}
