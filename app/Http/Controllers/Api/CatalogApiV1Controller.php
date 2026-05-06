<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductTypeBehavior;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogApiV1Controller extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));
        $search = trim((string) ($request->query('search', $request->query('q', ''))));
        $category = trim((string) $request->query('category', ''));
        $brand = trim((string) $request->query('brand', ''));
        $productType = ProductTypeBehavior::normalize((string) $request->query('product_type', ''));
        $hasProductTypeFilter = $request->query('product_type') !== null && trim((string) $request->query('product_type')) !== '';
        $attributeTerm = trim((string) $request->query('attribute_term', ''));

        $query = Product::query()
            ->where('store_id', $store->id)
            ->where('status', true)
            ->with([
                'brand:id,name,slug,store_id',
                'categories:id,name,slug,store_id',
                'tags:id,name,slug,store_id,color',
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants.options.variationType:id,name',
                'productAttributes.attribute:id,store_id,name,slug,display_type,is_filterable,is_visible',
                'productAttributes.terms:id,attribute_id,name,slug,swatch_value',
            ]);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhereHas('categories', fn ($q) => $q->where('categories.name', 'like', '%'.$search.'%'));
            });
        }

        if ($category !== '') {
            $query->whereHas('categories', function ($q) use ($category): void {
                if (ctype_digit($category)) {
                    $q->where('categories.id', (int) $category);
                } else {
                    $q->where('categories.slug', $category);
                }
            });
        }

        if ($brand !== '') {
            if (ctype_digit($brand)) {
                $query->where('brand_id', (int) $brand);
            } else {
                $query->whereHas('brand', fn ($q) => $q->where('slug', $brand));
            }
        }

        if ($hasProductTypeFilter) {
            $query->where('product_type', $productType);
        }

        if ($attributeTerm !== '') {
            $query->whereHas('productAttributes.terms', function ($q) use ($attributeTerm): void {
                if (ctype_digit($attributeTerm)) {
                    $q->where('attribute_terms.id', (int) $attributeTerm);
                } else {
                    $q->where('attribute_terms.slug', $attributeTerm);
                }
            });
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0));
        }

        $products = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product): array => $this->serializeProduct($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id && $product->status, 404);

        $product->load([
            'brand:id,name,slug,store_id',
            'categories:id,name,slug,store_id',
            'tags:id,name,slug,store_id,color',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'variants.options.variationType:id,name',
            'productAttributes.attribute:id,store_id,name,slug,display_type,is_filterable,is_visible',
            'productAttributes.terms:id,attribute_id,name,slug,swatch_value',
        ]);

        return response()->json(['data' => $this->serializeProduct($product)]);
    }

    public function categories(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $categories = Category::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        return response()->json(['data' => $categories]);
    }

    public function brands(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $brands = Brand::query()
            ->where('store_id', $store->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $brands]);
    }

    public function attributes(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $attributes = $store->attributes()
            ->where('is_visible', true)
            ->with(['terms' => fn ($q) => $q->select(['id', 'attribute_id', 'name', 'slug', 'swatch_value'])->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'display_type', 'is_filterable', 'is_visible']);

        return response()->json(['data' => $attributes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        $primaryImage = $product->images->first(fn ($image) => $image->isReady() && $image->is_primary)
            ?? $product->images->first(fn ($image) => $image->isReady());

        $meta = is_array($product->meta) ? $product->meta : [];
        $customFields = is_array($meta['custom_fields'] ?? null) ? $meta['custom_fields'] : [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'sku' => $product->sku,
            'base_price' => (string) $product->base_price,
            'product_type' => $product->product_type,
            'behavior' => ProductTypeBehavior::behaviorFor($product->product_type),
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'categories' => $product->categories->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values(),
            'attributes' => $product->productAttributes->map(fn ($productAttribute): array => [
                'id' => $productAttribute->attribute?->id,
                'name' => $productAttribute->attribute?->name,
                'slug' => $productAttribute->attribute?->slug,
                'display_type' => $productAttribute->attribute?->display_type,
                'is_filterable' => (bool) $productAttribute->attribute?->is_filterable,
                'terms' => $productAttribute->terms->map(fn ($term): array => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'swatch_value' => $term->swatch_value,
                ])->values(),
            ])->filter(fn (array $row): bool => $row['id'] !== null)->values(),
            'additional_details' => $customFields,
            'images' => $product->images->filter(fn ($image) => $image->isReady())->map(fn ($image): array => [
                'id' => $image->id,
                'url' => asset('storage/'.$image->image_path),
                'is_primary' => (bool) $image->is_primary,
            ])->values(),
            'primary_image_url' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
            'variants' => $product->variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (string) $variant->price,
                'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : null,
                'stock' => (int) $variant->stock,
                'options' => $variant->options->map(fn ($option): array => [
                    'group' => $option->variationType?->name,
                    'value' => $option->value,
                ])->values(),
            ])->values(),
        ];
    }
}
