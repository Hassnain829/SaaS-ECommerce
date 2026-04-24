<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperStorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_401_without_token(): void
    {
        $this->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();
    }

    public function test_catalog_returns_products_for_valid_token(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Dev API Store');

        $plain = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Catalog Item',
            'slug' => 'catalog-item-'.Str::random(6),
            'description' => null,
            'base_price' => 9.99,
            'sku' => 'CAT-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'CAT-1-D',
            'price' => 9.99,
            'stock' => 5,
            'stock_alert' => 0,
        ]);

        $this->withToken($plain)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk()
            ->assertJsonPath('store.id', $store->id)
            ->assertJsonCount(1, 'products');
    }

    public function test_place_order_decrements_stock(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Dev Order Store');

        $plain = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Order Item',
            'slug' => 'order-item-'.Str::random(6),
            'description' => null,
            'base_price' => 10,
            'sku' => 'ORD-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'ORD-1-D',
            'price' => 10,
            'stock' => 3,
            'stock_alert' => 0,
        ]);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', [
                'customer_name' => 'Test Buyer',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonStructure(['order' => ['reference', 'total', 'items']]);

        $this->assertSame(1, (int) $variant->fresh()->stock);
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name): Store
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

        $store->members()->attach($user->id, ['role' => 'owner']);

        return $store;
    }
}
