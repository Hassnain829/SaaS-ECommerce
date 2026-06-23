<?php

namespace Tests\Unit;

use App\Catalog\ProductImportField;
use App\Services\Catalog\ProductImportRowMapper;
use Tests\TestCase;

class ProductImportRowMapperTest extends TestCase
{
    private ProductImportRowMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = app(ProductImportRowMapper::class);
    }

    public function test_cells_to_keyed_row_aligns_headers_with_cells(): void
    {
        $row = $this->mapper->cellsToKeyedRow(
            ['SKU', 'Title', ''],
            ['ABC-1', 'Blue Shirt', 'ignored'],
        );

        $this->assertSame([
            'SKU' => 'ABC-1',
            'Title' => 'Blue Shirt',
        ], $row);
    }

    public function test_extract_mapped_fields_ignores_unknown_and_empty_mappings(): void
    {
        $row = ['Name' => 'Widget', 'SKU' => 'W-1', 'Extra' => 'x'];
        $mapping = [
            ProductImportField::PRODUCT_NAME => 'Name',
            ProductImportField::SKU => 'SKU',
            'not_a_field' => 'Extra',
            ProductImportField::BASE_PRICE => '',
        ];

        $fields = $this->mapper->extractMappedFields($row, $mapping);

        $this->assertSame([
            ProductImportField::PRODUCT_NAME => 'Widget',
            ProductImportField::SKU => 'W-1',
        ], $fields);
    }

    public function test_collect_unmapped_extras_preserves_unknown_columns_only(): void
    {
        $headers = ['Name', 'SKU', 'Vendor note'];
        $row = ['Name' => 'Widget', 'SKU' => 'W-1', 'Vendor note' => 'Handle with care'];
        $mapping = [ProductImportField::PRODUCT_NAME => 'Name', ProductImportField::SKU => 'SKU'];
        $customMappings = [['source' => 'Name', 'key' => 'legacy_name', 'scope' => 'product']];

        $extras = $this->mapper->collectUnmappedExtras($row, $headers, $mapping, $customMappings);

        $this->assertSame(['Vendor note' => 'Handle with care'], $extras);
    }

    public function test_extract_custom_field_values_respects_product_variant_and_attribute_scopes(): void
    {
        $row = [
            'Color' => 'Blue',
            'Size' => 'L',
            'Material' => 'Cotton|Wool',
        ];
        $customMappings = [
            ['source' => 'Color', 'key' => 'color_label', 'scope' => 'product'],
            ['source' => 'Size', 'key' => 'size_label', 'scope' => 'variant'],
            ['source' => 'Material', 'key' => 'material', 'scope' => 'attribute'],
        ];

        [$productCustom, $variantCustom] = $this->mapper->extractCustomFieldValues($row, $customMappings);
        $attributes = $this->mapper->extractAttributeValues($row, $customMappings);

        $this->assertSame(['color_label' => 'Blue'], $productCustom);
        $this->assertSame(['size_label' => 'L'], $variantCustom);
        $this->assertSame(['material' => ['Cotton', 'Wool']], $attributes);
    }

    public function test_split_delimited_prefers_pipe_semicolon_and_newline_before_comma(): void
    {
        $this->assertSame(['A', 'B'], $this->mapper->splitDelimited('A|B'));
        $this->assertSame(['A', 'B'], $this->mapper->splitDelimited('A;B'));
        $this->assertSame(['A', 'B'], $this->mapper->splitDelimited("A\nB"));
        $this->assertSame(['A', 'B'], $this->mapper->splitDelimited('A, B'));
        $this->assertSame(['Single'], $this->mapper->splitDelimited('Single'));
        $this->assertSame([], $this->mapper->splitDelimited('   '));
    }
}
