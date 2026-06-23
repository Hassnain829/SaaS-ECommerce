<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Presenters\FedExMerchantCheckPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExRateQuoteService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly CarrierOriginReadinessService $originReadiness,
    ) {}

    /**
     * @param  array<string, mixed>  $destinationInput
     * @param  array<string, mixed>  $packageInput
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function quoteRate(
        Store $store,
        CarrierAccount $account,
        Location $originLocation,
        array $destinationInput,
        array $packageInput,
        ?string $shipDate = null,
        ?string $serviceType = null,
        ?bool $residential = null,
        ?string $packagingType = null,
    ): array {
        $this->apiClient->assertMerchantCredentialsAccount($account);

        if (! filled($account->provider_account_number)) {
            $result = CarrierApiResult::failure(
                message: 'FedEx account number is required before requesting a test quote.',
                code: 'missing_account_number',
                requestSummary: ['local_validation' => true],
            );

            return ['result' => $result, 'presentation' => FedExMerchantCheckPresenter::rateQuote(null)];
        }

        $readiness = $this->originReadiness->assessForFulfillmentOrigin(
            $originLocation,
            CarrierOriginReadinessService::CARRIER_GENERIC,
        );

        if (! $readiness->ready) {
            $result = CarrierApiResult::failure(
                message: $readiness->merchantMessage,
                code: 'origin_not_ready',
                requestSummary: [
                    'endpoint' => $this->config->rateQuotePath(),
                    'local_validation' => true,
                    'origin_status' => $readiness->status,
                ],
            );

            return ['result' => $result, 'presentation' => FedExMerchantCheckPresenter::rateQuote(null)];
        }

        $origin = $readiness->normalizedAddress;
        $destinationCountry = strtoupper(trim((string) ($destinationInput['country_code'] ?? 'US')));
        $destinationPostal = trim((string) ($destinationInput['postal_code'] ?? ''));
        $destinationState = strtoupper(trim((string) ($destinationInput['state'] ?? ''))) ?: null;
        $destinationCity = trim((string) ($destinationInput['city'] ?? '')) ?: null;
        $weight = max(0.01, (float) ($packageInput['weight'] ?? 1));
        $length = max(0.01, (float) ($packageInput['length'] ?? 9));
        $width = max(0.01, (float) ($packageInput['width'] ?? 6));
        $height = max(0.01, (float) ($packageInput['height'] ?? 2));
        $weightUnit = strtoupper((string) ($packageInput['weight_unit'] ?? 'LB'));
        $dimensionUnit = strtoupper((string) ($packageInput['dimension_unit'] ?? 'IN'));
        $packagingType = strtoupper(trim((string) ($packagingType ?? $packageInput['packaging_type'] ?? 'YOUR_PACKAGING')));
        $serviceType = filled($serviceType) ? strtoupper(trim($serviceType)) : 'FEDEX_GROUND';
        $endpoint = $this->config->rateQuotePath();
        $shipDatestamp = $shipDate ?: now()->toDateString();

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
                'origin_country' => $origin['country_code'] ?? null,
                'origin_state' => $origin['state'] ?? null,
                'origin_postal_code' => $origin['postal_code'] ?? null,
                'origin_city' => $origin['city'] ?? null,
                'destination_country' => $destinationCountry,
                'destination_state' => $destinationState,
                'destination_postal_code' => $destinationPostal ?: null,
                'destination_city' => $destinationCity,
                'service_type' => $serviceType,
                'packaging_type' => $packagingType,
                'weight' => $weight,
                'weight_unit' => $weightUnit,
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'dimension_unit' => $dimensionUnit,
                'ship_date' => $shipDatestamp,
                'origin_location_id' => $originLocation->id,
                'residential_destination' => $residential,
                'test_quote_only' => true,
            ],
        );

        $requestedShipment = [
            'shipper' => [
                'address' => array_filter([
                    'postalCode' => $origin['postal_code'] ?? null,
                    'countryCode' => $origin['country_code'] ?? null,
                    'city' => $origin['city'] ?? null,
                    'stateOrProvinceCode' => $origin['state'] ?? null,
                ]),
            ],
            'recipient' => [
                'address' => array_filter([
                    'postalCode' => $destinationPostal ?: null,
                    'countryCode' => $destinationCountry,
                    'stateOrProvinceCode' => $destinationState,
                    'city' => $destinationCity,
                    'residential' => $residential,
                ]),
            ],
            'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
            'packagingType' => $packagingType,
            'rateRequestType' => ['ACCOUNT', 'LIST'],
            'shipDateStamp' => $shipDatestamp,
            'serviceType' => $serviceType,
            'requestedPackageLineItems' => [
                [
                    'weight' => [
                        'units' => $weightUnit,
                        'value' => $weight,
                    ],
                    'dimensions' => [
                        'length' => $length,
                        'width' => $width,
                        'height' => $height,
                        'units' => $dimensionUnit,
                    ],
                ],
            ],
        ];

        $payload = [
            'accountNumber' => [
                'value' => (string) $account->provider_account_number,
            ],
            'requestedShipment' => $requestedShipment,
        ];

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
                context: new FedExValidationEventContext(scenarioKey: 'rate_quote'),
            ),
            $endpoint,
        );

        $presentation = FedExMerchantCheckPresenter::rateQuote($result->data);

        if ($result->success) {
            $responseSummary = array_merge($result->responseSummary ?? [], [
                'rate_count' => $presentation['rate_count'],
            ]);

            $result = $result->copyWith(responseSummary: $responseSummary);
        }

        return ['result' => $result, 'presentation' => $presentation];
    }
}
