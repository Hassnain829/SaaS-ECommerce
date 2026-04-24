<?php

namespace Tests\Unit;

use App\Catalog\ProductImportField;
use PHPUnit\Framework\TestCase;

class ProductImportFieldMappingSectionsTest extends TestCase
{
    public function test_mapping_ui_sections_cover_all_catalog_label_fields(): void
    {
        $labelKeys = array_keys(ProductImportField::labels());
        $inSection = [];
        foreach (ProductImportField::mappingUiSections() as $section) {
            foreach ($section['fields'] as $field) {
                $inSection[$field] = true;
            }
        }
        foreach ($labelKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $inSection,
                'Import mapping UI section is missing field: '.$key
            );
        }
    }
}
