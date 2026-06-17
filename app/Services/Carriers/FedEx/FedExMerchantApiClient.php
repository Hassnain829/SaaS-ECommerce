<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;

class FedExMerchantApiClient
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
        private readonly FedExMerchantCredentialsOAuthService $oauthService,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

    public function assertMerchantCredentialsAccount(CarrierAccount $account): void
    {
        abort_unless($account->isFedEx(), 404);
        abort_unless($account->usesMerchantFedExDeveloperCredentials(), 404);
        abort_unless($account->hasMerchantFedExDeveloperCredentials(), 422);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestSummary
     */
    public function postJson(
        Store $store,
        CarrierAccount $account,
        string $action,
        string $path,
        array $payload,
        array $requestSummary,
    ): CarrierApiResult {
        $this->assertMerchantCredentialsAccount($account);
        $account->loadMissing('store');

        $environment = $this->config->environment($account->environment);
        $oauthSummary = $this->oauthRequestSummary($account, $environment);

        $oauthEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: $oauthSummary,
            environment: $account->environment,
        );

        $oauthResult = $this->oauthService->fetchTokenResult($account, fresh: true);
        $this->eventLogger->complete($oauthEvent, $oauthResult);

        if (! $oauthResult->success) {
            return CarrierApiResult::failure(
                message: $oauthResult->errorMessage ?? 'FedEx authentication failed. Run the connection check first.',
                code: $oauthResult->errorCode ?? 'oauth_failed',
                requestSummary: array_merge($requestSummary, ['oauth_failed' => true]),
                responseSummary: $oauthResult->responseSummary,
            );
        }

        $accessToken = (string) ($oauthResult->data['access_token'] ?? '');

        if ($accessToken === '') {
            return CarrierApiResult::failure(
                message: 'FedEx authentication did not return an access token.',
                code: 'missing_access_token',
                requestSummary: $requestSummary,
            );
        }

        $apiEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: $action,
            account: $account,
            requestSummary: $requestSummary,
            environment: $account->environment,
        );

        $apiResult = $this->httpClient->postJson(
            environment: $environment,
            path: $path,
            payload: $payload,
            bearerToken: $accessToken,
            requestSummary: $requestSummary,
        );

        $this->eventLogger->complete($apiEvent, $apiResult);

        return $apiResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function oauthRequestSummary(CarrierAccount $account, string $environment): array
    {
        $clientId = (string) ($account->merchantFedExClientId() ?? '');
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return [
            'endpoint' => $this->config->oauthPath(),
            'environment' => $environment,
            'client_id_present' => $clientId !== '',
            'client_id_last4' => strlen($clientId) >= 4 ? substr($clientId, -4) : null,
            'account_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'credentials_mode' => 'merchant_developer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function baseRequestSummary(CarrierAccount $account, string $endpoint): array
    {
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return [
            'endpoint' => $endpoint,
            'environment' => $this->config->environment($account->environment),
            'action' => 'merchant_api_check',
            'account_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'client_id_last4' => $this->clientIdLast4($account),
            'credentials_mode' => 'merchant_developer',
        ];
    }

    private function clientIdLast4(CarrierAccount $account): ?string
    {
        $clientId = (string) ($account->merchantFedExClientId() ?? '');

        return strlen($clientId) >= 4 ? substr($clientId, -4) : null;
    }
}
