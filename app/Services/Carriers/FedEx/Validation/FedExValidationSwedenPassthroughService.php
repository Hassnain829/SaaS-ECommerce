<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExOAuthTokenService;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationInputValidator;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationPayloadBuilder;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationResponseAnalyzer;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExHttpClient;
use Illuminate\Support\Str;

class FedExValidationSwedenPassthroughService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExTestCaseFixtureService $fixtures,
        private readonly FedExRegistrationInputValidator $inputValidator,
        private readonly FedExRegistrationPayloadBuilder $payloadBuilder,
        private readonly FedExRegistrationResponseAnalyzer $responseAnalyzer,
        private readonly FedExHttpClient $httpClient,
        private readonly CarrierApiEventLogger $eventLogger,
        private readonly FedExValidationAuthorizationEvidenceService $authorizationEvidence,
        private readonly FedExOAuthTokenService $oauthTokenService,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     blocked: bool,
     *     public_message: string,
     *     failure_code: ?string,
     *     validation_run_id: ?string,
     *     parent_event: ?CarrierApiEvent,
     *     address_event: ?CarrierApiEvent,
     *     child_event: ?CarrierApiEvent,
     *     child_credentials_detected: bool,
     *     mfa_detected: bool
     * }
     */
    public function run(Store $store, CarrierAccount $evidenceAccount): array
    {
        abort_unless($this->config->validationModeEnabled(), 403);
        abort_unless($evidenceAccount->usesFedExIntegratorProvider(), 404);
        abort_unless($evidenceAccount->environment === CarrierAccount::ENVIRONMENT_SANDBOX, 422);

        $validationRunId = (string) Str::uuid();
        $base = [
            'success' => false,
            'blocked' => false,
            'public_message' => FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            'failure_code' => null,
            'validation_run_id' => $validationRunId,
            'parent_event' => null,
            'address_event' => null,
            'child_event' => null,
            'child_credentials_detected' => false,
            'mfa_detected' => false,
        ];

        if (! $this->config->isConfigured($evidenceAccount->environment)) {
            return array_merge($base, [
                'blocked' => true,
                'failure_code' => 'sweden_passthrough_fixture_unavailable',
                'public_message' => 'FedEx platform credentials are not configured. Contact the platform administrator.',
                'validation_run_id' => null,
            ]);
        }

        if ($this->config->isDeprecatedRegistrationPath($this->config->accountRegistrationPath($evidenceAccount->environment))) {
            return array_merge($base, [
                'blocked' => true,
                'failure_code' => 'sweden_passthrough_fixture_unavailable',
                'public_message' => 'FedEx registration endpoint is not configured for validation.',
                'validation_run_id' => null,
            ]);
        }

        $fixture = $this->fixtures->swedenMfaPassthroughAccount();
        if ($fixture === null) {
            return array_merge($base, [
                'blocked' => true,
                'failure_code' => 'sweden_passthrough_fixture_unavailable',
                'public_message' => 'Sweden MFA passthrough workbook fixture is not available. Configure the FedEx baseline workbook or Sweden validation environment values.',
                'validation_run_id' => null,
            ]);
        }

        $runMeta = $this->runMetadata($validationRunId, $fixture);

        $parent = $this->authorizationEvidence->runParentAuthorization(
            $evidenceAccount,
            $validationRunId,
            FedExValidationSwedenPassthroughSupport::VALIDATION_CASE,
        );
        $base['parent_event'] = $parent['event'];

        if (! $parent['result']->success) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_parent_oauth_failed',
            ]);
        }

        $address = $this->runSwedenAddressRegistration(
            store: $store,
            evidenceAccount: $evidenceAccount,
            fixture: $fixture,
            platformToken: is_array($parent['result']->data) ? $parent['result']->data : [],
            runMeta: $runMeta,
        );
        $base['address_event'] = $address['event'];

        if (! $address['result']->success) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_registration_failed',
            ]);
        }

        $output = $this->responseAnalyzer->output($address['result']->data);
        $childCredentials = $this->responseAnalyzer->extractChildCredentials($address['result']->data);
        $mfaDetected = $this->responseAnalyzer->mfaDetected($output);
        $authTokenDetected = $this->responseAnalyzer->extractAccountAuthToken($address['result']->data) !== null;
        $childDetected = $childCredentials !== null;

        $base['child_credentials_detected'] = $childDetected;
        $base['mfa_detected'] = $mfaDetected;

        $this->attachRunMetadata($address['event'], array_merge($runMeta, [
            'child_credentials_detected' => $childDetected,
            'mfa_detected' => $mfaDetected,
            'account_auth_token_detected' => $authTokenDetected,
        ]));

        if ($mfaDetected && $childDetected) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_inconsistent_response',
            ]);
        }

        if ($mfaDetected) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_mfa_returned',
            ]);
        }

        if (! $childDetected && $authTokenDetected) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_auth_token_only',
            ]);
        }

        if (! $childDetected) {
            return array_merge($base, [
                'failure_code' => 'sweden_passthrough_credentials_missing',
            ]);
        }

        $child = $this->runSwedenChildAuthorization(
            store: $store,
            evidenceAccount: $evidenceAccount,
            childKey: (string) $childCredentials['customer_key'],
            childSecret: (string) $childCredentials['customer_password'],
            runMeta: $runMeta,
        );

        unset($childCredentials);

        $base['child_event'] = $child['event'];

        if (! $child['result']->success) {
            return array_merge($base, [
                'failure_code' => $child['result']->errorCode === 'transport_error'
                    ? 'sweden_passthrough_transport_error'
                    : 'sweden_passthrough_child_oauth_failed',
            ]);
        }

        return array_merge($base, [
            'success' => true,
            'public_message' => 'Sweden MFA passthrough validation completed successfully.',
            'failure_code' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array{access_token?: string}  $platformToken
     * @param  array<string, mixed>  $runMeta
     * @return array{result: CarrierApiResult, event: CarrierApiEvent}
     */
    private function runSwedenAddressRegistration(
        Store $store,
        CarrierAccount $evidenceAccount,
        array $fixture,
        array $platformToken,
        array $runMeta,
    ): array {
        $environment = $this->config->environment($evidenceAccount->environment);
        $registrationPath = $this->config->accountRegistrationPath($environment);
        $accountDetails = $this->registrationDetailsFromFixture($fixture);
        $validation = $this->inputValidator->validate($accountDetails);

        if ($validation['errors'] !== []) {
            $result = CarrierApiResult::failure(
                message: (string) reset($validation['errors']),
                code: 'invalid_registration_input',
                requestSummary: ['endpoint' => $registrationPath],
            );

            return ['result' => $result, 'event' => $this->startAndCompleteAddressEvent(
                $store,
                $evidenceAccount,
                $environment,
                $registrationPath,
                $runMeta,
                $result,
            )];
        }

        $accountDetails = $validation['normalized'];
        $accountNumber = preg_replace('/\D+/', '', (string) ($fixture['account_number'] ?? '')) ?? '';
        $payload = $this->payloadBuilder->buildV2Payload($accountNumber, $accountDetails);
        $requestSummary = array_merge(
            $this->payloadBuilder->buildRequestSummary($registrationPath, $accountNumber, $payload, $accountDetails),
            $runMeta,
        );

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $evidenceAccount,
            requestSummary: $requestSummary,
            environment: $environment,
            context: new FedExValidationEventContext(
                scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS,
            ),
        );

        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $result = CarrierApiResult::failure(
                message: 'Sweden validation account number must be 9 digits.',
                code: 'invalid_account_number',
                requestSummary: $requestSummary,
            );
            $completed = $this->eventLogger->complete($event, $result);

            return ['result' => $result, 'event' => $this->attachRunMetadata($completed, $runMeta)];
        }

        if (! filled($platformToken['access_token'] ?? null)) {
            $result = CarrierApiResult::failure(
                message: 'FedEx platform authentication failed.',
                code: 'platform_oauth_failed',
                requestSummary: $requestSummary,
            );
            $completed = $this->eventLogger->complete($event, $result);

            return ['result' => $result, 'event' => $this->attachRunMetadata($completed, $runMeta)];
        }

        $result = $this->httpClient->postJson(
            environment: $environment,
            path: $registrationPath,
            payload: $payload,
            bearerToken: (string) $platformToken['access_token'],
            requestSummary: $requestSummary,
        );

        $completed = $this->eventLogger->complete($event, $result);

        return ['result' => $result, 'event' => $this->attachRunMetadata($completed, $runMeta)];
    }

    /**
     * @param  array<string, mixed>  $runMeta
     * @return array{result: CarrierApiResult, event: CarrierApiEvent}
     */
    private function runSwedenChildAuthorization(
        Store $store,
        CarrierAccount $evidenceAccount,
        string $childKey,
        string $childSecret,
        array $runMeta,
    ): array {
        $environment = $this->config->environment($evidenceAccount->environment);
        $meta = FedExValidationScenarioCatalog::swedenPassthroughScenarios()[CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD];

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: (string) $meta['action'],
            account: $evidenceAccount,
            requestSummary: array_merge([
                'endpoint' => $this->config->oauthPath(),
                'http_method' => 'POST',
                'grant_type' => $meta['grant_type'],
                'credentials_mode' => 'sweden_passthrough_ephemeral_child',
                'account_last4' => $runMeta['account_last4'] ?? null,
            ], $runMeta),
            environment: $environment,
            context: new FedExValidationEventContext(
                scenarioKey: CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD,
            ),
        );

        $result = $this->oauthTokenService->fetchChildTokenResultForCredentials(
            environment: $environment,
            childKey: $childKey,
            childSecret: $childSecret,
            cache: false,
        );

        $completed = $this->eventLogger->complete($event, $result);

        return ['result' => $result, 'event' => $this->attachRunMetadata($completed, $runMeta)];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function registrationDetailsFromFixture(array $fixture): array
    {
        return [
            'account_number' => $fixture['account_number'],
            'provider_account_number' => $fixture['account_number'],
            'company_name' => $fixture['company_name'],
            'contact_name' => $fixture['contact_name'],
            'address_line1' => $fixture['address_line1'],
            'address_line2' => $fixture['address_line2'] ?? null,
            'city' => $fixture['city'],
            'state' => $fixture['state'] ?? '',
            'postal_code' => $fixture['postal_code'],
            'country_code' => 'SE',
            'residential' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function runMetadata(string $validationRunId, array $fixture): array
    {
        return [
            'validation_run_id' => $validationRunId,
            'validation_case' => FedExValidationSwedenPassthroughSupport::VALIDATION_CASE,
            'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
            'account_last4' => $fixture['account_last4'] ?? null,
            'fixture_source' => $fixture['source'] ?? null,
            'country_code' => 'SE',
        ];
    }

    /**
     * @param  array<string, mixed>  $runMeta
     */
    private function startAndCompleteAddressEvent(
        Store $store,
        CarrierAccount $evidenceAccount,
        string $environment,
        string $registrationPath,
        array $runMeta,
        CarrierApiResult $result,
    ): CarrierApiEvent {
        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $evidenceAccount,
            requestSummary: array_merge(['endpoint' => $registrationPath], $runMeta),
            environment: $environment,
            context: new FedExValidationEventContext(
                scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS,
            ),
        );

        return $this->attachRunMetadata($this->eventLogger->complete($event, $result), $runMeta);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function attachRunMetadata(CarrierApiEvent $event, array $metadata): CarrierApiEvent
    {
        $event->update([
            'request_summary' => array_merge($event->request_summary ?? [], $metadata),
            'response_summary' => array_merge($event->response_summary ?? [], $metadata),
        ]);

        return $event->fresh();
    }
}
