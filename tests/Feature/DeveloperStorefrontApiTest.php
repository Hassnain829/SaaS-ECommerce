<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeveloperStorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_401_without_token(): void
    {
        $this->getJson('/api/developer-storefront/catalog')->assertUnauthorized();
    }

    public function test_catalog_returns_products_for_active_connected_site_credential(): void
    {
        [$store, $plain] = $this->createConnectedStore('Dev API Store');
        $this->createStorefrontProduct($store);

        $this->withToken($plain)
            ->getJson('/api/developer-storefront/catalog')
            ->assertOk()
            ->assertJsonPath('store.id', $store->id)
            ->assertJsonPath('store.checkout_mode', 'platform_checkout')
            ->assertJsonStructure(['store' => ['platform_checkout' => ['ready']]])
            ->assertJsonCount(1, 'products');
    }

    public function test_legacy_store_token_cannot_authenticate(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Legacy Token Store');
        $plain = 'legacy_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        $this->withToken($plain)
            ->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();
    }

    public function test_direct_paid_order_submission_is_retired_and_has_no_side_effects(): void
    {
        [$store, $plain] = $this->createConnectedStore('Retired Direct Order Store');
        [$product, $variant] = $this->createStorefrontProduct($store, ['stock' => 5]);
        $before = [
            'customers' => DB::table('customers')->count(),
            'orders' => DB::table('orders')->count(),
            'reservations' => DB::table('inventory_reservations')->count(),
            'movements' => DB::table('stock_movements')->count(),
        ];

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', [
                'payment_status' => 'paid',
                'customer_email' => 'buyer@example.test',
                'items' => [[
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ]],
            ])
            ->assertNotFound();

        $this->assertSame($before['customers'], DB::table('customers')->count());
        $this->assertSame($before['orders'], DB::table('orders')->count());
        $this->assertSame($before['reservations'], DB::table('inventory_reservations')->count());
        $this->assertSame($before['movements'], DB::table('stock_movements')->count());
        $this->assertSame(5, (int) $variant->fresh()->stock);
    }

    private function createConnectedStore(string $name): array
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, $name);
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);

        return [$store, $issued['plain'], $issued['site']];
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
        $store->members()->attach($user->id, ['role' => 'owner']);

        return $store;
    }

    private function createStorefrontProduct(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Catalog Item',
            'slug' => 'catalog-item-'.Str::random(6),
            'description' => null,
            'base_price' => 9.99,
            'sku' => 'CAT-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 9.99,
            'stock' => $overrides['stock'] ?? 5,
            'stock_alert' => 0,
        ]);

        return [$product, $variant];
    }
}
