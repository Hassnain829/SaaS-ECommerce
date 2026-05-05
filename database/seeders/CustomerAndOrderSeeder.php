<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerAndOrderSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::query()->where('slug', 'demo-fashion')->first();

        if (!$store) {
            return;
        }

        $products = Product::query()
            ->where('store_id', $store->id)
            ->whereHas('variants')
            ->with('variants')
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            // Create a fallback product with a variant so we can seed orders
            $product = Product::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
                    'slug' => 'sample-product-for-orders'
                ],
                [
                    'name' => 'Sample Product for Orders',
                    'description' => 'This is a sample product generated automatically because the catalog was empty.',
                    'status' => true,
                    'product_type' => 'physical',
                    'sku' => 'DEMO-PROD-001',
                    'base_price' => 29.99,
                ]
            );

            $variant = $product->variants()->firstOrCreate(
                ['sku' => 'DEMO-SKU-001'],
                [
                    'price' => 29.99,
                    'stock' => 100,
                ]
            );

            // Create a fake default option so the UI doesn't break
            $product->variationTypes()->firstOrCreate(
                ['name' => 'Size'],
                ['type' => 'select']
            );

            $products = collect([$product->load('variants')]);
        }

        $customersData = [
            [
                'email' => 'john.doe@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'full_name' => 'John Doe',
                'phone' => '+1234567890',
                'status' => 'guest',
                'address' => [
                    'name' => 'John Doe',
                    'address_line1' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'United States',
                    'phone' => '+1234567890',
                ]
            ],
            [
                'email' => 'jane.smith@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'full_name' => 'Jane Smith',
                'phone' => '+0987654321',
                'status' => 'active',
                'address' => [
                    'name' => 'Jane Smith',
                    'address_line1' => '456 Oak Ave',
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'postal_code' => '90001',
                    'country' => 'United States',
                    'phone' => '+0987654321',
                ]
            ],
            [
                'email' => 'alice.wonder@example.com',
                'first_name' => 'Alice',
                'last_name' => 'Wonder',
                'full_name' => 'Alice Wonder',
                'phone' => '+1122334455',
                'status' => 'active',
                'address' => [
                    'name' => 'Alice Wonder',
                    'address_line1' => '789 Pine Rd',
                    'city' => 'Seattle',
                    'state' => 'WA',
                    'postal_code' => '98101',
                    'country' => 'United States',
                    'phone' => '+1122334455',
                ]
            ]
        ];

        foreach ($customersData as $cData) {
            $customer = Customer::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
                    'email' => $cData['email'],
                ],
                [
                    'first_name' => $cData['first_name'],
                    'last_name' => $cData['last_name'],
                    'full_name' => $cData['full_name'],
                    'phone' => $cData['phone'],
                    'status' => $cData['status'],
                    'total_orders' => 0,
                    'total_spent' => 0,
                ]
            );

            $customer->addresses()->create(array_merge($cData['address'], [
                'type' => 'shipping',
                'is_default' => true,
            ]));

            // Create 1-3 orders for each customer
            $numOrders = rand(1, 3);
            $totalSpent = 0;

            for ($i = 0; $i < $numOrders; $i++) {
                $orderTotal = 0;
                $orderItems = [];
                $itemCount = rand(1, min(3, $products->count()));

                $orderProducts = $products->random($itemCount);

                foreach ($orderProducts as $product) {
                    $variant = $product->variants->first();
                    $qty = rand(1, 2);
                    $subtotal = bcmul((string)$variant->price, (string)$qty, 2);

                    $orderItems[] = [
                        'product' => $product,
                        'variant' => $variant,
                        'quantity' => $qty,
                        'unit_price' => $variant->price,
                        'subtotal' => $subtotal,
                        'total' => $subtotal,
                    ];

                    $orderTotal = bcadd((string)$orderTotal, $subtotal, 2);
                }

                $placedAt = now()->subDays(rand(1, 30));

                $statuses = [Order::STATUS_CONFIRMED, Order::STATUS_PENDING, 'processing', 'completed'];
                $status = $statuses[array_rand($statuses)];

                $order = Order::query()->create([
                    'store_id' => $store->id,
                    'customer_id' => $customer->id,
                    'order_number' => (string)rand(10000, 99999),
                    'status' => $status,
                    'payment_status' => 'paid',
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'billing_same_as_shipping' => true,
                    'subtotal' => $orderTotal,
                    'total' => $orderTotal,
                    'grand_total' => $orderTotal,
                    'currency_code' => $store->currency,
                    'order_source' => 'developer_storefront',
                    'item_count' => count($orderItems),
                    'total_quantity' => collect($orderItems)->sum('quantity'),
                    'placed_at' => $placedAt,
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);

                $order->addresses()->create(array_merge($cData['address'], [
                    'type' => 'shipping',
                    'email' => $customer->email,
                ]));

                foreach ($orderItems as $item) {
                    $order->items()->create([
                        'product_id' => $item['product']->id,
                        'product_variant_id' => $item['variant']->id,
                        'product_name' => $item['product']->name,
                        'sku_snapshot' => $item['variant']->sku,
                        'barcode_snapshot' => $item['variant']->barcode,
                        'product_slug_snapshot' => $item['product']->slug,
                        'variant_label' => collect($item['variant']->options)->pluck('value')->join(' / '),
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['subtotal'],
                        'total' => $item['total'],
                    ]);
                }

                $totalSpent = bcadd((string)$totalSpent, (string)$orderTotal, 2);
            }

            $customer->update([
                'total_orders' => $numOrders,
                'total_spent' => $totalSpent,
                'average_order_value' => bcdiv((string)$totalSpent, (string)$numOrders, 2),
                'last_order_at' => now(),
            ]);
        }
    }
}
