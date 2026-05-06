<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductAttributeAssigner
{
    /**
     * @param  array<int|string, mixed>  $attributeTerms
     */
    public function syncTerms(Product $product, array $attributeTerms): void
    {
        $storeId = (int) $product->store_id;
        $normalized = [];

        foreach ($attributeTerms as $attributeId => $termIds) {
            $attributeId = (int) $attributeId;
            if ($attributeId < 1) {
                continue;
            }

            $ids = collect(is_array($termIds) ? $termIds : [$termIds])
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($ids === []) {
                continue;
            }

            $attribute = Attribute::query()
                ->where('store_id', $storeId)
                ->whereKey($attributeId)
                ->first();

            if (! $attribute) {
                continue;
            }

            $validTermIds = AttributeTerm::query()
                ->where('attribute_id', $attribute->id)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($validTermIds !== []) {
                $normalized[(int) $attribute->id] = $validTermIds;
            }
        }

        DB::transaction(function () use ($product, $normalized): void {
            $keepProductAttributeIds = [];

            foreach ($normalized as $attributeId => $termIds) {
                $productAttribute = ProductAttribute::query()->firstOrCreate([
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                ], [
                    'is_variation' => false,
                    'is_visible' => true,
                    'sort_order' => 0,
                ]);

                $productAttribute->terms()->sync($termIds);
                $keepProductAttributeIds[] = (int) $productAttribute->id;
            }

            $stale = ProductAttribute::query()
                ->where('product_id', $product->id)
                ->when($keepProductAttributeIds !== [], fn ($query) => $query->whereNotIn('id', $keepProductAttributeIds))
                ->get();

            foreach ($stale as $row) {
                $row->terms()->detach();
                $row->delete();
            }
        });
    }

    public function attachTermByNames(Store $store, Product $product, string $attributeName, string $termName, ?int $userId = null): void
    {
        $attributeName = trim($attributeName);
        $termName = trim($termName);

        if ($attributeName === '' || $termName === '') {
            return;
        }

        DB::transaction(function () use ($store, $product, $attributeName, $termName, $userId): void {
            $attributeSlug = Str::slug($attributeName) ?: 'attribute';
            $attribute = Attribute::query()->firstOrCreate([
                'store_id' => $store->id,
                'slug' => $attributeSlug,
            ], [
                'name' => $attributeName,
                'display_type' => 'text',
                'sort_order' => 0,
                'is_filterable' => true,
                'is_visible' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $termSlug = Str::slug($termName) ?: 'term';
            $term = AttributeTerm::query()->firstOrCreate([
                'attribute_id' => $attribute->id,
                'slug' => $termSlug,
            ], [
                'name' => $termName,
                'swatch_value' => null,
                'sort_order' => (int) $attribute->terms()->max('sort_order') + 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $productAttribute = ProductAttribute::query()->firstOrCreate([
                'product_id' => $product->id,
                'attribute_id' => $attribute->id,
            ], [
                'is_variation' => false,
                'is_visible' => true,
                'sort_order' => 0,
            ]);

            $productAttribute->terms()->syncWithoutDetaching([$term->id]);
        });
    }
}
