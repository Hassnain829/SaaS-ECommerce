<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Support\Arr;

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

        $requestSummary = [
            'endpoint' => $registrationPath,
            'account_number' => (string) ($accountDetails['provider_account_number'] ?? $account->provider_account_number),
            'company_name' => (string) ($accountDetails['company_name'] ?? ''),
            'city' => (string) ($accountDetails['city'] ?? ''),
            'country_code' => (string) ($accountDetails['country_code'] ?? ''),
        ];

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            account: $account,
            requestSummary: $requestSummary,
            environment: $environment,
        );

        $payload = [
            'customerName' => (string) ($accountDetails['company_name'] ?? ''),
            'accountNumber' => [
                'key' => '',
                'value' => (string) ($accountDetails['provider_account_number'] ?? $account->provider_account_number),
            ],
            'address' => [
                'streetLines' => array_values(array_filter([
                    (string) ($accountDetails['address_line1'] ?? ''),
                ])),
                'city' => (string) ($accountDetails['city'] ?? ''),
                'stateOrProvinceCode' => (string) ($accountDetails['state'] ?? ''),
                'postalCode' => (string) ($accountDetails['postal_code'] ?? ''),
                'countryCode' => strtoupper((string) ($accountDetails['country_code'] ?? '')),
                'residential' => (bool) ($accountDetails['residential'] ?? false),
            ],
        ];

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
                message: $result->errorMessage ?? 'FedEx account registration failed.',
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
}
