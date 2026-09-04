<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventoryAvailabilityService;
use App\Services\Inventory\InventorySyncService;
use App\Services\SecurityLogRecorder;
use App\Support\ProductInventoryState;
use App\Support\StorePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ProductInlineController extends Controller
{
    public function detachCategory(Request $request, Product $product, Category $category): JsonResponse|RedirectResponse
    {
        [$store, $user] = $this->authorizeInlineCatalog($request, $product);
        abort_unless((int) $category->store_id === (int) $store->id, 404);

        $detached = $product->categories()
            ->where('categories.id', $category->id)
            ->exists();

        if ($detached) {
            $product->categories()->detach($category->id);

            app(SecurityLogRecorder::class)->record(
                $request,
                'product_inline_detach_category',
                store: $store,
                metadata: [
                    'product_id' => $product->id,
                    'category_id' => $category->id,
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'product_id' => $product->id,
                'category_id' => $category->id,
                'removed' => $detached,
            ]);
        }

        return back()->with('success', $detached ? 'Category removed from this product.' : 'Category was already removed.')
            ->with('success_title', 'Category updated');
    }

    public function updatePrice(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        [$store, $user] = $this->authorizeInlineCatalog($request, $product);

        $validated = $request->validate([
            'base_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $price = round((float) $validated['base_price'], 2);

        // Moving the base price is enough: every variant without its own price
        // inherits it. Variants the merchant priced deliberately keep their
        // override, so this can no longer reprice only the first variant.
        $variant = null;
        DB::transaction(function () use ($product, $price, &$variant): void {
            $product->update(['base_price' => $price]);

            $variant = $this->defaultCatalogVariant($product);
        });

        app(SecurityLogRecorder::class)->record(
            $request,
            'product_inline_update_price',
            store: $store,
            metadata: [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'base_price' => $price,
            ]
        );

        $currency = $product->store?->currency ?? $store->currency ?? 'USD';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'base_price' => $price,
                'formatted' => $currency.number_format($price, 2),
            ]);
        }

        return back()->with('success', 'Price updated.')
            ->with('success_title', 'Price saved');
    }

    public function updateStock(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        [$store, $user] = $this->authorizeInlineCatalog($request, $product);

        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'variant_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('product_variants', 'id')->where(fn ($q) => $q->where('product_id', $product->id)),
            ],
        ]);

        $newStock = (int) $validated['stock'];
        $variant = null;
        if (! empty($validated['variant_id'])) {
            $variant = $product->variants()->where('id', (int) $validated['variant_id'])->first();
        }
        $variant = $variant ?? $this->defaultCatalogVariant($product);

        if (! $variant) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This product has no inventory row to update.',
                ], 422);
            }

            return back()->withErrors(['stock' => 'This product has no inventory row to update.']);
        }

        $this->applyInlineVariantStock($variant, $newStock, $user, 'List stock: set to '.$newStock);

        return $this->stockUpdateResponse($request, $store, $product, $variant, $newStock);
    }

    public function updateVariantStocks(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        [$store, $user] = $this->authorizeInlineCatalog($request, $product);

        $validated = $request->validate([
            'variants' => ['required', 'array', 'min:1', 'max:200'],
            'variants.*.id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('product_variants', 'id')->where(fn ($q) => $q->where('product_id', $product->id)),
            ],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:999999'],
        ]);

        $rows = collect($validated['variants'])
            ->map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'stock' => (int) $row['stock'],
            ])
            ->unique('id')
            ->values();

        $variantsById = $product->variants()
            ->whereIn('id', $rows->pluck('id')->all())
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $variant = $variantsById->get($row['id']);
            if (! $variant) {
                continue;
            }
            $this->applyInlineVariantStock(
                $variant,
                $row['stock'],
                $user,
                'List option stock: set to '.$row['stock']
            );
        }

        return $this->stockUpdateResponse($request, $store, $product, null, null, 'Option stock updated.');
    }

    /**
     * @return array{0: Store, 1: User}
     */
    private function authorizeInlineCatalog(Request $request, Product $product): array
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $user = $request->user();
        if (! $user?->hasStorePermission($store, StorePermission::CATALOG_MANAGE)) {
            abort(403, 'You are not authorized to edit catalog items in this store.');
        }

        abort_if($product->trashed(), 404);

        return [$store, $user];
    }

    private function defaultCatalogVariant(Product $product): ?ProductVariant
    {
        $variant = $product->variants()->whereDoesntHave('options')->orderBy('id')->first();

        return $variant ?? $product->variants()->orderBy('id')->first();
    }

    private function applyInlineVariantStock(
        ProductVariant $variant,
        int $newStock,
        $user,
        string $note
    ): void {
        $previous = app(InventoryAvailabilityService::class)->availableForVariant($variant);

        if ($newStock !== $previous) {
            app(InventoryAdjustmentService::class)->setVariantAvailable(
                $variant,
                $newStock,
                $note,
                $user,
                [
                    'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
                    'source' => 'catalog',
                    'performed_by' => $user?->id,
                    'previous_stock_for_movement' => $previous,
                    'initial_available' => $previous,
                ]
            );

            return;
        }

        app(InventorySyncService::class)->syncVariantStockCache($variant->fresh() ?? $variant);
    }

    private function stockUpdateResponse(
        Request $request,
        $store,
        Product $product,
        ?ProductVariant $variant,
        ?int $newStock,
        ?string $message = null
    ): JsonResponse|RedirectResponse {
        $product->unsetRelation('variants');
        $product->load(['variants:id,product_id,stock,stock_alert']);
        $product->loadSum('variants', 'stock');
        $product->loadMax('variants', 'stock_alert');

        $inventoryTotal = (int) ($product->variants_sum_stock ?? $product->variants->sum('stock'));
        $meta = is_array($product->meta) ? $product->meta : [];
        $meta['default_stock'] = $inventoryTotal;
        if ($product->variants->count() <= 1) {
            $meta['stock_alert'] = (int) ($product->variants->first()?->stock_alert ?? ($meta['stock_alert'] ?? 0));
        }
        $product->update(['meta' => $meta]);

        $state = ProductInventoryState::forProduct($product, $product->variants);
        $variantCount = $product->variants->count();
        $resolvedMessage = $message ?? (
            $variantCount > 1
                ? 'Main variant stock updated. Other variants were left unchanged.'
                : 'Stock updated.'
        );

        app(SecurityLogRecorder::class)->record(
            $request,
            'product_inline_update_stock',
            store: $store,
            metadata: [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'stock' => $newStock,
                'variant_count' => $variantCount,
                'stock_state' => $state['state'],
                'inventory_total' => $state['inventory'],
            ]
        );

        $variantStocks = $product->variants
            ->map(static fn (ProductVariant $row): array => [
                'id' => (int) $row->id,
                'stock' => (int) $row->stock,
                'stock_alert' => (int) $row->stock_alert,
            ])
            ->values()
            ->all();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'stock' => $newStock,
                'inventory_total' => $state['inventory'],
                'stock_alert' => $state['alert'],
                'stock_state' => $state['state'],
                'is_low' => $state['is_low'],
                'is_out' => $state['is_out'],
                'is_published' => (bool) $product->status,
                'variant_count' => $variantCount,
                'variants' => $variantStocks,
                'message' => $resolvedMessage,
            ]);
        }

        return back()->with('success', $resolvedMessage)
            ->with('success_title', 'Stock saved');
    }
}
