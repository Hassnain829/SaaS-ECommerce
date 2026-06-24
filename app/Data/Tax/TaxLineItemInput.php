<?php

namespace App\Data\Tax;

use App\Support\Money\DecimalString;
use InvalidArgumentException;

final readonly class TaxLineItemInput
{
    public string $lineKey;

    public string $unitPrice;

    public function __construct(
        string $lineKey,
        public int $quantity,
        string $unitPrice,
        public bool $isTaxable = true,
    ) {
        $lineKey = trim($lineKey);

        if ($lineKey === '') {
            throw new InvalidArgumentException('Line key is required.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $this->lineKey = $lineKey;
        $this->unitPrice = DecimalString::normalizeNonNegative(
            $unitPrice,
            'Unit price must be a non-negative decimal amount.',
        );
    }
}
