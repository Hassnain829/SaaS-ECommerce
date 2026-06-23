<?php

namespace App\Services\Carriers\FedEx\Auth;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExHttpClient;
use Illuminate\Support\Facades\Cache;

class FedExOAuthTokenService
{
    public const TOKEN_TYPE_PLATFORM = 'platform';

    public const TOKEN_TYPE_MERCHANT = 'merchant';

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
    ) {}

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    public function platformToken(?string $environment = null): ?array
    {
        $result = $this->fetchPlatformTokenResult($environment);

        if (! $result->success) {
            return null;
        }

        return $this->tokenPayloadFromResult($result);
    }

    public function fetchPlatformTokenResult(?string $environment = null, bool $fresh = false): CarrierApiResult
    {
        $environment = $this->config->environment($environment);

        if (! $this->config->isConfigured($environment)) {
            return CarrierApiResult::failure(
                message: 'FedEx sandbox connection is not available on this platform environment yet.',
                code: 'platform_config_missing',
                requestSummary: ['endpoint' => $this->config->oauthPath()],
            );
        }

        $cacheKey = $this->cacheKey($environment, self::TOKEN_TYPE_PLATFORM);

        if (! $fresh) {
            /** @var array{access_token: string, token_type: string, expires_in: int}|null $cached */
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && filled($cached['access_token'] ?? null)) {
                return CarrierApiResult::success(
                    data: $cached,
                    requestSummary: [
                        'endpoint' => $this->config->oauthPath(),
                        'cached' => true,
                    ],
                    responseSummary: [
                        'http_status' => 200,
                        'fedex_transaction_id' => null,
                        'cached' => true,
                    ],
                );
            }
        }

        $result = $this->requestToken($environment, [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->clientId($environment),
            'client_secret' => $this->config->clientSecret($environment),
        ]);

        if ($result->success) {
            $this->rememberToken($cacheKey, $result);
        }

        return $result;
    }

    /**
     * @return array{token: CarrierApiResult, payload: array<string, mixed>|null}
     */
    public function merchantToken(CarrierAccount $account): array
    {
        $result = $this->fetchMerchantTokenResult($account);

        return [
            'token' => $result,
            'payload' => $result->success ? $this->tokenPayloadFromResult($result) : null,
        ];
    }

    public function fetchMerchantTokenResult(CarrierAccount $account): CarrierApiResult
    {
        $environment = $this->config->environment($account->environment);
        $credentials = $account->credentials();

        $result = $this->requestToken($environment, [
            'grant_type' => 'csp_credentials',
            'client_id' => $this->config->clientId($environment),
            'client_secret' => $this->config->clientSecret($environment),
            'child_key' => (string) ($credentials['customer_key'] ?? ''),
            'child_secret' => (string) ($credentials['customer_password'] ?? ''),
        ]);

        if ($result->success) {
            $cacheKey = $this->cacheKey($environment, self::TOKEN_TYPE_MERCHANT, $account->id);
            $this->rememberToken($cacheKey, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestToken(string $environment, array $payload): CarrierApiResult
    {
        return $this->httpClient->postForm(
            environment: $environment,
            path: $this->config->oauthPath(),
            payload: $payload,
            retry: true,
            requestSummary: ['endpoint' => $this->config->oauthPath()],
        );
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    private function tokenPayloadFromResult(CarrierApiResult $result): ?array
    {
        $data = $result->data ?? [];
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            return null;
        }

        return [
            'access_token' => $accessToken,
            'token_type' => strtolower((string) ($data['token_type'] ?? 'bearer')),
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    private function rememberToken(string $cacheKey, CarrierApiResult $result): ?array
    {
        $payload = $this->tokenPayloadFromResult($result);

        if ($payload === null) {
            return null;
        }

        Cache::put($cacheKey, $payload, max(60, $payload['expires_in'] - 120));

        return $payload;
    }

    private function cacheKey(string $environment, string $tokenType, ?int $carrierAccountId = null): string
    {
        return implode(':', array_filter([
            'fedex',
            $environment,
            $tokenType,
            $carrierAccountId,
        ]));
    }
}
