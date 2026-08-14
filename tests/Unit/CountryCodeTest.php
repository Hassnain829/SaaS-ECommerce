<?php

namespace Tests\Unit;

use App\Support\CountryCode;
use PHPUnit\Framework\TestCase;

class CountryCodeTest extends TestCase
{
    public function test_normalizes_united_states_names_and_parenthetical_codes(): void
    {
        $this->assertSame('US', CountryCode::normalize('United States'));
        $this->assertSame('US', CountryCode::normalize('United States (US)'));
        $this->assertSame('US', CountryCode::normalize('USA'));
        $this->assertSame('US', CountryCode::normalize('us'));
    }

    public function test_from_address_prefers_full_country_name_over_truncated_code(): void
    {
        $this->assertSame('US', CountryCode::fromAddress([
            'country' => 'United States',
            'country_code' => 'UN',
        ]));
    }

    public function test_from_address_uses_iso_code_when_country_is_already_iso(): void
    {
        $this->assertSame('CA', CountryCode::fromAddress([
            'country' => 'CA',
            'country_code' => 'CA',
        ]));
    }
}
