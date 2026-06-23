<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use RuntimeException;

class FedExAccountRegistrationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
        private readonly CarrierApiEventLogger $eventLogger,
        private readonly FedExRegistrationResponseAnalyzer $responseAnalyzer,
    ) {}

    /**
     * @param  array<string, mixed>  $accountDetails
     * @param  array{access_token: string, token_type?: string, expires_in?: int}|null  $platformToken
     */
    public function register(
        Store $store,
        CarrierAccount $account,
        array $accountDetails,
        ?array $platformToken = null,
    ): CarrierApiResult {
        $environment = $this->config->environment($account->environment);
        $registrationPath = $this->config->accountRegistrationPath($environment);

        if ($this->config->isDeprecatedRegistrationPath($registrationPath)) {
            return CarrierApiResult::failure(
                message: 'FedEx account registration endpoint is deprecated. Update FEDEX_SANDBOX_ACCOUNT_REGISTRATION_PATH to the current Credential Registration API path from the FedEx Developer Portal.',
                code: 'deprecated_registration_endpoint',
                requestSummary: ['endpoint' => $registrationPath],
            );
        }

        if ($platformToken === null || ! filled($platformToken['access_token'] ?? null)) {
            return CarrierApiResult::failure(
                message: 'FedEx platform authentication failed. Contact the platform admin.',
                code: 'platform_oauth_failed',
                requestSummary: ['endpoint' => $registrationPath],
            );
        }

        $accountNumber = $this->resolveAccountNumber($account, $accountDetails);
        $validation = app(FedExRegistrationInputValidator::class)->validate($accountDetails);

        if ($validation['errors'] !== []) {
            return CarrierApiResult::failure(
                message: (string) reset($validation['errors']),
                code: 'invalid_registration_input',
                requestSummary: [
                    'endpoint' => $registrationPath,
                    'validation_errors' => array_keys($validation['errors']),
                ],
            );
        }

        $accountDetails = $validation['normalized'];
        $payload = $this->buildV2Payload($accountNumber, $accountDetails);
        $requestSummary = $this->buildRequestSummary($registrationPath, $accountNumber, $payload, $accountDetails);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $account,
            requestSummary: $requestSummary,
            environment: $environment,
            context: new FedExValidationEventContext(
                registrationSessionId: $account->registration_session_id,
                scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS,
            ),
        );

        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $result = CarrierApiResult::failure(
                message: 'FedEx account number must be 9 digits.',
                code: 'invalid_account_number',
                requestSummary: $requestSummary,
            );
            $this->eventLogger->complete($event, $result);

            return $result;
        }

        $result = $this->httpClient->postJson(
            environment: $environment,
            path: $registrationPath,
            payload: $payload,
            bearerToken: $platformToken['access_token'],
            requestSummary: $requestSummary,
        );

        $result = $this->normalizeRegistrationResult($result, $account, $accountNumber);

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     * @param  array{access_token: string, token_type?: string, expires_in?: int}|null  $platformToken
     */
    public function registerSession(
        Store $store,
        CarrierAccountRegistrationSession $session,
        array $accountDetails,
        ?array $platformToken = null,
    ): CarrierApiResult {
        return $this->performRegistration(
            store: $store,
            environment: $session->environment,
            accountDetails: $accountDetails,
            platformToken: $platformToken,
            account: null,
            session: $session,
        );
    }

    /**
     * @param  array{access_token: string, token_type?: string, expires_in?: int}  $platformToken
     */
    public function validateMfaPin(
        CarrierAccountRegistrationSession $session,
        string $pin,
        array $platformToken,
    ): CarrierApiResult {
        $path = $this->config->mfaPinValidationPath();
        if ($path === null) {
            return CarrierApiResult::failure(
                message: 'FedEx PIN validation endpoint is not configured.',
                code: 'mfa_endpoint_missing',
            );
        }

        $address = $session->registrationAddress();
        $payload = [
            'secureCodePin' => trim($pin),
            'customerName' => $session->account_name ?: ($address['company_name'] ?? $address['contact_name'] ?? ''),
        ];

        return $this->performConfiguredJsonCall(
            store: $session->store,
            environment: $session->environment,
            path: $path,
            payload: $payload,
            platformToken: $platformToken,
            session: $session,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            requestSummary: [
                'endpoint' => $path,
                'mfa_step' => 'pin_validation',
                'registration_session_id' => $session->id,
                'account_last4' => $session->account_last4,
            ],
            scenarioKey: $this->pinScenarioKey('validation', $session->mfa_method),
            mfaMethod: $session->mfa_method,
        );
    }

    /**
     * @param  array{access_token: string, token_type?: string, expires_in?: int}  $platformToken
     */
    public function initiateMfaPinGeneration(
        CarrierAccountRegistrationSession $session,
        string $method,
        array $platformToken,
    ): CarrierApiResult {
        $path = $this->config->mfaPinGenerationPath();
        if ($path === null) {
            return CarrierApiResult::success(data: ['skipped' => true]);
        }

        $address = $session->registrationAddress();
        $payload = array_filter([
            'transactionId' => $session->fedex_transaction_id,
            'customerName' => $session->account_name ?: ($address['company_name'] ?? $address['contact_name'] ?? ''),
            'option' => strtoupper($method),
            'locale' => 'en_US',
        ]);

        return $this->performConfiguredJsonCall(
            store: $session->store,
            environment: $session->environment,
            path: $path,
            payload: $payload,
            platformToken: $platformToken,
            session: $session,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            requestSummary: [
                'endpoint' => $path,
                'mfa_step' => 'pin_generation',
                'registration_session_id' => $session->id,
                'mfa_method' => strtolower($method),
                'account_last4' => $session->account_last4,
            ],
            finalizeRegistration: false,
            scenarioKey: $this->pinScenarioKey('generation', $method),
            mfaMethod: $method,
        );
    }

    /**
     * @param  array<string, mixed>  $invoiceInput
     * @param  array{access_token: string, token_type?: string, expires_in?: int}  $platformToken
     */
    public function validateMfaInvoice(
        CarrierAccountRegistrationSession $session,
        array $invoiceInput,
        array $platformToken,
        bool $finalizeRegistration = true,
        ?CarrierAccount $carrierAccount = null,
    ): CarrierApiResult {
        $path = $this->config->mfaInvoiceValidationPath();
        if ($path === null) {
            return CarrierApiResult::failure(
                message: 'FedEx invoice validation endpoint is not configured.',
                code: 'mfa_endpoint_missing',
            );
        }

        $address = $session->registrationAddress();
        $payload = [
            'invoiceDetail' => array_filter([
                'number' => trim((string) ($invoiceInput['invoice_number'] ?? '')),
                'date' => trim((string) ($invoiceInput['invoice_date'] ?? '')),
                'currency' => strtoupper(trim((string) ($invoiceInput['invoice_currency'] ?? 'USD'))),
                'amount' => trim((string) ($invoiceInput['invoice_amount'] ?? '')),
            ]),
            'customerName' => $session->account_name ?: ($address['company_name'] ?? $address['contact_name'] ?? ''),
            'locale' => 'en_US',
        ];

        return $this->performConfiguredJsonCall(
            store: $session->store,
            environment: $session->environment,
            path: $path,
            payload: $payload,
            platformToken: $platformToken,
            session: $session,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            requestSummary: [
                'endpoint' => $path,
                'mfa_step' => 'invoice_validation',
                'registration_session_id' => $session->id,
                'account_last4' => $session->account_last4,
            ],
            finalizeRegistration: $finalizeRegistration,
            scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_INVOICE,
            mfaMethod: 'invoice',
            carrierAccount: $carrierAccount,
        );
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     * @param  array{access_token: string, token_type?: string, expires_in?: int}|null  $platformToken
     */
    private function performRegistration(
        Store $store,
        string $environment,
        array $accountDetails,
        ?array $platformToken,
        ?CarrierAccount $account,
        ?CarrierAccountRegistrationSession $session = null,
    ): CarrierApiResult {
        $environment = $this->config->environment($environment);
        $registrationPath = $this->config->accountRegistrationPath($environment);

        if ($this->config->isDeprecatedRegistrationPath($registrationPath)) {
            return CarrierApiResult::failure(
                message: 'FedEx account registration endpoint is deprecated. Update FEDEX_SANDBOX_ACCOUNT_REGISTRATION_PATH to the current Credential Registration API path from the FedEx Developer Portal.',
                code: 'deprecated_registration_endpoint',
                requestSummary: ['endpoint' => $registrationPath],
            );
        }

        if ($platformToken === null || ! filled($platformToken['access_token'] ?? null)) {
            return CarrierApiResult::failure(
                message: 'FedEx platform authentication failed. Contact the platform admin.',
                code: 'platform_oauth_failed',
                requestSummary: ['endpoint' => $registrationPath],
            );
        }

        $accountNumber = $session?->accountNumber()
            ?? $this->resolveAccountNumber($account ?? new CarrierAccount, $accountDetails);
        $validation = app(FedExRegistrationInputValidator::class)->validate($accountDetails);

        if ($validation['errors'] !== []) {
            return CarrierApiResult::failure(
                message: (string) reset($validation['errors']),
                code: 'invalid_registration_input',
                requestSummary: [
                    'endpoint' => $registrationPath,
                    'validation_errors' => array_keys($validation['errors']),
                ],
            );
        }

        $accountDetails = $validation['normalized'];
        $payload = $this->buildV2Payload($accountNumber, $accountDetails);
        $requestSummary = $this->buildRequestSummary($registrationPath, $accountNumber, $payload, $accountDetails);
        if ($session !== null) {
            $requestSummary['registration_session_id'] = $session->id;
        }

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $account,
            requestSummary: $requestSummary,
            environment: $environment,
            context: new FedExValidationEventContext(
                registrationSessionId: $session?->id,
                scenarioKey: CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS,
            ),
        );

        if ($accountNumber === '' || strlen($accountNumber) !== 9) {
            $result = CarrierApiResult::failure(
                message: 'FedEx account number must be 9 digits.',
                code: 'invalid_account_number',
                requestSummary: $requestSummary,
            );
            $this->eventLogger->complete($event, $result);

            return $result;
        }

        $result = $this->httpClient->postJson(
            environment: $environment,
            path: $registrationPath,
            payload: $payload,
            bearerToken: $platformToken['access_token'],
            requestSummary: $requestSummary,
        );

        $result = $this->normalizeRegistrationResult($result, $account, $accountNumber);

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{access_token: string, token_type?: string, expires_in?: int}  $platformToken
     */
    private function performConfiguredJsonCall(
        Store $store,
        string $environment,
        string $path,
        array $payload,
        array $platformToken,
        CarrierAccountRegistrationSession $session,
        string $action,
        array $requestSummary,
        bool $finalizeRegistration = true,
        ?string $scenarioKey = null,
        ?string $mfaMethod = null,
        ?CarrierAccount $carrierAccount = null,
    ): CarrierApiResult {
        $accountAuthToken = $session->accountAuthToken();

        if (! filled($accountAuthToken)) {
            return CarrierApiResult::failure(
                message: 'FedEx verification could not continue because account authorization is missing. Start a new FedEx connection.',
                code: 'account_auth_token_missing',
                requestSummary: $requestSummary,
            );
        }

        if ($session->isAccountAuthTokenExpired()) {
            return CarrierApiResult::failure(
                message: 'FedEx verification authorization has expired. Start a new FedEx connection.',
                code: 'account_auth_token_expired',
                requestSummary: $requestSummary,
            );
        }

        $requestSummary = array_merge($requestSummary, [
            'account_auth_token_sent' => true,
        ]);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: $action,
            account: $carrierAccount,
            requestSummary: $requestSummary,
            environment: $environment,
            context: new FedExValidationEventContext(
                registrationSessionId: $session->id,
                scenarioKey: $scenarioKey,
                mfaMethod: $mfaMethod,
            ),
        );

        $result = $this->httpClient->postJson(
            environment: $this->config->environment($environment),
            path: $path,
            payload: $payload,
            headers: [
                'accountAuthToken' => $accountAuthToken,
            ],
            bearerToken: $platformToken['access_token'],
            requestSummary: $requestSummary,
        );

        $result = $this->normalizeRegistrationResult(
            $result,
            null,
            (string) $session->accountNumber(),
            $finalizeRegistration,
        );

        $this->eventLogger->complete($event, $result);

        return $result->copyWith(
            responseSummary: array_merge(
                is_array($result->responseSummary) ? $result->responseSummary : [],
                ['carrier_api_event_id' => $event->id],
            ),
        );
    }

    private function normalizeRegistrationResult(
        CarrierApiResult $result,
        ?CarrierAccount $account,
        string $accountNumber,
        bool $finalizeRegistration = true,
    ): CarrierApiResult {
        $responseSummary = array_merge(
            $result->responseSummary ?? [],
            $this->responseAnalyzer->buildDiagnostics($result->data, $result->responseSummary),
        );

        if ($result->success) {
            if (! $finalizeRegistration) {
                return $result->copyWith(
                    data: is_array($result->data) ? $result->data : null,
                    responseSummary: $responseSummary,
                );
            }

            $credentials = $this->responseAnalyzer->extractChildCredentials($result->data);

            if ($credentials !== null) {
                if ($account !== null) {
                    $account->setCredentials([
                        'customer_key' => $credentials['customer_key'],
                        'customer_password' => $credentials['customer_password'],
                    ]);
                    $account->save();
                }

                return $result->copyWith(
                    data: is_array($result->data) ? $result->data : ['output' => $this->responseAnalyzer->output($result->data)],
                    responseSummary: array_merge($responseSummary, ['registered' => true]),
                );
            }

            if ($this->responseAnalyzer->mfaDetected($this->responseAnalyzer->output($result->data))) {
                return $result->copyWith(
                    success: false,
                    data: is_array($result->data) ? $result->data : null,
                    errorCode: 'registration_mfa_required',
                    errorMessage: 'FedEx requires additional merchant verification before registration can finish. Choose a verification method to continue.',
                    responseSummary: $responseSummary,
                );
            }

            return $result->copyWith(
                success: false,
                errorCode: 'registration_incomplete',
                errorMessage: $this->responseAnalyzer->incompleteRegistrationMessage(),
                responseSummary: $responseSummary,
            );
        }

        return $result->copyWith(
            errorMessage: $this->registrationFailureMessage($result, $accountNumber),
            errorCode: $result->errorCode,
            responseSummary: $responseSummary,
        );
    }

    /**
     * Real registration payload for explicit developer use in local/testing only.
     * Never persisted to the database or shown in normal merchant UI.
     *
     * @return array<string, mixed>
     */
    public function debugRegistrationPayload(CarrierAccount $account): array
    {
        $this->assertDebugEnvironment();

        $details = $this->registrationDetailsForAccount($account);
        $accountNumber = $this->resolveAccountNumber($account, $details);

        return $this->buildV2Payload($accountNumber, $details);
    }

    /**
     * Redacted validation summary for FedEx support — no secrets or full account number.
     *
     * @return array<string, mixed>
     */
    public function redactedValidationSummary(CarrierAccount $account): array
    {
        $this->assertDebugEnvironment();

        $details = $this->registrationDetailsForAccount($account);
        $accountNumber = $this->resolveAccountNumber($account, $details);
        $environment = $this->config->environment($account->environment);
        $registrationPath = $this->config->accountRegistrationPath($environment);

        $latestEvent = $account->apiEvents()
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->latest('id')
            ->first();

        $requestSummary = is_array($latestEvent?->request_summary) ? $latestEvent->request_summary : [];
        $responseSummary = is_array($latestEvent?->response_summary) ? $latestEvent->response_summary : [];

        return [
            'exported_at' => now()->toIso8601String(),
            'carrier' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => $environment,
            'endpoint' => $requestSummary['endpoint'] ?? $registrationPath,
            'http_status' => $responseSummary['http_status'] ?? null,
            'fedex_transaction_id' => $responseSummary['fedex_transaction_id'] ?? $latestEvent?->request_id,
            'account_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'account_digit_count' => strlen($accountNumber),
            'customer_name_length' => strlen(trim((string) ($details['company_name'] ?? $details['contact_name'] ?? ''))),
            'country_code' => $requestSummary['country_code'] ?? strtoupper((string) ($details['country_code'] ?? 'US')),
            'state_code' => $requestSummary['state_or_province_code'] ?? strtoupper((string) ($details['state'] ?? '')),
            'postal_code' => $requestSummary['postal_code'] ?? ($details['postal_code'] ?? null),
            'address_line1_present' => filled($details['address_line1'] ?? null),
            'city_present' => filled($details['city'] ?? null),
            'payload_root_keys' => $requestSummary['payload_root_keys'] ?? ['customerName', 'accountNumber', 'address'],
            'address_keys' => $requestSummary['address_keys'] ?? ['streetLines', 'city', 'stateOrProvinceCode', 'postalCode', 'countryCode'],
            'residential_mode' => $requestSummary['residential_mode'] ?? $this->config->accountRegistrationResidentialMode(),
            'residential_sent' => $requestSummary['residential_sent'] ?? false,
            'fedex_error_code' => data_get($responseSummary, 'errors.0.code'),
            'fedex_error_message' => data_get($responseSummary, 'errors.0.message'),
            'note' => 'Full account number, tokens, secrets, phone, and email are redacted from this export.',
            'oauth_note' => 'Platform connection check success only confirms platform API access. FedEx account registration is a separate merchant account validation step.',
        ];
    }

    /**
     * @deprecated Use redactedValidationSummary() for merchant exports.
     *
     * @return array<string, mixed>
     */
    public function redactedRegistrationPayload(CarrierAccount $account): array
    {
        $this->assertDebugEnvironment();

        $payload = $this->debugRegistrationPayload($account);
        $accountNumber = (string) ($payload['accountNumber']['value'] ?? $payload['accountNumber'] ?? '');

        if ($accountNumber !== '') {
            $masked = strlen($accountNumber) >= 4
                ? str_repeat('*', max(0, strlen($accountNumber) - 4)).substr($accountNumber, -4)
                : str_repeat('*', strlen($accountNumber));

            if (is_array($payload['accountNumber'] ?? null)) {
                $payload['accountNumber']['value'] = $masked;
            } else {
                $payload['accountNumber'] = $masked;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationDetailsForAccount(CarrierAccount $account): array
    {
        return array_merge($account->registrationDetails(), [
            'provider_account_number' => $account->provider_account_number
                ?: data_get($account->settings, 'registration.provider_account_number'),
            'residential' => (bool) data_get($account->settings, 'registration.residential', false),
        ]);
    }

    private function assertDebugEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('FedEx registration debug payload is only available in local/testing.');
        }
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    private function resolveAccountNumber(CarrierAccount $account, array $accountDetails): string
    {
        $rawAccountNumber = $account->provider_account_number
            ?: data_get($account->settings, 'registration.provider_account_number')
            ?: data_get($accountDetails, 'provider_account_number')
            ?: data_get($accountDetails, 'account_number');

        return preg_replace('/\D+/', '', (string) $rawAccountNumber);
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     * @return array<string, mixed>
     */
    private function buildV2Payload(string $accountNumber, array $accountDetails): array
    {
        $customerName = trim((string) (
            $accountDetails['company_name']
            ?? $accountDetails['contact_name']
            ?? ''
        ));

        $residentialSetting = (bool) data_get($accountDetails, 'residential', false);
        $streetLines = array_values(array_filter([
            trim((string) ($accountDetails['address_line1'] ?? '')),
            filled($accountDetails['address_line2'] ?? null) ? trim((string) $accountDetails['address_line2']) : null,
        ]));
        $address = [
            'streetLines' => $streetLines,
            'city' => strtoupper(trim((string) ($accountDetails['city'] ?? ''))),
            'stateOrProvinceCode' => strtoupper(trim((string) ($accountDetails['state'] ?? ''))),
            'postalCode' => $this->resolveRegistrationPostalCode($accountDetails),
            'countryCode' => strtoupper(trim((string) ($accountDetails['country_code'] ?? 'US'))),
        ];

        return [
            'customerName' => $customerName,
            'accountNumber' => [
                'value' => $accountNumber,
            ],
            'address' => $this->applyRegistrationResidential($address, $residentialSetting),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    private function applyRegistrationResidential(array $address, bool $residentialSetting): array
    {
        $mode = $this->config->accountRegistrationResidentialMode();

        if ($mode === 'boolean') {
            $address['residential'] = $residentialSetting;
        } elseif ($mode === 'string') {
            $address['residential'] = $residentialSetting ? 'true' : 'false';
        }

        return $address;
    }

    /**
     * @param  array<string, mixed>  $accountDetails
     */
    private function resolveRegistrationPostalCode(array $accountDetails): string
    {
        if (filled($accountDetails['registration_postal_code_raw'] ?? null)) {
            return (string) $accountDetails['registration_postal_code_raw'];
        }

        $postal = trim((string) ($accountDetails['postal_code'] ?? ''));
        $digits = preg_replace('/\D+/', '', $postal) ?? '';

        if (strlen($digits) === 9 || strlen($digits) === 5) {
            return $digits;
        }

        return $postal;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $accountDetails
     * @return array<string, mixed>
     */
    private function buildRequestSummary(
        string $registrationPath,
        string $accountNumber,
        array $payload,
        array $accountDetails,
    ): array {
        $residentialSetting = (bool) data_get($accountDetails, 'residential', false);
        $residentialMode = $this->config->accountRegistrationResidentialMode();
        $residentialSent = array_key_exists('residential', $payload['address'] ?? []);
        $postalSent = (string) ($payload['address']['postalCode'] ?? '');
        $postalDigits = preg_replace('/\D+/', '', $postalSent) ?? '';

        return [
            'endpoint' => $registrationPath,
            'account_number_present' => $accountNumber !== '',
            'account_number_digits_len' => strlen($accountNumber),
            'account_number_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'account_number_shape' => 'object_value',
            'customer_name_present' => filled($payload['customerName'] ?? null),
            'customer_name_length' => strlen((string) ($payload['customerName'] ?? '')),
            'street_lines_count' => count($payload['address']['streetLines'] ?? []),
            'city' => $payload['address']['city'] ?? null,
            'city_present' => filled($payload['address']['city'] ?? null),
            'state_or_province_code' => $payload['address']['stateOrProvinceCode'] ?? null,
            'state_present' => filled($payload['address']['stateOrProvinceCode'] ?? null),
            'postal_code_input' => $accountDetails['postal_code'] ?? null,
            'postal_code_sent' => $postalSent !== '' ? $postalSent : null,
            'postal_code_digits_len' => strlen($postalDigits),
            'postal_code' => $postalSent !== '' ? $postalSent : null,
            'postal_code_present' => filled($postalSent),
            'country_code' => $payload['address']['countryCode'] ?? null,
            'residential_setting' => $residentialSetting,
            'residential_sent' => $residentialSent,
            'residential_mode' => $residentialMode,
            'payload_root_keys' => array_keys($payload),
            'address_keys' => array_keys($payload['address'] ?? []),
        ];
    }

    private function registrationFailureMessage(CarrierApiResult $result, string $accountNumber): string
    {
        foreach ((array) data_get($result->data, 'errors', []) as $error) {
            if (! is_array($error)) {
                continue;
            }

            if (($error['code'] ?? null) === 'SESSION.EXPIRED.ERROR') {
                return 'FedEx account authorization expired. The validation workspace refreshed authorization automatically — run invoice validation again if this persists.';
            }
        }

        $httpStatus = (int) ($result->responseSummary['http_status'] ?? 0);

        if ($httpStatus === 422) {
            return 'FedEx rejected the account registration details. Check that the account number, account name, and address match your FedEx account records exactly. If they are correct, this account may require FedEx support or integrator enablement.';
        }

        if ($httpStatus === 400 && strlen($accountNumber) === 9) {
            return 'FedEx rejected the account registration details. Confirm the 9-digit account number, account owner name, and billing address exactly match FedEx records.';
        }

        return $result->errorMessage ?? 'FedEx account registration failed.';
    }

    private function pinScenarioKey(string $phase, ?string $method): string
    {
        $method = strtolower(trim((string) $method));

        return match ($method) {
            'sms' => $phase === 'generation'
                ? CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_SMS
                : CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_SMS,
            'email' => $phase === 'generation'
                ? CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_EMAIL
                : CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_EMAIL,
            'call', 'phone', 'phone_call' => $phase === 'generation'
                ? CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_CALL
                : CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_CALL,
            default => $phase === 'generation'
                ? CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_SMS
                : CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_SMS,
        };
    }
}
