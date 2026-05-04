<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductWorkspaceViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_loads_for_owner_and_shows_workspace_identity(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS1');
        $product = $this->makeProduct($store, 'Alpha');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Quick view', false)
            ->assertSee('Product workspace', false)
            ->assertSee('Alpha', false)
            ->assertSee('Default inventory', false);
    }

    public function test_cross_store_workspace_returns_404(): void
    {
        $ownerA = $this->merchantUser('a-ws@example.com');
        $storeA = $this->makeStore($ownerA, 'Store A');
        $productA = $this->makeProduct($storeA, 'Only A');

        $ownerB = $this->merchantUser('b-ws@example.com');
        $storeB = $this->makeStore($ownerB, 'Store B');

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('products.show', $productA))
            ->assertNotFound();
    }

    public function test_custom_fields_render(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS2');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'With Custom',
            'slug' => 'with-custom-'.Str::random(6),
            'description' => null,
            'base_price' => 5,
            'sku' => 'CUS-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'custom_fields' => [
                    'supplier_code' => 'SUP-7788',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'CUS-1',
            'price' => 5,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Additional product details', false)
            ->assertSee('Supplier Code', false)
            ->assertSee('SUP-7788', false);
    }

    public function test_variant_additional_details_visible_in_workspace(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WSVar');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Variant Meta Product',
            'slug' => 'variant-meta-'.Str::random(6),
            'description' => null,
            'base_price' => 8,
            'sku' => 'VM-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => 'VM-1',
            'price' => 8,
            'stock' => 2,
            'stock_alert' => 0,
            'meta' => [
                'custom_fields' => [
                    'bundle' => '3-pack',
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Extra information for this variant', false)
            ->assertSee('Bundle', false)
            ->assertSee('3-pack', false);
    }

    public function test_workspace_empty_state_when_no_product_custom_fields(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS Empty CF');
        $product = $this->makeProduct($store, 'Plain Product');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('id="workspace-additional-details-heading"', false)
            ->assertSee('No additional details yet.', false);
    }

    public function test_workspace_main_section_separates_additional_details_from_advanced_import(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS Split');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Split Meta',
            'slug' => 'split-meta-'.Str::random(6),
            'description' => null,
            'base_price' => 4,
            'sku' => 'SPL-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'custom_fields' => [
                    'material' => 'denim',
                ],
                'import_extra' => [
                    'Unmapped Col' => 'import-only',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'SPL-1',
            'price' => 4,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product));

        $html = $response->assertOk()->getContent() ?: '';
        $response->assertSee('Additional product details', false)
            ->assertSee('Material', false)
            ->assertSee('denim', false)
            ->assertSee('Advanced imported data', false)
            ->assertSee('import-only', false);

        $mainPos = strpos($html, 'id="workspace-additional-details-heading"');
        $asidePos = strpos($html, 'id="workspace-advanced-imported-panel"');
        $this->assertNotFalse($mainPos);
        $this->assertNotFalse($asidePos);
        $this->assertLessThan($asidePos, $mainPos, 'Additional details should appear in the main column before the sidebar imported-data block.');
    }

    public function test_import_extra_section_present(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS3');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'With Extra',
            'slug' => 'with-extra-'.Str::random(6),
            'description' => null,
            'base_price' => 3,
            'sku' => 'EXT-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'import_extra' => [
                    'Legacy Column' => 'leftover value',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'EXT-1',
            'price' => 3,
            'stock' => 0,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Advanced imported data', false)
            ->assertSee('leftover value', false);
    }

    public function test_saving_from_workspace_edit_redirects_back_to_workspace(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS Save Ret');
        $product = $this->makeProduct($store, 'Save Return Item');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products.edit', $product))
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Save Return Item',
                'description' => null,
                'base_price' => 10,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 0,
                '_workspace_return_product_id' => (string) $product->id,
            ])
            ->assertRedirect(route('products.show', $product));
    }

    public function test_workspace_edit_uses_native_layout_without_duplicate_modal_title(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner, 'WS Edit Native');
        $product = $this->makeProduct($store, 'Native Edit Product');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('Native Edit Product', false)
            ->assertSee('Edit catalog item', false)
            ->assertSee('Additional details', false)
            ->assertDontSee('Catalog · Edit workspace', false);
    }

    public function test_manager_sees_edit_product_button(): void
    {
        $owner = $this->merchantUser('owner-mgr@example.com');
        $manager = $this->merchantUser('mgr@example.com');
        $store = $this->makeStore($owner, 'Mgr WS');
        $store->members()->syncWithoutDetaching([$manager->id => ['role' => Store::ROLE_MANAGER]]);
        $product = $this->makeProduct($store, 'Mgr Item');

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Edit product', false);
    }

    public function test_staff_does_not_see_edit_product_button(): void
    {
        $owner = $this->merchantUser('owner-st@example.com');
        $staff = $this->merchantUser('staff-st@example.com');
        $store = $this->makeStore($owner, 'Staff Store');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $product = $this->makeProduct($store, 'Staff Prod');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Staff Prod', false)
            ->assertDontSee('Edit product', false);
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
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 2,
            'stock_alert' => 0,
        ]);

        return $product;
    }
}
