<?php

namespace Tests\Unit;

use App\Catalog\ProductImportField;
use App\Support\Catalog\ProductImportRowPayloadSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportRowPayloadSanitizerTest extends TestCase
{
    #[Test]
    public function it_truncates_huge_description_and_keeps_short_fields(): void
    {
        config(['product_import.row_payload_max_json_bytes' => 32768]);
        config(['product_import.row_payload_max_chars_description' => 500]);

        $headers = ['Title', 'SKU', 'Price', 'Desc'];
        $cells = ['My Product', 'S-1', '9.99', str_repeat('Z', 20000)];
        $mapping = [
            ProductImportField::PRODUCT_NAME => 'Title',
            ProductImportField::SKU => 'SKU',
            ProductImportField::BASE_PRICE => 'Price',
            ProductImportField::DESCRIPTION => 'Desc',
        ];

        $out = ProductImportRowPayloadSanitizer::slimForInsert($headers, $cells, $mapping, []);
        $cellsOut = $out['payload']['cells'];
        $this->assertLessThanOrEqual(502, mb_strlen($cellsOut[3]));
        $this->assertSame('My Product', $cellsOut[0]);
        $this->assertTrue($out['payload']['meta']['truncated'] ?? false);
    }

    #[Test]
    public function it_limits_image_url_list_length(): void
    {
        $urls = [];
        for ($i = 0; $i < 50; $i++) {
            $urls[] = 'https://example.com/'.str_repeat('a', 80).'.png';
        }
        $headers = ['Title', 'SKU', 'Img'];
        $cells = ['X', 'S-2', implode('|', $urls)];
        $mapping = [
            ProductImportField::PRODUCT_NAME => 'Title',
            ProductImportField::SKU => 'SKU',
            ProductImportField::IMAGE_URLS => 'Img',
        ];

        $out = ProductImportRowPayloadSanitizer::slimForInsert($headers, $cells, $mapping, []);
        $imgCell = $out['payload']['cells'][2];
        $this->assertLessThan(strlen(implode('|', $urls)), strlen($imgCell));
        $this->assertLessThanOrEqual((int) config('product_import.row_payload_max_chars_image_urls_field', 8000), strlen($imgCell));
    }

    #[Test]
    public function it_produces_json_under_configured_byte_cap(): void
    {
        config(['product_import.row_payload_max_json_bytes' => 6000]);
        $headers = ['A', 'B'];
        $cells = [str_repeat('q', 10000), str_repeat('r', 10000)];
        $mapping = [ProductImportField::PRODUCT_NAME => 'A', ProductImportField::SKU => 'B'];

        $out = ProductImportRowPayloadSanitizer::slimForInsert($headers, $cells, $mapping, []);
        $json = json_encode($out['payload'], JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);
        $this->assertLessThanOrEqual(6000, strlen($json));
    }
}
