<?php

namespace App\Data\Checkout;

final readonly class CheckoutItemTotals
{
    public function __construct(
        public string $lineKey,
        public string $subtotal,
        public string $discountAmount,
        public string $taxAmount,
        public string $total,
        public bool $isTaxable,
    ) {}
}
