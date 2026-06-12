<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\CarrierProviderInterface;
use App\Services\Carriers\DTO\CarrierConnectionTestResult;

class FedExCarrierProvider implements CarrierProviderInterface
{
    private const BLOCKED_BY_FEDEX_MESSAGE = 'FedEx rejected Credential Registration. This account may require FedEx support or Integrator enablement.';

    private const FALLBACK_WARNING = 'Local sandbox fallback: this uses platform FedEx sandbox credentials only. It is not a production merchant-owned FedEx connection.';

    private const MERCHANT_CREDENTIALS_CONNECTED_MESSAGE = 'FedEx merchant credentials verified. Connected using merchant credentials. FedEx billing stays between you and FedEx. Labels are not enabled in this phase.';

    private const MERCHANT_CREDENTIALS_FAILED_MESSAGE = 'FedEx credentials could not be verified. Check the API key, secret key, environment, and account number.';

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExAccountRegistrationService $registrationService,
        private readonly FedExOAuthTokenService $oauthTokenService,
        private readonly FedExMerchantCredentialsOAuthService $merchantCredentialsOAuth,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

    public function providerCode(): string
    {
        return CarrierAccount::PROVIDER_FEDEX;
    }

    public function testConnection(CarrierAccount $account): CarrierConnectionTestResult
    {
        if ($account->usesMerchantFedExDeveloperCredentials()) {
            return $this->testMerchantCredentialsConnection($account);
        }

        return $this->testLegacyIntegratorConnection($account);
    }

    public function supportsRates(?CarrierAccount $account = null): bool
    {
        if ($account === null) {
            return false;
        }

        if ($account->usesMerchantFedExDeveloperCredentials()) {
            return false;
        }

        return $account->isSandboxPlatformFallback()
            && $account->isSandbox()
            && app()->environment(['local', 'testing']);
    }

    public function supportsLabels(): bool
    {
        return false;
    }

    public function supportsTracking(): bool
    {
        return false;
    }

    /**
     * Merchant-owned FedEx Developer credentials — OAuth only, no Credential Registration.
     */
    private function testMerchantCredentialsConnection(CarrierAccount $account): CarrierConnectionTestResult
    {
        if (! $this->config->allowsMerchantCredentialsEnvironment($account->environment)) {
            return CarrierConnectionTestResult::failed(
                'This FedEx environment is not supported for merchant credential connections.',
                'environment_not_supported',
            );
        }

        if (! $account->hasMerchantFedExDeveloperCredentials()) {
            return CarrierConnectionTestResult::failed(
                'FedEx API credentials are missing. Save your API key and secret, then run the connection check again.',
                'missing_merchant_credentials',
            );
        }

        if (! filled($account->provider_account_number)) {
            return CarrierConnectionTestResult::failed(
                'FedEx account number is required before testing the connection.',
                'missing_account_number',
            );
        }

        $account->loadMissing('store');
        $steps = [
            CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN => 'skipped_merchant_credentials',
            CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION => 'skipped_merchant_credentials',
        ];

        $clientId = (string) ($account->merchantFedExClientId() ?? '');
        $accountNumber = (string) ($account->provider_account_number ?? '');

        $merchantEvent = $this->eventLogger->start(
            store: $account->store,
            provider: $this->providerCode(),
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $account->environment,
                'client_id_present' => true,
                'client_id_last4' => strlen($clientId) >= 4 ? substr($clientId, -4) : null,
                'account_last4' => strlen($accountNumber) >= 4 ? substr($accountNumber, -4) : null,
                'credentials_mode' => 'merchant_developer',
            ],
            environment: $account->environment,
        );

        $merchantResult = $this->merchantCredentialsOAuth->fetchTokenResult($account, fresh: true);
        $this->eventLogger->complete($merchantEvent, $merchantResult);
        $steps[CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN] = $merchantResult->success
            ? CarrierApiEvent::STATUS_SUCCEEDED
            : CarrierApiEvent::STATUS_FAILED;

        if (! $merchantResult->success) {
            $account->markFailed(
                self::MERCHANT_CREDENTIALS_FAILED_MESSAGE,
                $merchantResult->errorCode,
            );

            return CarrierConnectionTestResult::failed(
                self::MERCHANT_CREDENTIALS_FAILED_MESSAGE,
                $merchantResult->errorCode,
                $steps,
                $merchantResult->errorMessage,
            );
        }

        $capabilities = [
            'rates' => false,
            'labels' => false,
            'tracking' => false,
            'pickup' => false,
            'checkout_rates' => false,
            'sandbox_connection' => $account->isSandbox(),
            'merchant_owned_connection' => true,
            'merchant_credentials_mode' => true,
        ];

        $account->markConnected($capabilities);
        $this->syncMerchantCredentialsVerification($account);

        return CarrierConnectionTestResult::connected(
            self::MERCHANT_CREDENTIALS_CONNECTED_MESSAGE,
            $capabilities,
            false,
            $steps,
        );
    }

    /**
     * Legacy integrator Credential Registration path — local/testing diagnostics only.
     */
    private function testLegacyIntegratorConnection(CarrierAccount $account): CarrierConnectionTestResult
    {
        if (! $this->config->allowsEnvironment($account->environment)) {
            return CarrierConnectionTestResult::failed(
                'Live FedEx connections are not enabled in this phase. Use sandbox only.',
                'live_not_enabled',
            );
        }

        if (! $this->config->isConfigured($account->environment)) {
            return CarrierConnectionTestResult::platformUnavailable(
                'FedEx sandbox connection is not available on this platform environment yet. Contact the platform admin.'
            );
        }

        $account->loadMissing('store');
        $steps = [];
        $registered = false;
        $useFallback = $account->usesSandboxPlatformFallback()
            && $this->config->allowsSandboxPlatformFallback();

        $platformEvent = $this->eventLogger->start(
            store: $account->store,
            provider: $this->providerCode(),
            action: CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            account: $account,
            requestSummary: ['endpoint' => $this->config->oauthPath()],
            environment: $account->environment,
        );

        $platformResult = $this->oauthTokenService->fetchPlatformTokenResult($account->environment, fresh: true);
        $this->eventLogger->complete($platformEvent, $platformResult);
        $steps[CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN] = $platformResult->success
            ? CarrierApiEvent::STATUS_SUCCEEDED
            : CarrierApiEvent::STATUS_FAILED;

        if (! $platformResult->success) {
            $account->markFailed(
                $platformResult->errorMessage ?? 'FedEx platform authentication failed.',
                $platformResult->errorCode,
            );

            return CarrierConnectionTestResult::failed(
                $platformResult->errorMessage ?? 'FedEx platform authentication failed.',
                $platformResult->errorCode,
                $steps,
            );
        }

        if ($useFallback) {
            return $this->completeSandboxPlatformFallback($account, $steps);
        }

        $platformToken = [
            'access_token' => (string) ($platformResult->data['access_token'] ?? ''),
        ];

        if (! $account->hasLegacyFedExChildCredentials()) {
            $details = $this->registrationService->registrationDetailsForAccount($account);

            if (! filled($details['provider_account_number'] ?? null)) {
                return CarrierConnectionTestResult::failed(
                    'FedEx account number is required before testing the connection.',
                    'missing_account_number',
                    $steps,
                );
            }

            $registration = $this->registrationService->register(
                $account->store,
                $account,
                $details,
                $platformToken,
            );

            $steps[CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION] = $registration->success
                ? CarrierApiEvent::STATUS_SUCCEEDED
                : CarrierApiEvent::STATUS_FAILED;

            if (! $registration->success) {
                if ($this->isCredentialRegistrationBlocked($registration)) {
                    $account->markBlockedByFedEx(self::BLOCKED_BY_FEDEX_MESSAGE, $registration->errorCode);

                    return CarrierConnectionTestResult::blockedByFedEx(
                        'FedEx platform credentials are valid, but Credential Registration was rejected by FedEx.',
                        $registration->errorCode,
                        $steps,
                        self::BLOCKED_BY_FEDEX_MESSAGE,
                    );
                }

                $account->markFailed(
                    $registration->errorMessage ?? 'FedEx account registration failed.',
                    $registration->errorCode,
                );

                return CarrierConnectionTestResult::failed(
                    'FedEx platform credentials are valid, but account registration failed.',
                    $registration->errorCode,
                    $steps,
                    $registration->errorMessage,
                );
            }

            $registered = true;
            $account->refresh();
        } else {
            $steps[CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION] = 'skipped';
        }

        $merchantEvent = $this->eventLogger->start(
            store: $account->store,
            provider: $this->providerCode(),
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'account_number' => $account->maskedAccountNumber(),
            ],
            environment: $account->environment,
        );

        $merchantResult = $this->oauthTokenService->fetchMerchantTokenResult($account);
        $this->eventLogger->complete($merchantEvent, $merchantResult);
        $steps[CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN] = $merchantResult->success
            ? CarrierApiEvent::STATUS_SUCCEEDED
            : CarrierApiEvent::STATUS_FAILED;

        if (! $merchantResult->success) {
            $account->markFailed(
                $merchantResult->errorMessage ?? 'FedEx merchant OAuth test failed.',
                $merchantResult->errorCode,
            );

            $message = $registered || $account->hasLegacyFedExChildCredentials()
                ? 'FedEx platform credentials are valid, but merchant OAuth failed.'
                : ($merchantResult->errorMessage ?? 'FedEx connection test failed.');

            return CarrierConnectionTestResult::failed(
                $message,
                $merchantResult->errorCode,
                $steps,
                $merchantResult->errorMessage,
            );
        }

        $capabilities = [
            'rates' => true,
            'labels' => false,
            'tracking' => false,
            'pickup' => false,
            'checkout_rates' => false,
            'sandbox_connection' => true,
            'merchant_owned_connection' => true,
        ];

        $account->markConnected($capabilities);

        return CarrierConnectionTestResult::connected(
            'FedEx merchant account connected for testing. FedEx billing stays between you and FedEx. Labels are not enabled in this phase.',
            $capabilities,
            $registered,
            $steps,
        );
    }

    /**
     * @param  array<string, string>  $steps
     */
    private function completeSandboxPlatformFallback(CarrierAccount $account, array $steps): CarrierConnectionTestResult
    {
        $steps[CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION] = 'skipped_fallback';
        $steps[CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN] = 'skipped_fallback';

        $capabilities = [
            'rates' => true,
            'labels' => false,
            'tracking' => false,
            'sandbox_connection' => true,
            'sandbox_platform_fallback' => true,
            'merchant_owned_connection' => false,
        ];

        $account->markSandboxPlatformFallback($capabilities);

        return CarrierConnectionTestResult::sandboxPlatformFallback(
            self::FALLBACK_WARNING,
            $capabilities,
            $steps,
        );
    }

    private function isCredentialRegistrationBlocked(\App\Services\Carriers\DTO\CarrierApiResult $registration): bool
    {
        return (int) ($registration->responseSummary['http_status'] ?? 0) === 422;
    }

    private function syncMerchantCredentialsVerification(CarrierAccount $account): void
    {
        $settings = is_array($account->settings) ? $account->settings : [];
        $settings['verification_status'] = 'connected_for_testing';
        $settings['verification_summary'] = self::MERCHANT_CREDENTIALS_CONNECTED_MESSAGE;
        $settings['last_tested_at'] = now()->toIso8601String();
        $settings['connection_mode'] = 'merchant_credentials';

        $account->forceFill(['settings' => $settings])->save();
    }
}
