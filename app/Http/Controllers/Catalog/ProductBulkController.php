<?php

namespace App\Http\Controllers\Catalog;

use App\Exceptions\Catalog\ProductPermanentDeleteBlockedException;
use App\Exceptions\Catalog\ProductPermanentDeleteCleanupPendingException;
use App\Exceptions\Catalog\ProductPermanentDeleteStorageException;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tag;
use App\Services\Catalog\ProductPermanentDeleteGalleryPurgeService;
use App\Services\Catalog\ProductPermanentDeleteService;
use App\Services\Delivery\ShippingWeightResolver;
use App\Services\Delivery\StoreShippingPreferences;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\SecurityLogRecorder;
use App\Services\StorefrontCatalogEventRecorder;
use App\Support\StorePermission;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ProductBulkController extends Controller
{
    public function handle(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $user = $request->user();
        if (! $user?->hasStorePermission($store, StorePermission::CATALOG_MANAGE)) {
            abort(403, 'You are not authorized to run bulk catalog actions in this store.');
        }

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete', 'restore', 'force_delete', 'stock', 'categories', 'brand', 'tags', 'status', 'shipping_weight'])],
            'product_ids' => ['nullable', 'array', 'min:1', 'max:20000'],
            'product_ids.*' => ['integer', 'min:1'],
            'product_ids_json' => ['nullable', 'string', 'max:2000000'],
            'stock_mode' => ['nullable', 'string', Rule::in(['set', 'delta'])],
            'stock_value' => ['nullable', 'integer', 'min:-999999', 'max:999999'],
            'bulk_variant_stock_scope' => ['nullable', 'string', Rule::in(['default_variant_only', 'all_variants_same', 'skip_multi_variant'])],
            'stock_apply_mode' => ['nullable', 'string', Rule::in(['empty_only', 'replace_all'])],
            'category_ids' => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'tag_ids' => ['nullable', 'array', 'max:50'],
            'tag_ids.*' => ['integer', 'min:1'],
            'product_status' => ['nullable', 'string', Rule::in(['published', 'draft'])],
            'shipping_weight_value' => ['nullable', 'numeric', 'gt:0', 'max:'.StoreShippingPreferences::MAX_ITEM_WEIGHT],
        ]);

        if ($validated['action'] === 'shipping_weight') {
            $request->validate([
                'shipping_weight_value' => ['required', 'numeric', 'gt:0', 'max:'.StoreShippingPreferences::MAX_ITEM_WEIGHT],
            ]);
            $validated['shipping_weight_value'] = $request->input('shipping_weight_value');
        }

        $uniqueIds = $this->resolveBulkProductIds($request, $validated);
        if ($uniqueIds === []) {
            return back()->withErrors(['bulk' => 'Select at least one product.'])->withInput();
        }
        if (count($uniqueIds) > 20000) {
            return back()->withErrors(['bulk' => 'Too many products selected for one bulk action. Narrow your filters and try again.'])->withInput();
        }

        if ($validated['action'] === 'stock') {
            $validated = array_merge($validated, $request->validate([
                'stock_mode' => ['required', 'string', Rule::in(['set', 'delta'])],
                'stock_value' => ['required', 'integer', 'min:-999999', 'max:999999'],
                'stock_apply_mode' => ['nullable', 'string', Rule::in(['empty_only', 'replace_all'])],
                'bulk_variant_stock_scope' => ['nullable', 'string', Rule::in(['default_variant_only', 'all_variants_same', 'skip_multi_variant'])],
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

        $action = $validated['action'];
        $productsQuery = Product::query()->where('store_id', $store->id)->whereIn('id', $uniqueIds);
        if (in_array($action, ['restore', 'force_delete'], true)) {
            $productsQuery->onlyTrashed();
        }

        // Large shipping-weight bulk ops chunk internally — avoid hydrating every model twice.
        if ($action === 'shipping_weight') {
            $foundCount = (clone $productsQuery)->count();
            if ($foundCount !== count($uniqueIds)) {
                return back()->withErrors(['bulk' => 'Some selected products are missing or do not belong to this store.'])->withInput();
            }

            return $this->bulkShippingWeight($request, $store, $uniqueIds, $validated, $foundCount);
        }

        $products = $productsQuery->get()->keyBy('id');
        if ($products->count() !== count($uniqueIds)) {
            return back()->withErrors(['bulk' => 'Some selected products are missing or do not belong to this store.'])->withInput();
        }

        $n = $products->count();

        return match ($action) {
            'delete' => $this->bulkDelete($store, $products, $n),
            'restore' => $this->bulkRestore($store, $products, $n),
            'force_delete' => $this->bulkForceDelete($store, $products, $n),
            'stock' => $this->bulkStock($request, $store, $products, $validated, $n),
            'categories' => $this->bulkCategories($store, $products, $validated, $n),
            'brand' => $this->bulkBrand($store, $products, $validated, $n),
            'tags' => $this->bulkTags($store, $products, $validated, $n),
            'status' => $this->bulkStatus($store, $products, $validated, $n),
            default => back()->withErrors(['bulk' => 'Unknown action.']),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function resolveBulkProductIds(Request $request, array $validated): array
    {
        $fromArray = array_values(array_unique(array_map('intval', $validated['product_ids'] ?? [])));
        if ($fromArray !== []) {
            return $fromArray;
        }

        $json = trim((string) ($validated['product_ids_json'] ?? $request->input('product_ids_json', '')));
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $decoded)));
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkDelete(Store $store, $products, int $n): RedirectResponse
    {
        DB::transaction(function () use ($products): void {
            foreach ($products as $product) {
                $product->delete();
            }
        });

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'delete', 'product_count' => $n]
        );

        return back()->with('success', $n.' product(s) deleted. You can undo that from Deleted products, or permanently remove them.')
            ->with('success_title', 'Products deleted');
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkRestore(Store $store, $products, int $n): RedirectResponse
    {
        DB::transaction(function () use ($products): void {
            foreach ($products as $product) {
                $product->restore();
            }
        });

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'restore', 'product_count' => $n]
        );

        return redirect()
            ->route('products')
            ->with('success', $n.' product(s) restored to your active catalog.')
            ->with('success_title', 'Products restored');
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function bulkForceDelete(Store $store, $products, int $n): RedirectResponse
    {
        try {
            app(ProductPermanentDeleteService::class)->forceDeleteMany($products);
        } catch (ProductPermanentDeleteBlockedException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->with('error_meta', 'Finish or cancel related checkouts before permanently deleting these products.');
        } catch (ProductPermanentDeleteStorageException $e) {
            report($e);

            return back()
                ->with('error', 'Some products could not be permanently deleted. None of the selected products were removed.')
                ->with('error_meta', 'Gallery file cleanup could not be completed safely.');
        } catch (ProductPermanentDeleteCleanupPendingException $e) {
            report($e);

            app(ProductPermanentDeleteGalleryPurgeService::class)
                ->retryPendingCleanup($e->operationId);

            app(SecurityLogRecorder::class)->record(
                request(),
                'product_bulk_action',
                store: $store,
                metadata: [
                    'action' => 'force_delete',
                    'product_count' => $n,
                    'gallery_cleanup_pending' => true,
                    'quarantine_operation_id' => $e->operationId,
                    'pending_quarantine_paths' => $e->pendingPaths,
                ]
            );

            return back()
                ->with('success', $n.' product(s) permanently deleted. This cannot be undone.')
                ->with('success_title', 'Permanently deleted')
                ->with('success_meta', 'Some temporary gallery cleanup could not be completed and will be retried.');
        } catch (QueryException $e) {
            report($e);

            return back()
                ->with('error', 'Some products could not be permanently deleted. None of the selected products were removed.')
                ->with('error_meta', 'Try deleting one product at a time, or contact support if this continues.');
        }

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'force_delete', 'product_count' => $n]
        );

        return back()->with('success', $n.' product(s) permanently deleted. This cannot be undone.')
            ->with('success_title', 'Permanently deleted');
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<string, mixed>  $validated
     */
    private function bulkStock(Request $request, Store $store, $products, array $validated, int $n): RedirectResponse
    {
        $mode = (string) $validated['stock_mode'];
        $value = (int) $validated['stock_value'];
        $userId = $request->user()?->id;
        $actor = $request->user();
        $scope = (string) ($validated['bulk_variant_stock_scope'] ?? 'default_variant_only');
        if (! in_array($scope, ['default_variant_only', 'all_variants_same', 'skip_multi_variant'], true)) {
            $scope = 'default_variant_only';
        }

        // Only meaningful for "set". Delta always adjusts every selected target.
        $applyMode = (string) ($validated['stock_apply_mode'] ?? 'empty_only');
        if ($mode !== 'set' || ! in_array($applyMode, ['empty_only', 'replace_all'], true)) {
            $applyMode = 'replace_all';
        }

        $skippedMulti = 0;
        $skippedExisting = 0;
        $updatedVariants = 0;

        DB::transaction(function () use ($products, $mode, $value, $actor, $userId, $scope, $applyMode, &$skippedMulti, &$skippedExisting, &$updatedVariants): void {
            $availability = app(InventoryAvailabilityService::class);
            $adjuster = app(InventoryAdjustmentService::class);

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
                    $previous = $availability->availableForVariant($variant);

                    if ($mode === 'set' && $applyMode === 'empty_only' && $previous > 0) {
                        $skippedExisting++;

                        continue;
                    }

                    $new = $mode === 'set'
                        ? max(0, $value)
                        : max(0, $previous + $value);
                    if ($new === $previous) {
                        continue;
                    }
                    $adjuster->setVariantAvailable(
                        $variant,
                        $new,
                        $mode === 'set' ? 'Bulk stock: set to '.$new : 'Bulk stock: adjust by '.$value,
                        $actor,
                        [
                            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
                            'source' => 'catalog',
                            'performed_by' => $userId,
                            'previous_stock_for_movement' => $previous,
                            'initial_available' => $previous,
                        ]
                    );
                    $updatedVariants++;
                }
            }
        });

        app(SecurityLogRecorder::class)->record(
            $request,
            'product_bulk_action',
            store: $store,
            metadata: [
                'action' => 'stock',
                'product_count' => $n,
                'stock_mode' => $mode,
                'stock_value' => $value,
                'stock_apply_mode' => $applyMode,
                'variant_scope' => $scope,
                'skipped_multi_variant' => $skippedMulti,
                'skipped_existing_stock' => $skippedExisting,
                'updated_variant_count' => $updatedVariants,
            ]
        );

        $parts = [];
        $parts[] = match ($scope) {
            'all_variants_same' => 'Stock updated on '.$updatedVariants.' variant row(s) across '.$n.' selected product(s).',
            'skip_multi_variant' => $skippedMulti > 0
                ? 'Stock updated for '.($n - $skippedMulti).' product(s). Skipped '.$skippedMulti.' multi-variant product(s) as requested.'
                : 'Stock updated for '.$n.' product(s).',
            default => 'Stock updated for '.$n.' product(s) (default inventory row only).',
        };
        if ($skippedExisting > 0) {
            $parts[] = $skippedExisting.' inventory row'.($skippedExisting === 1 ? '' : 's').' already had stock and '.($skippedExisting === 1 ? 'was' : 'were').' left unchanged.';
        }

        return back()->with('success', implode(' ', $parts))
            ->with('success_title', 'Bulk stock');
    }

    /**
     * @param  Collection<int, Product>  $products
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

        app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $products->keys()->all());

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'categories', 'product_count' => $n, 'category_ids' => $ids]
        );

        return back()->with('success', 'Categories applied to '.$n.' product(s).')
            ->with('success_title', 'Bulk categories');
    }

    /**
     * @param  Collection<int, Product>  $products
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
        app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $products->keys()->all());

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'brand', 'product_count' => $n, 'brand_id' => $brandId]
        );

        return back()->with('success', 'Brand assigned to '.$n.' product(s).')
            ->with('success_title', 'Bulk brand');
    }

    /**
     * @param  Collection<int, Product>  $products
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

        app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $products->keys()->all());

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'tags', 'product_count' => $n, 'tag_ids' => $ids]
        );

        return back()->with('success', 'Tags applied to '.$n.' product(s).')
            ->with('success_title', 'Bulk tags');
    }

    /**
     * @param  Collection<int, Product>  $products
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
        app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $products->keys()->all());

        app(SecurityLogRecorder::class)->record(
            request(),
            'product_bulk_action',
            store: $store,
            metadata: ['action' => 'status', 'product_count' => $n, 'status' => $status]
        );

        return back()->with('success', 'Status updated for '.$n.' product(s).')
            ->with('success_title', 'Bulk status');
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<string, mixed>  $validated
     */
    private function bulkShippingWeight(Request $request, Store $store, array $productIds, array $validated, int $n): RedirectResponse
    {
        $weight = app(ShippingWeightResolver::class)->normalizePositiveWeight($validated['shipping_weight_value'] ?? null);
        $max = app(StoreShippingPreferences::class)->maxItemWeightForStore($store);
        if ($weight === null || $weight > $max) {
            return back()->withErrors(['bulk' => 'Enter a shipping weight greater than zero and at most '.$max.'.'])->withInput();
        }

        $unit = app(StoreShippingPreferences::class)->weightUnitLabel($store);
        $resolver = app(ShippingWeightResolver::class);
        $updated = 0;
        $skippedNonShipping = 0;
        $updatedIds = [];

        // Chunked updates inside one transaction so partial commits cannot occur on failure.
        DB::transaction(function () use ($productIds, $store, $weight, $resolver, &$updated, &$skippedNonShipping, &$updatedIds): void {
            foreach (array_chunk($productIds, 500) as $chunkIds) {
                $chunk = Product::query()
                    ->where('store_id', $store->id)
                    ->whereIn('id', $chunkIds)
                    ->with('variants')
                    ->get();

                Product::withoutEvents(function () use ($chunk, $weight, $resolver, &$updated, &$skippedNonShipping, &$updatedIds): void {
                    ProductVariant::withoutEvents(function () use ($chunk, $weight, $resolver, &$updated, &$skippedNonShipping, &$updatedIds): void {
                        foreach ($chunk as $product) {
                            if (! (bool) $product->requires_shipping) {
                                $skippedNonShipping++;

                                continue;
                            }

                            $meta = is_array($product->meta) ? $product->meta : [];
                            $meta['shipping_weight'] = $weight;
                            unset($meta['weight']);
                            $product->forceFill(['meta' => $meta])->save();
                            $updated++;
                            $updatedIds[] = (int) $product->id;

                            foreach ($product->variants as $variant) {
                                $variantMeta = is_array($variant->meta) ? $variant->meta : [];
                                if (! array_key_exists('shipping_weight', $variantMeta) && ! array_key_exists('weight', $variantMeta)) {
                                    continue;
                                }

                                $resolver->persistVariantShippingWeightMeta($variantMeta, null);
                                $variant->forceFill(['meta' => $variantMeta])->save();
                            }
                        }
                    });
                });
            }
        });

        if ($updated > 0) {
            app(StoreShippingPreferences::class)->commitWeightUnitIfNeeded($store);
        }

        if ($updatedIds !== []) {
            app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $updatedIds);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'product_bulk_action',
            store: $store,
            metadata: [
                'action' => 'shipping_weight',
                'selected_product_count' => $n,
                'updated_product_count' => $updated,
                'skipped_non_shipping_count' => $skippedNonShipping,
                'weight' => $weight,
                'unit' => $unit,
            ]
        );

        $parts = ['Shipping weight set for '.$updated.' product'.($updated === 1 ? '' : 's').'. Variants now use this same product weight.'];
        if ($skippedNonShipping > 0) {
            $parts[] = $skippedNonShipping.' non-shippable product'.($skippedNonShipping === 1 ? ' was' : 's were').' skipped.';
        }

        return back()
            ->with('success', implode(' ', $parts))
            ->with('success_title', 'Bulk shipping weight');
    }

    private function defaultCatalogVariant(Product $product): ?ProductVariant
    {
        $v = $product->variants()->whereDoesntHave('options')->orderBy('id')->first();

        return $v ?? $product->variants()->orderBy('id')->first();
    }
}
