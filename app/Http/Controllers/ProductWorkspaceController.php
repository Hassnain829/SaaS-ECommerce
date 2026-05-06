<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Support\ProductDetailPresenter;
use App\Support\ProductEditPayload;
use App\Support\ProductTypeBehavior;
use App\Support\ProductVariantLabel;
use App\Support\StorePermission;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Store-scoped product workspace (read + dedicated edit entry).
 * Route names stay `products.show` / `products.edit`; this controller is the canonical product workspace surface.
 */
final class ProductWorkspaceController extends Controller
{
    /**
     * Dedicated store-scoped product edit (same save pipeline as the catalog list modal).
     * After save, `OnboardingController::updateProductFromManagement` may redirect back to the workspace when `_workspace_return_product_id` is set.
     */
    public function edit(Request $request, Product $product): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $user = $request->user();
        abort_unless(
            $user !== null && $user->hasStorePermission($store, StorePermission::CATALOG_MANAGE),
            403
        );

        $catalogTaxonomyCategories = $store->categories()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $catalogBrands = $store->brands()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $catalogTags = $store->tags()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $catalogAttributes = $store->attributes()
            ->with(['terms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /** @var array<string, mixed> $old */
        $old = $request->session()->get('_old_input', []);
        $editProductPayload = ProductEditPayload::withFormOld($product, $old);

        return view('user_view.product_workspace_edit', [
            'selectedStore' => $store,
            'product' => $product,
            'catalogBrands' => $catalogBrands,
            'catalogTags' => $catalogTags,
            'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
            'catalogAttributes' => $catalogAttributes,
            'editProductPayload' => $editProductPayload,
            'workspaceReturnProductId' => $product->id,
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $product->load([
            'store:id,name,currency',
            'brand:id,name,store_id',
            'categories:id,name,store_id',
            'tags:id,name,store_id,color',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'variationTypes' => fn ($q) => $q->orderBy('id'),
            'variationTypes.options:id,variation_type_id,value,sort_order',
            'variants' => fn ($q) => $q->orderBy('id'),
            'variants.options' => fn ($q) => $q->orderBy('variation_type_id')->orderBy('sort_order'),
            'variants.options.variationType:id,name',
            'variants.linkedCatalogImage:id,product_id,product_variant_id,image_path,status,sort_order,is_primary',
            'productAttributes.attribute:id,store_id,name,slug,display_type,is_filterable,is_visible',
            'productAttributes.terms:id,attribute_id,name,slug,swatch_value',
        ]);

        $user = $request->user();
        $canManageCatalog = $user !== null && $user->hasStorePermission($store, StorePermission::CATALOG_MANAGE);

        $recentMovements = StockMovement::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->with('performer:id,name')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $meta = is_array($product->meta) ? $product->meta : [];
        $catalog = is_array($meta['catalog'] ?? null) ? $meta['catalog'] : [];
        $customFieldRows = ProductDetailPresenter::associativeRows(is_array($meta['custom_fields'] ?? null) ? $meta['custom_fields'] : []);
        $importExtraRows = ProductDetailPresenter::associativeRowsWithRawKeys(
            is_array($meta['import_extra'] ?? null) ? $meta['import_extra'] : []
        );

        $primaryCatalogThumb = null;
        $primaryImg = $product->images->first(fn ($im) => $im->is_primary) ?? $product->images->first();
        if ($primaryImg && $primaryImg->isReady() && $primaryImg->image_path) {
            $primaryCatalogThumb = asset('storage/'.$primaryImg->image_path);
        }

        $variantSummaries = [];
        $variantCount = (int) $product->variants->count();
        foreach ($product->variants as $idx => $variant) {
            $optionsOrdered = $variant->options->sortBy(fn ($o) => [$o->variation_type_id, $o->sort_order])->values();
            $parsed = ProductVariantLabel::fromOptions($optionsOrdered);
            $label = ProductVariantLabel::forVariant($variant, $idx, $variantCount);
            $img = $variant->linkedCatalogImage;
            $thumbUrl = null;
            $imageIsProductFallback = false;
            if ($img && $img->isReady() && $img->image_path) {
                $thumbUrl = asset('storage/'.$img->image_path);
            } elseif ($primaryCatalogThumb !== null) {
                $thumbUrl = $primaryCatalogThumb;
                $imageIsProductFallback = true;
            }
            $vMeta = is_array($variant->meta) ? $variant->meta : [];
            $variantCustomRows = ProductDetailPresenter::associativeRows(
                is_array($vMeta['custom_fields'] ?? null) ? $vMeta['custom_fields'] : []
            );

            $variantSummaries[] = [
                'index' => $idx + 1,
                'label' => $label,
                'chips' => $parsed['chips'],
                'sku' => (string) $variant->sku,
                'price' => (string) $variant->price,
                'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null,
                'stock' => (int) $variant->stock,
                'stock_alert' => (int) $variant->stock_alert,
                'is_first' => $idx === 0,
                'catalog_image_thumb' => $thumbUrl,
                'catalog_image_is_product_fallback' => $imageIsProductFallback,
                'additional_detail_rows' => $variantCustomRows,
            ];
        }

        $optionGroupSummaries = $product->variationTypes->map(function ($type) {
            return [
                'name' => (string) $type->name,
                'values' => $type->options->sortBy('sort_order')->pluck('value')->values()->all(),
            ];
        })->values()->all();

        $attributeRows = $product->productAttributes
            ->filter(fn ($row): bool => $row->attribute !== null && (int) $row->attribute->store_id === (int) $store->id)
            ->map(fn ($row): array => [
                'name' => (string) $row->attribute->name,
                'is_filterable' => (bool) $row->attribute->is_filterable,
                'terms' => $row->terms->pluck('name')->filter()->values()->all(),
            ])
            ->values()
            ->all();

        $totalStock = (int) $product->variants->sum('stock');
        $maxVariantAlert = (int) $product->variants->max('stock_alert');
        $metaStockAlert = isset($meta['stock_alert']) ? (int) $meta['stock_alert'] : 0;
        $effectiveLowThreshold = max($maxVariantAlert, $metaStockAlert);

        return view('user_view.product_workspace', [
            'selectedStore' => $store,
            'product' => $product,
            'canManageCatalog' => $canManageCatalog,
            'recentMovements' => $recentMovements,
            'catalog' => $catalog,
            'customFieldRows' => $customFieldRows,
            'importExtraRows' => $importExtraRows,
            'attributeRows' => $attributeRows,
            'productBehavior' => ProductTypeBehavior::behaviorFor($product->product_type),
            'variantSummaries' => $variantSummaries,
            'optionGroupSummaries' => $optionGroupSummaries,
            'totalStock' => $totalStock,
            'effectiveLowThreshold' => $effectiveLowThreshold,
        ]);
    }
}
