<?php

namespace App\Data\Tax;

final readonly class TaxCalculationResult
{
    public const SKIP_REASON_MISSING_COUNTRY = 'missing_country';

    /**
     * @param  list<ItemTaxAllocation>  $itemAllocations
     * @param  list<TaxLineOutput>  $taxLines
     */
    public function __construct(
        public string $itemsSubtotal,
        public string $taxableItemsSubtotal,
        public string $itemsTax,
        public string $shippingTax,
        public string $totalTax,
        public array $itemAllocations,
        public array $taxLines,
        public int $settingsVersion,
        public ?MatchedTaxRate $matchedRate,
        public bool $taxCalculationSkipped,
        public ?string $skipReason,
    ) {}
}
