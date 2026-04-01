<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCurrentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_only_shows_products_for_the_active_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $betaStore = $this->createMemberStore($merchant, 'Beta Store');

        $alphaProduct = $this->createProduct($alphaStore, 'Alpha Product');
        $betaProduct = $this->createProduct($betaStore, 'Beta Product');

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertSeeText($alphaProduct->name);
        $response->assertDontSeeText($betaProduct->name);
        $response->assertViewHas('selectedStore', fn ($store) => $store?->is($alphaStore));
    }

    public function test_creating_a_product_from_the_main_products_flow_saves_it_under_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $betaStore = $this->createMemberStore($merchant, 'Beta Store');

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->post(route('product.store'), [
                'name' => 'Created In Alpha',
                'description' => 'Created from the main products page.',
                'bulk_price' => 99.50,
                'sku' => 'ALPHA-001',
                'product_type' => 'physical',
                'bulk_stock' => 12,
                'stock_alert' => 3,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'name' => 'Created In Alpha',
            'store_id' => $alphaStore->id,
        ]);

        $this->assertDatabaseMissing('products', [
            'name' => 'Created In Alpha',
            'store_id' => $betaStore->id,
        ]);
    }

    public function test_updating_a_product_only_works_when_it_belongs_to_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $product = $this->createProduct($alphaStore, 'Original Product');

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Updated Product',
                'description' => 'Updated description.',
                'base_price' => 149.99,
                'sku' => 'UPDATED-001',
                'product_type' => 'physical',
                'stock_alert' => 4,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'store_id' => $alphaStore->id,
            'name' => 'Updated Product',
            'sku' => 'UPDATED-001',
        ]);
    }

    public function test_deleting_a_product_only_works_when_it_belongs_to_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $product = $this->createProduct($alphaStore, 'Delete Me');

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->delete(route('product.destroy', ['productId' => $product->id]));

        $response->assertRedirect(route('products'));

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_cross_store_product_modification_is_blocked(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $betaStore = $this->createMemberStore($merchant, 'Beta Store');
        $betaProduct = $this->createProduct($betaStore, 'Beta Only Product');

        $updateResponse = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->put(route('product.update', ['productId' => $betaProduct->id]), [
                'name' => 'Illegal Update',
                'description' => 'Should not update.',
                'base_price' => 200,
                'sku' => 'ILLEGAL-001',
                'product_type' => 'physical',
                'stock_alert' => 5,
            ]);

        $updateResponse->assertNotFound();

        $deleteResponse = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->delete(route('product.destroy', ['productId' => $betaProduct->id]));

        $deleteResponse->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $betaProduct->id,
            'store_id' => $betaStore->id,
            'name' => 'Beta Only Product',
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
            'slug' => str($name)->slug() . '-' . fake()->unique()->numberBetween(1000, 9999),
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

    protected function createProduct(Store $store, string $name): Product
    {
        return Product::create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug() . '-' . fake()->unique()->numberBetween(1000, 9999),
            'description' => $name . ' description',
            'base_price' => 99.99,
            'sku' => str($name)->upper()->slug('-'),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'default_stock' => 10,
                'stock_alert' => 2,
                'image_path' => null,
                'image_paths' => [],
            ],
        ]);
    }
}
