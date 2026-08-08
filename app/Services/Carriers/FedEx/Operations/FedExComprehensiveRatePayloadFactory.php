<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\DTO\FedExShipmentRateRequest;
use App\Services\Carriers\FedEx\Support\FedExHandoffTypeResolver;

final class FedExComprehensiveRatePayloadFactory
{
    public function __construct(
        private readonly FedExHandoffTypeResolver $handoffTypeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public function build(CarrierAccount $account, array $fixture, string $shipDateStamp): array
    {
        $shipper = is_array($fixture['shipper'] ?? null) ? $fixture['shipper'] : [];
        $recipient = is_array($fixture['recipient'] ?? null) ? $fixture['recipient'] : [];
        $package = is_array($fixture['package'] ?? null) ? $fixture['package'] : [];

        $request = FedExShipmentRateRequest::fromOriginDestinationPackage(
            origin: [
                'postal_code' => $shipper['postal_code'] ?? null,
                'country_code' => $shipper['country_code'] ?? 'US',
                'city' => $shipper['city'] ?? null,
                'state' => $shipper['state'] ?? null,
            ],
            destination: [
                'postal_code' => $recipient['postal_code'] ?? null,
                'country_code' => $recipient['country_code'] ?? 'US',
                'city' => $recipient['city'] ?? null,
                'state' => $recipient['state'] ?? null,
                'residential' => $recipient['residential'] ?? null,
            ],
            package: [
                'weight' => $package['weight'] ?? 10,
                'weight_unit' => $package['weight_unit'] ?? 'LB',
                'length' => $package['length'] ?? null,
                'width' => $package['width'] ?? null,
                'height' => $package['height'] ?? null,
                'dimension_unit' => $package['dimension_unit'] ?? 'IN',
            ],
            shipDate: $shipDateStamp,
            serviceType: isset($fixture['expected_service_type']) ? (string) $fixture['expected_service_type'] : null,
            packagingType: (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
        );

        $request = $request->withPickupAndRateOptions(
            pickupType: $this->handoffTypeResolver->resolve(
                null,
                isset($fixture['pickup_type']) ? (string) $fixture['pickup_type'] : null,
            ),
            rateRequestTypes: array_values((array) ($fixture['rate_request_types'] ?? ['ACCOUNT', 'LIST'])),
            returnTransitTimes: (bool) ($fixture['return_transit_times'] ?? true),
            carrierCodes: filled($fixture['carrier_codes'] ?? null) ? array_values((array) $fixture['carrier_codes']) : null,
            rateDisplayOption: filled($fixture['rate_display_option'] ?? null) ? (string) $fixture['rate_display_option'] : null,
        );

        return $this->buildFromRequest($account, $request);
    }

    /**
     * Production / merchant negotiated-rate payload (Comprehensive Rates API).
     *
     * @return array<string, mixed>
     */
    public function buildFromRequest(CarrierAccount $account, FedExShipmentRateRequest $request): array
    {
        $packageLineItems = [];
        foreach ($request->packages as $index => $package) {
            $weight = max(0.01, (float) ($package['weight'] ?? 1));
            $item = [
                'sequenceNumber' => $index + 1,
                'weight' => [
                    'units' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                    'value' => (string) $weight,
                ],
            ];

            if (isset($package['length'], $package['width'], $package['height'])
                && $package['length'] !== null
                && $package['width'] !== null
                && $package['height'] !== null
            ) {
                $item['dimensions'] = [
                    'length' => (int) max(1, round((float) $package['length'])),
                    'width' => (int) max(1, round((float) $package['width'])),
                    'height' => (int) max(1, round((float) $package['height'])),
                    'units' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
                ];
            }

            $packageLineItems[] = $item;
        }

        if ($packageLineItems === []) {
            $packageLineItems[] = [
                'sequenceNumber' => 1,
                'weight' => [
                    'units' => 'LB',
                    'value' => '1',
                ],
            ];
        }

        $requestedShipment = array_filter([
            'shipper' => [
                'address' => array_filter([
                    'postalCode' => (string) ($request->shipper['postal_code'] ?? ''),
                    'countryCode' => strtoupper((string) ($request->shipper['country_code'] ?? 'US')),
                    'city' => $request->shipper['city'] ?? null,
                    'stateOrProvinceCode' => $request->shipper['state'] ?? null,
                ]),
            ],
            'recipient' => [
                'address' => array_filter([
                    'streetLines' => array_values(array_filter([
                        trim((string) ($request->recipient['address_line1'] ?? '')),
                        trim((string) ($request->recipient['address_line2'] ?? '')),
                    ])) ?: null,
                    'postalCode' => (string) ($request->recipient['postal_code'] ?? ''),
                    'countryCode' => strtoupper((string) ($request->recipient['country_code'] ?? 'US')),
                    'city' => $request->recipient['city'] ?? null,
                    'stateOrProvinceCode' => $request->recipient['state'] ?? null,
                    'residential' => array_key_exists('residential', $request->recipient)
                        ? $request->recipient['residential']
                        : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
            ],
            'pickupType' => $request->pickupType,
            'packagingType' => $request->packagingType,
            'serviceType' => $request->serviceType,
            'rateRequestType' => array_values($request->rateRequestTypes ?: ['ACCOUNT', 'LIST']),
            'shipDateStamp' => $request->shipDate,
            'requestedPackageLineItems' => $packageLineItems,
            'totalPackageCount' => count($packageLineItems),
            'preferredCurrency' => $request->preferredCurrency,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($request->commodities !== []) {
            $requestedShipment['customsClearanceDetail'] = [
                'commodities' => $request->commodities,
            ];
        }

        $payload = [
            'accountNumber' => [
                'value' => (string) ($account->fedExAccountNumber() ?? ''),
            ],
            'rateRequestControlParameters' => [
                'returnTransitTimes' => $request->returnTransitTimes,
                'servicesNeededOnRateFailure' => true,
            ],
            'requestedShipment' => $requestedShipment,
        ];

        if (filled($request->rateDisplayOption)) {
            $payload['rateDisplayOption'] = (string) $request->rateDisplayOption;
        }

        if ($request->carrierCodes !== null && $request->carrierCodes !== []) {
            $payload['carrierCodes'] = array_values($request->carrierCodes);
        }

        return $payload;
    }
}
