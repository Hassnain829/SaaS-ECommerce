<?php

namespace App\Data\Tax;

final readonly class TaxLineOutput
{
    public const APPLIES_TO_ITEMS = 'items';

    public const APPLIES_TO_SHIPPING = 'shipping';

    public function __construct(
        public int $taxRateId,
        public string $jurisdictionCountryCode,
        public string $jurisdictionRegionCode,
        public string $ratePercent,
        public string $taxableAmount,
        public string $taxAmount,
        public string $appliesTo,
        public int $settingsVersion,
    ) {}
}
