<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
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

    public function test_product_create_persists_brand_when_brand_belongs_to_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Brand Product Store');
        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'In-Store Brand',
            'slug' => 'in-store-brand',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Branded Widget',
                'description' => 'With brand.',
                'bulk_price' => 19.99,
                'sku' => 'BW-001',
                'product_type' => 'physical',
                'bulk_stock' => 5,
                'stock_alert' => 2,
                'brand_id' => $brand->id,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'name' => 'Branded Widget',
            'store_id' => $store->id,
            'brand_id' => $brand->id,
        ]);
    }

    public function test_product_create_rejects_brand_from_another_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Brand Store');
        $betaStore = $this->createMemberStore($merchant, 'Beta Brand Store');
        $foreignBrand = Brand::query()->create([
            'store_id' => $betaStore->id,
            'name' => 'Beta Only Brand',
            'slug' => 'beta-only-brand',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->from(route('products'))
            ->post(route('product.store'), [
                'name' => 'Should Fail Brand',
                'description' => 'Cross-store brand.',
                'bulk_price' => 9.99,
                'sku' => 'BF-001',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'brand_id' => $foreignBrand->id,
            ]);

        $response->assertRedirect(route('products'));
        $response->assertSessionHasErrors('brand_id');

        $this->assertDatabaseMissing('products', [
            'name' => 'Should Fail Brand',
            'store_id' => $alphaStore->id,
        ]);
    }

    public function test_product_update_rejects_brand_from_another_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Update Brand');
        $betaStore = $this->createMemberStore($merchant, 'Beta Update Brand');
        $product = $this->createProduct($alphaStore, 'Alpha Widget');
        $foreignBrand = Brand::query()->create([
            'store_id' => $betaStore->id,
            'name' => 'Foreign Brand',
            'slug' => 'foreign-brand-upd',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->from(route('products'))
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Alpha Widget',
                'description' => $product->description,
                'base_price' => 99.99,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 2,
                'brand_id' => $foreignBrand->id,
            ]);

        $response->assertRedirect(route('products'));
        $response->assertSessionHasErrors('brand_id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'brand_id' => null,
        ]);
    }

    public function test_products_list_loads_brand_relation_for_rows(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'List Brand Store');
        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Row Brand',
            'slug' => 'row-brand',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);
        $product = $this->createProduct($store, 'Visible Brand Product');
        $product->update(['brand_id' => $brand->id]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertSee('Row Brand');
    }

    public function test_products_page_filters_by_brand_query_for_current_store(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Filter Brand Store');
        $brandA = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Alpha Brand',
            'slug' => 'alpha-brand-f',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);
        $brandB = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Beta Brand',
            'slug' => 'beta-brand-f',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);
        $pA = $this->createProduct($store, 'Product A');
        $pA->update(['brand_id' => $brandA->id]);
        $pB = $this->createProduct($store, 'Product B');
        $pB->update(['brand_id' => $brandB->id]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['brand' => $brandA->id]));

        $response->assertOk();
        $response->assertSeeText('Product A');
        $response->assertDontSeeText('Product B');
        $response->assertViewHas('activeBrandFilter', fn ($b) => $b && $b->is($brandA));
    }

    public function test_products_page_ignores_brand_filter_from_another_store(): void
    {
        $merchant = $this->createMerchantUser();
        $alpha = $this->createMemberStore($merchant, 'Alpha F Store');
        $beta = $this->createMemberStore($merchant, 'Beta F Store');
        $foreignBrand = Brand::query()->create([
            'store_id' => $beta->id,
            'name' => 'Beta Only',
            'slug' => 'beta-only-f',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);
        $p = $this->createProduct($alpha, 'Alpha Solo');
        $p->update(['brand_id' => null]);

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alpha->id])
            ->get(route('products', ['brand' => $foreignBrand->id]));

        $response->assertOk();
        $response->assertSeeText('Alpha Solo');
        $response->assertViewHas('activeBrandFilter', null);
    }

    public function test_products_taxonomy_category_query_is_separate_from_product_type_filter(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Taxonomy Type Store');

        $taxonomy = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Browse Group',
            'slug' => 'browse-group',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $physicalTagged = $this->createProduct($store, 'Physical With Taxonomy');
        $physicalTagged->update(['product_type' => 'physical']);
        $physicalTagged->categories()->sync([$taxonomy->id]);

        $physicalPlain = $this->createProduct($store, 'Physical No Taxonomy');
        $physicalPlain->update(['product_type' => 'physical']);

        $catResponse = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['category' => $taxonomy->id]));

        $catResponse->assertOk();
        $catResponse->assertSeeText('Physical With Taxonomy');
        $catResponse->assertDontSeeText('Physical No Taxonomy');

        $typeResponse = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['product_type' => 'physical']));

        $typeResponse->assertOk();
        $typeResponse->assertSeeText('Physical With Taxonomy');
        $typeResponse->assertSeeText('Physical No Taxonomy');

        $ignoredStringCategory = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['category' => 'physical']));

        $ignoredStringCategory->assertOk();
        $ignoredStringCategory->assertSeeText('Physical With Taxonomy');
        $ignoredStringCategory->assertSeeText('Physical No Taxonomy');
    }

    public function test_store_products_route_sets_current_store_and_redirects_to_products(): void
    {
        $merchant = $this->createMerchantUser();
        $alpha = $this->createMemberStore($merchant, 'Alpha Nav');
        $beta = $this->createMemberStore($merchant, 'Beta Nav');

        $response = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alpha->id])
            ->get(route('store.products', ['storeId' => $beta->id]));

        $response->assertRedirect(route('products'));
        $response->assertSessionHas('current_store_id', $beta->id);
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
            ],
        ]);
    }
}
