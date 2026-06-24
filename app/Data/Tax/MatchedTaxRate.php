<?php

namespace App\Data\Tax;

use App\Models\TaxRate;

final readonly class MatchedTaxRate
{
    public function __construct(
        public int $taxRateId,
        public string $name,
        public string $countryCode,
        public string $regionCode,
        public string $ratePercent,
        public int $priority,
    ) {}

    public static function fromModel(TaxRate $rate): self
    {
        return new self(
            taxRateId: (int) $rate->id,
            name: (string) $rate->name,
            countryCode: (string) $rate->country_code,
            regionCode: (string) $rate->region_code,
            ratePercent: bcadd((string) $rate->rate_percent, '0', 4),
            priority: (int) $rate->priority,
        );
    }
}
