<?php

namespace Tests\Unit;

use App\Services\Catalog\WooCommerceImportNormalizer;
use App\Services\Catalog\WooCommerceImportPreset;
use App\Support\Catalog\WooCommerceCsvDetector;
use PHPUnit\Framework\TestCase;

class WooCommerceCsvDetectorTest extends TestCase
{
    public function test_detects_standard_woocommerce_export_headers(): void
    {
        $headers = [
            'ID', 'Type', 'SKU', 'Name', 'Published', 'Visibility in catalog',
            'Regular price', 'Sale price', 'In stock?', 'Stock', 'Parent',
            'Images', 'Attribute 1 name', 'Attribute 1 value(s)',
        ];

        $this->assertTrue(WooCommerceCsvDetector::detect($headers));
    }

    public function test_does_not_detect_generic_catalog_headers(): void
    {
        $this->assertFalse(WooCommerceCsvDetector::detect([
            'Title', 'SKU', 'Price', 'Stock', 'Category',
        ]));
    }

    public function test_normalizer_swaps_sale_and_regular_price_and_generates_sku(): void
    {
        $headers = ['ID', 'Type', 'SKU', 'Name', 'Published', 'Regular price', 'Sale price', 'Parent'];
        $parent = [
            'ID' => '10',
            'Type' => 'simple',
            'SKU' => '',
            'Name' => 'Mug',
            'Published' => '1',
            'Regular price' => '20',
            'Sale price' => '12',
            'Parent' => '',
        ];
        $context = WooCommerceImportNormalizer::buildContext($headers, [$parent], 9);
        $normalized = WooCommerceImportNormalizer::normalize($headers, $parent, $context, 9, 1);

        $this->assertSame('simple', $normalized['role']);
        $this->assertTrue($normalized['generated_sku']);
        $this->assertSame('woo-10', $normalized['row']['SKU']);
        $this->assertSame('12', $normalized['row']['Regular price']);
        $this->assertSame('20', $normalized['row']['Sale price']);
        $this->assertSame('published', $normalized['row']['Published']);
    }

    public function test_normalizer_rejects_grouped_and_resolves_variation_parent(): void
    {
        $headers = ['ID', 'Type', 'SKU', 'Name', 'Regular price', 'Parent', 'Published'];
        $rows = [
            ['ID' => '20', 'Type' => 'variable', 'SKU' => 'SHIRT', 'Name' => 'Shirt', 'Regular price' => '30', 'Parent' => '', 'Published' => '1'],
            ['ID' => '21', 'Type' => 'variation', 'SKU' => 'SHIRT-RED', 'Name' => 'Shirt', 'Regular price' => '30', 'Parent' => 'id:20', 'Published' => '1'],
            ['ID' => '22', 'Type' => 'grouped', 'SKU' => 'BUNDLE', 'Name' => 'Bundle', 'Regular price' => '40', 'Parent' => '', 'Published' => '1'],
        ];
        $context = WooCommerceImportNormalizer::buildContext($headers, $rows, 3);

        $variation = WooCommerceImportNormalizer::normalize($headers, $rows[1], $context, 3, 2);
        $this->assertSame('variation', $variation['role']);
        $this->assertSame('SHIRT', $variation['row']['Parent']);
        $this->assertNull($variation['parent_error']);

        $grouped = WooCommerceImportNormalizer::normalize($headers, $rows[2], $context, 3, 3);
        $this->assertSame('unsupported', $grouped['role']);
        $this->assertNotNull($grouped['unsupported_reason']);
    }

    public function test_catalog_unit_hints_read_imperial_headers(): void
    {
        $hints = WooCommerceImportPreset::catalogUnitHints(
            [
                'weight' => 'Weight (lbs)',
                'length' => 'Length (in)',
                'width' => 'Width (in)',
                'height' => 'Height (in)',
            ],
            [
                'weight' => '0.45',
                'length' => '4',
                'width' => '2',
                'height' => '3',
            ]
        );

        $this->assertSame('lb', $hints['weight_unit'] ?? null);
        $this->assertSame('in', $hints['dimension_unit'] ?? null);
    }
}
