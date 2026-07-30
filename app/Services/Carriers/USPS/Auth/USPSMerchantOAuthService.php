<?php

namespace App\Services\Carriers\USPS\Auth;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSHttpClient;
use App\Services\Carriers\USPS\Support\USPSMerchantOAuthException;
use App\Services\Carriers\USPS\Support\USPSOAuthSubjectExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class USPSMerchantOAuthService
{
    private const STATE_TTL_SECONDS = 900;

    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly USPSOAuthTokenService $platformOAuthTokenService,
        private readonly USPSOAuthSubjectExtractor $subjectExtractor,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    public function isAvailable(): bool
    {
        return $this->config->merchantOAuthEnabled();
    }

    public function resolveAuthorizeRedirectUrl(CarrierAccount $account, int $sessionUserId): string
    {
        abort_unless($this->isAvailable(), 404);
        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        if (! $account->hasUspsMerchantIdentifiers()) {
            throw new USPSMerchantOAuthException(
                'Save your USPS CRID, MID, and EPA before authorizing with USPS.',
                'identifiers_missing',
            );
        }

        if ($account->hasMerchantOAuthSubjectId()) {
            return $this->resolveReauthorizationRedirectUrl($account, $sessionUserId);
        }

        return $this->resolveCopAuthorizationRedirectUrl($account, $sessionUserId);
    }

    /**
     * @return array{store_id: int, carrier_account_id: int, user_id: int}|null
     */
    public function resolveOAuthState(string $state): ?array
    {
        $state = trim($state);

        if ($state === '') {
            return null;
        }

        /** @var array{store_id: int, carrier_account_id: int, user_id: int}|null $payload */
        $payload = Cache::get($this->stateCacheKey($state));

        return is_array($payload) ? $payload : null;
    }

    public function forgetOAuthState(string $state): void
    {
        Cache::forget($this->stateCacheKey($state));
    }

    public function exchangeAuthorizationCode(Store $store, CarrierAccount $account, string $code): CarrierApiResult
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
                'grant_type' => 'authorization_code',
                'token_received' => false,
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $result = $this->httpClient->postJson(
            path: $this->config->oauthPath(),
            payload: [
                'client_id' => $this->config->merchantOAuthConsumerKey(),
                'client_secret' => $this->config->merchantOAuthConsumerSecret(),
                'code' => $code,
                'redirect_uri' => $this->config->oauthRedirectUrl(),
                'grant_type' => 'authorization_code',
            ],
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
                'grant_type' => 'authorization_code',
                'token_received' => false,
            ],
        );

        if ($result->success) {
            $tokens = $this->tokenPayloadFromResult($result);
            $subjectId = $this->subjectExtractor->extractFromTokenResponse($result->data ?? []);

            if ($tokens !== null) {
                $account->setMerchantOAuthTokens(
                    accessToken: $tokens['access_token'],
                    refreshToken: $tokens['refresh_token'],
                    expiresIn: $tokens['expires_in'],
                    subjectId: $subjectId,
                );
            } elseif ($subjectId !== null) {
                $account->setMerchantOAuthSubjectId($subjectId);
            }

            $result = CarrierApiResult::success(
                data: $result->data,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: array_merge($result->requestSummary ?? [], [
                    'token_received' => $tokens !== null,
                    'subject_received' => $subjectId !== null,
                    'expires_in_present' => filled($result->data['expires_in'] ?? null),
                    'refresh_token_present' => filled($result->data['refresh_token'] ?? null),
                ]),
                responseSummary: $result->responseSummary,
            );
        }

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    public function refreshAccessToken(Store $store, CarrierAccount $account): CarrierApiResult
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        $refreshToken = $account->merchantOAuthRefreshToken();

        if ($refreshToken === null) {
            return CarrierApiResult::failure(
                message: 'USPS authorization refresh token is missing. Reauthorize with USPS.',
                code: 'refresh_token_missing',
            );
        }

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
                'grant_type' => 'refresh_token',
                'token_received' => false,
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $result = $this->httpClient->postJson(
            path: $this->config->oauthPath(),
            payload: [
                'client_id' => $this->config->merchantOAuthConsumerKey(),
                'client_secret' => $this->config->merchantOAuthConsumerSecret(),
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
                'grant_type' => 'refresh_token',
                'token_received' => false,
            ],
        );

        if ($result->success) {
            $tokens = $this->tokenPayloadFromResult($result);
            $subjectId = $this->subjectExtractor->extractFromTokenResponse($result->data ?? []);

            if ($tokens !== null) {
                $account->setMerchantOAuthTokens(
                    accessToken: $tokens['access_token'],
                    refreshToken: $tokens['refresh_token'] ?? $refreshToken,
                    expiresIn: $tokens['expires_in'],
                    subjectId: $subjectId ?? $account->merchantOAuthSubjectId(),
                );
            }
        }

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    public function revokeRefreshToken(Store $store, CarrierAccount $account): CarrierApiResult
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        $refreshToken = $account->merchantOAuthRefreshToken();

        if ($refreshToken === null) {
            return CarrierApiResult::success(
                data: ['revoked' => false, 'reason' => 'refresh_token_missing'],
                requestSummary: ['revoked' => false],
                responseSummary: ['http_status' => null],
            );
        }

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_USPS_OAUTH_REVOKE,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthRevokePath(),
                'environment' => $this->config->environment(),
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $result = $this->httpClient->postJson(
            path: $this->config->oauthRevokePath(),
            payload: [
                'client_id' => $this->config->merchantOAuthConsumerKey(),
                'client_secret' => $this->config->merchantOAuthConsumerSecret(),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ],
            requestSummary: [
                'endpoint' => $this->config->oauthRevokePath(),
                'environment' => $this->config->environment(),
            ],
        );

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    public function accessTokenForAccount(Store $store, CarrierAccount $account, bool $fresh = false): ?string
    {
        if (! $account->hasMerchantOAuthTokens()) {
            return null;
        }

        if (! $fresh && ! $account->merchantOAuthAccessTokenExpired()) {
            return $account->merchantOAuthAccessToken();
        }

        $refreshResult = $this->refreshAccessToken($store, $account->fresh());

        if (! $refreshResult->success) {
            return null;
        }

        return $account->fresh()->merchantOAuthAccessToken();
    }

    private function resolveCopAuthorizationRedirectUrl(CarrierAccount $account, int $sessionUserId): string
    {
        $copUrl = $this->config->merchantCopAuthorizationUrl();

        if ($copUrl === null) {
            throw new USPSMerchantOAuthException(
                'USPS merchant COP authorization is not configured yet. Use the manual portal confirmation until USPS provides the official Platform authorization link.',
                'cop_authorization_unavailable',
            );
        }

        if (! $this->config->merchantOAuthRedirectUrlIsValid()) {
            throw new USPSMerchantOAuthException(
                'USPS OAuth callback must use an HTTPS URL registered in the USPS developer portal. For local testing, set USPS_MERCHANT_OAUTH_ALLOW_HTTP_REDIRECT=true and use http://127.0.0.1 or http://localhost.',
                'redirect_uri_invalid',
            );
        }

        $state = Str::random(48);
        $this->storeOAuthState($state, (int) $account->store_id, (int) $account->id, $sessionUserId);

        $query = http_build_query(array_filter([
            'redirect_uri' => $this->config->oauthRedirectUrl(),
            'state' => $state,
        ]));

        $separator = str_contains($copUrl, '?') ? '&' : '?';

        return $copUrl.$separator.$query;
    }

    private function resolveReauthorizationRedirectUrl(CarrierAccount $account, int $sessionUserId): string
    {
        $subjectId = $account->merchantOAuthSubjectId();

        if ($subjectId === null) {
            throw new USPSMerchantOAuthException(
                'USPS merchant OAuth subject is missing. Complete first-time COP authorization before reauthorizing.',
                'oauth_subject_missing',
            );
        }

        if (! $this->config->merchantOAuthRedirectUrlIsValid()) {
            throw new USPSMerchantOAuthException(
                'USPS OAuth callback must use an HTTPS URL registered in the USPS developer portal.',
                'redirect_uri_invalid',
            );
        }

        $platformToken = $this->platformOAuthTokenService->accessToken();

        if ($platformToken === null) {
            throw new USPSMerchantOAuthException(
                'Unable to obtain the platform USPS access token required to start merchant reauthorization.',
                'platform_token_missing',
            );
        }

        $state = Str::random(48);
        $this->storeOAuthState($state, (int) $account->store_id, (int) $account->id, $sessionUserId);

        $query = [
            'client_id' => $this->config->merchantOAuthConsumerKey(),
            'redirect_uri' => $this->config->oauthRedirectUrl(),
            'response_type' => 'code',
            'user_id' => $subjectId,
            'state' => $state,
        ];

        $scope = $this->config->merchantOAuthScope();

        if ($scope !== null) {
            $query['scope'] = $scope;
        }

        $authorizePath = '/'.ltrim($this->config->oauthAuthorizePath(), '/');
        $authorizeUrl = $this->config->baseUrl().$authorizePath.'?'.http_build_query($query);

        try {
            $response = Http::connectTimeout($this->config->connectTimeout())
                ->timeout($this->config->requestTimeout())
                ->acceptJson()
                ->withToken($platformToken['access_token'])
                ->withoutRedirecting()
                ->get($authorizeUrl);

            if ($response->redirect()) {
                $location = trim((string) $response->header('Location'));

                if ($location !== '') {
                    return $location;
                }
            }

            if ($response->successful()) {
                $payload = $response->json();

                if (is_array($payload)) {
                    foreach (['authorization_url', 'redirect_url', 'location'] as $key) {
                        $candidate = trim((string) ($payload[$key] ?? ''));

                        if ($candidate !== '') {
                            return $candidate;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Fall back to direct redirect URL below.
        }

        return $authorizeUrl;
    }

    private function storeOAuthState(string $state, int $storeId, int $carrierAccountId, int $userId): void
    {
        Cache::put($this->stateCacheKey($state), [
            'store_id' => $storeId,
            'carrier_account_id' => $carrierAccountId,
            'user_id' => $userId,
        ], self::STATE_TTL_SECONDS);
    }

    private function stateCacheKey(string $state): string
    {
        return 'usps_merchant_oauth_state:'.$state;
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int}|null
     */
    private function tokenPayloadFromResult(CarrierApiResult $result): ?array
    {
        $data = $result->data ?? [];
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            return null;
        }

        $refreshToken = filled($data['refresh_token'] ?? null)
            ? (string) $data['refresh_token']
            : null;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }
}
