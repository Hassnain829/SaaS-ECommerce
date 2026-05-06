<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperStorefrontOrderEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_storefront_order_creates_order_events(): void
    {
        [$store, $token] = $this->createTokenedStore('Event Store');
        [$product, $variant] = $this->createStorefrontProduct($store);

        $this->withToken($token)
            ->postJson('/api/developer-storefront/orders', $this->validOrderPayload($product, $variant, 2))
            ->assertCreated();

        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_number', '#1001')
            ->firstOrFail();

        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_CREATED,
            'title' => 'Order placed',
        ]);

        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
            'title' => 'Payment marked as paid',
        ]);

        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
            'title' => 'Inventory deducted',
        ]);

        $this->assertSame([
            OrderLifecycle::EVENT_ORDER_CREATED,
            OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
            OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
        ], $order->events()->pluck('event_type')->all());
    }

    private function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function createMemberStore(User $user, string $name): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);

        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function createTokenedStore(string $name): array
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, $name);
        $plain = 'baa_dev_test_'.Str::random(32);

        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $plain];
    }

    private function createStorefrontProduct(Store $store): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Event Product',
            'slug' => 'event-product-'.Str::random(6),
            'description' => null,
            'base_price' => 9.99,
            'sku' => 'EVENT-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 9.99,
            'stock' => 5,
            'stock_alert' => 0,
        ]);

        return [$product, $variant];
    }

    private function validOrderPayload(Product $product, ProductVariant $variant, int $quantity = 1): array
    {
        return [
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.test',
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
                    'quantity' => $quantity,
                ],
            ],
        ];
    }
}
