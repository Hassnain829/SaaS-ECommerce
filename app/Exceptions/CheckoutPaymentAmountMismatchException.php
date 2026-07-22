<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutPaymentAmountMismatchException extends RuntimeException
{
    public function __construct(
        public readonly int $checkoutId,
        public readonly int $expectedMinor,
        public readonly ?int $providerActualMinor,
        public readonly ?int $localPaymentIntentMinor,
        public readonly string $expectedCurrency,
        public readonly ?string $providerCurrency,
        public readonly string $providerIntentId,
        public readonly ?int $localPaymentIntentAmountAsMinor = null,
    ) {
        parent::__construct('Checkout payment amount or currency did not match the stored checkout total.');
    }

    /**
     * @return array<string, int|string|null>
     */
    public function context(): array
    {
        return [
            'checkout_id' => $this->checkoutId,
            'expected_minor' => $this->expectedMinor,
            'provider_actual_minor' => $this->providerActualMinor,
            'local_payment_intent_minor' => $this->localPaymentIntentMinor,
            'local_payment_intent_amount_as_minor' => $this->localPaymentIntentAmountAsMinor,
            'expected_currency' => $this->expectedCurrency,
            'provider_currency' => $this->providerCurrency,
            'provider_intent_id' => $this->providerIntentId,
        ];
    }
}
