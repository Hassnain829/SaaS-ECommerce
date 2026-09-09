<?php

namespace Tests\Unit;

use App\Support\Tax\TaxCountryCatalog;
use PHPUnit\Framework\TestCase;

class TaxCountryCatalogTest extends TestCase
{
    public function test_existing_us_and_germany_codes_are_preserved(): void
    {
        $us = TaxCountryCatalog::regionsFor('US');
        $de = TaxCountryCatalog::regionsFor('DE');

        $this->assertSame('Texas', $us['TX']);
        $this->assertSame('Bavaria', $de['BY']);
        $this->assertArrayNotHasKey('DE-BY', $de);
    }

    public function test_european_and_middle_east_countries_have_subdivisions(): void
    {
        $this->assertSame('Paris', TaxCountryCatalog::regionsFor('FR')['75']);
        $this->assertSame('Milano', TaxCountryCatalog::regionsFor('IT')['MI']);
        $this->assertSame('North Holland', TaxCountryCatalog::regionsFor('NL')['NH']);
        $this->assertSame('Dubai', TaxCountryCatalog::regionsFor('AE')['DU']);
        $this->assertSame('Riyadh', TaxCountryCatalog::regionsFor('SA')['01']);
        $this->assertSame('Amman', TaxCountryCatalog::regionsFor('JO')['AM']);
    }

    public function test_region_label_resolves_france_and_uae(): void
    {
        $this->assertSame('Paris', TaxCountryCatalog::regionLabel('FR', '75'));
        $this->assertSame('Abu Dhabi', TaxCountryCatalog::regionLabel('AE', 'AZ'));
    }
}
