<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExComprehensiveRateResult;
use App\Services\Carriers\FedEx\DTO\FedExShipmentRateRequest;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExHandoffTypeResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Production negotiated rates via Comprehensive Rates and Transit Times API.
 * Never uses platform sandbox fallback credentials.
 */
class FedExNegotiatedRateService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExOperationGuard $guard,
        private readonly FedExComprehensiveRatePayloadFactory $payloadFactory,
        private readonly FedExComprehensiveRateResponseParser $responseParser,
        private readonly FedExComprehensiveRateAccessClassifier $accessClassifier,
        private readonly CarrierOriginReadinessService $originReadiness,
    ) {}

    /**
     * @param  array<string, mixed>  $destinationInput
     * @param  array<string, mixed>  $packageInput
     * @return array{
     *     result: FedExComprehensiveRateResult,
     *     rates: list<array<string, mixed>>,
     *     quote_ids: list<int>
     * }
     */
    public function quoteForOriginDestination(
        Store $store,
        CarrierAccount $account,
        Location $originLocation,
        array $destinationInput,
        array $packageInput,
        ?string $shipDate = null,
        ?string $serviceType = null,
        ?bool $residential = null,
        ?string $packagingType = null,
        ?int $orderId = null,
        bool $forCheckout = false,
        ?int $shipmentPackageId = null,
        ?string $pickupType = null,
    ): array {
        $capability = $forCheckout
            ? FedExOperationGuard::CAPABILITY_CHECKOUT_RATES
            : FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES;

        $this->guard->assertAccountForOperation($store, $account, $capability);

        abort_unless($this->config->comprehensiveRateQuotePathConfigured(), 422, 'FedEx Comprehensive Rates endpoint is not configured correctly.');

        if (! filled($account->fedExAccountNumber())) {
            return $this->failedLocal(
                'missing_account_number',
                'FedEx account number is required before requesting negotiated rates.',
            );
        }

        $readiness = $this->originReadiness->assessForFulfillmentOrigin(
            $originLocation,
            CarrierOriginReadinessService::CARRIER_GENERIC,
        );

        if (! $readiness->ready) {
            return $this->failedLocal('origin_not_ready', $readiness->merchantMessage);
        }

        $origin = $readiness->normalizedAddress;
        $shipDateStamp = $this->guard->assertShipDate($shipDate);
        $destinationCountry = strtoupper(trim((string) ($destinationInput['country_code'] ?? 'US')));
        $originCountry = strtoupper((string) ($origin['country_code'] ?? 'US'));

        $this->guard->assertOriginDestinationAllowed(
            $originCountry,
            $destinationCountry,
            $account->environment,
        );

        $packages = isset($packageInput[0]) && is_array($packageInput[0])
            ? $packageInput
            : [$packageInput];

        $normalizedPackages = [];
        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                continue;
            }
            $weight = $package['weight'] ?? null;
            if (! is_numeric($weight) || (float) $weight <= 0) {
                return $this->failedLocal(
                    'package_weight_required',
                    'Each package needs a weight greater than zero before FedEx rates can be requested.',
                );
            }
            if (! is_numeric($package['length'] ?? null) || (float) $package['length'] <= 0
                || ! is_numeric($package['width'] ?? null) || (float) $package['width'] <= 0
                || ! is_numeric($package['height'] ?? null) || (float) $package['height'] <= 0
            ) {
                return $this->failedLocal(
                    'package_dimensions_required',
                    'Each package needs length, width, and height before FedEx rates can be requested.',
                );
            }

            $normalizedPackages[] = [
                'weight' => (float) $weight,
                'weight_unit' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                'length' => (float) $package['length'],
                'width' => (float) $package['width'],
                'height' => (float) $package['height'],
                'dimension_unit' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
            ];
        }

        if ($normalizedPackages === []) {
            return $this->failedLocal(
                'package_required',
                'Choose a package before requesting FedEx rates.',
            );
        }

        $resolvedPickup = app(FedExHandoffTypeResolver::class)
            ->resolve($store, $pickupType);

        $request = new FedExShipmentRateRequest(
            shipper: [
                'postal_code' => $origin['postal_code'] ?? null,
                'country_code' => $originCountry,
                'city' => $origin['city'] ?? null,
                'state' => $origin['state'] ?? null,
            ],
            recipient: [
                'postal_code' => trim((string) ($destinationInput['postal_code'] ?? '')) ?: null,
                'country_code' => $destinationCountry,
                'city' => trim((string) ($destinationInput['city'] ?? '')) ?: null,
                'state' => strtoupper(trim((string) ($destinationInput['state'] ?? ''))) ?: null,
                'residential' => $residential ?? (array_key_exists('residential', $destinationInput) ? (bool) $destinationInput['residential'] : null),
                'address_line1' => $destinationInput['address_line1'] ?? $destinationInput['street'] ?? null,
                'address_line2' => $destinationInput['address_line2'] ?? null,
            ],
            packages: $normalizedPackages,
            shipDate: $shipDateStamp,
            pickupType: $resolvedPickup,
            packagingType: strtoupper(trim((string) ($packagingType ?? $packageInput['packaging_type'] ?? 'YOUR_PACKAGING'))),
            serviceType: filled($serviceType) ? strtoupper(trim($serviceType)) : null,
            orderId: $orderId,
            shipmentPackageId: $shipmentPackageId,
            idempotencySubject: implode(':', array_filter([
                $forCheckout ? 'checkout' : 'merchant',
                (string) ($orderId ?? ''),
                $originCountry,
                $destinationCountry,
                trim((string) ($destinationInput['postal_code'] ?? '')),
                $shipDateStamp,
                (string) ($serviceType ?? 'ALL'),
                (string) ($shipmentPackageId ?? ''),
                $resolvedPickup,
            ])),
        );

        return $this->quote($store, $account, $request, $forCheckout, (int) $originLocation->id);
    }

    /**
     * @return array{
     *     result: FedExComprehensiveRateResult,
     *     rates: list<array<string, mixed>>,
     *     quote_ids: list<int>
     * }
     */
    public function quote(
        Store $store,
        CarrierAccount $account,
        FedExShipmentRateRequest $request,
        bool $forCheckout = false,
        ?int $originLocationId = null,
    ): array {
        $capability = $forCheckout
            ? FedExOperationGuard::CAPABILITY_CHECKOUT_RATES
            : FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES;

        $this->guard->assertAccountForOperation($store, $account, $capability);
        abort_unless($this->config->comprehensiveRateQuotePathConfigured(), 422, 'FedEx Comprehensive Rates endpoint is not configured correctly.');

        $endpoint = $this->config->comprehensiveRateQuotePath();
        $payload = $this->payloadFactory->buildFromRequest($account, $request);
        $customerTransactionId = $this->guard->idempotencyKey(
            $store,
            $account,
            $forCheckout ? 'checkout_rate' : 'negotiated_rate',
            $request->idempotencySubject,
        );

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
                'operation' => $forCheckout ? 'checkout_negotiated_rate' : 'merchant_negotiated_rate',
                'origin_location_id' => $originLocationId,
                'origin_country' => $request->shipper['country_code'] ?? null,
                'origin_postal_code' => $request->shipper['postal_code'] ?? null,
                'destination_country' => $request->recipient['country_code'] ?? null,
                'destination_postal_code' => $request->recipient['postal_code'] ?? null,
                'destination_residential' => $request->recipient['residential'] ?? null,
                'ship_date' => $request->shipDate,
                'service_type' => $request->serviceType,
                'packaging_type' => $request->packagingType,
                'package_count' => count($request->packages),
                'packages' => $request->packages,
                'package_fingerprint' => app(FedExShipQuoteBindingService::class)->packageFingerprint($request->packages),
                'pickup_type' => $request->pickupType,
                'rate_request_types' => $request->rateRequestTypes,
                'return_transit_times' => $request->returnTransitTimes,
                'order_id' => $request->orderId,
                'customer_transaction_id' => $customerTransactionId,
                'platform_fallback_used' => false,
            ],
        );

        $apiResult = $this->apiClient->postJson(
            store: $store,
            account: $account,
            action: CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            path: $endpoint,
            payload: $payload,
            requestSummary: $requestSummary,
            customerTransactionId: $customerTransactionId,
        );

        $result = $this->buildResult($apiResult, $request->serviceType);
        $quoteIds = $this->persistQuotes($store, $account, $request, $result, $requestSummary);

        return [
            'result' => $result,
            'rates' => $result->availableRates,
            'quote_ids' => $quoteIds,
        ];
    }

    private function buildResult(CarrierApiResult $apiResult, ?string $expectedServiceType): FedExComprehensiveRateResult
    {
        $httpStatus = (int) data_get($apiResult->responseSummary, 'http_status');
        $responseBody = is_array($apiResult->data) ? $apiResult->data : null;
        $endpoint = $this->config->comprehensiveRateQuotePath();
        $classification = $this->accessClassifier->classify(
            $httpStatus > 0 ? $httpStatus : null,
            $responseBody,
            $endpoint,
            $apiResult->errorCode,
        );

        $parsed = $this->responseParser->parse(
            $responseBody,
            expectedServiceType: $expectedServiceType,
            expectedRateType: 'ACCOUNT',
            allowFallbackToAnyRate: false,
        );

        $errors = [];
        foreach ((array) data_get($responseBody, 'errors', []) as $error) {
            if (! is_array($error)) {
                continue;
            }
            $errors[] = array_filter([
                'code' => $error['code'] ?? null,
                'message' => $error['message'] ?? null,
            ]);
        }

        if ($errors === [] && $apiResult->errorMessage !== null) {
            $errors[] = array_filter([
                'code' => $apiResult->errorCode,
                'message' => $apiResult->errorMessage,
            ]);
        }

        $hasAccountRate = strtoupper((string) ($parsed['rate_type'] ?? '')) === 'ACCOUNT'
            && filled($parsed['amount'] ?? null)
            && filled($parsed['currency'] ?? null);

        if (! $hasAccountRate && $parsed['available_rates'] !== []) {
            $accountRate = collect($parsed['available_rates'])
                ->first(fn (array $rate): bool => strtoupper((string) ($rate['rate_type'] ?? '')) === 'ACCOUNT'
                    && filled($rate['amount'] ?? null)
                    && filled($rate['currency'] ?? null));
            if (is_array($accountRate)) {
                $parsed['service_type'] = $accountRate['service_type'] ?? $parsed['service_type'];
                $parsed['service_name'] = $accountRate['service_name'] ?? $parsed['service_name'];
                $parsed['rate_type'] = $accountRate['rate_type'] ?? $parsed['rate_type'];
                $parsed['currency'] = $accountRate['currency'] ?? $parsed['currency'];
                $parsed['amount'] = $accountRate['amount'] ?? $parsed['amount'];
                $parsed['response_amount_path'] = $accountRate['response_amount_path'] ?? $parsed['response_amount_path'];
                $parsed['transit_days'] = $accountRate['transit_days'] ?? $parsed['transit_days'];
                $parsed['delivery_date'] = $accountRate['delivery_date'] ?? $parsed['delivery_date'];
                $parsed['surcharges'] = $accountRate['surcharges'] ?? $parsed['surcharges'];
                $parsed['duties_and_taxes'] = $accountRate['duties_and_taxes'] ?? $parsed['duties_and_taxes'];
                $hasAccountRate = true;
            }
        }

        $listOnly = ! $hasAccountRate && collect($parsed['available_rates'])
            ->contains(fn (array $rate): bool => strtoupper((string) ($rate['rate_type'] ?? '')) === 'LIST');

        if ($listOnly) {
            $errors[] = [
                'code' => 'account_rate_unavailable',
                'message' => 'FedEx returned list rates only. Negotiated account rates are required.',
            ];
        }

        $successful = $apiResult->success
            && $classification['access_state'] === FedExComprehensiveRateAccessClassifier::STATE_PASSED
            && $hasAccountRate;

        return new FedExComprehensiveRateResult(
            successful: $successful,
            httpStatus: $httpStatus > 0 ? $httpStatus : null,
            transactionId: data_get($responseBody, 'transactionId') ? (string) data_get($responseBody, 'transactionId') : ($apiResult->requestId ?: null),
            serviceType: $parsed['service_type'],
            serviceName: $parsed['service_name'],
            rateType: $hasAccountRate ? 'ACCOUNT' : ($parsed['rate_type'] ?? null),
            currency: $hasAccountRate ? $parsed['currency'] : null,
            amount: $hasAccountRate ? $parsed['amount'] : null,
            responseAmountPath: $hasAccountRate ? $parsed['response_amount_path'] : null,
            availableRates: $parsed['available_rates'],
            errors: $errors,
            accessState: $classification['access_state'],
            eventId: data_get($apiResult->responseSummary, 'carrier_api_event_id') ? (int) data_get($apiResult->responseSummary, 'carrier_api_event_id') : null,
            fedexErrorCode: $listOnly ? 'account_rate_unavailable' : $classification['fedex_error_code'],
            fedexErrorMessage: $listOnly
                ? 'FedEx returned list rates only. Negotiated account rates are required.'
                : $classification['fedex_error_message'],
            transitDays: $hasAccountRate ? ($parsed['transit_days'] ?? null) : null,
            deliveryDate: $hasAccountRate ? ($parsed['delivery_date'] ?? null) : null,
            surcharges: $hasAccountRate ? ($parsed['surcharges'] ?? []) : [],
            dutiesAndTaxes: $hasAccountRate ? ($parsed['duties_and_taxes'] ?? []) : [],
            merchantMessage: $successful
                ? null
                : FedExSafeExceptionMapper::merchantMessage(
                    $listOnly ? 'account_rate_unavailable' : ($classification['fedex_error_code'] ?? $apiResult->errorCode),
                    $listOnly
                        ? 'FedEx did not return negotiated account rates for this shipment.'
                        : ($classification['fedex_error_message'] ?? $apiResult->errorMessage),
                    $httpStatus > 0 ? $httpStatus : null,
                ),
        );
    }

    /**
     * @param  array<string, mixed>  $requestSummary
     * @return list<int>
     */
    private function persistQuotes(
        Store $store,
        CarrierAccount $account,
        FedExShipmentRateRequest $request,
        FedExComprehensiveRateResult $result,
        array $requestSummary,
    ): array {
        $ids = [];
        $rates = $result->availableRates !== []
            ? $result->availableRates
            : [[
                'service_type' => $result->serviceType,
                'service_name' => $result->serviceName,
                'rate_type' => $result->rateType,
                'currency' => $result->currency,
                'amount' => $result->amount,
                'transit_days' => $result->transitDays,
            ]];

        if (! $result->successful && $result->amount === null) {
            $quote = CarrierRateQuote::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'order_id' => $request->orderId,
                'package_id' => $request->shipmentPackageId,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'environment' => $account->environment,
                'origin_postal_code' => $request->shipper['postal_code'] ?? null,
                'destination_postal_code' => $request->recipient['postal_code'] ?? null,
                'service_code' => $request->serviceType,
                'service_name' => null,
                'amount' => 0,
                'currency' => 'USD',
                'estimated_days' => null,
                'status' => CarrierRateQuote::STATUS_FAILED,
                'request_summary' => $requestSummary,
                'response_summary' => $result->toResponseSummary(),
                'error_code' => $result->fedexErrorCode ?? $result->accessState,
                'error_message' => $result->merchantMessage,
                'created_by' => is_numeric(Auth::id()) ? (int) Auth::id() : null,
            ]);
            $ids[] = (int) $quote->id;

            return $ids;
        }

        foreach ($rates as $rate) {
            if (! is_array($rate) || ! filled($rate['amount'] ?? null)) {
                continue;
            }

            $quote = CarrierRateQuote::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'order_id' => $request->orderId,
                'package_id' => $request->shipmentPackageId,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'environment' => $account->environment,
                'origin_postal_code' => $request->shipper['postal_code'] ?? null,
                'destination_postal_code' => $request->recipient['postal_code'] ?? null,
                'service_code' => $rate['service_type'] ?? $result->serviceType,
                'service_name' => $rate['service_name'] ?? $result->serviceName,
                'amount' => $rate['amount'],
                'currency' => $rate['currency'] ?? $result->currency,
                'estimated_days' => isset($rate['transit_days']) ? (int) $rate['transit_days'] : $result->transitDays,
                'status' => CarrierRateQuote::STATUS_SUCCEEDED,
                'request_summary' => $requestSummary,
                'response_summary' => array_merge($result->toResponseSummary(), [
                    'selected_service_type' => $rate['service_type'] ?? null,
                    'selected_rate_type' => $rate['rate_type'] ?? null,
                    'delivery_date' => $rate['delivery_date'] ?? $result->deliveryDate,
                    'surcharges' => $rate['surcharges'] ?? $result->surcharges,
                ]),
                'error_code' => null,
                'error_message' => null,
                'created_by' => is_numeric(Auth::id()) ? (int) Auth::id() : null,
            ]);
            $ids[] = (int) $quote->id;
        }

        return $ids;
    }

    /**
     * @return array{result: FedExComprehensiveRateResult, rates: list<array<string, mixed>>, quote_ids: list<int>}
     */
    private function failedLocal(string $code, string $message): array
    {
        $result = new FedExComprehensiveRateResult(
            successful: false,
            httpStatus: null,
            transactionId: null,
            serviceType: null,
            serviceName: null,
            rateType: null,
            currency: null,
            amount: null,
            responseAmountPath: null,
            availableRates: [],
            errors: [['code' => $code, 'message' => $message]],
            accessState: FedExComprehensiveRateAccessClassifier::STATE_FAILED_INVALID_REQUEST,
            eventId: null,
            fedexErrorCode: $code,
            fedexErrorMessage: $message,
            merchantMessage: FedExSafeExceptionMapper::merchantMessage($code, $message),
        );

        return ['result' => $result, 'rates' => [], 'quote_ids' => []];
    }
}
