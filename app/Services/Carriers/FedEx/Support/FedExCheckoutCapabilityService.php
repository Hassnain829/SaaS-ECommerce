<?php

namespace App\Services\Carriers\FedEx\Support;

use App\Models\CarrierAccount;

/**
 * Single authority for FedEx checkout capability mutations.
 */
final class FedExCheckoutCapabilityService
{
    public function platformCheckoutRatesEnabled(): bool
    {
        return (bool) config('carriers.fedex.checkout_rates_enabled', false);
    }

    /**
     * Enable account checkout-rate capability only when the platform flag allows it.
     *
     * @return bool True when account checkout capability was enabled.
     */
    public function enableAccountCheckoutRatesIfAllowed(CarrierAccount $account): bool
    {
        if (! $this->platformCheckoutRatesEnabled()) {
            return false;
        }

        $caps = is_array($account->capabilities) ? $account->capabilities : [];
        $caps['checkout_rates'] = true;
        $caps['rates'] = true;

        $account->forceFill([
            'capabilities' => $caps,
            'enabled_for_checkout' => true,
        ])->save();

        return true;
    }

    /**
     * Controlled return destinations after FedEx connection (no arbitrary URLs).
     *
     * @return array{key: string, label: string, route: string, params?: array<string, mixed>}|null
     */
    public function resolveReturnIntent(?string $intentKey): ?array
    {
        $key = strtolower(trim((string) $intentKey));

        return match ($key) {
            'delivery_checkout_setup' => [
                'key' => 'delivery_checkout_setup',
                'label' => 'Continue delivery setup',
                'route' => 'settings.delivery.setup.delivery-option',
                'params' => [],
            ],
            'fedex_center' => [
                'key' => 'fedex_center',
                'label' => 'Open FedEx Center',
                'route' => 'shippingAutomation',
                'params' => ['tab' => 'providers'],
            ],
            default => null,
        };
    }

    public const SESSION_RETURN_INTENT = 'fedex_connection_return_intent';
}
