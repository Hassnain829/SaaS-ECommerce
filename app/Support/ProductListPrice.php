<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Selling-price summary for product list, inline edit, and workspace.
 *
 * The list must show what shoppers pay (variant prices), not only products.base_price.
 */
final class ProductListPrice
{
    /**
     * Option rows the list popover can edit one-by-one without turning into a spreadsheet.
     * Larger catalogs stay a compact summary; per-option prices belong in the product workspace.
     */
    public const LIST_OPTION_EDITOR_LIMIT = 8;

    /**
     * @param  Collection<int, ProductVariant>|iterable<int, ProductVariant>|null  $variants
     * @return array{
     *     min: float,
     *     max: float,
     *     uniform: bool,
     *     mixed: bool,
     *     compact_list: bool,
     *     variant_count: int,
     *     has_standard_base: bool,
     *     display: string,
     *     list_display: string,
     *     currency: string
     * }
     */
    public static function forProduct(Product $product, $variants = null, ?string $currency = null): array
    {
        $code = strtoupper(trim((string) ($currency ?: ($product->store?->currency ?? 'USD'))) ?: 'USD');
        $variantList = self::variantCollection($product, $variants);

        foreach ($variantList as $variant) {
            if ($variant instanceof ProductVariant && ! $variant->relationLoaded('product')) {
                $variant->setRelation('product', $product);
            }
        }

        $amounts = $variantList
            ->filter(fn ($variant): bool => $variant instanceof ProductVariant)
            ->map(fn (ProductVariant $variant): float => round((float) $variant->price, 2))
            ->values();

        if ($amounts->isEmpty()) {
            $amounts = collect([round((float) $product->base_price, 2)]);
        }

        $min = (float) $amounts->min();
        $max = (float) $amounts->max();
        $uniform = abs($max - $min) < 0.005;
        $variantCount = $variantList->count();
        $compactList = $variantCount > self::LIST_OPTION_EDITOR_LIMIT;
        $hasStandardBase = $variantList->contains(
            fn ($variant): bool => $variant instanceof ProductVariant
                && $variant->relationLoaded('options')
                && $variant->options->isEmpty()
        );
        $display = $uniform
            ? MoneyDisplay::formatWithCode($min, $code)
            : sprintf(
                '%s %s – %s',
                $code,
                number_format($min, 2, '.', ','),
                number_format($max, 2, '.', ',')
            );

        return [
            'min' => $min,
            'max' => $max,
            'uniform' => $uniform,
            'mixed' => ! $uniform && $variantCount > 1,
            'compact_list' => $compactList,
            'variant_count' => $variantCount,
            'has_standard_base' => $hasStandardBase,
            'display' => $display,
            'list_display' => (! $uniform && $compactList)
                ? 'From '.MoneyDisplay::formatWithCode($min, $code)
                : $display,
            'currency' => $code,
        ];
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
