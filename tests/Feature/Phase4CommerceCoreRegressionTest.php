<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4CommerceCoreRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_order_appears_in_orders_list_and_customer_profile(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('Storefront Phase 4');
        [$product, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/developer-storefront/orders', $this->payload($product, $variant))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $customer = Customer::query()->where('store_id', $store->id)->where('email', 'repeat@example.test')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText(strtoupper($order->order_number))
            ->assertSeeText('Repeat Buyer')
            ->assertSeeText('Developer Storefront')
            ->assertSeeText('Paid')
            ->assertSeeText('Unfulfilled');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customersProfile', $customer))
            ->assertOk()
            ->assertSeeText(strtoupper($order->order_number))
            ->assertSeeText('USD 20.00');
    }

    public function test_repeat_storefront_customer_email_links_to_same_customer(): void
    {
        [$store, $token] = $this->tokenedStore('Repeat Customer Store');
        [$product, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/developer-storefront/orders', $this->payload($product, $variant))
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/developer-storefront/orders', $this->payload($product, $variant))
            ->assertCreated();

        $this->assertSame(1, Customer::query()->where('store_id', $store->id)->where('email', 'repeat@example.test')->count());
        $this->assertSame(2, Order::query()->where('store_id', $store->id)->count());
    }

    private function tokenedStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token, $owner];
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Storefront Product',
            'slug' => 'storefront-product-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'SF-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 20,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    private function payload(Product $product, ProductVariant $variant): array
    {
        return [
            'customer_name' => 'Repeat Buyer',
            'customer_email' => 'repeat@example.test',
            'customer_phone' => '+923001234567',
            'shipping_address' => [
                'address_line1' => '123 Market Street',
                'city' => 'Karachi',
                'state' => 'Sindh',
                'postal_code' => '74000',
                'country' => 'Pakistan',
                'phone' => '+923001234567',
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ];
    }
}
