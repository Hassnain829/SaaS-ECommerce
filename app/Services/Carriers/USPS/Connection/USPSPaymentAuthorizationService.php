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
use App\Services\Carriers\USPS\Support\USPSPaymentRolePayloadBuilder;

class USPSPaymentAuthorizationService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly USPSOAuthTokenService $platformOAuthTokenService,
        private readonly USPSPaymentRolePayloadBuilder $rolePayloadBuilder,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    public function verify(Store $store, CarrierAccount $account): CarrierApiResult
    {
        abort_unless($account->isUspsMerchantLabelProvider(), 404);
        abort_unless((int) $account->store_id === (int) $store->id, 404);

        $rolePayload = $this->rolePayloadBuilder->build($account);

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_USPS_PAYMENT_AUTHORIZATION,
            account: $account,
            requestSummary: [
                'endpoint' => $this->config->paymentAuthorizationPath(),
                'environment' => $this->config->environment(),
                'role_names' => array_values(array_map(
                    static fn (array $role): string => (string) ($role['roleName'] ?? ''),
                    $rolePayload['roles'] ?? [],
                )),
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

        $result = $this->httpClient->postJson(
            path: $this->config->paymentAuthorizationPath(),
            payload: $rolePayload,
            bearerToken: $accessToken['access_token'],
            requestSummary: [
                'endpoint' => $this->config->paymentAuthorizationPath(),
                'environment' => $this->config->environment(),
                'role_names' => array_values(array_map(
                    static fn (array $role): string => (string) ($role['roleName'] ?? ''),
                    $rolePayload['roles'] ?? [],
                )),
                'epa_last4' => $this->lastFour((string) $account->uspsMerchantEpa()),
            ],
        );

        if ($result->success && ! $this->responseHasPaymentToken($result->data)) {
            $result = CarrierApiResult::failure(
                message: 'USPS did not return a payment authorization token. Confirm your EPA has a valid payment method in the USPS Business Portal.',
                code: 'payment_token_missing',
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $this->redactPaymentResponseSummary($result->responseSummary),
            );
        } elseif ($result->success) {
            $result = CarrierApiResult::success(
                data: ['payment_token_received' => true],
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $this->redactPaymentResponseSummary($result->responseSummary),
            );
        }

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function responseHasPaymentToken(?array $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        foreach (['paymentAuthorizationToken', 'payment_authorization_token', 'authorizationToken', 'token'] as $key) {
            if (filled($data[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    private function redactPaymentResponseSummary(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        $summary['payment_token_received'] = true;

        return $summary;
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
