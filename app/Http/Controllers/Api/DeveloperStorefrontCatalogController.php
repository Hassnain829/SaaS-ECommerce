<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\StockMovementRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeveloperStorefrontCatalogController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');

        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('status', true)
            ->whereHas('variants')
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants.options.variationType',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'currency' => $store->currency,
            ],
            'products' => $products->map(fn (Product $p) => $this->serializeProduct($p)),
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $order = DB::transaction(function () use ($store, $validated) {
            $lines = [];
            $total = '0';

            $mergedItems = [];
            foreach ($validated['items'] as $item) {
                $key = (int) $item['product_id'].'-'.(int) $item['variant_id'];
                if (! isset($mergedItems[$key])) {
                    $mergedItems[$key] = [
                        'product_id' => (int) $item['product_id'],
                        'variant_id' => (int) $item['variant_id'],
                        'quantity' => 0,
                    ];
                }
                $mergedItems[$key]['quantity'] += (int) $item['quantity'];
            }

            $variantRows = ProductVariant::query()
                ->whereIn('id', collect($mergedItems)->pluck('variant_id')->unique()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($mergedItems as $item) {
                /** @var ProductVariant|null $variant */
                $variant = $variantRows->get((int) $item['variant_id']);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more variants are invalid.',
                    ]);
                }

                $product = Product::query()
                    ->where('id', (int) $item['product_id'])
                    ->where('store_id', $store->id)
                    ->where('status', true)
                    ->first();

                if (! $product || (int) $variant->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages([
                        'items' => 'Product and variant do not match this store.',
                    ]);
                }

                $qty = (int) $item['quantity'];
                $previousStock = (int) $variant->stock;

                if ($previousStock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$product->name} (SKU {$variant->sku}).",
                    ]);
                }

                $unit = (string) $variant->price;
                $line = bcmul($unit, (string) $qty, 2);

                $variant->update(['stock' => $previousStock - $qty]);
                $variant->refresh();

                StockMovementRecorder::recordAdjustment(
                    $store,
                    $product,
                    $variant,
                    $previousStock,
                    (int) $variant->stock,
                    null,
                    'developer_storefront',
                    \App\Models\StockMovement::TYPE_ORDER_SALE,
                    'Developer test storefront order'
                );

                $lines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $line,
                ];

                $total = bcadd($total, $line, 2);
            }

            $reference = 'baa_'.Str::lower(Str::random(20));

            $order = Order::query()->create([
                'store_id' => $store->id,
                'reference' => $reference,
                'status' => Order::STATUS_CONFIRMED,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'] ?? null,
                'total' => $total,
                'currency' => $store->currency,
                'source' => 'developer_storefront',
                'meta' => ['channel' => 'developer_test_react'],
            ]);

            foreach ($lines as $row) {
                $order->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']->id,
                    'product_name' => $row['product']->name,
                    'variant_label' => $this->variantLabel($row['variant']),
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);
            }

            return $order->load('items');
        });

        return response()->json([
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status,
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'items' => $order->items->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'variant_label' => $i->variant_label,
                    'quantity' => $i->quantity,
                    'unit_price' => (string) $i->unit_price,
                    'line_total' => (string) $i->line_total,
                ]),
            ],
        ], 201);
    }

    private function serializeProduct(Product $product): array
    {
        $primary = $product->images->first(fn ($img) => $img->is_primary)
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
            'primary_image_url' => $imageUrl,
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => (string) $v->price,
                'stock' => (int) $v->stock,
                'options' => $v->options->map(fn ($o) => [
                    'type' => $o->variationType?->name ?? 'Option',
                    'value' => $o->value,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $variant->loadMissing('options.variationType');

        if ($variant->options->isEmpty()) {
            return 'Default';
        }

        return $variant->options
            ->map(fn ($o) => ($o->variationType?->name ?? 'Option').': '.$o->value)
            ->implode(', ');
    }
}
