<?php

namespace App\Data\Checkout;

use App\Data\Tax\TaxLineOutput;
use Carbon\CarbonInterface;

final readonly class CheckoutTotalsResult
{
    /**
     * @param  array<string, CheckoutItemTotals>  $itemTotals
     * @param  list<TaxLineOutput>  $taxLines
     * @param  array<string, mixed>  $taxSnapshot
     */
    public function __construct(
        public int $storeId,
        public string $subtotal,
        public string $discountTotal,
        public string $shippingTotal,
        public string $itemsTax,
        public string $shippingTax,
        public string $taxTotal,
        public string $grandTotal,
        public bool $pricesIncludeTax,
        public array $itemTotals,
        public array $taxLines,
        public array $taxSnapshot,
        public CarbonInterface $calculatedAt,
    ) {}

    public function itemTotalsFor(string $lineKey): ?CheckoutItemTotals
    {
        return $this->itemTotals[$lineKey] ?? null;
    }
}
