<?php

namespace App\Services\Payments;

use App\Models\PaymentProviderAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class StripeConnectService
{
    public function __construct(
        private readonly StripeConfig $stripeConfig,
    ) {
    }

    public function startOnboarding(Store $store, User $user, string $mode): string
    {
        $account = $this->createOrRetrieveConnectedAccount($store, $user, $mode);

        return $this->createAccountOnboardingLink($account, $mode);
    }

    public function createOrRetrieveConnectedAccount(Store $store, User $user, string $mode): PaymentProviderAccount
    {
        $mode = $this->normalizeMode($mode);
        $this->ensureModeConfigured($mode);

        $existing = PaymentProviderAccount::query()
            ->forStore($store)
            ->stripe()
            ->connect()
            ->mode($mode)
            ->whereNotNull('provider_account_id')
            ->where('status', '!=', 'disabled')
            ->latest('id')
            ->first();

        if ($existing) {
            $this->markDefault($existing);

            return $existing->fresh();
        }

        $client = $this->clientForMode($mode);
        $account = $client->accounts->create([
            'type' => 'express',
            'country' => strtoupper((string) ($store->country_code ?? 'US')),
            'email' => $user->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'store_id' => (string) $store->id,
                'store_name' => (string) $store->name,
                'created_by_user_id' => (string) $user->id,
                'connect_mode' => $mode,
            ],
        ]);

        $raw = method_exists($account, 'toArray') ? $account->toArray() : (array) $account;

        return DB::transaction(function () use ($store, $user, $mode, $raw): PaymentProviderAccount {
            $account = PaymentProviderAccount::query()->create([
                'store_id' => $store->id,
                'provider' => 'stripe',
                'provider_account_id' => (string) ($raw['id'] ?? ''),
                'mode' => $mode,
                'connection_type' => PaymentProviderAccount::CONNECTION_CONNECT,
                'display_name' => $mode === PaymentProviderAccount::MODE_LIVE
                    ? 'Stripe live account'
                    : 'Stripe test account',
                'status' => 'pending',
                'is_default' => true,
                'settings' => [
                    'account_type' => 'express',
                    'onboarding_started_at' => now()->toISOString(),
                ],
                'capabilities' => $raw['capabilities'] ?? null,
                'metadata' => [
                    'created_from' => 'payments_settings',
                    'connect_mode' => $mode,
                ],
                'created_by' => $user->id,
                'charges_enabled' => (bool) ($raw['charges_enabled'] ?? false),
                'payouts_enabled' => (bool) ($raw['payouts_enabled'] ?? false),
                'requirements_currently_due' => data_get($raw, 'requirements.currently_due'),
                'requirements_disabled_reason' => data_get($raw, 'requirements.disabled_reason'),
                'last_verified_at' => now(),
            ]);

            $this->markDefault($account);

            return $account->fresh();
        });
    }

    public function createAccountOnboardingLink(PaymentProviderAccount $account, ?string $mode = null): string
    {
        if (! filled($account->provider_account_id)) {
            throw new \RuntimeException('This Stripe account is missing its account ID.');
        }

        $mode = $this->normalizeMode($mode ?? (string) $account->mode);
        $this->ensureModeConfigured($mode);

        if ($account->mode !== $mode) {
            throw new \RuntimeException('This Stripe account does not match the selected payment mode.');
        }

        $link = $this->clientForMode($mode)->accountLinks->create([
            'account' => (string) $account->provider_account_id,
            'refresh_url' => $this->stripeConfig->connectRefreshUrl($mode),
            'return_url' => $this->stripeConfig->connectReturnUrl($mode),
            'type' => 'account_onboarding',
        ]);

        return (string) ($link->url ?? '');
    }

    public function handleReturn(Store $store, string $mode): ?PaymentProviderAccount
    {
        $account = $this->connectedAccountForStore($store, $mode);

        return $account ? $this->refreshAccountStatus($account) : null;
    }

    public function refreshAccountStatus(PaymentProviderAccount $account): PaymentProviderAccount
    {
        if (! filled($account->provider_account_id)) {
            return $account;
        }

        $mode = $this->normalizeMode((string) $account->mode);
        $stripeAccount = $this->clientForMode($mode)->accounts->retrieve((string) $account->provider_account_id, []);
        $raw = method_exists($stripeAccount, 'toArray') ? $stripeAccount->toArray() : (array) $stripeAccount;

        return $this->applyAccountStatus($account, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function applyAccountStatus(PaymentProviderAccount $account, array $raw): PaymentProviderAccount
    {
        $chargesEnabled = (bool) ($raw['charges_enabled'] ?? false);
        $payoutsEnabled = (bool) ($raw['payouts_enabled'] ?? false);
        $currentlyDue = data_get($raw, 'requirements.currently_due');
        $disabledReason = data_get($raw, 'requirements.disabled_reason');
        $currentlyDue = is_array($currentlyDue) ? array_values($currentlyDue) : [];

        $status = match (true) {
            filled($disabledReason) => 'restricted',
            $chargesEnabled => 'active',
            default => 'pending',
        };

        $updates = [
            'provider_account_id' => (string) ($raw['id'] ?? $account->provider_account_id),
            'status' => $status,
            'capabilities' => $raw['capabilities'] ?? $account->capabilities,
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'requirements_currently_due' => $currentlyDue,
            'requirements_disabled_reason' => $disabledReason,
            'last_verified_at' => now(),
        ];

        if ($status === 'active' && ! $account->onboarding_completed_at) {
            $updates['onboarding_completed_at'] = now();
        }

        $account->forceFill($updates)->save();

        if ($status === 'active') {
            $this->markDefault($account);
        }

        return $account->fresh();
    }

    public function disconnectAccount(PaymentProviderAccount $account): PaymentProviderAccount
    {
        $account->forceFill([
            'status' => 'disabled',
            'is_default' => false,
            'charges_enabled' => false,
        ])->save();

        return $account->fresh();
    }

    public function disableLocally(PaymentProviderAccount $account): PaymentProviderAccount
    {
        return $this->disconnectAccount($account);
    }

    public function connectedAccountForStore(Store $store, string $mode): ?PaymentProviderAccount
    {
        return PaymentProviderAccount::query()
            ->forStore($store)
            ->stripe()
            ->connect()
            ->mode($this->normalizeMode($mode))
            ->where('status', '!=', 'disabled')
            ->latest('id')
            ->first();
    }

    public function resolveSecretKeyForMode(string $mode): string
    {
        $secret = $this->stripeConfig->stripeSecretKey($this->normalizeMode($mode));
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.($mode === 'live' ? 'live' : 'test').' mode.');
        }

        return $secret;
    }

    public function clientForMode(string $mode): StripeClient
    {
        return new StripeClient($this->resolveSecretKeyForMode($mode));
    }

    private function markDefault(PaymentProviderAccount $account): void
    {
        PaymentProviderAccount::query()
            ->forStore($account->store)
            ->stripe()
            ->connect()
            ->mode((string) $account->mode)
            ->whereKeyNot($account->id)
            ->update(['is_default' => false]);

        $account->forceFill(['is_default' => true])->save();
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, [PaymentProviderAccount::MODE_TEST, PaymentProviderAccount::MODE_LIVE], true)
            ? $mode
            : PaymentProviderAccount::MODE_TEST;
    }

    private function ensureModeConfigured(string $mode): void
    {
        if (! $this->stripeConfig->isConnectModeConfigured($mode)) {
            throw new \RuntimeException(
                $mode === PaymentProviderAccount::MODE_LIVE
                    ? 'Stripe live mode is not configured yet. Add live Stripe keys before connecting a live account.'
                    : 'Stripe test mode is not configured yet. Add test Stripe keys before connecting a test account.'
            );
        }
    }
}
