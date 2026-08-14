<?php

namespace Tests\Unit;

use App\Catalog\ProductImportField;
use App\Services\Catalog\ProductImportAutoColumnMapper;
use PHPUnit\Framework\TestCase;

class ProductImportAutoColumnMapperTest extends TestCase
{
    public function test_guess_maps_official_template_headers(): void
    {
        $headers = [
            'product_name',
            'sku',
            'base_price',
            'stock',
            'brand',
            'category',
            'tags',
            'description',
            'product_type',
            'image_urls',
            'supplier_code',
        ];

        $m = ProductImportAutoColumnMapper::guess($headers);

        $this->assertSame('product_name', $m[ProductImportField::PRODUCT_NAME]);
        $this->assertSame('sku', $m[ProductImportField::SKU]);
        $this->assertSame('base_price', $m[ProductImportField::BASE_PRICE]);
        $this->assertSame('stock', $m[ProductImportField::STOCK]);
        $this->assertSame('brand', $m[ProductImportField::BRAND]);
        $this->assertSame('category', $m[ProductImportField::CATEGORY]);
        $this->assertSame('tags', $m[ProductImportField::TAGS]);
        $this->assertSame('description', $m[ProductImportField::DESCRIPTION]);
        $this->assertSame('product_type', $m[ProductImportField::PRODUCT_TYPE]);
        $this->assertSame('image_urls', $m[ProductImportField::IMAGE_URLS]);
    }

    public function test_guess_maps_imperial_woocommerce_export_headers(): void
    {
        $headers = [
            'ID', 'Type', 'SKU', 'GTIN, UPC, EAN, or ISBN', 'Name', 'Published',
            'Visibility in catalog', 'In stock?', 'Stock', 'Weight (lbs)',
            'Length (in)', 'Width (in)', 'Height (in)', 'Regular price',
            'Categories', 'Images', 'Parent', 'Brands',
            'Attribute 1 name', 'Attribute 1 value(s)',
        ];

        $m = ProductImportAutoColumnMapper::guess($headers);

        $this->assertSame('Name', $m[ProductImportField::PRODUCT_NAME]);
        $this->assertSame('Weight (lbs)', $m[ProductImportField::WEIGHT]);
        $this->assertSame('Length (in)', $m[ProductImportField::LENGTH]);
        $this->assertSame('Width (in)', $m[ProductImportField::WIDTH]);
        $this->assertSame('Height (in)', $m[ProductImportField::HEIGHT]);
        $this->assertSame('Brands', $m[ProductImportField::BRAND]);
        $this->assertSame('GTIN, UPC, EAN, or ISBN', $m[ProductImportField::BARCODE]);
        $this->assertSame('Images', $m[ProductImportField::IMAGE_URLS]);
        $this->assertSame('Parent', $m[ProductImportField::PARENT_SKU]);
    }

    public function test_suggest_custom_maps_supplier_column(): void
    {
        $headers = ['Title', 'SKU', 'Supplier'];
        $mapping = [
            ProductImportField::PRODUCT_NAME => 'Title',
            ProductImportField::SKU => 'SKU',
        ];

        $custom = ProductImportAutoColumnMapper::suggestCustomMappings($headers, $mapping);

        $this->assertCount(1, $custom);
        $this->assertSame('Supplier', $custom[0]['source']);
        $this->assertSame('supplier_code', $custom[0]['key']);
    }
}
