<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Models\PaymentProviderAccount;
use App\Models\Store;
use InvalidArgumentException;

class PaymentProviderManager
{
    public function driver(?string $provider = null): PaymentProviderInterface
    {
        $provider = $provider ?: (string) config('payments.default_provider', 'stripe');

        return match ($provider) {
            'stripe' => app(StripePlatformPaymentProvider::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider}]."),
        };
    }

    public function accountForCheckout(Store $store): ?PaymentProviderAccount
    {
        $provider = (string) config('payments.default_provider', 'stripe');

        if ($provider !== 'stripe') {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider}].");
        }

        $connectedAccount = $this->activeConnectedAccountForStore($store);

        if ($connectedAccount) {
            return $connectedAccount;
        }

        if ($this->canUsePlatformSandboxFallback()) {
            return $this->ensurePlatformSandboxAccount($store);
        }

        return null;
    }

    public function activeConnectedAccountForStore(Store $store): ?PaymentProviderAccount
    {
        return PaymentProviderAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', 'stripe')
            ->where('mode', (string) config('payments.stripe.mode', 'test'))
            ->where('connection_type', 'connect')
            ->where('status', 'active')
            ->where('is_default', true)
            ->where('charges_enabled', true)
            ->first();
    }

    public function canUsePlatformSandboxFallback(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('payments.stripe.allow_platform_sandbox_fallback', true)
            && filled(config('payments.stripe.key'))
            && filled(config('payments.stripe.secret'));
    }

    private function ensurePlatformSandboxAccount(Store $store): PaymentProviderAccount
    {
        $mode = (string) config('payments.stripe.mode', 'test');

        return PaymentProviderAccount::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'provider' => 'stripe',
                'mode' => $mode,
                'connection_type' => 'platform',
            ],
            [
                'display_name' => 'Platform Stripe sandbox',
                'status' => 'active',
                'is_default' => false,
                'settings' => [
                    'publishable_key_configured' => filled(config('payments.stripe.key')),
                    'secret_key_configured' => filled(config('payments.stripe.secret')),
                    'fallback_only' => true,
                ],
                'metadata' => [
                    'managed_by' => 'platform',
                    'checkout_note' => 'Local/testing fallback. Store owners should connect their own Stripe account before production.',
                ],
                'charges_enabled' => true,
                'payouts_enabled' => false,
                'last_verified_at' => now(),
            ]
        );
    }
}
