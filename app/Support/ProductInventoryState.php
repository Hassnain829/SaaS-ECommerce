<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Shared inventory status for product list, filters, stats, and inline edits.
 *
 * States:
 * - out: no sellable units
 * - low: at least one variant is above zero and at/below its low-stock alert
 * - in: otherwise healthy
 */
final class ProductInventoryState
{
    public const OUT = 'out';

    public const LOW = 'low';

    public const IN = 'in';

    /**
     * @param  Collection<int, ProductVariant>|iterable<int, ProductVariant>|null  $variants
     * @return array{inventory: int, alert: int, state: string, is_low: bool, is_out: bool}
     */
    public static function forProduct(Product $product, $variants = null): array
    {
        $variantList = self::variantCollection($product, $variants);
        $inventory = (int) ($product->variants_sum_stock ?? $variantList->sum(fn (ProductVariant $variant): int => (int) $variant->stock));
        $alert = self::alertLevel($product, $variantList);
        $isOut = $inventory <= 0;
        $isLow = ! $isOut && self::hasLowVariant($variantList);

        // Fallback when variants were not loaded: compare totals to alert (list aggregates).
        if (! $isOut && ! $isLow && $variantList->isEmpty() && $alert > 0 && $inventory <= $alert) {
            $isLow = true;
        }

        $state = $isOut ? self::OUT : ($isLow ? self::LOW : self::IN);

        return [
            'inventory' => max(0, $inventory),
            'alert' => max(0, $alert),
            'state' => $state,
            'is_low' => $isLow,
            'is_out' => $isOut,
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>|iterable<int, ProductVariant>  $variants
     */
    public static function hasLowVariant($variants): bool
    {
        foreach ($variants as $variant) {
            if (! $variant instanceof ProductVariant) {
                continue;
            }
            $stock = (int) $variant->stock;
            $alert = (int) $variant->stock_alert;
            if ($stock > 0 && $alert > 0 && $stock <= $alert) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ProductVariant>|iterable<int, ProductVariant>|null  $variants
     */
    public static function alertLevel(Product $product, $variants = null): int
    {
        $variantList = self::variantCollection($product, $variants);
        if ($variantList->isNotEmpty()) {
            return max(0, (int) $variantList->max(fn (ProductVariant $variant): int => (int) $variant->stock_alert));
        }

        if (isset($product->variants_max_stock_alert) && $product->variants_max_stock_alert !== null) {
            return max(0, (int) $product->variants_max_stock_alert);
        }

        $meta = is_array($product->meta) ? $product->meta : [];

        return max(0, (int) ($meta['stock_alert'] ?? 0));
    }

    /**
     * @param  Collection<int, ProductVariant>|iterable<int, ProductVariant>|null  $variants
     * @return Collection<int, ProductVariant>
     */
    private static function variantCollection(Product $product, $variants = null): Collection
    {
        if ($variants instanceof Collection) {
            return $variants;
        }

        if (is_iterable($variants)) {
            return collect($variants);
        }

        if ($product->relationLoaded('variants')) {
            return $product->variants;
        }

        return collect();
    }
}
