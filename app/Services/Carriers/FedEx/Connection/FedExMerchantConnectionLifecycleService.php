<?php

namespace App\Services\Carriers\FedEx\Connection;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\DB;
use LogicException;

final class FedExMerchantConnectionLifecycleService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExIntegratorChildOAuthService $childOAuth,
        private readonly FedExIntegratorRegistrationOrchestrator $orchestrator,
    ) {}

    public function assertOwnedModelAAccount(CarrierAccount $account): void
    {
        abort_unless($account->usesFedExIntegratorProvider(), 404);
    }

    public function assertActiveManageableAccount(CarrierAccount $account): void
    {
        $this->assertOwnedModelAAccount($account);
        abort_unless($account->disconnected_at === null, 404);
        abort_unless($account->replaced_at === null, 404);
    }

    /** @deprecated Use assertActiveManageableAccount() */
    public function assertManageable(CarrierAccount $account): void
    {
        $this->assertActiveManageableAccount($account);
    }

    public function verify(CarrierAccount $account): CarrierApiResult
    {
        $this->assertOwnedModelAAccount($account);
        abort_unless($account->disconnected_at === null, 404);
        abort_unless($account->replaced_at === null, 404);

        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor(
            (int) $account->store_id,
            (string) $account->environment,
        );

        // Never assign a missing active key during verify.
        if ($account->fedex_active_store_key !== $expectedKey) {
            return CarrierApiResult::failure(
                message: 'This FedEx account is not the active connection for this store. Use Reconnect if you need to replace it.',
                code: 'fedex_active_key_mismatch',
            );
        }

        if (! $account->hasLegacyFedExChildCredentials()) {
            return CarrierApiResult::failure(
                message: 'FedEx credentials are missing. Reconnect your account to continue.',
                code: 'child_credentials_missing',
            );
        }

        try {
            $result = $this->childOAuth->fetchTokenResult($account, fresh: true);
        } catch (\Throwable) {
            $this->storeSanitizedVerifyError(
                $account,
                'FedEx could not be reached right now. Your connection is unchanged — try again shortly.',
                'fedex_verify_transient',
            );

            return CarrierApiResult::failure(
                message: 'FedEx could not be reached right now. Your connection is unchanged — try again shortly.',
                code: 'fedex_verify_transient',
            );
        }

        if ($result->success) {
            // Preserve merchant-enabled capabilities; verify only refreshes connection health.
            $account->forceFill([
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'status' => CarrierAccount::STATUS_ENABLED,
                'last_verified_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            return $result;
        }

        if ($this->isDefinitiveInvalidChildCredentials($result)) {
            $safe = 'FedEx rejected these credentials. Reconnect your account or disconnect and start again.';
            $account->forceFill([
                'connection_status' => CarrierAccount::CONNECTION_FAILED,
                'last_error_code' => 'child_credentials_invalid',
                'last_error_message' => $safe,
                // Preserve active key so a new normal connect cannot bypass reconnect rules.
                'capabilities' => $this->disabledCapabilities($account),
                'enabled_for_checkout' => false,
            ])->save();

            return CarrierApiResult::failure(
                message: $safe,
                code: 'child_credentials_invalid',
                requestSummary: $result->requestSummary,
                responseSummary: $this->sanitizedResponseSummary($result),
            );
        }

        $safe = 'FedEx could not verify this connection right now. Your account stays connected — try again shortly.';
        $this->storeSanitizedVerifyError($account, $safe, 'fedex_verify_transient');

        return CarrierApiResult::failure(
            message: $safe,
            code: 'fedex_verify_transient',
            requestSummary: $result->requestSummary,
            responseSummary: $this->sanitizedResponseSummary($result),
        );
    }

    public function beginReconnect(
        Store $store,
        User $user,
        CarrierAccount $account,
        int $originLocationId,
    ): CarrierAccountRegistrationSession {
        $this->assertActiveManageableAccount($account);
        abort_unless((int) $account->store_id === (int) $store->id, 404);
        abort_unless($this->config->modelAEnabled(), 404);

        $environment = (string) $account->environment;
        abort_unless($this->config->allowsIntegratorEnvironment($environment), 422);

        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, $environment);
        abort_unless($account->fedex_active_store_key === $expectedKey, 422);

        $session = $this->orchestrator->start(
            $store,
            $user,
            $originLocationId,
            $environment,
        );

        $session->forceFill([
            'replacing_carrier_account_id' => $account->id,
        ])->save();

        return $session->refresh();
    }

    /**
     * @return array{disconnected: bool, idempotent: bool}
     */
    public function disconnect(CarrierAccount $account): array
    {
        $this->assertOwnedModelAAccount($account);

        $cacheKey = null;
        $idempotent = false;

        DB::transaction(function () use ($account, &$cacheKey, &$idempotent): void {
            /** @var CarrierAccount $locked */
            $locked = CarrierAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwnedModelAAccount($locked);

            if ($locked->disconnected_at !== null) {
                $idempotent = true;

                return;
            }

            // Capture OAuth cache key before clearing credentials.
            if ($locked->hasLegacyFedExChildCredentials()) {
                $cacheKey = $this->childOAuth->tokenCacheKey($locked);
            }

            $capabilities = $this->disabledCapabilities($locked);

            $locked->forceFill([
                'connection_status' => CarrierAccount::CONNECTION_DISABLED,
                'status' => CarrierAccount::STATUS_DISABLED,
                'enabled_for_checkout' => false,
                'fedex_active_store_key' => null,
                'credentials_encrypted' => null,
                'provider_account_number_encrypted' => null,
                'provider_account_number' => null,
                'capabilities' => $capabilities,
                'disconnected_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();

            CarrierAccountRegistrationSession::query()
                ->where(function ($query) use ($locked): void {
                    $query->where('carrier_account_id', $locked->id)
                        ->orWhere('replacing_carrier_account_id', $locked->id);
                })
                ->orderBy('id')
                ->get()
                ->each(function (CarrierAccountRegistrationSession $session): void {
                    $session->clearTransientFedExSecrets();
                });
        });

        if (is_string($cacheKey) && $cacheKey !== '') {
            $this->childOAuth->clearTokenCacheKey($cacheKey);
        }

        return [
            'disconnected' => true,
            'idempotent' => $idempotent,
        ];
    }

    /**
     * Atomic replacement is owned exclusively by FedExIntegratorRegistrationOrchestrator.
     *
     * @throws LogicException
     */
    public function replaceActiveAccount(
        CarrierAccount $incoming,
        CarrierAccount $outgoing,
    ): void {
        unset($incoming, $outgoing);

        throw new LogicException(
            'Atomic FedEx Model A replacement is owned by FedExIntegratorRegistrationOrchestrator. Do not call FedExMerchantConnectionLifecycleService::replaceActiveAccount().'
        );
    }

    public function resumeChildOAuthVerification(
        Store $store,
        CarrierAccountRegistrationSession $session,
    ): CarrierAccountRegistrationSession {
        return $this->orchestrator->resumeChildOAuthVerification($store, $session);
    }

    /**
     * @return list<string>
     */
    public function resumableStatuses(): array
    {
        return [
            CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
        ];
    }

    public function findResumableSessionForAccount(CarrierAccount $account): ?CarrierAccountRegistrationSession
    {
        return CarrierAccountRegistrationSession::query()
            ->where('store_id', $account->store_id)
            ->where('provider', CarrierAccountRegistrationSession::PROVIDER_FEDEX)
            ->where('connection_model', CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER)
            ->where('carrier_account_id', $account->id)
            ->whereIn('status', $this->resumableStatuses())
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledCapabilities(CarrierAccount $account): array
    {
        $capabilities = is_array($account->capabilities) ? $account->capabilities : [];

        return array_merge($capabilities, [
            'rates' => false,
            'labels' => false,
            'tracking' => false,
            'pickup' => false,
            'checkout_rates' => false,
        ]);
    }

    private function storeSanitizedVerifyError(CarrierAccount $account, string $message, string $code): void
    {
        $account->forceFill([
            'last_error_code' => $code,
            'last_error_message' => $message,
            // Keep connected + active key for transient failures.
        ])->save();
    }

    private function isDefinitiveInvalidChildCredentials(CarrierApiResult $result): bool
    {
        $status = (int) data_get($result->responseSummary, 'http_status', 0);
        if (in_array($status, [401, 403], true)) {
            return true;
        }

        $code = strtolower((string) ($result->errorCode ?? ''));

        return str_contains($code, 'invalid_client')
            || str_contains($code, 'unauthorized')
            || str_contains($code, 'authentication')
            || $code === 'child_credentials_invalid';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizedResponseSummary(CarrierApiResult $result): ?array
    {
        $summary = $result->responseSummary;
        if (! is_array($summary)) {
            return null;
        }

        unset($summary['raw'], $summary['body'], $summary['access_token'], $summary['token']);

        return $summary;
    }
}
