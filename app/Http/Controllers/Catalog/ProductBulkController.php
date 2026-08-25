<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tag;
use App\Services\Delivery\ShippingWeightResolver;
use App\Services\Delivery\StoreShippingPreferences;
use App\Services\Delivery\VariantShippingWeightBulkService;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\SecurityLogRecorder;
use App\Services\StorefrontCatalogEventRecorder;
use App\Support\StorePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

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
            'shipping_weight_mode' => ['nullable', 'string', Rule::in(['missing_only', 'replace_all'])],
            'shipping_weight_target' => ['nullable', 'string', Rule::in(['products', 'variants'])],
            'variant_bulk_mode' => ['nullable', 'string', Rule::in(['map_by_option', 'use_option_values', 'clear'])],
            'variant_option_name' => ['nullable', 'string', 'max:100'],
            'variant_weight_map_json' => ['nullable', 'string', 'max:500000'],
        ]);

        if ($validated['action'] === 'shipping_weight') {
            $target = (string) ($request->input('shipping_weight_target') ?: 'products');
            $validated['shipping_weight_target'] = $target;

            if ($target === 'products') {
                $request->validate([
                    'shipping_weight_value' => ['required', 'numeric', 'gt:0', 'max:'.StoreShippingPreferences::MAX_ITEM_WEIGHT],
                    'shipping_weight_mode' => ['required', 'string', Rule::in(['missing_only', 'replace_all'])],
                ]);
                $validated['shipping_weight_value'] = $request->input('shipping_weight_value');
                $validated['shipping_weight_mode'] = $request->input('shipping_weight_mode');
            } else {
                $request->validate([
                    'variant_bulk_mode' => ['required', 'string', Rule::in(['map_by_option', 'use_option_values', 'clear'])],
                    'shipping_weight_mode' => ['required', 'string', Rule::in(['missing_only', 'replace_all'])],
                ]);
                $validated['variant_bulk_mode'] = (string) $request->input('variant_bulk_mode');
                $validated['shipping_weight_mode'] = (string) $request->input('shipping_weight_mode');

                if ($validated['variant_bulk_mode'] !== 'clear') {
                    $request->validate([
                        'variant_option_name' => ['required', 'string', 'max:100'],
                    ]);
                    $validated['variant_option_name'] = trim((string) $request->input('variant_option_name'));
                }

                if ($validated['variant_bulk_mode'] === 'map_by_option') {
                    $map = $this->decodeVariantWeightMap($request, $store);
                    if ($map === []) {
                        return back()->withErrors(['bulk' => 'Enter at least one option value weight to apply.'])->withInput();
                    }
                    $validated['variant_weight_map'] = $map;
                }
            }
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

            if (($validated['shipping_weight_target'] ?? 'products') === 'variants') {
                return $this->bulkVariantShippingWeight($request, $store, $uniqueIds, $validated, $foundCount);
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
     * @param  \Illuminate\Support\Collection<int, Product>  $products
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
     * @param  \Illuminate\Support\Collection<int, Product>  $products
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
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    private function bulkForceDelete(Store $store, $products, int $n): RedirectResponse
    {
        DB::transaction(function () use ($products): void {
            foreach ($products as $product) {
                $product->forceDelete();
            }
        });

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
     * @param  \Illuminate\Support\Collection<int, Product>  $products
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
            $availability = app(\App\Services\Inventory\InventoryAvailabilityService::class);
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
                            'movement_type' => \App\Models\StockMovement::TYPE_EDIT_UPDATE,
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

    public function previewShippingWeight(Request $request): JsonResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $user = $request->user();
        if (! $user?->hasStorePermission($store, StorePermission::CATALOG_MANAGE)) {
            abort(403);
        }

        $validated = $request->validate([
            'product_ids' => ['nullable', 'array', 'min:1', 'max:20000'],
            'product_ids.*' => ['integer', 'min:1'],
            'product_ids_json' => ['nullable', 'string', 'max:2000000'],
            'shipping_weight_target' => ['required', 'string', Rule::in(['variants'])],
            'variant_bulk_mode' => ['required', 'string', Rule::in(['map_by_option', 'use_option_values', 'clear'])],
            'variant_option_name' => ['nullable', 'string', 'max:100'],
            'variant_weight_map_json' => ['nullable', 'string', 'max:500000'],
            'shipping_weight_mode' => ['nullable', 'string', Rule::in(['missing_only', 'replace_all'])],
        ]);

        $productIds = $this->resolveBulkProductIds($request, $validated);
        if ($productIds === []) {
            return response()->json(['message' => 'Select at least one product.'], 422);
        }

        $foundCount = Product::query()->where('store_id', $store->id)->whereIn('id', $productIds)->count();
        if ($foundCount !== count($productIds)) {
            return response()->json(['message' => 'Some selected products are missing or do not belong to this store.'], 422);
        }

        $mode = (string) ($validated['shipping_weight_mode'] ?? 'missing_only');
        $bulkMode = (string) $validated['variant_bulk_mode'];
        $optionName = trim((string) ($validated['variant_option_name'] ?? ''));
        try {
            $map = $bulkMode === 'map_by_option' ? $this->decodeVariantWeightMap($request, $store) : [];
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Invalid variant weight map.',
            ], 422);
        }

        $preview = app(VariantShippingWeightBulkService::class)->preview(
            $store,
            $productIds,
            $bulkMode,
            $optionName,
            $map,
            $mode,
        );

        return response()->json($preview);
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<string, mixed>  $validated
     */
    private function bulkVariantShippingWeight(Request $request, Store $store, array $productIds, array $validated, int $n): RedirectResponse
    {
        $bulkMode = (string) ($validated['variant_bulk_mode'] ?? 'map_by_option');
        $applyMode = (string) ($validated['shipping_weight_mode'] ?? 'missing_only');
        $optionName = trim((string) ($validated['variant_option_name'] ?? ''));
        $map = $bulkMode === 'map_by_option' ? ($validated['variant_weight_map'] ?? []) : [];

        $unit = app(StoreShippingPreferences::class)->weightUnitLabel($store);
        $updatedIds = [];
        $result = [
            'updated_variant_count' => 0,
            'skipped_existing_count' => 0,
            'skipped_unmatched_count' => 0,
            'skipped_non_shipping_count' => 0,
            'updated_product_ids' => [],
        ];

        try {
            DB::transaction(function () use ($store, $productIds, $bulkMode, $optionName, $map, $applyMode, &$result): void {
                ProductVariant::withoutEvents(function () use ($store, $productIds, $bulkMode, $optionName, $map, $applyMode, &$result): void {
                    $result = app(VariantShippingWeightBulkService::class)->apply(
                        $store,
                        $productIds,
                        $bulkMode,
                        $optionName,
                        $map,
                        $applyMode,
                    );
                });
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['bulk' => $e->getMessage()])->withInput();
        }

        $updated = (int) $result['updated_variant_count'];
        $updatedIds = $result['updated_product_ids'];

        if ($updated > 0) {
            app(StoreShippingPreferences::class)->commitWeightUnitIfNeeded($store);
            app(StorefrontCatalogEventRecorder::class)->recordCatalogUpdated($store->id, $updatedIds);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'product_bulk_action',
            store: $store,
            metadata: [
                'action' => 'shipping_weight_variants',
                'selected_product_count' => $n,
                'updated_variant_count' => $updated,
                'skipped_existing_count' => $result['skipped_existing_count'],
                'skipped_unmatched_count' => $result['skipped_unmatched_count'],
                'skipped_non_shipping_count' => $result['skipped_non_shipping_count'],
                'variant_bulk_mode' => $bulkMode,
                'variant_option_name' => $optionName !== '' ? $optionName : null,
                'apply_mode' => $applyMode,
                'unit' => $unit,
            ]
        );

        $parts = [];
        if ($bulkMode === 'clear') {
            $parts[] = "Cleared variant shipping weights on {$updated} variant".($updated === 1 ? '' : 's').'.';
        } else {
            $parts[] = "Variant shipping weights updated on {$updated} variant".($updated === 1 ? '' : 's').'.';
        }
        if ($result['skipped_existing_count'] > 0) {
            $parts[] = $result['skipped_existing_count'].' variant'.($result['skipped_existing_count'] === 1 ? '' : 's').' already had a weight and were left unchanged.';
        }
        if ($result['skipped_unmatched_count'] > 0) {
            $parts[] = $result['skipped_unmatched_count'].' variant'.($result['skipped_unmatched_count'] === 1 ? '' : 's').' did not match the selected option or weight map.';
        }

        return back()
            ->with('success', implode(' ', $parts))
            ->with('success_title', 'Bulk variant shipping weight');
    }

    /**
     * @return array<string, float>
     */
    private function decodeVariantWeightMap(Request $request, Store $store): array
    {
        $raw = $request->input('variant_weight_map_json');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $prefs = app(StoreShippingPreferences::class);
        $max = $prefs->maxItemWeightForStore($store);
        $unit = $prefs->weightUnitLabel($store);
        $resolver = app(ShippingWeightResolver::class);
        $map = [];

        foreach ($decoded as $optionValue => $weight) {
            if (! is_string($optionValue) && ! is_int($optionValue)) {
                continue;
            }
            if (! is_numeric($weight)) {
                throw ValidationException::withMessages([
                    'bulk' => 'Each variant shipping weight must be a number greater than zero and at most '.$max.' '.$unit.'.',
                ]);
            }

            $normalized = $resolver->normalizePositiveWeight($weight);
            if ($normalized === null || $normalized > $max) {
                throw ValidationException::withMessages([
                    'bulk' => 'Each variant shipping weight must be greater than zero and at most '.$max.' '.$unit.'.',
                ]);
            }

            $map[(string) $optionValue] = $normalized;
        }

        return $map;
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<string, mixed>  $validated
     */
    private function bulkShippingWeight(Request $request, Store $store, array $productIds, array $validated, int $n): RedirectResponse
    {
        $mode = (string) ($validated['shipping_weight_mode'] ?? 'missing_only');
        $weight = app(ShippingWeightResolver::class)->normalizePositiveWeight($validated['shipping_weight_value'] ?? null);
        if ($weight === null || $weight > app(StoreShippingPreferences::class)->maxItemWeightForStore($store)) {
            return back()->withErrors(['bulk' => 'Enter a shipping weight greater than zero and at most '.app(StoreShippingPreferences::class)->maxItemWeightForStore($store).'.'])->withInput();
        }

        $unit = app(StoreShippingPreferences::class)->weightUnitLabel($store);
        $resolver = app(ShippingWeightResolver::class);
        $updated = 0;
        $skippedNonShipping = 0;
        $skippedExisting = 0;
        $updatedIds = [];

        // Chunked updates inside one transaction so partial commits cannot occur on failure.
        DB::transaction(function () use ($productIds, $store, $mode, $weight, $resolver, &$updated, &$skippedNonShipping, &$skippedExisting, &$updatedIds): void {
            foreach (array_chunk($productIds, 500) as $chunkIds) {
                $chunk = Product::query()
                    ->where('store_id', $store->id)
                    ->whereIn('id', $chunkIds)
                    ->get();

                Product::withoutEvents(function () use ($chunk, $mode, $weight, $resolver, &$updated, &$skippedNonShipping, &$skippedExisting, &$updatedIds): void {
                    foreach ($chunk as $product) {
                        if (! (bool) $product->requires_shipping) {
                            $skippedNonShipping++;

                            continue;
                        }

                        if ($mode === 'missing_only' && $resolver->resolveExactProductLevel($product) !== null) {
                            $skippedExisting++;

                            continue;
                        }

                        $meta = is_array($product->meta) ? $product->meta : [];
                        $meta['shipping_weight'] = $weight;
                        $product->forceFill(['meta' => $meta])->save();
                        $updated++;
                        $updatedIds[] = (int) $product->id;
                    }
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
                'skipped_existing_weight_count' => $skippedExisting,
                'mode' => $mode,
                'weight' => $weight,
                'unit' => $unit,
            ]
        );

        $parts = ["Shipping weight set for {$updated} product".($updated === 1 ? '' : 's').'.'];
        if ($skippedExisting > 0) {
            $parts[] = "{$skippedExisting} product".($skippedExisting === 1 ? '' : 's').' already had a weight and were left unchanged.';
        }
        if ($skippedNonShipping > 0) {
            $parts[] = "{$skippedNonShipping} non-shippable product".($skippedNonShipping === 1 ? ' was' : 's were').' skipped.';
        }

        return back()
            ->with('success', implode(' ', $parts))
            ->with('success_title', 'Bulk shipping weight');
    }

    private function defaultCatalogVariant(Product $product): ?\App\Models\ProductVariant
    {
        $v = $product->variants()->whereDoesntHave('options')->orderBy('id')->first();

        return $v ?? $product->variants()->orderBy('id')->first();
    }
}
