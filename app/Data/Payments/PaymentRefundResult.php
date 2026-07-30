<?php

namespace App\Data\Payments;

class PaymentRefundResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $providerRefundId,
        public readonly string $status,
        public readonly ?string $amount = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currencyCode = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
        public readonly array $raw = [],
        public readonly ?string $providerAccountId = null,
        public readonly ?string $mode = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'requires_action', 'processing'], true);
    }

    public function failed(): bool
    {
        return in_array($this->status, ['failed', 'canceled', 'cancelled'], true)
            || filled($this->failureCode);
    }
}
