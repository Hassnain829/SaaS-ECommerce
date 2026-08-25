<?php

namespace Tests\Unit;

use App\Services\Delivery\VariantOptionWeightParser;
use Tests\TestCase;

class VariantOptionWeightParserTest extends TestCase
{
    public function test_parses_lb_oz_and_kg_to_store_unit(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertSame(5.0, $parser->parseToStoreUnit('5 lb', 'LB'));
        $this->assertSame(10.0, $parser->parseToStoreUnit('10 lbs', 'LB'));
        $this->assertSame(1.0, $parser->parseToStoreUnit('16 oz', 'LB'));
        $this->assertEqualsWithDelta(2.204, $parser->parseToStoreUnit('1 kg', 'LB'), 0.01);
        $this->assertEqualsWithDelta(1.0, $parser->parseToStoreUnit('1 kg', 'KG'), 0.01);
    }

    public function test_detects_weight_related_option_groups(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertTrue($parser->optionGroupLooksWeightRelated('Weight'));
        $this->assertTrue($parser->optionGroupLooksWeightRelated('Item weight'));
        $this->assertTrue($parser->optionGroupLooksWeightRelated('Shipping weight'));
        $this->assertTrue($parser->optionGroupLooksWeightRelated('Net weight'));
        $this->assertTrue($parser->optionGroupLooksWeightRelated('Package weight'));
        $this->assertTrue($parser->optionGroupLooksWeightRelated('Pack weight'));
        $this->assertFalse($parser->optionGroupLooksWeightRelated('Pack size'));
        $this->assertFalse($parser->optionGroupLooksWeightRelated('Color'));
        $this->assertFalse($parser->optionGroupLooksWeightRelated('Size'));
    }

    public function test_pack_size_bare_numbers_are_not_safe_for_automatic_parse(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertFalse($parser->isSafeForAutomaticWeightParse('Pack size', ['6', '12', '24']));
        $this->assertTrue($parser->isSafeForAutomaticWeightParse('Pack size', ['8 oz', '16 oz', '2 lb']));
    }

    public function test_builds_weight_map_from_option_values(): void
    {
        $parser = app(VariantOptionWeightParser::class);
        $map = $parser->buildWeightMapFromOptionValues(['5 lb', '10 lb', 'Blue'], 'LB');

        $this->assertSame(5.0, $map['5 lb']);
        $this->assertSame(10.0, $map['10 lb']);
        $this->assertNull($map['Blue']);
    }

    public function test_bare_numbers_are_not_treated_as_lb_by_default(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertNull($parser->parseToStoreUnit('5', 'LB'));
        $this->assertNull($parser->parseToStoreUnit('5', 'KG'));
        $this->assertNull($parser->buildWeightMapFromOptionValues(['5', '10'], 'KG')['5']);
    }

    public function test_bare_numbers_use_store_unit_only_when_assumed(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertSame(5.0, $parser->parseBareNumberAsStoreUnit('5', 'KG'));
        $map = $parser->buildWeightMapFromOptionValues(['5', '10', '20'], 'KG', true);

        $this->assertSame(5.0, $map['5']);
        $this->assertSame(10.0, $map['10']);
        $this->assertSame(20.0, $map['20']);
    }

    public function test_automatic_parse_safety_requires_weight_group_or_explicit_units(): void
    {
        $parser = app(VariantOptionWeightParser::class);

        $this->assertTrue($parser->isSafeForAutomaticWeightParse('Weight', ['5', '10']));
        $this->assertTrue($parser->isSafeForAutomaticWeightParse('Size', ['5 lb', '10 lb']));
        $this->assertFalse($parser->isSafeForAutomaticWeightParse('Size', ['10', '12', '14']));
        $this->assertFalse($parser->isSafeForAutomaticWeightParse('Pack size', ['6', '12', '24']));
        $this->assertFalse($parser->isSafeForAutomaticWeightParse('Color', ['Black', 'White']));
    }
}
