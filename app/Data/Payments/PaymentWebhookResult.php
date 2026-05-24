<?php

namespace App\Data\Payments;

class PaymentWebhookResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $providerIntentId,
        public readonly string $status,
        public readonly ?float $amount = null,
        public readonly ?string $currencyCode = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $raw = [],
        public readonly ?string $providerAccountId = null,
        public readonly ?string $mode = null,
    ) {
    }
}
