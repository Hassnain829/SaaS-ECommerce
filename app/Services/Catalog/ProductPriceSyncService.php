<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductListPrice;

final class ProductPriceSyncService
{
    /**
     * Set one selling price for a product.
     *
     * When every variant should follow that price, stored overrides are cleared
     * so rows inherit products.base_price. Option-only products (no standard /
     * base row) always follow the list price. A standard/base row keeps
     * separately priced size/color rows unless $applyToEveryVariant is true.
     */
    public function setSharedSellingPrice(Product $product, float $price, bool $applyToEveryVariant = false): void
    {
        $price = round($price, 2);
        $storeId = (int) $product->store_id;

        $product->update(['base_price' => $price]);

        $hasStandardBase = $product->variants()
            ->where('store_id', $storeId)
            ->whereDoesntHave('options')
            ->exists();
        $hasOptionVariants = $product->variants()
            ->where('store_id', $storeId)
            ->whereHas('options')
            ->exists();

        if ($applyToEveryVariant || ! $hasStandardBase || ! $hasOptionVariants) {
            $product->variants()->where('store_id', $storeId)->update(['price' => null]);

            return;
        }

        $product->variants()
            ->where('store_id', $storeId)
            ->whereDoesntHave('options')
            ->update(['price' => null]);
    }

    /**
     * @param  list<array{id: int, price: float}>  $rows
     * @return array<string, mixed>
     */
    public function setVariantPrices(Product $product, array $rows): array
    {
        $storeId = (int) $product->store_id;
        $ids = [];
        $normalized = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $ids[] = $id;
            $normalized[$id] = round((float) ($row['price'] ?? 0), 2);
        }

        $variants = $product->variants()
            ->where('store_id', $storeId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $saved = [];
        foreach ($normalized as $id => $amount) {
            $variant = $variants->get($id);
            if (! $variant instanceof ProductVariant) {
                continue;
            }
            $variant->update(['price' => $amount]);
            $saved[] = $amount;
        }

        if ($saved === []) {
            return $this->summary($product);
        }

        $unique = array_values(array_unique($saved));
        if (count($unique) === 1) {
            $shared = $unique[0];
            $product->update(['base_price' => $shared]);
            $product->variants()->where('store_id', $storeId)->whereIn('id', array_keys($normalized))->update(['price' => null]);
        } else {
            $product->update(['base_price' => min($saved)]);
        }

        return $this->summary($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Product $product): array
    {
        $product->unsetRelation('variants');
        $product->load(['store:id,currency', 'variants:id,product_id,store_id,price,sku', 'variants.options:id,variation_type_id,value']);

        $list = ProductListPrice::forProduct($product);
        $variants = $product->variants->map(static function (ProductVariant $variant) use ($product): array {
            if (! $variant->relationLoaded('product')) {
                $variant->setRelation('product', $product);
            }

            return [
                'id' => (int) $variant->id,
                'price' => (float) $variant->price,
                'price_override' => $variant->priceOverride(),
            ];
        })->values()->all();

        return [
            'base_price' => round((float) $product->base_price, 2),
            'display' => $list['display'],
            'list_display' => $list['list_display'],
            'compact_list' => $list['compact_list'],
            'mixed' => $list['mixed'],
            'min' => $list['min'],
            'max' => $list['max'],
            'variant_count' => $list['variant_count'],
            'variants' => $variants,
        ];
    }
}
