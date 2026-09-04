<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
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
     * @param  array<string, mixed>|null  $originAddressOverride  When set, use this shipper address instead of the Location readiness address
     *                                                            (required for return flows: customer → merchant).
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>, service_types: list<string>}
     */
    public function checkAvailability(
        Store $store,
        CarrierAccount $account,
        Location $originLocation,
        array $destinationInput,
        ?string $shipDate = null,
        ?string $packagingType = null,
        bool $enforceProductionGuard = false,
        ?string $pickupType = null,
        ?array $originAddressOverride = null,
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

        if ($originAddressOverride !== null) {
            $origin = [
                'postal_code' => trim((string) ($originAddressOverride['postal_code'] ?? '')),
                'country_code' => strtoupper(trim((string) ($originAddressOverride['country_code'] ?? 'US'))),
                'city' => trim((string) ($originAddressOverride['city'] ?? '')) ?: null,
                'state' => strtoupper(trim((string) ($originAddressOverride['state'] ?? ''))) ?: null,
            ];

            if ($origin['postal_code'] === '' || $origin['country_code'] === '') {
                $result = CarrierApiResult::failure(
                    message: 'A complete origin address is required to check FedEx service options.',
                    code: 'origin_not_ready',
                    requestSummary: [
                        'endpoint' => $this->config->serviceAvailabilityPath(),
                        'local_validation' => true,
                        'origin_status' => 'override_incomplete',
                    ],
                );

                return [
                    'result' => $result,
                    'presentation' => FedExMerchantCheckPresenter::serviceAvailability(null),
                    'service_types' => [],
                ];
            }
        } else {
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

                return [
                    'result' => $result,
                    'presentation' => FedExMerchantCheckPresenter::serviceAvailability(null),
                    'service_types' => [],
                ];
            }

            $origin = $readiness->normalizedAddress;
        }

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
        $resolvedPickupType = filled($pickupType)
            ? strtoupper(trim((string) $pickupType))
            : null;

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
                $resolvedPickupType ?: 'pickup:default',
                $originAddressOverride !== null ? 'origin:override' : 'origin:location',
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
                'pickup_type' => $resolvedPickupType,
                'origin_location_id' => $originLocation->id,
                'customer_transaction_id' => $customerTransactionId,
                'platform_fallback_used' => false,
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
        ];

        if ($resolvedPickupType !== null && $resolvedPickupType !== '') {
            $requestedShipment['pickupType'] = $resolvedPickupType;
        }

        $payload = [
            'requestedShipment' => $requestedShipment,
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
                : new FedExApiEventContext(scenarioKey: 'service_availability'),
            customerTransactionId: $customerTransactionId,
        );

        $presentation = FedExMerchantCheckPresenter::serviceAvailability($result->data);
        $serviceTypes = self::serviceTypesFromPresentation($presentation);

        if ($result->success) {
            $responseSummary = array_merge($result->responseSummary ?? [], [
                'service_count' => $presentation['service_count'],
                'package_type_count' => $presentation['package_type_count'],
                'service_types' => $serviceTypes,
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

        return [
            'result' => $result,
            'presentation' => $presentation,
            'service_types' => $serviceTypes,
        ];
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @return list<string>
     */
    public static function serviceTypesFromPresentation(array $presentation): array
    {
        $types = [];
        foreach ((array) ($presentation['services'] ?? []) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $code = strtoupper(trim((string) ($service['service_type'] ?? '')));
            if ($code !== '' && ! in_array($code, $types, true)) {
                $types[] = $code;
            }
        }

        return $types;
    }

    /**
     * Soft intersection used by merchant shipping-options orchestration.
     * Rates remain the pricing source of truth — empty intersections keep original rates.
     *
     * @param  list<array<string, mixed>>  $rates
     * @param  list<int|null>  $quoteIds
     * @param  list<string>  $availableServiceTypes
     * @return array{rates: list<array<string, mixed>>, quote_ids: list<int|null>}
     */
    public static function intersectRatesWithAvailability(array $rates, array $quoteIds, array $availableServiceTypes): array
    {
        $allowed = array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper(trim($code)),
            array_filter($availableServiceTypes, static fn ($code): bool => filled($code)),
        )));

        if ($allowed === [] || $rates === []) {
            return ['rates' => $rates, 'quote_ids' => $quoteIds];
        }

        $filteredRates = [];
        $filteredQuoteIds = [];

        foreach ($rates as $index => $rate) {
            if (! is_array($rate)) {
                continue;
            }
            $serviceType = strtoupper(trim((string) ($rate['service_type'] ?? '')));
            if ($serviceType === '' || ! in_array($serviceType, $allowed, true)) {
                continue;
            }
            $filteredRates[] = $rate;
            $filteredQuoteIds[] = $quoteIds[$index] ?? null;
        }

        if ($filteredRates === []) {
            return ['rates' => $rates, 'quote_ids' => $quoteIds];
        }

        return ['rates' => $filteredRates, 'quote_ids' => $filteredQuoteIds];
    }
}
