<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Support\Facades\Cache;

class FedExMerchantCredentialsOAuthService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
    ) {}

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

        $cacheKey = $this->tokenCacheKey($account, $environment);

        if ($fresh) {
            Cache::forget($cacheKey);
        } elseif ($cached = $this->cachedTokenPayload($cacheKey)) {
            return CarrierApiResult::success(
                data: $cached,
                requestSummary: array_merge($this->safeRequestSummary($account, $environment), [
                    'cached' => true,
                ]),
                responseSummary: [
                    'http_status' => 200,
                    'fedex_transaction_id' => null,
                    'cached' => true,
                ],
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

        if ($result->success) {
            $payload = $this->tokenPayloadFromResult($result);

            if ($payload !== null) {
                Cache::put($cacheKey, $payload, max(60, $payload['expires_in'] - 120));
            }
        }

        return $result;
    }

    public function clearTokenCache(CarrierAccount $account): void
    {
        $environment = $this->config->environment($account->environment);
        Cache::forget($this->tokenCacheKey($account, $environment));
    }

    public function tokenCacheKey(CarrierAccount $account, ?string $environment = null): string
    {
        $environment = $this->config->environment($environment ?? $account->environment);
        $clientId = (string) ($account->merchantFedExClientId() ?? '');

        return implode(':', [
            'fedex',
            'merchant_developer',
            $environment,
            (string) $account->id,
            hash('sha256', $clientId),
        ]);
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

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    private function cachedTokenPayload(string $cacheKey): ?array
    {
        /** @var array{access_token?: string, token_type?: string, expires_in?: int}|null $cached */
        $cached = Cache::get($cacheKey);

        if (! is_array($cached) || ! filled($cached['access_token'] ?? null)) {
            return null;
        }

        return [
            'access_token' => (string) $cached['access_token'],
            'token_type' => strtolower((string) ($cached['token_type'] ?? 'bearer')),
            'expires_in' => (int) ($cached['expires_in'] ?? 3600),
        ];
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    private function tokenPayloadFromResult(CarrierApiResult $result): ?array
    {
        $data = $result->data ?? [];
        $accessToken = FedExHttpClient::normalizeBearerToken((string) ($data['access_token'] ?? ''));

        if ($accessToken === null || $accessToken === '') {
            return null;
        }

        return [
            'access_token' => $accessToken,
            'token_type' => strtolower((string) ($data['token_type'] ?? 'bearer')),
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }
}
