<?php

namespace App\Data\Tax;

use App\Models\TaxRate;

final readonly class TaxAddressInput
{
    public string $countryCode;

    public string $regionCode;

    public function __construct(string $countryCode, ?string $regionCode = null)
    {
        $this->countryCode = TaxRate::normalizeCountryCode($countryCode);
        $this->regionCode = TaxRate::normalizeRegionCode($regionCode);
    }
}
