<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\CarrierProviderInterface;
use App\Services\Carriers\DTO\CarrierConnectionTestResult;

class FedExCarrierProvider implements CarrierProviderInterface
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExAccountRegistrationService $registrationService,
        private readonly FedExOAuthTokenService $oauthTokenService,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

    public function providerCode(): string
    {
        return CarrierAccount::PROVIDER_FEDEX;
    }

    public function testConnection(CarrierAccount $account): CarrierConnectionTestResult
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

        $platformToken = [
            'access_token' => (string) ($platformResult->data['access_token'] ?? ''),
        ];

        if (! $account->hasMerchantCredentials()) {
            $details = array_merge($account->registrationDetails(), [
                'provider_account_number' => $account->provider_account_number,
            ]);

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

            $message = $registered || $account->hasMerchantCredentials()
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
            'rates' => false,
            'labels' => false,
            'tracking' => false,
            'sandbox_connection' => true,
        ];

        $account->markConnected($capabilities);

        return CarrierConnectionTestResult::connected(
            'FedEx sandbox account connected successfully.',
            $capabilities,
            $registered,
            $steps,
        );
    }

    public function supportsRates(): bool
    {
        return false;
    }

    public function supportsLabels(): bool
    {
        return false;
    }

    public function supportsTracking(): bool
    {
        return false;
    }
}
