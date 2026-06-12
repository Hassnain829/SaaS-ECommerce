<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Services\Carriers\DTO\CarrierApiResult;

class FedExMerchantCredentialsOAuthService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
    ) {
    }

    public function fetchTokenResult(CarrierAccount $account, bool $fresh = false): CarrierApiResult
    {
        abort_unless($account->hasMerchantFedExDeveloperCredentials(), 404);

        $environment = $this->config->environment($account->environment);
        $clientId = $account->merchantFedExClientId();
        $clientSecret = $account->merchantFedExClientSecret();

        if (! filled($clientId) || ! filled($clientSecret)) {
            return CarrierApiResult::failure(
                message: 'FedEx API credentials are missing. Save your API key and secret, then run the connection check again.',
                code: 'missing_merchant_credentials',
                requestSummary: $this->safeRequestSummary($account, $environment),
            );
        }

        $result = $this->httpClient->postForm(
            environment: $environment,
            path: $this->config->oauthPath(),
            payload: [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            retry: true,
            requestSummary: $this->safeRequestSummary($account, $environment),
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeRequestSummary(CarrierAccount $account, string $environment): array
    {
        $clientId = (string) ($account->merchantFedExClientId() ?? '');
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return [
            'endpoint' => $this->config->oauthPath(),
            'environment' => $environment,
            'client_id_present' => $clientId !== '',
            'client_id_last4' => strlen($clientId) >= 4 ? substr($clientId, -4) : null,
            'account_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
            'grant_type' => 'client_credentials',
            'credentials_mode' => 'merchant_developer',
        ];
    }
}
