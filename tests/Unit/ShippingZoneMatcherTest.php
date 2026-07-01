<?php

namespace Tests\Unit;

use App\Models\ShippingZone;
use App\Services\Shipping\ShippingZoneMatcher;
use Tests\TestCase;

class ShippingZoneMatcherTest extends TestCase
{
    public function test_us_state_abbreviation_matches_full_name_in_zone(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['US'],
            'regions' => ['Texas'],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'US',
            'state' => 'TX',
            'postal_code' => '75002',
        ]));
    }

    public function test_us_state_full_name_matches_abbreviation_in_zone(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['US'],
            'regions' => ['TX'],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'US',
            'state' => 'Texas',
            'postal_code' => '75002',
        ]));
    }
}
