<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Store;
use RuntimeException;

final class ProductImportSourceIdentity
{
    public const SYSTEM_WOOCOMMERCE = 'woocommerce';

    /**
     * @param  array<string, string>  $keyedRow
     */
    public static function findProduct(Store $store, ProductImport $import, string $sku, array $keyedRow = []): ?Product
    {
        if (! self::isWooRow($keyedRow)) {
            return self::findProductBySku($store, $sku);
        }

        $sourceSite = self::requiredSourceSite($import);
        $sourceProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        $found = null;
        if ($sourceProductId !== '') {
            $found = Product::query()
                ->where('store_id', $store->id)
                ->where('source_system', self::SYSTEM_WOOCOMMERCE)
                ->where('source_site', $sourceSite)
                ->where('source_product_id', $sourceProductId)
                ->first();
        }

        $skuMatch = self::findProductBySku($store, $sku);
        if ($found) {
            if ($skuMatch && (int) $skuMatch->id !== (int) $found->id) {
                throw self::skuCollision($sku, 'product');
            }

            return $found;
        }

        if (! $skuMatch) {
            return null;
        }

        if (
            blank($skuMatch->source_system)
            && blank($skuMatch->source_site)
            && self::allowsExistingSkuLink($import)
        ) {
            return $skuMatch;
        }

        throw self::skuCollision($sku, 'product');
    }

    /**
     * @param  list<int>  $optionIds
     * @param  array<string, string>  $keyedRow
     */
    public static function findVariant(
        Product $product,
        ProductImport $import,
        string $desiredSku,
        array $optionIds,
        array $keyedRow,
        callable $findByOptions,
    ): ?ProductVariant {
        if (! self::isWooRow($keyedRow)) {
            $byOptions = $findByOptions($product, $optionIds);
            if ($byOptions instanceof ProductVariant) {
                return $byOptions;
            }

            return self::findVariantBySku($product, $desiredSku);
        }

        $sourceSite = self::requiredSourceSite($import);
        $sourceVariationId = trim((string) ($keyedRow['__woo_variation_id'] ?? ''));
        if ($sourceVariationId !== '') {
            $found = ProductVariant::query()
                ->where('store_id', $product->store_id)
                ->where('source_system', self::SYSTEM_WOOCOMMERCE)
                ->where('source_site', $sourceSite)
                ->where('source_variation_id', $sourceVariationId)
                ->first();
            if ($found) {
                if ((int) $found->product_id !== (int) $product->id) {
                    throw new RuntimeException('WooCommerce variation ID '.$sourceVariationId.' is already linked to another product from this source site.');
                }

                return $found;
            }
        }

        $byOptions = $findByOptions($product, $optionIds);
        $skuMatch = self::findStoreVariantBySku((int) $product->store_id, $desiredSku);
        $candidate = $byOptions instanceof ProductVariant ? $byOptions : $skuMatch;
        if (! $candidate) {
            return null;
        }
        if ((int) $candidate->product_id !== (int) $product->id) {
            throw self::skuCollision($desiredSku, 'variant');
        }

        // Simple Woo products have no variation ID; once the product is linked to
        // this source, its default variant can be updated safely on re-import.
        if ($sourceVariationId === '' && self::productMatchesImportSource($product, $sourceSite)) {
            return $candidate;
        }

        if (
            blank($candidate->source_system)
            && blank($candidate->source_site)
            && self::allowsExistingSkuLink($import)
        ) {
            return $candidate;
        }

        throw self::skuCollision($desiredSku, 'variant');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $keyedRow
     * @return array<string, mixed>
     */
    public static function mergeMeta(array $meta, ProductImport $import, array $keyedRow): array
    {
        $wooProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        if (! self::isWooRow($keyedRow)) {
            return $meta;
        }

        $originalSlug = trim((string) ($keyedRow['__woo_original_slug'] ?? ''));
        $meta['source_identity'] = array_filter([
            'system' => self::SYSTEM_WOOCOMMERCE,
            'site' => self::requiredSourceSite($import),
            'woo_product_id' => $wooProductId !== '' ? $wooProductId : null,
            'woo_variation_id' => trim((string) ($keyedRow['__woo_variation_id'] ?? '')) ?: null,
            'source_sku' => trim((string) ($keyedRow['__woo_source_sku'] ?? '')) ?: null,
            'import_batch_id' => (int) $import->id,
            'original_slug' => $originalSlug !== '' ? $originalSlug : null,
            'original_path' => $originalSlug !== '' ? '/product/'.$originalSlug.'/' : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return $meta;
    }

    /** @param array<string, string> $keyedRow */
    public static function productColumns(ProductImport $import, array $keyedRow): array
    {
        $wooProductId = trim((string) ($keyedRow['__woo_product_id'] ?? ''));
        if ($wooProductId === '' || ! self::isWooRow($keyedRow)) {
            return [];
        }

        return [
            'source_system' => self::SYSTEM_WOOCOMMERCE,
            'source_site' => self::requiredSourceSite($import),
            'source_product_id' => $wooProductId,
        ];
    }

    /** @param array<string, string> $keyedRow */
    public static function variantColumns(ProductImport $import, array $keyedRow): array
    {
        $wooVariationId = trim((string) ($keyedRow['__woo_variation_id'] ?? ''));
        if ($wooVariationId === '' || ! self::isWooRow($keyedRow)) {
            return [];
        }

        return [
            'source_system' => self::SYSTEM_WOOCOMMERCE,
            'source_site' => self::requiredSourceSite($import),
            'source_variation_id' => $wooVariationId,
        ];
    }

    public static function normalizeSourceSite(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }

    private static function requiredSourceSite(ProductImport $import): string
    {
        $sourceSite = self::normalizeSourceSite((string) ($import->source_site ?: data_get($import->import_state, 'source_site', '')));
        if ($sourceSite === null) {
            throw new RuntimeException('Enter the exact WordPress/WooCommerce source URL before confirming this import.');
        }

        return $sourceSite;
    }

    /** @param array<string, string> $keyedRow */
    private static function isWooRow(array $keyedRow): bool
    {
        return trim((string) ($keyedRow['__woo_role'] ?? '')) !== ''
            || trim((string) ($keyedRow['__woo_product_id'] ?? '')) !== ''
            || trim((string) ($keyedRow['__woo_variation_id'] ?? '')) !== '';
    }

    private static function allowsExistingSkuLink(ProductImport $import): bool
    {
        return data_get($import->import_state, 'approve_existing_sku_links') === true;
    }

    private static function productMatchesImportSource(Product $product, string $sourceSite): bool
    {
        return $product->source_system === self::SYSTEM_WOOCOMMERCE
            && $product->source_site === $sourceSite;
    }

    private static function findProductBySku(Store $store, string $sku): ?Product
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return Product::query()
            ->where('store_id', $store->id)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
            ->first();
    }

    private static function findVariantBySku(Product $product, string $sku): ?ProductVariant
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return $product->variants()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
    }

    private static function findStoreVariantBySku(int $storeId, string $sku): ?ProductVariant
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        return ProductVariant::query()
            ->where('store_id', $storeId)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
            ->first();
    }

    private static function skuCollision(string $sku, string $kind): RuntimeException
    {
        return new RuntimeException(
            'SKU "'.$sku.'" already belongs to an existing '.$kind.'. The WooCommerce row was not linked. Confirm the SKU link only if both records are the same catalog item.'
        );
    }
}
