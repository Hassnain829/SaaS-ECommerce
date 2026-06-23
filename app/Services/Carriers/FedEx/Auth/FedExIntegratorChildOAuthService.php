<?php

namespace App\Services\Carriers\FedEx\Auth;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExHttpClient;
use Illuminate\Support\Facades\Cache;

class FedExIntegratorChildOAuthService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOAuthTokenService $oauthTokenService,
    ) {}

    public function fetchTokenResult(CarrierAccount $account, bool $fresh = false): CarrierApiResult
    {
        abort_unless($account->usesFedExIntegratorProvider(), 404);
        abort_unless($account->hasLegacyFedExChildCredentials(), 422);

        $environment = $this->config->environment($account->environment);
        $cacheKey = $this->tokenCacheKey($account, $environment);

        if ($fresh) {
            Cache::forget($cacheKey);
        } elseif ($cached = $this->cachedTokenPayload($cacheKey)) {
            return CarrierApiResult::success(
                data: $cached,
                requestSummary: [
                    'endpoint' => $this->config->oauthPath(),
                    'environment' => $environment,
                    'credentials_mode' => 'integrator_child',
                    'account_last4' => $this->accountLast4($account),
                    'cached' => true,
                ],
                responseSummary: [
                    'http_status' => 200,
                    'fedex_transaction_id' => null,
                    'cached' => true,
                ],
            );
        }

        if (! $this->config->isConfigured($environment)) {
            return CarrierApiResult::failure(
                message: 'FedEx platform credentials are not configured. Contact the platform administrator.',
                code: 'platform_config_missing',
                requestSummary: [
                    'endpoint' => $this->config->oauthPath(),
                    'environment' => $environment,
                    'credentials_mode' => 'integrator_child',
                ],
            );
        }

        $result = $this->oauthTokenService->fetchMerchantTokenResult($account);

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
        $childKey = (string) ($account->credentials()['customer_key'] ?? '');

        return implode(':', [
            'fedex',
            'integrator_child',
            $environment,
            (string) $account->id,
            hash('sha256', $childKey),
        ]);
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

    private function accountLast4(CarrierAccount $account): ?string
    {
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null;
    }
}
