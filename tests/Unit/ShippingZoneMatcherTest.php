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

    public function test_country_wide_zone_matches_any_state_and_zip_plus_four(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'United States',
            'state' => 'NY',
            'postal_code' => '10001-1234',
        ]));
        $this->assertTrue($matcher->isCountryWide($zone));
    }

    public function test_region_restricted_zone_rejects_other_states(): void
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
            'postal_code' => '78701',
        ]));
        $this->assertFalse($matcher->matches($zone, [
            'country' => 'US',
            'state' => 'CA',
            'postal_code' => '90001',
        ]));
    }

    public function test_postal_rule_matches_zip_plus_four_and_prefix(): void
    {
        $exact = new ShippingZone([
            'is_active' => true,
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => ['75002'],
        ]);
        $prefix = new ShippingZone([
            'is_active' => true,
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => ['787*'],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($exact, [
            'country' => 'US',
            'state' => 'TX',
            'postal_code' => '75002-4321',
        ]));
        $this->assertFalse($matcher->matches($exact, [
            'country' => 'US',
            'state' => 'TX',
            'postal_code' => '78701',
        ]));
        $this->assertTrue($matcher->matches($prefix, [
            'country' => 'US',
            'state' => 'TX',
            'postal_code' => '78701',
        ]));
    }

    public function test_canada_province_name_and_spaced_postal_code_match(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['CA'],
            'regions' => ['Ontario'],
            'postal_patterns' => ['M5V*'],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'Canada',
            'state' => 'ON',
            'postal_code' => 'M5V 2T6',
        ]));
        $this->assertFalse($matcher->matches($zone, [
            'country' => 'Canada',
            'state' => 'BC',
            'postal_code' => 'V6B 1A1',
        ]));
    }

    public function test_catalog_country_name_matches_iso_zone(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['DE'],
            'regions' => [],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'Germany',
            'postal_code' => '10115',
        ]));
    }

    public function test_france_department_name_matches_code_in_zone(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['FR'],
            'regions' => ['75'],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'France',
            'state' => 'Paris',
            'postal_code' => '75001',
        ]));
        $this->assertFalse($matcher->matches($zone, [
            'country' => 'France',
            'state' => '13',
            'postal_code' => '13001',
        ]));
    }

    public function test_uae_emirate_code_matches_name_in_zone(): void
    {
        $zone = new ShippingZone([
            'is_active' => true,
            'countries' => ['AE'],
            'regions' => ['Dubai'],
            'postal_patterns' => [],
        ]);

        $matcher = new ShippingZoneMatcher;

        $this->assertTrue($matcher->matches($zone, [
            'country' => 'United Arab Emirates',
            'state' => 'DU',
            'postal_code' => '00000',
        ]));
        $this->assertFalse($matcher->matches($zone, [
            'country' => 'AE',
            'state' => 'AZ',
            'postal_code' => '00000',
        ]));
    }
}
