<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Models\PaymentProviderAccount;
use App\Models\Store;
use App\Support\PlatformPaymentMode;
use InvalidArgumentException;

class PaymentProviderManager
{
    public function __construct(
        private readonly StripeConfig $stripeConfig,
    ) {
    }

    public function driver(?string $provider = null): PaymentProviderInterface
    {
        $provider = $provider ?: (string) config('payments.default_provider', 'stripe');

        return match ($provider) {
            'stripe' => app(StripePlatformPaymentProvider::class),
            default => throw new InvalidArgumentException("Unsupported payment provider [{$provider}]."),
        };
    }

    public function platformPaymentModeForStore(Store $store): string
    {
        return PlatformPaymentMode::forStore($store);
    }

    public function accountForCheckout(Store $store, ?string $mode = null): ?PaymentProviderAccount
    {
        $provider = (string) config('payments.default_provider', 'stripe');

        if ($provider !== 'stripe') {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider}].");
        }

        $mode = $this->normalizeMode($mode ?? $this->platformPaymentModeForStore($store));
        $connectedAccount = $this->activeConnectedAccountForStore($store, $mode);

        if ($connectedAccount) {
            return $connectedAccount;
        }

        if ($mode === PlatformPaymentMode::TEST && $this->canUsePlatformSandboxFallback($mode)) {
            return $this->ensurePlatformSandboxAccount($store, $mode);
        }

        return null;
    }

    public function activeConnectedAccountForStore(Store $store, ?string $mode = null): ?PaymentProviderAccount
    {
        $mode = $this->normalizeMode($mode ?? $this->platformPaymentModeForStore($store));

        return PaymentProviderAccount::query()
            ->forStore($store)
            ->stripe()
            ->connect()
            ->mode($mode)
            ->where('status', 'active')
            ->where('is_default', true)
            ->where('charges_enabled', true)
            ->first();
    }

    public function connectAccountForStore(Store $store, string $mode): ?PaymentProviderAccount
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

    public function canUsePlatformSandboxFallback(?string $mode = null): bool
    {
        $mode = $this->normalizeMode($mode ?? PlatformPaymentMode::TEST);

        return $mode === PlatformPaymentMode::TEST
            && app()->environment(['local', 'testing'])
            && (bool) config('payments.stripe.allow_platform_sandbox_fallback', true)
            && $this->stripeConfig->isModeConfigured($mode);
    }

    public function isCheckoutReady(Store $store, ?string $mode = null): bool
    {
        return $this->accountForCheckout($store, $mode) !== null;
    }

    private function ensurePlatformSandboxAccount(Store $store, string $mode): PaymentProviderAccount
    {
        return PaymentProviderAccount::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'provider' => 'stripe',
                'mode' => $mode,
                'connection_type' => PaymentProviderAccount::CONNECTION_PLATFORM,
            ],
            [
                'display_name' => 'Platform Stripe sandbox',
                'status' => 'active',
                'is_default' => false,
                'settings' => [
                    'publishable_key_configured' => filled($this->stripeConfig->stripePublicKey($mode)),
                    'secret_key_configured' => filled($this->stripeConfig->stripeSecretKey($mode)),
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

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return in_array($mode, PlatformPaymentMode::ALL, true) ? $mode : PlatformPaymentMode::TEST;
    }
}
