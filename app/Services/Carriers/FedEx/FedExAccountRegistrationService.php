<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Support\Arr;
use RuntimeException;

class FedExAccountRegistrationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

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
        $payload = $this->buildV2Payload($accountNumber, $accountDetails);
        $requestSummary = $this->buildRequestSummary($registrationPath, $accountNumber, $payload, $accountDetails);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $account,
            requestSummary: $requestSummary,
            environment: $environment,
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

        if ($result->success) {
            $output = Arr::get($result->data, 'output', $result->data ?? []);
            $customerKey = (string) (Arr::get($output, 'child_Key') ?? Arr::get($output, 'child_key') ?? Arr::get($output, 'customerKey') ?? '');
            $customerPassword = (string) (Arr::get($output, 'childSecret') ?? Arr::get($output, 'child_secret') ?? Arr::get($output, 'customerPassword') ?? '');

            if ($customerKey === '' || $customerPassword === '') {
                if (Arr::get($output, 'mfaOptions.mfaRequired') === true) {
                    $result = CarrierApiResult::failure(
                        message: 'FedEx account registration requires additional merchant verification (MFA). Complete MFA in FedEx, then retry the connection test.',
                        code: 'registration_mfa_required',
                        requestId: $result->requestId,
                        durationMs: $result->durationMs,
                        requestSummary: $result->requestSummary,
                        responseSummary: $result->responseSummary,
                    );
                } else {
                    $result = CarrierApiResult::failure(
                        message: 'FedEx account registration did not return merchant credentials.',
                        code: 'registration_incomplete',
                        requestId: $result->requestId,
                        durationMs: $result->durationMs,
                        requestSummary: $result->requestSummary,
                        responseSummary: $result->responseSummary,
                    );
                }
            } else {
                $account->setCredentials([
                    'customer_key' => $customerKey,
                    'customer_password' => $customerPassword,
                ]);
                $account->save();

                $result = CarrierApiResult::success(
                    data: ['registered' => true],
                    requestId: $result->requestId,
                    durationMs: $result->durationMs,
                    requestSummary: $result->requestSummary,
                    responseSummary: array_merge($result->responseSummary ?? [], ['registered' => true]),
                );
            }
        } else {
            $result = CarrierApiResult::failure(
                message: $this->registrationFailureMessage($result, $accountNumber),
                code: $result->errorCode,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $result->responseSummary,
            );
        }

        $this->eventLogger->complete($event, $result);

        return $result;
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
     * Redacted registration payload for FedEx Developer Portal API Validation.
     *
     * @return array<string, mixed>
     */
    public function redactedRegistrationPayload(CarrierAccount $account): array
    {
        $this->assertDebugEnvironment();

        $payload = $this->debugRegistrationPayload($account);
        $accountNumber = (string) ($payload['accountNumber'] ?? '');

        if ($accountNumber !== '') {
            $payload['accountNumber'] = strlen($accountNumber) >= 4
                ? str_repeat('*', max(0, strlen($accountNumber) - 4)).substr($accountNumber, -4)
                : str_repeat('*', strlen($accountNumber));
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
        $address = [
            'streetLines' => array_values(array_filter([
                trim((string) ($accountDetails['address_line1'] ?? '')),
            ])),
            'city' => strtoupper(trim((string) ($accountDetails['city'] ?? ''))),
            'stateOrProvinceCode' => strtoupper(trim((string) ($accountDetails['state'] ?? ''))),
            'postalCode' => trim((string) ($accountDetails['postal_code'] ?? '')),
            'countryCode' => strtoupper(trim((string) ($accountDetails['country_code'] ?? 'US'))),
        ];

        return [
            'customerName' => $customerName,
            'accountNumber' => $accountNumber,
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

        return [
            'endpoint' => $registrationPath,
            'account_number_present' => $accountNumber !== '',
            'account_number_digits_len' => strlen($accountNumber),
            'account_number_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'customer_name_present' => filled($payload['customerName'] ?? null),
            'customer_name_length' => strlen((string) ($payload['customerName'] ?? '')),
            'street_lines_count' => count($payload['address']['streetLines'] ?? []),
            'city' => $payload['address']['city'] ?? null,
            'city_present' => filled($payload['address']['city'] ?? null),
            'state_or_province_code' => $payload['address']['stateOrProvinceCode'] ?? null,
            'state_present' => filled($payload['address']['stateOrProvinceCode'] ?? null),
            'postal_code' => $payload['address']['postalCode'] ?? null,
            'postal_code_present' => filled($payload['address']['postalCode'] ?? null),
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
        $httpStatus = (int) ($result->responseSummary['http_status'] ?? 0);

        if ($httpStatus === 422) {
            return 'FedEx rejected one of the registration fields. Confirm the account owner name and billing address exactly match FedEx records. If the details are correct, this FedEx account may not be eligible for Integrator Credential Registration without FedEx support.';
        }

        if ($httpStatus === 400 && strlen($accountNumber) === 9) {
            return 'FedEx rejected the account registration details. Confirm the 9-digit account number, account owner name, and billing address exactly match FedEx records.';
        }

        return $result->errorMessage ?? 'FedEx account registration failed.';
    }
}
