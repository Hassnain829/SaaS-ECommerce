<?php

namespace App\Data\Tax;

final readonly class ItemTaxAllocation
{
    public function __construct(
        public string $lineKey,
        public string $lineSubtotal,
        public string $taxableAmount,
        public string $taxAmount,
    ) {}
}
