<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Store;

final class ProductImportSourceIdentity
{
    public const SYSTEM_WOOCOMMERCE = 'woocommerce';

    /**
     * @param  array<string, string>  $keyedRow
     */
    public static function findProduct(Store $store, string $sku, array $keyedRow = []): ?Product
    {
        $sourceProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        if ($sourceProductId !== '') {
            $found = Product::query()
                ->where('store_id', $store->id)
                ->where('source_system', self::SYSTEM_WOOCOMMERCE)
                ->where('source_product_id', $sourceProductId)
                ->first();
            if ($found) {
                return $found;
            }
        }

        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return Product::query()
            ->where('store_id', $store->id)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
            ->first();
    }

    /**
     * @param  list<int>  $optionIds
     * @param  array<string, string>  $keyedRow
     */
    public static function findVariant(
        Product $product,
        string $desiredSku,
        array $optionIds,
        array $keyedRow,
        callable $findByOptions,
    ): ?ProductVariant {
        $sourceVariationId = trim((string) ($keyedRow['__woo_variation_id'] ?? ''));
        if ($sourceVariationId !== '') {
            $found = $product->variants()
                ->where('source_variation_id', $sourceVariationId)
                ->first();
            if ($found) {
                return $found;
            }
        }

        $byOptions = $findByOptions($product, $optionIds);
        if ($byOptions instanceof ProductVariant) {
            return $byOptions;
        }

        $desiredSku = trim($desiredSku);
        if ($desiredSku === '') {
            return null;
        }

        return $product->variants()
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($desiredSku)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $keyedRow
     * @return array<string, mixed>
     */
    public static function mergeMeta(array $meta, ProductImport $import, Store $store, array $keyedRow): array
    {
        $wooProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        if ($wooProductId === '' && ($keyedRow['__woo_role'] ?? '') === '') {
            return $meta;
        }

        $originalSlug = trim((string) ($keyedRow['__woo_original_slug'] ?? ''));
        $meta['source_identity'] = array_filter([
            'system' => self::SYSTEM_WOOCOMMERCE,
            'site' => $store->connectedWebsiteUrl(),
            'woo_product_id' => $wooProductId !== '' ? $wooProductId : null,
            'woo_variation_id' => trim((string) ($keyedRow['__woo_variation_id'] ?? '')) ?: null,
            'source_sku' => trim((string) ($keyedRow['__woo_source_sku'] ?? '')) ?: null,
            'import_batch_id' => (int) $import->id,
            'original_slug' => $originalSlug !== '' ? $originalSlug : null,
            'original_path' => $originalSlug !== '' ? '/product/'.$originalSlug.'/' : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return $meta;
    }

    /**
     * @param  array<string, string>  $keyedRow
     */
    public static function productColumns(array $keyedRow): array
    {
        $wooProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        if ($wooProductId === '') {
            return [];
        }

        return [
            'source_system' => self::SYSTEM_WOOCOMMERCE,
            'source_product_id' => $wooProductId,
        ];
    }

    /**
     * @param  array<string, string>  $keyedRow
     */
    public static function variantColumns(array $keyedRow): array
    {
        $wooVariationId = trim((string) ($keyedRow['__woo_variation_id'] ?? ''));
        if ($wooVariationId === '') {
            return [];
        }

        return [
            'source_variation_id' => $wooVariationId,
        ];
    }
}
