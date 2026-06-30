<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;

final class FedExComprehensiveRatePayloadFactory
{
    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function build(CarrierAccount $account, array $fixture, string $shipDateStamp): array
    {
        $shipper = is_array($fixture['shipper'] ?? null) ? $fixture['shipper'] : [];
        $recipient = is_array($fixture['recipient'] ?? null) ? $fixture['recipient'] : [];
        $package = is_array($fixture['package'] ?? null) ? $fixture['package'] : [];

        $requestedShipment = array_filter([
            'shipper' => [
                'address' => array_filter([
                    'postalCode' => (string) ($shipper['postal_code'] ?? ''),
                    'countryCode' => strtoupper((string) ($shipper['country_code'] ?? 'US')),
                    'city' => $shipper['city'] ?? null,
                    'stateOrProvinceCode' => $shipper['state'] ?? null,
                ]),
            ],
            'recipient' => [
                'address' => array_filter([
                    'postalCode' => (string) ($recipient['postal_code'] ?? ''),
                    'countryCode' => strtoupper((string) ($recipient['country_code'] ?? 'US')),
                    'city' => $recipient['city'] ?? null,
                    'stateOrProvinceCode' => $recipient['state'] ?? null,
                    'residential' => array_key_exists('residential', $recipient) ? (bool) $recipient['residential'] : null,
                ], static fn (mixed $value): bool => $value !== null),
            ],
            'pickupType' => (string) ($fixture['pickup_type'] ?? 'DROPOFF_AT_FEDEX_LOCATION'),
            'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
            'serviceType' => $fixture['expected_service_type'] ?? null,
            'rateRequestType' => array_values((array) ($fixture['rate_request_types'] ?? ['ACCOUNT', 'LIST'])),
            'shipDateStamp' => $shipDateStamp,
            'requestedPackageLineItems' => [
                array_filter([
                    'weight' => [
                        'units' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                        'value' => (string) ($package['weight'] ?? '10'),
                    ],
                    'dimensions' => isset($package['length'], $package['width'], $package['height'])
                        ? [
                            'length' => (int) round((float) $package['length']),
                            'width' => (int) round((float) $package['width']),
                            'height' => (int) round((float) $package['height']),
                            'units' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
                        ]
                        : null,
                ], static fn (mixed $value): bool => $value !== null),
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $payload = [
            'accountNumber' => [
                'value' => (string) $account->provider_account_number,
            ],
            'rateRequestControlParameters' => [
                'returnTransitTimes' => (bool) ($fixture['return_transit_times'] ?? true),
                'servicesNeededOnRateFailure' => true,
            ],
            'requestedShipment' => $requestedShipment,
        ];

        if (filled($fixture['rate_display_option'] ?? null)) {
            $payload['rateDisplayOption'] = (string) $fixture['rate_display_option'];
        }

        if (filled($fixture['carrier_codes'] ?? null)) {
            $payload['carrierCodes'] = array_values((array) $fixture['carrier_codes']);
        }

        return $payload;
    }
}
