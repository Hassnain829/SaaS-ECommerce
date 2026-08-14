<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Support\Catalog\ProductImportHeaderNormalizer;

/**
 * Maps standard WooCommerce product-export headers onto catalog import fields.
 */
final class WooCommerceImportPreset
{
    /**
     * @param  array<string, string>  $mapping
     * @param  list<string|int>  $headers
     * @return array<string, string>
     */
    public static function apply(array $mapping, array $headers): array
    {
        $byNorm = [];
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                continue;
            }
            $norm = ProductImportHeaderNormalizer::normalizeForMatch($header);
            if ($norm !== '' && ! isset($byNorm[$norm])) {
                $byNorm[$norm] = $header;
            }
        }

        foreach (self::headerMap() as $field => $synonyms) {
            if (($mapping[$field] ?? '') !== '') {
                continue;
            }
            foreach ($synonyms as $synonym) {
                if (isset($byNorm[$synonym])) {
                    $mapping[$field] = $byNorm[$synonym];
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Preserve WooCommerce operational columns as additional product details.
     *
     * @param  list<string|int>  $headers
     * @param  array<string, string>  $mapping
     * @return list<array{source: string, key: string, scope: string}>
     */
    public static function suggestCustomMappings(array $headers, array $mapping): array
    {
        $used = [];
        foreach ($mapping as $header) {
            if (is_string($header) && $header !== '') {
                $used[$header] = true;
            }
        }

        $out = [];
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '' || isset($used[$header])) {
                continue;
            }
            $key = match (ProductImportHeaderNormalizer::normalizeForMatch($header)) {
                'tax status' => 'tax_status',
                'tax class' => 'tax_class',
                'shipping class' => 'shipping_class',
                'backorders allowed?', 'backorders' => 'backorders',
                'in stock?' => 'in_stock',
                'sold individually?' => 'sold_individually',
                'is featured?' => 'featured',
                'allow customer reviews?' => 'allow_reviews',
                'purchase note' => 'purchase_note',
                default => null,
            };
            if ($key !== null) {
                $out[] = ['source' => $header, 'key' => $key, 'scope' => 'product'];
                $used[$header] = true;
            }
        }

        return $out;
    }

    /**
     * WooCommerce uses one SKU/price/stock column for both products and variations.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    public static function mirrorMappedFields(array $fields): array
    {
        $copies = [
            ProductImportField::VARIANT_SKU => ProductImportField::SKU,
            ProductImportField::VARIANT_PRICE => ProductImportField::BASE_PRICE,
            ProductImportField::VARIANT_COMPARE_AT_PRICE => ProductImportField::COMPARE_AT_PRICE,
            ProductImportField::VARIANT_STOCK => ProductImportField::STOCK,
            ProductImportField::VARIANT_STOCK_ALERT => ProductImportField::LOW_STOCK_THRESHOLD,
        ];
        foreach ($copies as $target => $source) {
            if (trim((string) ($fields[$target] ?? '')) === '' && trim((string) ($fields[$source] ?? '')) !== '') {
                $fields[$target] = $fields[$source];
            }
        }

        return $fields;
    }

    /**
     * Record the unit implied by WooCommerce header labels (lbs/in vs kg/cm).
     *
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $catalogMeta
     * @return array<string, string>
     */
    public static function catalogUnitHints(array $mapping, array $catalogMeta): array
    {
        $hints = [];
        if (trim((string) ($catalogMeta['weight'] ?? '')) !== '') {
            $unit = self::unitFromHeader((string) ($mapping[ProductImportField::WEIGHT] ?? ''), [
                'lb' => ['(lbs)', '(lb)', ' lbs', ' lb'],
                'oz' => ['(oz)', ' oz'],
                'kg' => ['(kg)', ' kg'],
                'g' => ['(g)', ' g'],
            ]);
            if ($unit !== null) {
                $hints['weight_unit'] = $unit;
            }
        }

        $hasDimension = trim((string) ($catalogMeta['length'] ?? '')) !== ''
            || trim((string) ($catalogMeta['width'] ?? '')) !== ''
            || trim((string) ($catalogMeta['height'] ?? '')) !== '';
        if ($hasDimension) {
            $dimHeader = trim(implode(' ', [
                (string) ($mapping[ProductImportField::LENGTH] ?? ''),
                (string) ($mapping[ProductImportField::WIDTH] ?? ''),
                (string) ($mapping[ProductImportField::HEIGHT] ?? ''),
            ]));
            $unit = self::unitFromHeader($dimHeader, [
                'in' => ['(in)', '(inches)', ' in', ' inch'],
                'cm' => ['(cm)', ' cm'],
                'mm' => ['(mm)', ' mm'],
            ]);
            if ($unit !== null) {
                $hints['dimension_unit'] = $unit;
            }
        }

        return $hints;
    }

    /**
     * @param  array<string, list<string>>  $units
     */
    private static function unitFromHeader(string $header, array $units): ?string
    {
        $norm = ' '.ProductImportHeaderNormalizer::normalizeForMatch($header).' ';
        if (trim($norm) === '') {
            return null;
        }
        foreach ($units as $unit => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($norm, $needle)) {
                    return $unit;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function headerMap(): array
    {
        return [
            ProductImportField::PRODUCT_NAME => ['name', 'product name', 'title'],
            ProductImportField::SKU => ['sku'],
            ProductImportField::PARENT_SKU => ['parent', 'parent sku'],
            ProductImportField::DESCRIPTION => ['description'],
            ProductImportField::SHORT_DESCRIPTION => ['short description'],
            ProductImportField::BASE_PRICE => ['regular price'],
            ProductImportField::COMPARE_AT_PRICE => ['sale price'],
            ProductImportField::STATUS => ['published'],
            ProductImportField::VISIBILITY => ['visibility in catalog', 'visibility'],
            ProductImportField::CATEGORY => ['categories', 'category'],
            ProductImportField::BRAND => ['brands', 'brand', 'brand name'],
            ProductImportField::BARCODE => ['gtin, upc, ean, or isbn', 'gtin', 'upc', 'ean', 'isbn', 'barcode'],
            ProductImportField::TAGS => ['tags'],
            ProductImportField::STOCK => ['stock', 'stock quantity'],
            ProductImportField::LOW_STOCK_THRESHOLD => ['low stock amount', 'low stock threshold'],
            ProductImportField::WEIGHT => ['weight (lbs)', 'weight lbs', 'weight (lb)', 'weight (oz)', 'weight kg', 'weight (kg)', 'weight'],
            ProductImportField::LENGTH => ['length (in)', 'length in', 'length (inches)', 'length cm', 'length (cm)', 'length'],
            ProductImportField::WIDTH => ['width (in)', 'width in', 'width (inches)', 'width cm', 'width (cm)', 'width'],
            ProductImportField::HEIGHT => ['height (in)', 'height in', 'height (inches)', 'height cm', 'height (cm)', 'height'],
            ProductImportField::IMAGE_URLS => ['images', 'image urls'],
            ProductImportField::OPTION_1_NAME => ['attribute 1 name'],
            ProductImportField::OPTION_1_VALUE => ['attribute 1 value(s)', 'attribute 1 value', 'attribute 1 values'],
            ProductImportField::OPTION_2_NAME => ['attribute 2 name'],
            ProductImportField::OPTION_2_VALUE => ['attribute 2 value(s)', 'attribute 2 value', 'attribute 2 values'],
            ProductImportField::OPTION_3_NAME => ['attribute 3 name'],
            ProductImportField::OPTION_3_VALUE => ['attribute 3 value(s)', 'attribute 3 value', 'attribute 3 values'],
        ];
    }
}
