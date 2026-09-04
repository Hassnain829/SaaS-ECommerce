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

class ProductEditBasicsStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_basics_bulk_stock_and_alert_update_simple_product_variant(): void
    {
        [$owner, $store, $product, $variant] = $this->simpleProductSetup(stock: 10, alert: 2);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->updatePayload($product, [
                'bulk_stock' => 40,
                'stock_alert' => 5,
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => 12.5,
                    'stock' => 10,
                    'stock_alert' => 2,
                    'option_map' => [],
                ]],
            ]))
            ->assertRedirect();

        $fresh = $product->fresh()->variants()->first();
        $this->assertNotNull($fresh);
        $this->assertSame(40, (int) $fresh->stock);
        $this->assertSame(5, (int) $fresh->stock_alert);
        $this->assertSame(40, (int) data_get($product->fresh()->meta, 'default_stock'));
        $this->assertSame(5, (int) data_get($product->fresh()->meta, 'stock_alert'));
    }

    public function test_basics_stock_zero_persists_and_does_not_blank(): void
    {
        [$owner, $store, $product, $variant] = $this->simpleProductSetup(stock: 8, alert: 3);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->updatePayload($product, [
                'bulk_stock' => 0,
                'stock_alert' => 3,
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => 10,
                    'stock' => 8,
                    'stock_alert' => 3,
                    'option_map' => [],
                ]],
            ]))
            ->assertRedirect();

        $fresh = $product->fresh()->variants()->first();
        $this->assertNotNull($fresh);
        $this->assertSame(0, (int) $fresh->stock);
    }

    public function test_basics_low_stock_alert_change_does_not_change_sellable_stock(): void
    {
        [$owner, $store, $product, $variant] = $this->simpleProductSetup(stock: 25, alert: 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->updatePayload($product, [
                'bulk_stock' => 25,
                'stock_alert' => 7,
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => 10,
                    'stock' => 25,
                    'stock_alert' => 1,
                    'option_map' => [],
                ]],
            ]))
            ->assertRedirect();

        $fresh = $product->fresh()->variants()->first();
        $this->assertNotNull($fresh);
        $this->assertSame(25, (int) $fresh->stock);
        $this->assertSame(7, (int) $fresh->stock_alert);
    }

    public function test_bulk_stock_does_not_overwrite_a_single_option_variant(): void
    {
        [$owner, $store, $product, $variant] = $this->simpleProductSetup(stock: 160, alert: 20);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->updatePayload($product, [
                'bulk_stock' => 160,
                'stock_alert' => 20,
                'variation_types' => [
                    ['name' => 'color', 'type' => 'select', 'options' => ['r', 'g', 'b']],
                ],
                'variants' => [
                    [
                        'id' => $variant->id,
                        'sku' => $variant->sku.'-R',
                        'price' => '',
                        'stock' => 0,
                        'stock_alert' => 20,
                        'option_map' => ['0' => 0],
                    ],
                    [
                        'sku' => $variant->sku.'-G',
                        'price' => '',
                        'stock' => 0,
                        'stock_alert' => 20,
                        'option_map' => ['0' => 1],
                    ],
                    [
                        'sku' => $variant->sku.'-B',
                        'price' => '',
                        'stock' => 0,
                        'stock_alert' => 20,
                        'option_map' => ['0' => 2],
                    ],
                ],
            ]))
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(0, (int) $product->variants()->sum('stock'));
    }

    public function test_bulk_stock_does_not_fill_the_only_option_variant(): void
    {
        [$owner, $store, $product, $variant] = $this->simpleProductSetup(stock: 160, alert: 20);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->updatePayload($product, [
                'bulk_stock' => 160,
                'stock_alert' => 20,
                'variation_types' => [
                    ['name' => 'color', 'type' => 'select', 'options' => ['r']],
                ],
                'variants' => [[
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => '',
                    'stock' => 0,
                    'stock_alert' => 20,
                    'option_map' => ['0' => 0],
                ]],
            ]))
            ->assertRedirect();

        $fresh = $product->fresh()->variants()->first();
        $this->assertNotNull($fresh);
        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertSame(0, (int) $fresh->stock);
        $this->assertSame(1, $fresh->options()->count());
    }

    public function test_edit_workspace_shows_simplified_section_nav(): void
    {
        [$owner, $store, $product] = $this->simpleProductSetup(stock: 4, alert: 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Price &amp; stock', false)
            ->assertSee('Option groups', false)
            ->assertSee('data-pf-step="pricing"', false)
            ->assertSee('Low stock alert', false)
            ->assertSee('edit_product_stock', false)
            ->assertDontSee('data-product-edit-tab>Options</a>', false)
            ->assertDontSee('Extra info', false);
    }

    /**
     * @return array{0: User, 1: Store, 2: Product, 3: ProductVariant}
     */
    private function simpleProductSetup(int $stock, int $alert): array
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Basics Stock Product',
            'slug' => 'basics-stock-'.Str::lower(Str::random(6)),
            'description' => 'Desc',
            'base_price' => 10,
            'sku' => 'BSK-'.strtoupper(Str::random(4)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'default_stock' => $stock,
                'stock_alert' => $alert,
            ],
        ]);
        $variant = $product->variants()->create([
            'sku' => $product->sku.'-V',
            'price' => 10,
            'stock' => $stock,
            'stock_alert' => $alert,
        ]);

        return [$owner, $store, $product, $variant];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'description' => $product->description,
            'base_price' => $product->base_price,
            'sku' => $product->sku,
            'product_type' => $product->product_type,
            'stock_alert' => (int) data_get($product->meta, 'stock_alert', 0),
            'bulk_stock' => (int) data_get($product->meta, 'default_stock', 0),
            '_custom_fields_editor' => '1',
            'inventory_stock_allocation_mode' => 'manual',
        ], $overrides);
    }

    private function makeUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Edit Stock Store',
            'slug' => 'edit-stock-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }
}
