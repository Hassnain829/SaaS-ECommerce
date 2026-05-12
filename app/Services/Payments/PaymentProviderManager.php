<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
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
}
