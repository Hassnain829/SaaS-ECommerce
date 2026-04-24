<?php

namespace Tests\Unit;

use App\Support\Catalog\ProductImportHeaderNormalizer;
use PHPUnit\Framework\TestCase;

class ProductImportHeaderNormalizerTest extends TestCase
{
    public function test_trim_for_storage_strips_bom_and_nbsp(): void
    {
        $raw = "\xEF\xBB\xBF"."\xC2\xA0Product\xC2\xA0Name ";
        $this->assertSame('Product Name', ProductImportHeaderNormalizer::trimForStorage($raw));
    }

    public function test_normalize_for_match_unifies_separators(): void
    {
        $this->assertSame('base price', ProductImportHeaderNormalizer::normalizeForMatch('Base-Price'));
        $this->assertSame('product name', ProductImportHeaderNormalizer::normalizeForMatch('product_name'));
    }

    public function test_detects_case_insensitive_duplicates(): void
    {
        $this->assertTrue(ProductImportHeaderNormalizer::hasCaseInsensitiveDuplicateHeaders(['SKU', 'Price', 'sku']));
        $this->assertFalse(ProductImportHeaderNormalizer::hasCaseInsensitiveDuplicateHeaders(['SKU', 'Price', 'Qty']));
    }
}
