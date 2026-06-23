<?php

namespace App\Services\Carriers\USPS\Auth;

use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSHttpClient;
use Illuminate\Support\Facades\Cache;

class USPSOAuthTokenService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
    ) {}

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    public function accessToken(bool $fresh = false): ?array
    {
        $result = $this->fetchTokenResult($fresh);

        if (! $result->success) {
            return null;
        }

        return $this->tokenPayloadFromResult($result);
    }

    public function fetchTokenResult(bool $fresh = false): CarrierApiResult
    {
        if (! $this->config->isConfigured()) {
            return CarrierApiResult::failure(
                message: 'USPS testing connection is not available on this platform environment yet. Contact the platform admin.',
                code: 'platform_config_missing',
                requestSummary: [
                    'endpoint' => $this->config->oauthPath(),
                    'environment' => $this->config->environment(),
                    'token_received' => false,
                    'expires_in_present' => false,
                ],
            );
        }

        $cacheKey = $this->cacheKey();

        if (! $fresh) {
            /** @var array{access_token: string, token_type: string, expires_in: int}|null $cached */
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && filled($cached['access_token'] ?? null)) {
                return CarrierApiResult::success(
                    data: $cached,
                    requestSummary: [
                        'endpoint' => $this->config->oauthPath(),
                        'environment' => $this->config->environment(),
                        'token_received' => true,
                        'expires_in_present' => true,
                        'cached' => true,
                    ],
                    responseSummary: [
                        'http_status' => 200,
                        'cached' => true,
                    ],
                );
            }
        }

        $result = $this->httpClient->postJson(
            path: $this->config->oauthPath(),
            payload: [
                'client_id' => $this->config->consumerKey(),
                'client_secret' => $this->config->consumerSecret(),
                'grant_type' => 'client_credentials',
            ],
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
                'token_received' => false,
                'expires_in_present' => false,
            ],
        );

        if ($result->success) {
            $payload = $this->tokenPayloadFromResult($result);

            if ($payload !== null) {
                Cache::put($cacheKey, $payload, max(60, $payload['expires_in'] - 120));
            }

            return CarrierApiResult::success(
                data: $result->data,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: array_merge($result->requestSummary ?? [], [
                    'token_received' => $payload !== null,
                    'expires_in_present' => filled($result->data['expires_in'] ?? null),
                ]),
                responseSummary: $result->responseSummary,
            );
        }

        return CarrierApiResult::failure(
            message: $result->errorMessage ?? 'USPS OAuth token request failed.',
            code: $result->errorCode,
            requestId: $result->requestId,
            durationMs: $result->durationMs,
            requestSummary: array_merge($result->requestSummary ?? [], [
                'token_received' => false,
                'expires_in_present' => false,
            ]),
            responseSummary: $result->responseSummary,
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

    private function cacheKey(): string
    {
        return implode(':', [
            'usps',
            $this->config->environment(),
            'platform_oauth',
        ]);
    }
}
