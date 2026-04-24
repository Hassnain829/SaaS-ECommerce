<?php

namespace App\Catalog;

/**
 * Canonical import targets (column_mapping keys → source header names as values).
 */
final class ProductImportField
{
    public const PRODUCT_NAME = 'product_name';

    public const SKU = 'sku';

    /** Groups multiple spreadsheet rows into one catalog product when set. */
    public const PARENT_SKU = 'parent_sku';

    public const VARIANT_SKU = 'variant_sku';

    public const BARCODE = 'barcode';

    public const SHORT_DESCRIPTION = 'short_description';

    public const DESCRIPTION = 'description';

    public const BASE_PRICE = 'base_price';

    public const COMPARE_AT_PRICE = 'compare_at_price';

    public const COST_PRICE = 'cost_price';

    public const PRODUCT_TYPE = 'product_type';

    public const STATUS = 'status';

    public const VISIBILITY = 'visibility';

    public const CATEGORY = 'category';

    public const BRAND = 'brand';

    public const TAGS = 'tags';

    public const STOCK = 'stock';

    public const LOW_STOCK_THRESHOLD = 'low_stock_threshold';

    public const WEIGHT = 'weight';

    public const LENGTH = 'length';

    public const WIDTH = 'width';

    public const HEIGHT = 'height';

    public const IMAGE_URLS = 'image_urls';

    /** @deprecated Prefer structured option_* columns for variant imports */
    public const VARIANT_OPTION_1 = 'variant_option_1';

    /** @deprecated Prefer structured option_* columns for variant imports */
    public const VARIANT_OPTION_2 = 'variant_option_2';

    public const OPTION_1_NAME = 'option_1_name';

    public const OPTION_1_VALUE = 'option_1_value';

    public const OPTION_2_NAME = 'option_2_name';

    public const OPTION_2_VALUE = 'option_2_value';

    public const OPTION_3_NAME = 'option_3_name';

    public const OPTION_3_VALUE = 'option_3_value';

    public const VARIANT_PRICE = 'variant_price';

    public const VARIANT_COMPARE_AT_PRICE = 'variant_compare_at_price';

    public const VARIANT_STOCK = 'variant_stock';

    public const VARIANT_STOCK_ALERT = 'variant_stock_alert';

    public const VARIANT_IMAGE_URL = 'variant_image_url';

    /** Free text; stored on variant meta for downstream use */
    public const VARIANT_STATUS = 'variant_status';

    public static function optionNameField(int $slot): string
    {
        return match ($slot) {
            1 => self::OPTION_1_NAME,
            2 => self::OPTION_2_NAME,
            3 => self::OPTION_3_NAME,
            default => self::OPTION_1_NAME,
        };
    }

    public static function optionValueField(int $slot): string
    {
        return match ($slot) {
            1 => self::OPTION_1_VALUE,
            2 => self::OPTION_2_VALUE,
            3 => self::OPTION_3_VALUE,
            default => self::OPTION_1_VALUE,
        };
    }

    /**
     * True when the mapping defines at least one full option column pair (name + value).
     */
    public static function usesStructuredVariantRows(array $mapping): bool
    {
        foreach ([1, 2, 3] as $slot) {
            if (self::slotFullyMapped($mapping, $slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int> Slots 1–3 that have both name and value columns mapped.
     */
    public static function structuredVariantSlots(array $mapping): array
    {
        $out = [];
        foreach ([1, 2, 3] as $slot) {
            if (self::slotFullyMapped($mapping, $slot)) {
                $out[] = $slot;
            }
        }

        return $out;
    }

    /**
     * Mapping has a name column but not value, or value but not name (invalid mapping).
     */
    public static function hasPartialOptionSlotMapping(array $mapping): bool
    {
        foreach ([1, 2, 3] as $slot) {
            $n = $mapping[self::optionNameField($slot)] ?? '';
            $v = $mapping[self::optionValueField($slot)] ?? '';
            $hasN = is_string($n) && $n !== '';
            $hasV = is_string($v) && $v !== '';
            if ($hasN xor $hasV) {
                return true;
            }
        }

        return false;
    }

    private static function slotFullyMapped(array $mapping, int $slot): bool
    {
        $n = $mapping[self::optionNameField($slot)] ?? '';
        $v = $mapping[self::optionValueField($slot)] ?? '';

        return is_string($n) && $n !== '' && is_string($v) && $v !== '';
    }

    /**
     * @return array<string, string> field => label
     */
    public static function labels(): array
    {
        return [
            self::PRODUCT_NAME => 'Product name',
            self::SKU => 'Product SKU',
            self::PARENT_SKU => 'Parent product SKU (groups rows into one product)',
            self::VARIANT_SKU => 'Variant SKU',
            self::BARCODE => 'Barcode / GTIN',
            self::SHORT_DESCRIPTION => 'Short description',
            self::DESCRIPTION => 'Description',
            self::BASE_PRICE => 'Base price (product default)',
            self::COMPARE_AT_PRICE => 'Compare-at price (product default)',
            self::COST_PRICE => 'Cost price',
            self::PRODUCT_TYPE => 'Product type',
            self::STATUS => 'Status (published/draft)',
            self::VISIBILITY => 'Visibility (published/draft)',
            self::CATEGORY => 'Categories (delimited)',
            self::BRAND => 'Brand name',
            self::TAGS => 'Tags (delimited)',
            self::STOCK => 'Stock quantity (simple / default row)',
            self::LOW_STOCK_THRESHOLD => 'Low stock threshold (simple / default row)',
            self::WEIGHT => 'Weight',
            self::LENGTH => 'Length',
            self::WIDTH => 'Width',
            self::HEIGHT => 'Height',
            self::IMAGE_URLS => 'Image URLs (delimited, product gallery)',
            self::VARIANT_OPTION_1 => 'Variant option 1 (legacy text, stored in meta)',
            self::VARIANT_OPTION_2 => 'Variant option 2 (legacy text, stored in meta)',
            self::OPTION_1_NAME => 'Option 1 group label (e.g. Color)',
            self::OPTION_1_VALUE => 'Option 1 value (e.g. Red)',
            self::OPTION_2_NAME => 'Option 2 group label (e.g. Size)',
            self::OPTION_2_VALUE => 'Option 2 value (e.g. Medium)',
            self::OPTION_3_NAME => 'Option 3 group label',
            self::OPTION_3_VALUE => 'Option 3 value',
            self::VARIANT_PRICE => 'Variant price',
            self::VARIANT_COMPARE_AT_PRICE => 'Variant compare-at price',
            self::VARIANT_STOCK => 'Variant stock quantity',
            self::VARIANT_STOCK_ALERT => 'Variant low-stock alert',
            self::VARIANT_IMAGE_URL => 'Variant image URL',
            self::VARIANT_STATUS => 'Variant listing note (stored in variant meta)',
        ];
    }

    /**
     * Required for classic one-row-per-product imports.
     *
     * @return list<string>
     */
    public static function requiredForImport(): array
    {
        return [self::PRODUCT_NAME, self::SKU];
    }

    /**
     * System fields required when structured variant columns are used.
     *
     * @return list<string>
     */
    public static function requiredWhenStructuredVariants(array $mapping): array
    {
        if (! self::usesStructuredVariantRows($mapping)) {
            return [];
        }

        $needs = [self::PRODUCT_NAME];
        $hasParent = is_string($mapping[self::PARENT_SKU] ?? null) && $mapping[self::PARENT_SKU] !== '';
        $hasSku = is_string($mapping[self::SKU] ?? null) && $mapping[self::SKU] !== '';
        if (! $hasParent && ! $hasSku) {
            $needs[] = self::PARENT_SKU;
        }

        return $needs;
    }

    /**
     * Fields persisted on normalized product/variant columns where columns exist.
     *
     * @return list<string>
     */
    public static function normalizedProductColumns(): array
    {
        return [
            self::PRODUCT_NAME,
            self::SKU,
            self::DESCRIPTION,
            self::BASE_PRICE,
            self::PRODUCT_TYPE,
            self::STATUS,
            self::VISIBILITY,
        ];
    }

    /**
     * Merchant-facing groups for the import column mapping UI (Day 17.6).
     * Every key in {@see self::labels()} appears in exactly one section.
     *
     * @return list<array{id: string, title: string, intro: string, fields: list<string>, default_open: bool}>
     */
    public static function mappingUiSections(): array
    {
        return [
            [
                'id' => 'required_basics',
                'title' => 'Required basics',
                'intro' => 'Identify each row in your file. For multi-row variant files, map Parent product SKU so rows combine into one product.',
                'fields' => [self::PRODUCT_NAME, self::SKU, self::PARENT_SKU],
                'default_open' => true,
            ],
            [
                'id' => 'product_information',
                'title' => 'Product information',
                'intro' => 'Descriptions, taxonomy, and how the product appears in your catalog.',
                'fields' => [
                    self::SHORT_DESCRIPTION,
                    self::DESCRIPTION,
                    self::BRAND,
                    self::CATEGORY,
                    self::TAGS,
                    self::PRODUCT_TYPE,
                    self::STATUS,
                    self::VISIBILITY,
                    self::BARCODE,
                ],
                'default_open' => true,
            ],
            [
                'id' => 'pricing_inventory',
                'title' => 'Pricing & inventory',
                'intro' => 'Default prices and stock for simple products or the parent row before variant rows.',
                'fields' => [
                    self::BASE_PRICE,
                    self::COMPARE_AT_PRICE,
                    self::COST_PRICE,
                    self::STOCK,
                    self::LOW_STOCK_THRESHOLD,
                    self::WEIGHT,
                    self::LENGTH,
                    self::WIDTH,
                    self::HEIGHT,
                ],
                'default_open' => false,
            ],
            [
                'id' => 'variants',
                'title' => 'Variants',
                'intro' => 'Sellable combinations: option labels and values, per-row SKU, price, stock, and optional notes.',
                'fields' => [
                    self::VARIANT_SKU,
                    self::VARIANT_OPTION_1,
                    self::VARIANT_OPTION_2,
                    self::OPTION_1_NAME,
                    self::OPTION_1_VALUE,
                    self::OPTION_2_NAME,
                    self::OPTION_2_VALUE,
                    self::OPTION_3_NAME,
                    self::OPTION_3_VALUE,
                    self::VARIANT_PRICE,
                    self::VARIANT_COMPARE_AT_PRICE,
                    self::VARIANT_STOCK,
                    self::VARIANT_STOCK_ALERT,
                    self::VARIANT_STATUS,
                ],
                'default_open' => false,
            ],
            [
                'id' => 'images',
                'title' => 'Images',
                'intro' => 'URLs for the product gallery and optional per-variant photos.',
                'fields' => [self::IMAGE_URLS, self::VARIANT_IMAGE_URL],
                'default_open' => false,
            ],
        ];
    }
}
