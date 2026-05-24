<?php

namespace App\Data\Payments;

class PaymentIntentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $providerIntentId,
        public readonly ?string $clientSecret,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currencyCode,
        public readonly array $raw = [],
        public readonly ?string $providerAccountId = null,
        public readonly ?string $mode = null,
    ) {
    }
}
