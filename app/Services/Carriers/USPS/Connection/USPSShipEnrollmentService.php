<?php

namespace App\Services\Carriers\USPS\Connection;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\USPS\Auth\USPSOAuthTokenService;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSHttpClient;

class USPSShipEnrollmentService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly USPSOAuthTokenService $platformOAuthTokenService,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    public function verify(Store $store, CarrierAccount $account): CarrierApiResult
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_USPS_SHIP_ENROLLMENT_VERIFY,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->shipEnrollmentPath(),
                'environment' => $this->config->environment(),
                'crid_last4' => $this->lastFour((string) $account->uspsMerchantCrid()),
                'mid_last4' => $this->lastFour((string) $account->uspsMerchantMid()),
                'epa_last4' => $this->lastFour((string) $account->uspsMerchantEpa()),
            ],
            environment: $account->environment ?? CarrierAccount::ENVIRONMENT_TESTING,
        );

        $accessToken = $this->platformOAuthTokenService->accessToken();
        if ($accessToken === null) {
            $result = CarrierApiResult::failure(
                message: 'Platform USPS access is not available yet. Contact the platform admin.',
                code: 'platform_token_missing',
            );
            $this->eventLogger->complete($event, $result);

            return $result;
        }

        $payload = [
            'CRID' => (string) $account->uspsMerchantCrid(),
            'MID' => (string) $account->uspsMerchantMid(),
            'accountNumber' => (string) $account->uspsMerchantEpa(),
        ];

        $result = $this->httpClient->postJson(
            path: $this->config->shipEnrollmentPath(),
            payload: $payload,
            bearerToken: $accessToken['access_token'],
            requestSummary: [
                'endpoint' => $this->config->shipEnrollmentPath(),
                'environment' => $this->config->environment(),
                'crid_last4' => $this->lastFour((string) $account->uspsMerchantCrid()),
                'mid_last4' => $this->lastFour((string) $account->uspsMerchantMid()),
                'epa_last4' => $this->lastFour((string) $account->uspsMerchantEpa()),
            ],
        );

        if ($result->success && ! $this->responseIndicatesEnrollment($result->data)) {
            $result = CarrierApiResult::failure(
                message: 'USPS Ship enrollment is not complete for this business account. Finish USPS Ship setup in the USPS Business Portal.',
                code: 'ship_enrollment_incomplete',
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $result->responseSummary,
            );
        }

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function responseIndicatesEnrollment(?array $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        if (array_key_exists('enrolled', $data)) {
            return (bool) $data['enrolled'];
        }

        $status = strtoupper((string) ($data['enrollmentStatus'] ?? $data['status'] ?? ''));

        return in_array($status, ['ENROLLED', 'ACTIVE', 'VERIFIED', 'COMPLETE', 'COMPLETED'], true);
    }

    private function lastFour(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return strlen($value) <= 4 ? $value : substr($value, -4);
    }
}
