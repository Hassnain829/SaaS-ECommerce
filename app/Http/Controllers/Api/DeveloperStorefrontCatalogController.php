<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderEventRecorder;
use App\Services\OrderNumberGenerator;
use App\Support\OrderLifecycle;
use App\Support\StockMovementRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.address_line1' => ['required', 'string'],
            'shipping_address.city' => ['required', 'string'],
            'shipping_address.state' => ['nullable', 'string'],
            'shipping_address.postal_code' => ['required', 'string'],
            'shipping_address.country' => ['required', 'string'],
            'shipping_address.phone' => ['nullable', 'string'],
            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing_address' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $order = DB::transaction(function () use ($store, $validated) {
            $lines = [];
            $total = '0';
            $itemCount = 0;
            $totalQuantity = 0;

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
                ->whereHas('product', fn ($query) => $query
                    ->where('store_id', $store->id)
                    ->where('status', true))
                ->with('product')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($mergedItems as $item) {
                /** @var ProductVariant|null $variant */
                $variant = $variantRows->get((int) $item['variant_id']);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more products or variants are not available for this store.',
                    ]);
                }

                $product = $variant->product;

                if (! $product || (int) $product->id !== (int) $item['product_id']) {
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
                    'subtotal' => $line,
                    'total' => $line,
                ];

                $total = bcadd($total, $line, 2);
                $itemCount++;
                $totalQuantity += $qty;
            }

            $orderNumber = app(OrderNumberGenerator::class)->generate($store);

            $customer = \App\Models\Customer::firstOrCreate(
                ['store_id' => $store->id, 'email' => $validated['customer_email']],
                [
                    'full_name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'] ?? null,
                    'status' => 'guest',
                ]
            );

            // update stats
            $customer->increment('total_orders');
            $customer->total_spent = bcadd((string)$customer->total_spent, $total, 2);
            if ($customer->total_orders > 0) {
                $customer->average_order_value = bcdiv((string)$customer->total_spent, (string)$customer->total_orders, 2);
            }
            $customer->last_order_at = now();
            $customer->save();

            $order = Order::query()->create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'order_number' => $orderNumber,
                'status' => OrderLifecycle::ORDER_CONFIRMED,
                'payment_status' => OrderLifecycle::PAYMENT_PAID,
                'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'billing_same_as_shipping' => $validated['billing_same_as_shipping'] ?? true,
                'subtotal' => $total,
                'total' => $total,
                'grand_total' => $total,
                'currency_code' => $store->currency,
                'order_source' => 'developer_storefront',
                'channel' => 'developer_test_react',
                'item_count' => $itemCount,
                'total_quantity' => $totalQuantity,
                'placed_at' => now(),
                'confirmed_at' => now(),
            ]);

            $shipping = $validated['shipping_address'];
            $order->addresses()->create([
                'type' => 'shipping',
                'name' => $validated['customer_name'],
                'email' => $validated['customer_email'],
                'address_line1' => $shipping['address_line1'] ?? null,
                'city' => $shipping['city'] ?? null,
                'state' => $shipping['state'] ?? null,
                'postal_code' => $shipping['postal_code'] ?? null,
                'country' => $shipping['country'] ?? null,
                'phone' => $shipping['phone'] ?? null,
            ]);

            if (!($validated['billing_same_as_shipping'] ?? true) && !empty($validated['billing_address'])) {
                $billing = $validated['billing_address'];
                $order->addresses()->create([
                    'type' => 'billing',
                    'name' => $validated['customer_name'],
                    'email' => $validated['customer_email'],
                    'address_line1' => $billing['address_line1'] ?? null,
                    'city' => $billing['city'] ?? null,
                    'state' => $billing['state'] ?? null,
                    'postal_code' => $billing['postal_code'] ?? null,
                    'country' => $billing['country'] ?? null,
                    'phone' => $billing['phone'] ?? null,
                ]);
            }

            $customer->addresses()->firstOrCreate(
                ['type' => 'shipping', 'address_line1' => $shipping['address_line1']],
                [
                    'name' => $validated['customer_name'],
                    'city' => $shipping['city'] ?? null,
                    'state' => $shipping['state'] ?? null,
                    'postal_code' => $shipping['postal_code'] ?? null,
                    'country' => $shipping['country'] ?? null,
                    'phone' => $shipping['phone'] ?? null,
                    'is_default' => true,
                ]
            );

            foreach ($lines as $row) {
                $order->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']->id,
                    'product_name' => $row['product']->name,
                    'sku_snapshot' => $row['variant']->sku,
                    'barcode_snapshot' => $row['variant']->barcode,
                    'product_slug_snapshot' => $row['product']->slug,
                    'product_image_snapshot' => $this->productImageSnapshot($row['product']),
                    'product_type_snapshot' => $row['product']->product_type,
                    'variant_label' => $this->variantLabel($row['variant']),
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'subtotal' => $row['subtotal'],
                    'total' => $row['total'],
                ]);
            }

            $eventRecorder = app(OrderEventRecorder::class);
            $eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_ORDER_CREATED,
                'Order placed',
                'Order was created from the developer storefront.',
                [
                    'source' => 'developer_storefront',
                    'channel' => 'developer_test_react',
                    'order_number' => $order->order_number,
                ],
                createdAt: $order->placed_at
            );

            $eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
                'Payment marked as paid',
                'Payment status was set to paid during checkout.',
                [
                    'payment_status' => OrderLifecycle::PAYMENT_PAID,
                    'source' => 'developer_storefront',
                ],
                createdAt: $order->placed_at?->copy()->addMinute()
            );

            $eventRecorder->record(
                $order,
                OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
                'Inventory deducted',
                'Stock was deducted for ordered items.',
                [
                    'item_count' => $itemCount,
                    'total_quantity' => $totalQuantity,
                ],
                createdAt: $order->placed_at?->copy()->addMinutes(2)
            );

            return $order->load(['items', 'addresses', 'events']);
        });

        return response()->json([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => (string) $order->total,
                'currency_code' => $order->currency_code,
                'items' => $order->items->map(fn ($i) => [
                    'product_name' => $i->product_name,
                    'variant_label' => $i->variant_label,
                    'quantity' => $i->quantity,
                    'unit_price' => (string) $i->unit_price,
                    'total' => (string) $i->total,
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

    private function productImageSnapshot(Product $product): ?string
    {
        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->value('image_path');
    }
}
