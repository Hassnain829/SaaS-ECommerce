<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Support\Catalog\ProductImportHeaderNormalizer;

/**
 * Guesses column_mapping from spreadsheet headers so standard files can skip manual mapping.
 */
final class ProductImportAutoColumnMapper
{
    /**
     * @param  list<string|int>  $headers
     * @return array<string, string>
     */
    public static function guess(array $headers): array
    {
        $mapping = [];
        foreach (array_keys(ProductImportField::labels()) as $k) {
            $mapping[$k] = '';
        }

        /** @var array<string, string> $byNorm normalized label => first original header (storage key) */
        $byNorm = [];
        foreach ($headers as $h) {
            if (! is_string($h) || $h === '') {
                continue;
            }
            $n = ProductImportHeaderNormalizer::normalizeForMatch($h);
            if ($n === '') {
                continue;
            }
            if (! isset($byNorm[$n])) {
                $byNorm[$n] = $h;
            }
        }

        $assigned = [];

        foreach (self::rules() as [$field, $synonyms]) {
            if (($mapping[$field] ?? '') !== '') {
                continue;
            }
            foreach ($synonyms as $syn) {
                if (! isset($byNorm[$syn])) {
                    continue;
                }
                $header = $byNorm[$syn];
                if (isset($assigned[$header])) {
                    continue;
                }
                $mapping[$field] = $header;
                $assigned[$header] = true;

                break;
            }
        }

        return $mapping;
    }

    /**
     * Specific rules first, generic last. Avoid header names that are usually cell values (e.g. "Active").
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    private static function rules(): array
    {
        return [
            [ProductImportField::PARENT_SKU, ['parent sku', 'parent product sku', 'parent id', 'master sku']],
            [ProductImportField::VARIANT_SKU, ['variant sku', 'child sku']],
            [ProductImportField::OPTION_1_NAME, ['option 1 name', 'option1 name', 'option name 1', 'attr 1 name']],
            [ProductImportField::OPTION_1_VALUE, ['option 1 value', 'option1 value', 'option value 1', 'attr 1 value']],
            [ProductImportField::OPTION_2_NAME, ['option 2 name', 'option2 name', 'option name 2', 'attr 2 name']],
            [ProductImportField::OPTION_2_VALUE, ['option 2 value', 'option2 value', 'option value 2', 'attr 2 value']],
            [ProductImportField::OPTION_3_NAME, ['option 3 name', 'option3 name', 'attr 3 name']],
            [ProductImportField::OPTION_3_VALUE, ['option 3 value', 'option3 value', 'attr 3 value']],
            [ProductImportField::VARIANT_STOCK, ['variant stock', 'variant quantity', 'qty per variant']],
            [ProductImportField::VARIANT_PRICE, ['variant price', 'variant unit price', 'price per variant', 'unit price']],
            [ProductImportField::VARIANT_COMPARE_AT_PRICE, ['variant compare at price', 'variant compare price', 'variant msrp']],
            [ProductImportField::VARIANT_STOCK_ALERT, ['variant low stock', 'variant stock alert', 'variant reorder']],
            [ProductImportField::VARIANT_IMAGE_URL, ['variant image url', 'variant image', 'variant photo url']],
            [ProductImportField::VARIANT_STATUS, ['variant status', 'variant note', 'variant listing note']],
            [ProductImportField::VARIANT_OPTION_1, ['variant option 1', 'variation 1']],
            [ProductImportField::VARIANT_OPTION_2, ['variant option 2', 'variation 2']],
            [ProductImportField::COMPARE_AT_PRICE, ['compare at price', 'compare price', 'msrp', 'list price', 'was price']],
            [ProductImportField::COST_PRICE, ['cost price', 'unit cost', 'cogs']],
            [ProductImportField::BASE_PRICE, ['base price', 'retail price', 'selling price', 'regular price', 'price']],
            [ProductImportField::LOW_STOCK_THRESHOLD, ['low stock threshold', 'reorder point', 'min stock', 'safety stock']],
            [ProductImportField::STOCK, ['stock', 'quantity', 'qty', 'inventory', 'on hand', 'stock quantity', 'available']],
            [ProductImportField::IMAGE_URLS, ['image urls', 'image url', 'images', 'photos', 'image links', 'gallery urls']],
            [ProductImportField::SHORT_DESCRIPTION, ['short description', 'summary', 'excerpt', 'subtitle']],
            [ProductImportField::DESCRIPTION, ['description', 'long description', 'details', 'body']],
            [ProductImportField::BRAND, ['brand', 'brand name', 'manufacturer', 'vendor']],
            [ProductImportField::CATEGORY, ['category', 'categories', 'taxonomy', 'collection', 'department']],
            [ProductImportField::TAGS, ['tags', 'keywords', 'labels']],
            [ProductImportField::PRODUCT_TYPE, ['product type', 'item type']],
            [ProductImportField::STATUS, ['status', 'publish status', 'listing status', 'product status']],
            [ProductImportField::VISIBILITY, ['visibility', 'catalog visibility']],
            [ProductImportField::BARCODE, ['barcode', 'gtin', 'ean', 'upc', 'mpn', 'isbn']],
            [ProductImportField::WEIGHT, ['weight', 'mass']],
            [ProductImportField::LENGTH, ['length']],
            [ProductImportField::WIDTH, ['width']],
            [ProductImportField::HEIGHT, ['height']],
            [ProductImportField::PRODUCT_NAME, ['product name', 'product title', 'item name', 'title', 'name', 'handle']],
            [ProductImportField::SKU, ['sku', 'product sku', 'item sku', 'product code', 'item code', 'article number', 'stock keeping unit']],
        ];
    }

    /**
     * Optional custom-field rows for columns that are not built-in catalog targets (e.g. supplier_code in the template).
     *
     * @param  list<string|int>  $headers
     * @param  array<string, string>  $mapping
     * @return list<array{source: string, key: string, scope: string}>
     */
    public static function suggestCustomMappings(array $headers, array $mapping): array
    {
        $used = [];
        foreach ($mapping as $v) {
            if (is_string($v) && $v !== '') {
                $used[$v] = true;
            }
        }

        $out = [];
        foreach ($headers as $h) {
            if (! is_string($h) || $h === '' || isset($used[$h])) {
                continue;
            }
            $n = ProductImportHeaderNormalizer::normalizeForMatch($h);
            $key = match ($n) {
                'supplier', 'supplier code', 'supplier_code', 'vendor code', 'supplier id' => 'supplier_code',
                'material', 'materials' => 'material',
                'country of origin', 'origin' => 'country_of_origin',
                default => null,
            };
            if ($key !== null) {
                $out[] = ['source' => $h, 'key' => $key, 'scope' => 'product'];
            }
        }

        return $out;
    }
}
