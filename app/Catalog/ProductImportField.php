<?php

namespace App\Catalog;

/**
 * Canonical import targets (column_mapping keys → source header names as values).
 */
final class ProductImportField
{
    public const PRODUCT_NAME = 'product_name';

    public const SKU = 'sku';

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

    public const VARIANT_OPTION_1 = 'variant_option_1';

    public const VARIANT_OPTION_2 = 'variant_option_2';

    /**
     * @return array<string, string> field => label
     */
    public static function labels(): array
    {
        return [
            self::PRODUCT_NAME => 'Product name',
            self::SKU => 'Product SKU (match key)',
            self::VARIANT_SKU => 'Variant SKU (optional)',
            self::BARCODE => 'Barcode / GTIN',
            self::SHORT_DESCRIPTION => 'Short description',
            self::DESCRIPTION => 'Description',
            self::BASE_PRICE => 'Base price',
            self::COMPARE_AT_PRICE => 'Compare-at price',
            self::COST_PRICE => 'Cost price',
            self::PRODUCT_TYPE => 'Product type',
            self::STATUS => 'Status (published/draft)',
            self::VISIBILITY => 'Visibility (published/draft)',
            self::CATEGORY => 'Categories (delimited)',
            self::BRAND => 'Brand name',
            self::TAGS => 'Tags (delimited)',
            self::STOCK => 'Stock quantity',
            self::LOW_STOCK_THRESHOLD => 'Low stock threshold',
            self::WEIGHT => 'Weight',
            self::LENGTH => 'Length',
            self::WIDTH => 'Width',
            self::HEIGHT => 'Height',
            self::IMAGE_URLS => 'Image URLs (delimited)',
            self::VARIANT_OPTION_1 => 'Variant option 1 (stored in meta)',
            self::VARIANT_OPTION_2 => 'Variant option 2 (stored in meta)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredForImport(): array
    {
        return [self::PRODUCT_NAME, self::SKU];
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
}
