<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutPaymentSynchronizationException extends RuntimeException
{
    public function __construct(
        public readonly int $checkoutId,
        public readonly string $providerIntentId,
        public readonly string $reason,
        public readonly ?int $expectedAmountMinor = null,
        public readonly ?int $providerAmountMinor = null,
        public readonly ?string $expectedCurrency = null,
        public readonly ?string $providerCurrency = null,
        public readonly ?string $providerStatus = null,
    ) {
        parent::__construct('Checkout payment could not be synchronized with the payment provider.');
    }

    /**
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return [
            'checkout_id' => $this->checkoutId,
            'provider_intent_id' => $this->providerIntentId,
            'reason' => $this->reason,
            'expected_amount_minor' => $this->expectedAmountMinor,
            'provider_amount_minor' => $this->providerAmountMinor,
            'expected_currency' => $this->expectedCurrency,
            'provider_currency' => $this->providerCurrency,
            'provider_status' => $this->providerStatus,
        ];
    }
}
