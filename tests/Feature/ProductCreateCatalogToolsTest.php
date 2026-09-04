<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCreateCatalogToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_product_page_includes_catalog_tools_and_empty_organization_fields(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.create'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('id="catalog-hub-brands-rows"', $html);
        $this->assertStringContainsString('id="catalog-hub-tags-rows"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="brands"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="tags"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="categories"', $html);
        $this->assertStringContainsString('data-catalog-tools-ajax="true"', $html);
        $this->assertStringContainsString('data-open-catalog-tools', $html);
        $this->assertStringContainsString('Create a category', $html);
        $this->assertStringContainsString('Create brand', $html);
        $this->assertStringContainsString('Create tag', $html);
        $this->assertStringContainsString('No categories yet', $html);
        $this->assertStringContainsString('id="catalog-edit-section-organization"', $html);
        $this->assertStringContainsString('data-pf-step="organization"', $html);
        $this->assertStringContainsString('data-pf-step="pricing"', $html);
        $this->assertStringContainsString('data-pf-step="media"', $html);
        $this->assertStringContainsString('data-pf-step="tax-shipping"', $html);
        $this->assertStringContainsString('Price, stock, groups, and variants', $html);
        $this->assertStringContainsString('Option groups and variants', $html);
        $this->assertStringContainsString('Add option group', $html);
        $this->assertStringContainsString('Manage variants below', $html);
        $this->assertStringNotContainsString('data-pf-step="variants"', $html);
        $this->assertLessThan(
            strpos($html, 'data-pf-step="media"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="essentials"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="pricing"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="media"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="organization"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="pricing"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="tax-shipping"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="organization"') ?: PHP_INT_MAX
        );
        $this->assertStringContainsString('data-pf-panel', $html);
        $this->assertStringContainsString('class="pf-section"', $html);
        $this->assertStringNotContainsString('min-h-0 flex-1 overflow-y-auto px-6 py-6', $html);
        $this->assertStringContainsString('id="productImageLightbox"', $html);
        $this->assertStringContainsString('Drag to change photo order', $html);
        $this->assertStringContainsString('name="image_order[]"', $html);
        $this->assertStringContainsString('data-product-create-guard', $html);
        $this->assertStringContainsString('id="productCreateLeaveModal"', $html);
        $this->assertStringContainsString('data-product-create-allow-leave', $html);
        $this->assertStringContainsString('data-required-flag-for="edit_product_name"', $html);
        $this->assertStringContainsString('data-required-flag-for="edit_product_price"', $html);
        $this->assertStringContainsString('ui-modal-shell--alert', $html);
        $this->assertStringContainsString('Unsaved product', $html);
    }

    public function test_category_json_create_returns_assignable_item_for_product_form(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('categories.store'), [
                'name' => 'Apparel',
                'status' => 'active',
                '_open_category_add_modal' => '1',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('kind', 'category')
            ->assertJsonPath('action', 'created')
            ->assertJsonPath('item.name', 'Apparel')
            ->assertJsonPath('item.assignable', true);

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Apparel',
            'status' => 'active',
        ]);
    }

    public function test_brand_and_tag_json_create_from_add_product_context(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('brands.store'), [
                'name' => 'Northwind',
                'status' => 'active',
                'featured' => '0',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'brand')
            ->assertJsonPath('item.name', 'Northwind')
            ->assertJsonPath('item.update_url', route('brands.update', Brand::query()->where('name', 'Northwind')->firstOrFail()))
            ->assertJsonPath('item.destroy_url', route('brands.destroy', Brand::query()->where('name', 'Northwind')->firstOrFail()));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tags.store'), [
                'name' => 'Featured',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'tag')
            ->assertJsonPath('item.name', 'Featured')
            ->assertJsonPath('item.update_url', route('tags.update', Tag::query()->where('name', 'Featured')->firstOrFail()))
            ->assertJsonPath('item.destroy_url', route('tags.destroy', Tag::query()->where('name', 'Featured')->firstOrFail()));
    }

    public function test_html_category_create_from_add_product_returns_to_add_product(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Home',
                'status' => 'active',
                '_catalog_return' => 'products.create',
            ])
            ->assertRedirect(route('products.create', ['step' => 'organization']));

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Home',
        ]);
    }

    public function test_html_category_create_without_return_still_lands_on_products_list(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Garden',
                'status' => 'active',
            ])
            ->assertRedirect(route('products'));
    }

    public function test_inactive_category_is_not_assignable_on_the_product_form(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('categories.store'), [
                'name' => 'Hidden group',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('item.assignable', false);
    }

    public function test_add_product_page_lists_existing_categories_for_assignment(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Shoes',
            'slug' => 'shoes',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Stride',
            'slug' => 'stride',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Sale',
            'slug' => 'sale',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Shoes', false)
            ->assertSee('Stride', false)
            ->assertSee('Sale', false)
            ->assertSee('id="edit_product_category_ids"', false);
    }

    private function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $owner, string $name = 'Create Catalog Store'): Store
    {
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'create-catalog-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }
}
