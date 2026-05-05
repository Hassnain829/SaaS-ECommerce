<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
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
        [$store, $plain] = $this->createTokenedStore('Dev Order Store');
        [$product, $variant] = $this->createStorefrontProduct($store, [
            'name' => 'Order Item',
            'sku' => 'ORD-1',
            'variant_sku' => 'ORD-1-D',
            'price' => 10,
            'stock' => 3,
        ]);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', $this->validOrderPayload($product, $variant, 2))
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001')
            ->assertJsonStructure(['order' => ['order_number', 'total', 'items']]);

        $this->assertSame(1, (int) $variant->fresh()->stock);
    }

    public function test_place_order_requires_shipping_address(): void
    {
        [$store, $plain] = $this->createTokenedStore('Dev Missing Address Store');
        [$product, $variant] = $this->createStorefrontProduct($store);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', [
                'customer_name' => 'Test Buyer',
                'customer_email' => 'buyer@example.test',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shipping_address',
                'shipping_address.address_line1',
                'shipping_address.city',
                'shipping_address.postal_code',
                'shipping_address.country',
            ]);
    }

    public function test_place_order_rejects_variant_from_another_store(): void
    {
        [$store, $plain] = $this->createTokenedStore('Dev Store A');
        [$otherStore] = $this->createTokenedStore('Dev Store B');
        [$product] = $this->createStorefrontProduct($store, ['sku' => 'STORE-A-PROD']);
        [, $otherVariant] = $this->createStorefrontProduct($otherStore, [
            'sku' => 'STORE-B-PROD',
            'variant_sku' => 'STORE-B-VAR',
            'stock' => 8,
        ]);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', $this->validOrderPayload($product, $otherVariant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(8, (int) $otherVariant->fresh()->stock);
    }

    public function test_place_order_rejects_insufficient_stock(): void
    {
        [$store, $plain] = $this->createTokenedStore('Dev Low Stock Store');
        [$product, $variant] = $this->createStorefrontProduct($store, [
            'sku' => 'LOW-STOCK',
            'variant_sku' => 'LOW-STOCK-D',
            'stock' => 1,
        ]);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', $this->validOrderPayload($product, $variant, 2))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(1, (int) $variant->fresh()->stock);
    }

    public function test_successful_order_creates_customer_addresses_items_and_stock_movement(): void
    {
        [$store, $plain] = $this->createTokenedStore('Dev Full Order Store');
        [$product, $variant] = $this->createStorefrontProduct($store, [
            'sku' => 'FULL-ORDER',
            'variant_sku' => 'FULL-ORDER-D',
            'price' => 12.50,
            'stock' => 5,
        ]);

        $this->withToken($plain)
            ->postJson('/api/developer-storefront/orders', $this->validOrderPayload($product, $variant, 2))
            ->assertCreated()
            ->assertJsonPath('order.total', '25.00')
            ->assertJsonPath('order.currency_code', $store->currency);

        $this->assertDatabaseHas('customers', [
            'store_id' => $store->id,
            'email' => 'buyer@example.test',
            'full_name' => 'Test Buyer',
            'total_orders' => 1,
        ]);

        $this->assertDatabaseHas('orders', [
            'store_id' => $store->id,
            'order_number' => '#1001',
            'customer_email' => 'buyer@example.test',
            'subtotal' => 25.00,
            'grand_total' => 25.00,
        ]);

        $this->assertDatabaseHas('order_addresses', [
            'type' => 'shipping',
            'email' => 'buyer@example.test',
            'address_line1' => '123 Market Street',
            'city' => 'Karachi',
            'postal_code' => '74000',
            'country' => 'Pakistan',
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'type' => 'shipping',
            'address_line1' => '123 Market Street',
            'city' => 'Karachi',
            'postal_code' => '74000',
            'country' => 'Pakistan',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Catalog Item',
            'sku_snapshot' => 'FULL-ORDER-D',
            'quantity' => 2,
            'unit_price' => 12.50,
            'subtotal' => 25.00,
            'total' => 25.00,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => 5,
            'quantity_change' => -2,
            'new_stock' => 3,
            'movement_type' => StockMovement::TYPE_ORDER_SALE,
            'source' => 'developer_storefront',
        ]);
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

    protected function createTokenedStore(string $name): array
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

    protected function createStorefrontProduct(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Catalog Item',
            'slug' => ($overrides['slug'] ?? 'catalog-item').'-'.Str::random(6),
            'description' => null,
            'base_price' => $overrides['price'] ?? 9.99,
            'sku' => $overrides['sku'] ?? 'CAT-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 9.99,
            'stock' => $overrides['stock'] ?? 5,
            'stock_alert' => 0,
        ]);

        return [$product, $variant];
    }

    protected function validOrderPayload(Product $product, ProductVariant $variant, int $quantity = 1): array
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
