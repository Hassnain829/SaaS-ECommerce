<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;

class FedExMerchantApiClient
{
    private const OAUTH_REJECTION_MESSAGE = 'FedEx rejected the OAuth token for this request. Reconnect the FedEx credentials or verify the API key, secret, environment, and project permissions.';

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExHttpClient $httpClient,
        private readonly FedExMerchantCredentialsOAuthService $merchantOAuthService,
        private readonly FedExIntegratorChildOAuthService $integratorChildOAuthService,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    public function assertFedExApiAccount(CarrierAccount $account): void
    {
        abort_unless($account->isFedEx(), 404);

        if ($account->usesFedExIntegratorProvider()) {
            abort_unless($account->hasLegacyFedExChildCredentials(), 422);

            return;
        }

        if ($account->usesMerchantFedExDeveloperCredentials() && $this->config->modelBDeveloperFallbackEnabled()) {
            abort_unless($account->hasMerchantFedExDeveloperCredentials(), 422);

            return;
        }

        abort(422, 'This FedEx account is not configured for API checks. Connect through FedEx Integrator Provider or enable developer fallback.');
    }

    /** @deprecated Use assertFedExApiAccount() */
    public function assertMerchantCredentialsAccount(CarrierAccount $account): void
    {
        $this->assertFedExApiAccount($account);
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
        ?FedExValidationEventContext $context = null,
    ): CarrierApiResult {
        $this->assertFedExApiAccount($account);
        $account->loadMissing('store');

        $environment = $this->config->environment($account->environment);
        $credentialsMode = $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer';
        $oauthSummary = $this->oauthRequestSummary($account, $environment, $credentialsMode);

        $oauthEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: $oauthSummary,
            environment: $account->environment,
        );

        $oauthResult = $account->usesFedExIntegratorProvider()
            ? $this->integratorChildOAuthService->fetchTokenResult($account, fresh: false)
            : $this->merchantOAuthService->fetchTokenResult($account, fresh: false);

        $this->eventLogger->complete($oauthEvent, $oauthResult);

        if (! $oauthResult->success) {
            return CarrierApiResult::failure(
                message: $oauthResult->errorMessage ?? 'FedEx authentication failed. Run the connection check first.',
                code: $oauthResult->errorCode ?? 'oauth_failed',
                requestSummary: array_merge($requestSummary, ['oauth_failed' => true]),
                responseSummary: $oauthResult->responseSummary,
            );
        }

        $accessToken = FedExHttpClient::normalizeBearerToken((string) ($oauthResult->data['access_token'] ?? ''));

        if ($accessToken === null || $accessToken === '') {
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
            requestSummary: $this->authenticatedRequestSummary($account, $environment, $requestSummary, $accessToken, $credentialsMode),
            environment: $account->environment,
            context: $context,
        );

        $apiResult = $this->httpClient->postJson(
            environment: $environment,
            path: $path,
            payload: $payload,
            bearerToken: $accessToken,
            requestSummary: $this->authenticatedRequestSummary($account, $environment, $requestSummary, $accessToken, $credentialsMode),
        );

        if ($this->isUnauthorized($apiResult)) {
            if ($account->usesFedExIntegratorProvider()) {
                $this->integratorChildOAuthService->clearTokenCache($account);
                $refreshResult = $this->integratorChildOAuthService->fetchTokenResult($account, fresh: true);
            } else {
                $this->merchantOAuthService->clearTokenCache($account);
                $refreshResult = $this->merchantOAuthService->fetchTokenResult($account, fresh: true);
            }

            if (! $refreshResult->success) {
                $failedResult = CarrierApiResult::failure(
                    message: $refreshResult->errorMessage ?? self::OAUTH_REJECTION_MESSAGE,
                    code: $refreshResult->errorCode ?? 'oauth_failed',
                    requestId: $apiResult->requestId,
                    durationMs: $apiResult->durationMs,
                    requestSummary: $this->authenticatedRequestSummary(
                        $account,
                        $environment,
                        array_merge($requestSummary, ['token_refreshed_after_401' => true]),
                        null,
                        $credentialsMode,
                    ),
                    responseSummary: array_merge($apiResult->responseSummary ?? [], [
                        'token_refreshed_after_401' => true,
                    ]),
                );
                $this->eventLogger->complete($apiEvent, $failedResult);

                return $failedResult;
            }

            $refreshedToken = FedExHttpClient::normalizeBearerToken((string) ($refreshResult->data['access_token'] ?? ''));

            if ($refreshedToken === null || $refreshedToken === '') {
                $failedResult = CarrierApiResult::failure(
                    message: self::OAUTH_REJECTION_MESSAGE,
                    code: 'missing_access_token',
                    requestId: $apiResult->requestId,
                    durationMs: $apiResult->durationMs,
                    requestSummary: $this->authenticatedRequestSummary(
                        $account,
                        $environment,
                        array_merge($requestSummary, ['token_refreshed_after_401' => true]),
                        null,
                        $credentialsMode,
                    ),
                    responseSummary: array_merge($apiResult->responseSummary ?? [], [
                        'token_refreshed_after_401' => true,
                    ]),
                );
                $this->eventLogger->complete($apiEvent, $failedResult);

                return $failedResult;
            }

            $retrySummary = $this->authenticatedRequestSummary(
                $account,
                $environment,
                array_merge($requestSummary, ['token_refreshed_after_401' => true]),
                $refreshedToken,
                $credentialsMode,
            );

            $apiResult = $this->httpClient->postJson(
                environment: $environment,
                path: $path,
                payload: $payload,
                bearerToken: $refreshedToken,
                requestSummary: $retrySummary,
            );

            if ($this->isUnauthorized($apiResult)) {
                $apiResult = CarrierApiResult::failure(
                    message: self::OAUTH_REJECTION_MESSAGE,
                    code: (string) ($apiResult->errorCode ?? '401'),
                    requestId: $apiResult->requestId,
                    durationMs: $apiResult->durationMs,
                    requestSummary: $retrySummary,
                    responseSummary: array_merge($apiResult->responseSummary ?? [], [
                        'token_refreshed_after_401' => true,
                    ]),
                );
            }
        }

        $completedEvent = $this->eventLogger->complete($apiEvent, $apiResult);

        return $apiResult->copyWith(
            responseSummary: array_merge($apiResult->responseSummary ?? [], [
                'carrier_api_event_id' => $completedEvent->id,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function oauthRequestSummary(CarrierAccount $account, string $environment, ?string $credentialsMode = null): array
    {
        $credentialsMode ??= $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer';

        return [
            'endpoint' => $this->config->oauthPath(),
            'environment' => $environment,
            'client_id_present' => $credentialsMode === 'merchant_developer'
                ? filled($account->merchantFedExClientId())
                : filled($this->config->parentClientId($environment)),
            'client_id_last4' => $credentialsMode === 'merchant_developer'
                ? $this->clientIdLast4($account)
                : $this->parentClientIdLast4($environment),
            'account_last4' => $this->accountLast4($account),
            'credentials_mode' => $credentialsMode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function baseRequestSummary(CarrierAccount $account, string $endpoint): array
    {
        $credentialsMode = $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer';

        return [
            'endpoint' => $endpoint,
            'environment' => $this->config->environment($account->environment),
            'action' => 'merchant_api_check',
            'account_last4' => $this->accountLast4($account),
            'client_id_last4' => $credentialsMode === 'integrator_child'
                ? $this->parentClientIdLast4($account->environment)
                : $this->clientIdLast4($account),
            'credentials_mode' => $credentialsMode,
        ];
    }

    /**
     * @param  array<string, mixed>  $requestSummary
     * @return array<string, mixed>
     */
    private function authenticatedRequestSummary(
        CarrierAccount $account,
        string $environment,
        array $requestSummary,
        ?string $accessToken,
        string $credentialsMode,
    ): array {
        return array_merge($requestSummary, [
            'environment' => $environment,
            'account_last4' => $this->accountLast4($account),
            'client_id_last4' => $credentialsMode === 'integrator_child'
                ? $this->parentClientIdLast4($environment)
                : $this->clientIdLast4($account),
            'auth_header_present' => filled($accessToken),
            'auth_scheme' => filled($accessToken) ? 'Bearer' : null,
            'credentials_mode' => $credentialsMode,
        ]);
    }

    private function isUnauthorized(CarrierApiResult $result): bool
    {
        return ! $result->success
            && (int) data_get($result->responseSummary, 'http_status') === 401;
    }

    private function accountLast4(CarrierAccount $account): ?string
    {
        $accountNumber = (string) ($account->provider_account_number ?? '');

        return strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null;
    }

    private function clientIdLast4(CarrierAccount $account): ?string
    {
        $clientId = (string) ($account->merchantFedExClientId() ?? '');

        return strlen($clientId) >= 4 ? substr($clientId, -4) : null;
    }

    private function parentClientIdLast4(?string $environment = null): ?string
    {
        $clientId = (string) ($this->config->parentClientId($environment) ?? '');

        return strlen($clientId) >= 4 ? substr($clientId, -4) : null;
    }
}
