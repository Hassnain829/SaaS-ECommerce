<?php

namespace App\Services\Payments;

use App\Models\PaymentProviderAccount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class StripeConnectService
{
    public function createOrRetrieveConnectedAccount(Store $store, User $user): PaymentProviderAccount
    {
        $mode = $this->mode();

        $existing = PaymentProviderAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', 'stripe')
            ->where('mode', $mode)
            ->where('connection_type', 'connect')
            ->whereNotNull('provider_account_id')
            ->where('status', '!=', 'disabled')
            ->latest('id')
            ->first();

        if ($existing) {
            $this->markDefault($existing);

            return $existing->fresh();
        }

        $client = $this->client();
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
            ],
        ]);

        $raw = method_exists($account, 'toArray') ? $account->toArray() : (array) $account;

        return DB::transaction(function () use ($store, $user, $mode, $raw): PaymentProviderAccount {
            $account = PaymentProviderAccount::query()->create([
                'store_id' => $store->id,
                'provider' => 'stripe',
                'provider_account_id' => (string) ($raw['id'] ?? ''),
                'mode' => $mode,
                'connection_type' => 'connect',
                'display_name' => 'Connected Stripe account',
                'status' => 'pending',
                'is_default' => true,
                'settings' => [
                    'account_type' => 'express',
                    'onboarding_started_at' => now()->toISOString(),
                ],
                'capabilities' => $raw['capabilities'] ?? null,
                'metadata' => [
                    'created_from' => 'payments_settings',
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

    public function createAccountOnboardingLink(PaymentProviderAccount $account): string
    {
        if (! filled($account->provider_account_id)) {
            throw new \RuntimeException('This Stripe account is missing its account ID.');
        }

        $link = $this->client()->accountLinks->create([
            'account' => (string) $account->provider_account_id,
            'refresh_url' => $this->configuredUrl('connect_refresh_url', route('settings.payments.stripe.refresh', [], true)),
            'return_url' => $this->configuredUrl('connect_return_url', route('settings.payments.stripe.return', [], true)),
            'type' => 'account_onboarding',
        ]);

        return (string) ($link->url ?? '');
    }

    public function refreshAccountStatus(PaymentProviderAccount $account): PaymentProviderAccount
    {
        if (! filled($account->provider_account_id)) {
            return $account;
        }

        $stripeAccount = $this->client()->accounts->retrieve((string) $account->provider_account_id, []);
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

    public function disableLocally(PaymentProviderAccount $account): PaymentProviderAccount
    {
        $account->forceFill([
            'status' => 'disabled',
            'is_default' => false,
            'charges_enabled' => false,
        ])->save();

        return $account->fresh();
    }

    private function markDefault(PaymentProviderAccount $account): void
    {
        PaymentProviderAccount::query()
            ->where('store_id', $account->store_id)
            ->where('provider', $account->provider)
            ->where('mode', $account->mode)
            ->whereKeyNot($account->id)
            ->update(['is_default' => false]);

        $account->forceFill(['is_default' => true])->save();
    }

    private function client(): StripeClient
    {
        $secret = (string) config('payments.stripe.secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe platform secret is not configured.');
        }

        return new StripeClient($secret);
    }

    private function mode(): string
    {
        return (string) config('payments.stripe.mode', 'test');
    }

    private function configuredUrl(string $key, string $fallback): string
    {
        $value = trim((string) config('payments.stripe.'.$key, ''));

        return $value !== '' ? $value : $fallback;
    }
}
