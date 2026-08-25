<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Immutable catalog identity captured on stock movement creation.
 * Survives product/variant force-delete after FK nullOnDelete.
 */
final class StockMovementIdentitySnapshot
{
    /**
     * @return array{
     *     product_name_snapshot: string|null,
     *     sku_snapshot: string|null,
     *     variant_label_snapshot: string|null
     * }
     */
    public static function resolve(?Product $product, ?ProductVariant $variant = null): array
    {
        if ($variant !== null && ! $variant->relationLoaded('product') && $product !== null) {
            $variant->setRelation('product', $product);
        }

        if ($variant !== null && $product === null) {
            $variant->loadMissing('product');
            $product = $variant->product;
        }

        $sku = null;
        if ($variant !== null && filled($variant->sku)) {
            $sku = (string) $variant->sku;
        } elseif ($product !== null && filled($product->sku)) {
            $sku = (string) $product->sku;
        }

        $variantLabel = null;
        if ($variant !== null) {
            $variant->loadMissing(['options.variationType']);
            $variantLabel = ProductVariantLabel::forVariant($variant, 0, 1);
            if ($variantLabel === 'Default variant' && $variant->options->isEmpty()) {
                $variantLabel = null;
            }
        }

        return [
            'product_name_snapshot' => $product !== null ? (string) $product->name : null,
            'sku_snapshot' => $sku,
            'variant_label_snapshot' => $variantLabel,
        ];
    }
}
