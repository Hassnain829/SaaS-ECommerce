<?php

namespace App\Services\Carriers\USPS;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\CarrierProviderInterface;
use App\Services\Carriers\DTO\CarrierConnectionTestResult;

class USPSCarrierProvider implements CarrierProviderInterface
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSOAuthTokenService $oauthTokenService,
        private readonly USPSAddressValidationService $addressValidationService,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

    public function providerCode(): string
    {
        return CarrierAccount::PROVIDER_USPS;
    }

    public function testConnection(CarrierAccount $account): CarrierConnectionTestResult
    {
        if (! $this->config->allowsEnvironment($account->environment)) {
            return CarrierConnectionTestResult::failed(
                'USPS production connections are not enabled in this phase. Use testing only.',
                'live_not_enabled',
            );
        }

        if (! $this->config->isConfigured()) {
            return CarrierConnectionTestResult::platformUnavailable(
                'USPS public API connection is not available on this platform environment yet. Contact the platform admin.'
            );
        }

        $account->loadMissing('store');
        $steps = [];

        $oauthEvent = $this->eventLogger->start(
            store: $account->store,
            provider: $this->providerCode(),
            action: CarrierApiEvent::ACTION_OAUTH_TOKEN,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->oauthPath(),
                'environment' => $this->config->environment(),
            ],
            environment: $account->environment,
        );

        $oauthResult = $this->oauthTokenService->fetchTokenResult(fresh: true);
        $this->eventLogger->complete($oauthEvent, $oauthResult);
        $steps[CarrierApiEvent::ACTION_OAUTH_TOKEN] = $oauthResult->success
            ? CarrierApiEvent::STATUS_SUCCEEDED
            : CarrierApiEvent::STATUS_FAILED;

        if (! $oauthResult->success) {
            $account->markFailed(
                $oauthResult->errorMessage ?? 'USPS OAuth token request failed.',
                $oauthResult->errorCode,
            );

            return CarrierConnectionTestResult::failed(
                $oauthResult->errorMessage ?? 'USPS OAuth token request failed.',
                $oauthResult->errorCode,
                $steps,
            );
        }

        $token = (string) ($oauthResult->data['access_token'] ?? '');
        $originLocation = $this->resolveDefaultOriginLocation($account);

        if ($originLocation !== null && $token !== '') {
            $addressResult = $this->addressValidationService->validateOriginLocation(
                $account->store,
                $account,
                $originLocation,
                $token,
            );

            $steps[CarrierApiEvent::ACTION_ADDRESS_VALIDATION] = $addressResult->success
                ? CarrierApiEvent::STATUS_SUCCEEDED
                : CarrierApiEvent::STATUS_FAILED;
        } else {
            $steps[CarrierApiEvent::ACTION_ADDRESS_VALIDATION] = 'skipped';
        }

        $capabilities = [
            'address_validation' => true,
            'rates' => true,
            'labels' => false,
            'tracking' => false,
            'sandbox_connection' => true,
            'platform_credentials' => true,
            'merchant_owned_connection' => false,
        ];

        $account->markConnected($capabilities);

        $message = 'USPS testing connection verified successfully.';
        if (($steps[CarrierApiEvent::ACTION_ADDRESS_VALIDATION] ?? null) === CarrierApiEvent::STATUS_FAILED) {
            $message = 'USPS OAuth succeeded. Address validation did not pass for the default origin location, but the connection is available for rate quote testing.';
        }

        return CarrierConnectionTestResult::connected(
            $message,
            $capabilities,
            false,
            $steps,
        );
    }

    public function supportsRates(?CarrierAccount $account = null): bool
    {
        if ($account === null || ! $account->isUsps()) {
            return false;
        }

        return $account->isConnected()
            && ! $this->config->labelsEnabled();
    }

    public function supportsLabels(): bool
    {
        return false;
    }

    public function supportsTracking(): bool
    {
        return false;
    }

    private function resolveDefaultOriginLocation(CarrierAccount $account): ?Location
    {
        $locationId = data_get($account->settings, 'default_origin_location_id');

        if (! filled($locationId)) {
            return null;
        }

        return Location::query()
            ->where('store_id', $account->store_id)
            ->whereKey((int) $locationId)
            ->first();
    }
}
