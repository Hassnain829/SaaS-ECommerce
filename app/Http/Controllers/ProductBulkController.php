<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use App\Support\StockMovementRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ProductBulkController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $user = $request->user();
        if (! $user?->hasStoreRole($store, [Store::ROLE_OWNER, Store::ROLE_MANAGER])) {
            abort(403, 'You are not authorized to run bulk catalog actions in this store.');
        }

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete', 'stock', 'categories', 'brand', 'tags', 'status'])],
            'product_ids' => ['required', 'array', 'min:1', 'max:500'],
            'product_ids.*' => ['integer', 'min:1'],
            'stock_mode' => ['nullable', 'string', Rule::in(['set', 'delta'])],
            'stock_value' => ['nullable', 'integer', 'min:-999999', 'max:999999'],
            'bulk_variant_stock_scope' => ['nullable', 'string', Rule::in(['default_variant_only', 'all_variants_same', 'skip_multi_variant'])],
            'category_ids' => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'tag_ids' => ['nullable', 'array', 'max:50'],
            'tag_ids.*' => ['integer', 'min:1'],
            'product_status' => ['nullable', 'string', Rule::in(['published', 'draft'])],
        ]);

        if ($validated['action'] === 'stock') {
            $validated = array_merge($validated, $request->validate([
                'stock_mode' => ['required', 'string', Rule::in(['set', 'delta'])],
                'stock_value' => ['required', 'integer', 'min:-999999', 'max:999999'],
            ]));
        }

        if ($validated['action'] === 'categories') {
            $validated = array_merge($validated, $request->validate([
                'category_ids' => ['required', 'array', 'min:1', 'max:50'],
                'category_ids.*' => ['integer', 'min:1'],
            ]));
        }

        if ($validated['action'] === 'brand') {
            $validated = array_merge($validated, $request->validate([
                'brand_id' => ['required', 'integer', 'min:1'],
            ]));
        }

        if ($validated['action'] === 'tags') {
            $validated = array_merge($validated, $request->validate([
                'tag_ids' => ['required', 'array', 'min:1', 'max:50'],
                'tag_ids.*' => ['integer', 'min:1'],
            ]));
        }

        if ($validated['action'] === 'status') {
            $validated = array_merge($validated, $request->validate([
                'product_status' => ['required', 'string', Rule::in(['published', 'draft'])],
            ]));
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $validated['product_ids'])));
        $products = Product::query()->where('store_id', $store->id)->whereIn('id', $uniqueIds)->get()->keyBy('id');
        if ($products->count() !== count($uniqueIds)) {
            return back()->withErrors(['bulk' => 'Some selected products are missing or do not belong to this store.'])->withInput();
        }

        $action = $validated['action'];
        $n = $products->count();

        return match ($action) {
            'delete' => $this->bulkDelete($store, $products, $n),
            'stock' => $this->bulkStock($request, $store, $products, $validated, $n),
            'categories' => $this->bulkCategories($store, $products, $validated, $n),
            'brand' => $this->bulkBrand($store, $products, $validated, $n),
            'tags' => $this->bulkTags($store, $products, $validated, $n),
            'status' => $this->bulkStatus($store, $products, $validated, $n),
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function bulkDelete(Store $store, $products, int $n): RedirectResponse
    {
        DB::transaction(function () use ($products): void {
            foreach ($products as $product) {
                $product->delete();
            }
        });

        return back()->with('success', $n.' product(s) archived (soft deleted) from this store.')
            ->with('success_title', 'Bulk delete');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkStock(Request $request, Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $mode = (string) $validated['stock_mode'];
        $value = (int) $validated['stock_value'];
        $userId = $request->user()?->id;
        $scope = (string) ($validated['bulk_variant_stock_scope'] ?? 'default_variant_only');
        if (! in_array($scope, ['default_variant_only', 'all_variants_same', 'skip_multi_variant'], true)) {
            $scope = 'default_variant_only';
        }

        $skippedMulti = 0;

        DB::transaction(function () use ($store, $products, $mode, $value, $userId, $scope, &$skippedMulti): void {
            foreach ($products as $product) {
                $product->loadCount('variants');
                $variantCount = (int) $product->variants_count;
                $isMultiVariant = $variantCount > 1;

                if ($isMultiVariant && $scope === 'skip_multi_variant') {
                    $skippedMulti++;

                    continue;
                }

                $targets = $scope === 'all_variants_same'
                    ? $product->variants()->orderBy('id')->get()
                    : collect([$this->defaultCatalogVariant($product)])->filter();

                foreach ($targets as $variant) {
                    if (! $variant) {
                        continue;
                    }
                    $previous = (int) $variant->stock;
                    $new = $mode === 'set'
                        ? max(0, $value)
                        : max(0, $previous + $value);
                    if ($new === $previous) {
                        continue;
                    }
                    $variant->update(['stock' => $new]);
                    StockMovementRecorder::recordAdjustment(
                        $store,
                        $product,
                        $variant->fresh(),
                        $previous,
                        $new,
                        $userId,
                        'catalog',
                        \App\Models\StockMovement::TYPE_EDIT_UPDATE,
                        $mode === 'set' ? 'Bulk stock: set to '.$new : 'Bulk stock: adjust by '.$value
                    );
                }
            }
        });

        $msg = match ($scope) {
            'all_variants_same' => 'Stock updated on every variant row for '.$n.' product(s).',
            'skip_multi_variant' => $skippedMulti > 0
                ? 'Stock updated for '.($n - $skippedMulti).' product(s). Skipped '.$skippedMulti.' multi-variant product(s) as requested.'
                : 'Stock updated for '.$n.' product(s).',
            default => 'Stock updated for '.$n.' product(s) (default inventory row only).',
        };

        return back()->with('success', $msg)
            ->with('success_title', 'Bulk stock');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkCategories(Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $ids = array_values(array_unique(array_map('intval', $validated['category_ids'] ?? [])));
        if ($ids === []) {
            return back()->withErrors(['category_ids' => 'Select at least one category.'])->withInput();
        }

        $validCount = Category::query()->where('store_id', $store->id)->whereIn('id', $ids)->count();
        if ($validCount !== count($ids)) {
            return back()->withErrors(['category_ids' => 'One or more categories are invalid for this store.'])->withInput();
        }

        foreach ($products as $product) {
            $product->categories()->syncWithoutDetaching($ids);
        }

        return back()->with('success', 'Categories applied to '.$n.' product(s).')
            ->with('success_title', 'Bulk categories');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkBrand(Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $brandId = isset($validated['brand_id']) ? (int) $validated['brand_id'] : 0;
        if ($brandId < 1) {
            return back()->withErrors(['brand_id' => 'Select a brand.'])->withInput();
        }

        if (! Brand::query()->where('store_id', $store->id)->whereKey($brandId)->exists()) {
            return back()->withErrors(['brand_id' => 'That brand does not belong to this store.'])->withInput();
        }

        Product::query()->where('store_id', $store->id)->whereIn('id', $products->keys())->update(['brand_id' => $brandId]);

        return back()->with('success', 'Brand assigned to '.$n.' product(s).')
            ->with('success_title', 'Bulk brand');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkTags(Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $ids = array_values(array_unique(array_map('intval', $validated['tag_ids'] ?? [])));
        if ($ids === []) {
            return back()->withErrors(['tag_ids' => 'Select at least one tag.'])->withInput();
        }

        $validCount = Tag::query()->where('store_id', $store->id)->whereIn('id', $ids)->count();
        if ($validCount !== count($ids)) {
            return back()->withErrors(['tag_ids' => 'One or more tags are invalid for this store.'])->withInput();
        }

        foreach ($products as $product) {
            $product->tags()->syncWithoutDetaching($ids);
        }

        return back()->with('success', 'Tags applied to '.$n.' product(s).')
            ->with('success_title', 'Bulk tags');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkStatus(Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $status = $validated['product_status'] ?? '';
        if ($status === '') {
            return back()->withErrors(['product_status' => 'Choose published or draft.'])->withInput();
        }

        $bool = $status === 'published';
        Product::query()->where('store_id', $store->id)->whereIn('id', $products->keys())->update(['status' => $bool]);

        return back()->with('success', 'Status updated for '.$n.' product(s).')
            ->with('success_title', 'Bulk status');
    }

    private function defaultCatalogVariant(Product $product): ?\App\Models\ProductVariant
    {
        $v = $product->variants()->whereDoesntHave('options')->orderBy('id')->first();

        return $v ?? $product->variants()->orderBy('id')->first();
    }
}
