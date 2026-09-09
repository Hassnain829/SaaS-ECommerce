<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Payments\PaymentProviderManager;
use App\Support\CheckoutMode;
use App\Support\ProductTypeBehavior;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperStorefrontCatalogController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $store->stampDeveloperStorefrontLastSeen();

        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('status', true)
            ->whereHas('variants')
            ->with([
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants.options.variationType',
                'variants.catalogImages:id,product_id,image_path,status,is_primary',
            ])
            ->orderByDesc('id')
            ->get();

        try {
            $platformReady = app(PaymentProviderManager::class)->accountForCheckout($store) !== null;
        } catch (\Throwable) {
            $platformReady = false;
        }

        return response()->json([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'currency' => $store->currency,
                'checkout_mode' => CheckoutMode::PLATFORM,
                'platform_checkout' => [
                    'ready' => $platformReady,
                ],
            ],
            'products' => $products->map(fn (Product $product) => $this->serializeProduct($product)),
        ]);
    }

    private function serializeProduct(Product $product): array
    {
        $meta = is_array($product->meta) ? $product->meta : [];
        $customProductTypeLabel = trim((string) ($meta['custom_product_type_label'] ?? ''));
        $primary = $product->images->first(fn ($image) => $image->is_primary)
            ?? $product->images->first();

        $imageUrl = null;
        if ($primary && $primary->isReady() && $primary->image_path) {
            $imageUrl = asset('storage/'.$primary->image_path);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'product_type' => $product->product_type,
            'product_type_label' => ProductTypeBehavior::productTypeLabel($product->product_type, $customProductTypeLabel),
            'behavior' => ProductTypeBehavior::behaviorFor($product->product_type),
            'primary_image_url' => $imageUrl,
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (string) $variant->price,
                'stock' => (int) $variant->stock,
                'options' => $variant->options->map(fn ($option) => [
                    'type' => $option->variationType?->name ?? 'Option',
                    'value' => $option->value,
                ])->values()->all(),
                'images' => $variant->catalogImages
                    ->filter(fn ($image) => $image->isReady() && $image->image_path)
                    ->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => asset('storage/'.$image->image_path),
                    ])
                    ->values()
                    ->all(),
                'image_url' => (($primaryVariantImage = $variant->catalogImages->first(fn ($image) => $image->isReady() && $image->image_path)) !== null)
                    ? asset('storage/'.$primaryVariantImage->image_path)
                    : null,
            ])->values()->all(),
        ];
    }
}
