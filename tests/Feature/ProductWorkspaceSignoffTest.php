<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWorkspaceSignoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_page_merchant_surface_has_no_quick_view_wording(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Signoff Store');
        $product = $this->makeProduct($store, 'Signoff Product');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Quick view', false)
            ->assertSee('Product workspace', false)
            ->assertSee('Edit product', false);
    }

    public function test_dedicated_edit_page_loads_without_catalog_bridge(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Edit Page Store');
        $product = $this->makeProduct($store, 'Editable');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Editable', false)
            ->assertSee('Edit catalog item', false)
            ->assertSee('Back to product workspace', false)
            ->assertSee('Save and return to workspace', false)
            ->assertSee('id="editProductForm"', false)
            ->assertSee('id="catalog-editor-workspace-layout"', false)
            ->assertDontSee('Editing from product workspace', false);
    }

    public function test_staff_cannot_open_dedicated_edit_page(): void
    {
        $owner = $this->merchantUser('owner-sf@example.com');
        $staff = $this->merchantUser('staff-sf@example.com');
        $store = $this->makeStore($owner, 'Staff Edit');
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        $product = $this->makeProduct($store, 'Staff Product');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.edit', $product))
            ->assertForbidden();
    }

    public function test_edit_route_is_store_scoped(): void
    {
        $owner = $this->merchantUser();
        $storeA = $this->makeStore($owner, 'Store A SF');
        $storeB = $this->makeStore($owner, 'Store B SF');
        $productB = $this->makeProduct($storeB, 'Only B');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products.edit', $productB))
            ->assertNotFound();
    }

    public function test_product_update_with_workspace_return_redirects_to_workspace(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Return Store');
        $product = $this->makeProduct($store, 'Return Product');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                '_workspace_return_product_id' => (string) $product->id,
                'name' => 'Return Product Renamed',
                'description' => 'd',
                'base_price' => 10,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 1,
                'variation_types' => [],
            ])
            ->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('Return Product Renamed', $product->name);
    }

    public function test_catalog_list_does_not_show_workspace_bridge_banner_for_legacy_query(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'Legacy Query Store');
        $product = $this->makeProduct($store, 'Legacy Product');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', [
                'edit_product' => $product->id,
                'workspace_return' => $product->id,
            ]))
            ->assertOk()
            ->assertDontSee('Editing from product workspace', false);
    }

    protected function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function makeStore(User $owner, string $name): Store
    {
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    protected function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(fake()->lexify('????')),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        return $product;
    }
}
