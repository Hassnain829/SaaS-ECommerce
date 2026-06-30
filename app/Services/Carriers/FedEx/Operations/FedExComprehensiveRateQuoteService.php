<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExComprehensiveRateResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService;

class FedExComprehensiveRateQuoteService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExComprehensiveRatePayloadFactory $payloadFactory,
        private readonly FedExComprehensiveRateResponseParser $responseParser,
        private readonly FedExComprehensiveRateAccessClassifier $accessClassifier,
        private readonly FedExTestCaseFixtureService $fixtureService,
    ) {}

    public function quote(
        Store $store,
        CarrierAccount $account,
        ?array $fixture = null,
        ?FedExValidationEventContext $validationContext = null,
    ): FedExComprehensiveRateResult {
        abort_unless($this->config->comprehensiveRateQuotePathConfigured(), 422, 'FedEx Comprehensive Rates endpoint is not configured correctly.');

        $fixture ??= $this->fixtureService->comprehensiveRateQuoteCase();
        $shipDateStamp = now()->toDateString();
        $endpoint = $this->config->comprehensiveRateQuotePath();
        $payload = $this->payloadFactory->build($account, $fixture, $shipDateStamp);

        if (! filled($account->provider_account_number)) {
            return new FedExComprehensiveRateResult(
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
                errors: [['code' => 'missing_account_number', 'message' => 'FedEx account number is required before requesting a comprehensive rate quote.']],
                accessState: FedExComprehensiveRateAccessClassifier::STATE_FAILED_INVALID_REQUEST,
                eventId: null,
            );
        }

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
                'endpoint' => $endpoint,
                'http_method' => 'POST',
                'fixture_case_key' => (string) ($fixture['case_key'] ?? 'comprehensive_rate_baseline'),
                'fixture_source' => (string) ($fixture['source'] ?? 'unknown'),
                'origin_country' => strtoupper((string) ($fixture['origin_country'] ?? 'US')),
                'destination_country' => strtoupper((string) ($fixture['destination_country'] ?? 'US')),
                'expected_service_type' => $fixture['expected_service_type'] ?? null,
                'expected_rate_type' => $fixture['expected_rate_type'] ?? 'ACCOUNT',
                'ship_date' => $shipDateStamp,
                'pickup_type' => $fixture['pickup_type'] ?? null,
                'packaging_type' => $fixture['packaging_type'] ?? null,
            ],
        );

        $context ??= new FedExValidationEventContext(
            scenarioKey: CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE,
            testCaseKey: (string) ($fixture['case_key'] ?? 'comprehensive_rate_baseline'),
        );

        $apiResult = $this->apiClient->postJson(
            store: $store,
            account: $account,
            action: CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            path: $endpoint,
            payload: $payload,
            requestSummary: $requestSummary,
            context: $context,
        );

        $result = $this->buildResult($apiResult, $fixture, $endpoint);
        $this->enrichCompletedEvent($apiResult, $result, $fixture);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function buildResult(CarrierApiResult $apiResult, array $fixture, string $endpoint): FedExComprehensiveRateResult
    {
        $httpStatus = (int) data_get($apiResult->responseSummary, 'http_status');
        $responseBody = is_array($apiResult->data) ? $apiResult->data : null;
        $classification = $this->accessClassifier->classify(
            $httpStatus > 0 ? $httpStatus : null,
            $responseBody,
            $endpoint,
            $apiResult->errorCode,
        );

        $parsed = $this->responseParser->parse(
            $responseBody,
            expectedServiceType: isset($fixture['expected_service_type']) ? (string) $fixture['expected_service_type'] : null,
            expectedRateType: (string) ($fixture['expected_rate_type'] ?? 'ACCOUNT'),
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

        $successful = $apiResult->success
            && $classification['access_state'] === FedExComprehensiveRateAccessClassifier::STATE_PASSED
            && $parsed['amount'] !== null
            && $parsed['currency'] !== null;

        return new FedExComprehensiveRateResult(
            successful: $successful,
            httpStatus: $httpStatus > 0 ? $httpStatus : null,
            transactionId: data_get($responseBody, 'transactionId') ? (string) data_get($responseBody, 'transactionId') : null,
            serviceType: $parsed['service_type'],
            serviceName: $parsed['service_name'],
            rateType: $parsed['rate_type'],
            currency: $parsed['currency'],
            amount: $parsed['amount'],
            responseAmountPath: $parsed['response_amount_path'],
            availableRates: $parsed['available_rates'],
            errors: $errors,
            accessState: $classification['access_state'],
            eventId: data_get($apiResult->responseSummary, 'carrier_api_event_id') ? (int) data_get($apiResult->responseSummary, 'carrier_api_event_id') : null,
            fedexErrorCode: $classification['fedex_error_code'],
            fedexErrorMessage: $classification['fedex_error_message'],
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function enrichCompletedEvent(CarrierApiResult $apiResult, FedExComprehensiveRateResult $result, array $fixture): void
    {
        $eventId = (int) data_get($apiResult->responseSummary, 'carrier_api_event_id', 0);
        if ($eventId <= 0) {
            return;
        }

        $event = CarrierApiEvent::query()->find($eventId);
        if ($event === null) {
            return;
        }

        $responseSummary = array_merge($event->response_summary ?? [], $result->toResponseSummary(), [
            'fixture_source' => (string) ($fixture['source'] ?? 'unknown'),
            'fixture_case_key' => (string) ($fixture['case_key'] ?? 'comprehensive_rate_baseline'),
        ]);

        $errorCode = match ($result->accessState) {
            FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ENTITLEMENT => 'fedex_comprehensive_rate_blocked_entitlement',
            FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ACCESS => 'fedex_comprehensive_rate_blocked_access',
            FedExComprehensiveRateAccessClassifier::STATE_FAILED_AUTHENTICATION => 'fedex_comprehensive_rate_auth_failed',
            default => $event->error_code,
        };

        $event->update([
            'response_summary' => $responseSummary,
            'error_code' => $errorCode,
        ]);
    }
}
