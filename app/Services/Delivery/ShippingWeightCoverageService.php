<?php

namespace App\Services\Delivery;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * Determines whether shippable catalog rows have exact shipping-weight coverage
 * for Delivery readiness and setup UX (variant-aware).
 */
final class ShippingWeightCoverageService
{
    public function __construct(
        private readonly ShippingWeightResolver $weightResolver,
        private readonly StoreShippingPreferences $shippingPreferences,
    ) {}

    public function productHasExactCoverage(Product $product): bool
    {
        if ($this->weightResolver->resolveExactProductLevel($product) !== null) {
            return true;
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get(['id', 'product_id', 'meta']);

        if ($variants->isEmpty()) {
            return false;
        }

        foreach ($variants as $variant) {
            if ($this->weightResolver->resolveExactVariantLevel($variant) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Shippable published products that lack exact product or full-variant coverage.
     */
    public function countProductsMissingExactCoverage(Store $store): int
    {
        return $this->missingExactCoverageQuery($store)->count();
    }

    public function storeHasCheckoutFallback(Store $store): bool
    {
        return $this->shippingPreferences->fallbackItemWeight($store) !== null;
    }

    /**
     * @return Builder<Product>
     */
    public function shippablePublishedQuery(Store $store): Builder
    {
        return Product::query()
            ->where('store_id', $store->id)
            ->where('requires_shipping', true)
            ->where('status', true);
    }

    /**
     * Products that would rely on store fallback at checkout (variant-aware).
     *
     * @return Builder<Product>
     */
    public function missingExactCoverageQuery(Store $store): Builder
    {
        $query = $this->shippablePublishedQuery($store);

        $this->applyProductLevelWeightMissing($query);

        $query->where(function (Builder $outer): void {
            $outer->whereDoesntHave('variants')
                ->orWhereHas('variants', function (Builder $variantQuery): void {
                    $this->applyVariantLevelWeightMissing($variantQuery);
                });
        });

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applyProductLevelWeightMissing(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->where(function (Builder $weight): void {
                $weight->whereNull('meta->shipping_weight')
                    ->orWhere('meta->shipping_weight', '')
                    ->orWhere('meta->shipping_weight', '<=', 0);
            })->where(function (Builder $legacy): void {
                $legacy->whereNull('meta->weight')
                    ->orWhere('meta->weight', '')
                    ->orWhere('meta->weight', '<=', 0);
            });
        });
    }

    /**
     * @param  Builder<ProductVariant>  $query
     */
    private function applyVariantLevelWeightMissing(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->where(function (Builder $weight): void {
                $weight->whereNull('meta->shipping_weight')
                    ->orWhere('meta->shipping_weight', '')
                    ->orWhere('meta->shipping_weight', '<=', 0);
            })->where(function (Builder $legacy): void {
                $legacy->whereNull('meta->weight')
                    ->orWhere('meta->weight', '')
                    ->orWhere('meta->weight', '<=', 0);
            });
        });
    }
}
