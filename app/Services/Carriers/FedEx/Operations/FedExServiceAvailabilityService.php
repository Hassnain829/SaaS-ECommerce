<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Presenters\FedExMerchantCheckPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExServiceAvailabilityService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly FedExOperationGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $destinationInput
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function checkAvailability(
        Store $store,
        CarrierAccount $account,
        Location $originLocation,
        array $destinationInput,
        ?string $shipDate = null,
        ?string $packagingType = null,
        bool $enforceProductionGuard = false,
    ): array {
        if ($enforceProductionGuard) {
            $this->guard->assertAccountForOperation(
                $store,
                $account,
                FedExOperationGuard::CAPABILITY_SERVICE_AVAILABILITY,
            );
        } else {
            $this->apiClient->assertFedExApiAccount($account, $store);
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
                    'endpoint' => $this->config->serviceAvailabilityPath(),
                    'local_validation' => true,
                    'origin_status' => $readiness->status,
                ],
            );

            return ['result' => $result, 'presentation' => FedExMerchantCheckPresenter::serviceAvailability(null)];
        }

        $origin = $readiness->normalizedAddress;
        $destinationCountry = strtoupper(trim((string) ($destinationInput['country_code'] ?? 'US')));
        $originCountry = strtoupper((string) ($origin['country_code'] ?? 'US'));
        $destinationPostal = trim((string) ($destinationInput['postal_code'] ?? ''));
        $destinationState = strtoupper(trim((string) ($destinationInput['state'] ?? ''))) ?: null;
        $destinationCity = trim((string) ($destinationInput['city'] ?? '')) ?: null;
        $endpoint = $this->config->serviceAvailabilityPath();
        $shipDatestamp = $enforceProductionGuard
            ? $this->guard->assertShipDate($shipDate)
            : ($shipDate ?: now()->toDateString());
        $packagingType = $packagingType ?: 'YOUR_PACKAGING';

        if ($enforceProductionGuard) {
            $this->guard->assertOriginDestinationAllowed(
                $originCountry,
                $destinationCountry,
                $account->environment,
            );
        }

        $customerTransactionId = $this->guard->idempotencyKey(
            $store,
            $account,
            'service_availability',
            implode(':', [
                $originCountry,
                (string) ($origin['postal_code'] ?? ''),
                $destinationCountry,
                $destinationPostal,
                $shipDatestamp,
                $packagingType,
            ]),
        );

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'operation' => $enforceProductionGuard ? 'merchant_service_availability' : 'diagnostic_service_availability',
                'origin_country' => $origin['country_code'] ?? null,
                'origin_state' => $origin['state'] ?? null,
                'origin_postal_code' => $origin['postal_code'] ?? null,
                'destination_country' => $destinationCountry,
                'destination_state' => $destinationState,
                'destination_city' => $destinationCity,
                'destination_postal_code' => $destinationPostal ?: null,
                'ship_date' => $shipDatestamp,
                'packaging_type' => $packagingType,
                'origin_location_id' => $originLocation->id,
                'customer_transaction_id' => $customerTransactionId,
                'platform_fallback_used' => false,
            ],
        );

        $payload = [
            'requestedShipment' => [
                'shipper' => [
                    'address' => array_filter([
                        'postalCode' => $origin['postal_code'] ?? null,
                        'countryCode' => $origin['country_code'] ?? null,
                        'city' => $origin['city'] ?? null,
                        'stateOrProvinceCode' => $origin['state'] ?? null,
                    ]),
                ],
                'recipients' => [
                    [
                        'address' => array_filter([
                            'postalCode' => $destinationPostal ?: null,
                            'countryCode' => $destinationCountry,
                            'stateOrProvinceCode' => $destinationState,
                            'city' => $destinationCity,
                        ]),
                    ],
                ],
                'shipDatestamp' => $shipDatestamp,
                'packagingType' => $packagingType,
            ],
            'carrierCodes' => ['FDXE', 'FDXG'],
        ];

        $result = $this->apiClient->postJson(
            store: $store,
            account: $account,
            action: CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
            path: $endpoint,
            payload: $payload,
            requestSummary: $requestSummary,
            context: $enforceProductionGuard
                ? null
                : new FedExValidationEventContext(scenarioKey: 'service_availability'),
            customerTransactionId: $customerTransactionId,
        );

        $presentation = FedExMerchantCheckPresenter::serviceAvailability($result->data);

        if ($result->success) {
            $responseSummary = array_merge($result->responseSummary ?? [], [
                'service_count' => $presentation['service_count'],
                'package_type_count' => $presentation['package_type_count'],
            ]);

            $result = $result->copyWith(responseSummary: $responseSummary);
        } elseif ($result->errorMessage) {
            $result = CarrierApiResult::failure(
                message: FedExSafeExceptionMapper::merchantMessage(
                    $result->errorCode,
                    $result->errorMessage,
                    (int) data_get($result->responseSummary, 'http_status') ?: null,
                ),
                code: $result->errorCode,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $result->responseSummary,
                evidence: $result->evidence,
            );
        }

        return ['result' => $result, 'presentation' => $presentation];
    }
}
