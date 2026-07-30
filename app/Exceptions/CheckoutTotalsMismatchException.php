<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutTotalsMismatchException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $checkoutId,
        public readonly array $context = [],
        string $message = 'Checkout financial totals are inconsistent.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_merge([
            'checkout_id' => $this->checkoutId,
        ], $this->context);
    }
}
