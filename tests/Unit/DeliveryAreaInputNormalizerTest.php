<?php

namespace Tests\Unit;

use App\Services\Delivery\DeliveryAreaInputNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryAreaInputNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryAreaInputNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new DeliveryAreaInputNormalizer;
    }

    public function test_simple_mode_stores_one_country_and_prefix_postal_rule(): void
    {
        $request = Request::create('/', 'POST', [
            'country_code' => 'US',
            'region_codes' => ['TX', 'California'],
            'postal_rules_json' => json_encode([
                ['type' => 'exact', 'value' => '75002'],
                ['type' => 'prefix', 'value' => '606'],
            ]),
        ]);

        $result = $this->normalizer->normalizeFromRequest($request);

        $this->assertSame(['US'], $result['countries']);
        $this->assertSame(['TX', 'CA'], $result['regions']);
        $this->assertSame(['75002', '606*'], $result['postal_patterns']);
    }

    public function test_legacy_wildcard_values_load_as_starts_with_rules(): void
    {
        $rules = $this->normalizer->postalPatternsToRules(['606*', '75002']);

        $this->assertSame([
            ['type' => 'prefix', 'value' => '606'],
            ['type' => 'exact', 'value' => '75002'],
        ], $rules);
    }

    public function test_duplicate_postal_rules_are_removed(): void
    {
        $patterns = $this->normalizer->rulesToPostalPatterns([
            ['type' => 'exact', 'value' => '75002'],
            ['type' => 'exact', 'value' => '75002'],
            ['type' => 'prefix', 'value' => '606'],
        ]);

        $this->assertSame(['75002', '606*'], $patterns);
    }

    public function test_simple_mode_requires_valid_country(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'country_code' => 'ZZZ',
        ]));
    }

    public function test_legacy_comma_fields_remain_supported(): void
    {
        $result = $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'countries' => 'US, CA',
            'regions' => 'Texas, ON',
            'postal_patterns' => '606*, 75002',
        ]));

        $this->assertSame(['US', 'CA'], $result['countries']);
        $this->assertSame(['TX', 'ON'], $result['regions']);
        $this->assertSame(['606*', '75002'], $result['postal_patterns']);
    }

    public function test_legacy_mode_preserves_unknown_country_codes_and_records_warning(): void
    {
        $result = $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'zone_editor_mode' => 'legacy',
            'legacy_countries' => 'US, ZZ',
        ]));

        $this->assertSame(['US', 'ZZ'], $result['countries']);
        $this->assertSame([
            'Country code "ZZ" is not in the standard catalog and was kept as imported.',
        ], $this->normalizer->consumeCountryWarnings());
    }

    public function test_legacy_unknown_country_codes_round_trip_after_save(): void
    {
        $result = $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'zone_editor_mode' => 'legacy',
            'legacy_countries' => 'US, ZZ',
        ]));

        $this->assertSame(['US', 'ZZ'], $result['countries']);

        $secondPass = $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'zone_editor_mode' => 'legacy',
            'legacy_countries' => implode(', ', $result['countries']),
        ]));

        $this->assertSame(['US', 'ZZ'], $secondPass['countries']);
    }

    public function test_tokenize_accepts_array_input_without_string_cast_warning(): void
    {
        $result = $this->normalizer->normalizeFromRequest(Request::create('/', 'POST', [
            'countries' => ['US', 'CA'],
            'regions' => ['TX', 'ON'],
        ]));

        $this->assertSame(['US', 'CA'], $result['countries']);
        $this->assertSame(['TX', 'ON'], $result['regions']);
    }
}
