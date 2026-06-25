<?php

namespace App\Data\Payments;

final readonly class PaymentIntentUpdateResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $providerIntentId,
        public int $amountMinor,
        public string $currencyCode,
        public string $status,
        public ?string $clientSecret,
        public array $raw,
        public ?string $mode = null,
    ) {}
}
