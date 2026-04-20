<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_category_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner-cat@example.com');
        $store = $this->createMemberStore($owner, 'Owner Cat Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'status' => 'active',
                'sort_order' => 0,
                '_open_category_add_modal' => '1',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Electronics',
            'slug' => 'electronics',
            'status' => 'active',
        ]);
    }

    public function test_manager_can_create_a_category_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner-mgr-cat@example.com');
        $manager = $this->createMerchantUser('manager-cat@example.com');
        $store = $this->createMemberStore($owner, 'Managed Cat Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Apparel',
                'slug' => 'apparel',
                'status' => 'inactive',
                'sort_order' => 2,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'slug' => 'apparel',
        ]);
    }

    public function test_staff_cannot_create_a_category(): void
    {
        $owner = $this->createMerchantUser('owner-staff-cat@example.com');
        $staff = $this->createMerchantUser('staff-cat@example.com');
        $store = $this->createMemberStore($owner, 'Staff Cat Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Blocked',
                'slug' => 'blocked-cat',
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('categories', [
            'slug' => 'blocked-cat',
        ]);
    }

    public function test_staff_cannot_update_or_delete_a_category(): void
    {
        $owner = $this->createMerchantUser('owner-staff-cat2@example.com');
        $staff = $this->createMerchantUser('staff-cat2@example.com');
        $store = $this->createMemberStore($owner, 'Staff Cat Store 2', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Staff Block',
            'slug' => 'staff-block-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $upd = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('categories.update', $category), [
                '_editing_category_id' => (string) $category->id,
                'name' => 'Hijack',
                'slug' => 'hijack-cat',
                'status' => 'active',
            ]);

        $upd->assertForbidden();
        $category->refresh();
        $this->assertSame('Staff Block', $category->name);

        $del = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('categories.destroy', $category));

        $del->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_category_slug_is_unique_per_store_not_globally(): void
    {
        $user = $this->createMerchantUser('slug-shared@example.com');
        $storeA = $this->createMemberStore($user, 'Slug Store A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Slug Store B', Store::ROLE_OWNER);

        $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('categories.store'), [
                'name' => 'Shared Name',
                'slug' => 'shared-slug',
                'status' => 'active',
            ])
            ->assertRedirect(route('products'));

        $this->actingAs($user)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('categories.store'), [
                'name' => 'Shared Name B',
                'slug' => 'shared-slug',
                'status' => 'active',
            ])
            ->assertRedirect(route('products'));

        $this->assertDatabaseHas('categories', [
            'store_id' => $storeA->id,
            'slug' => 'shared-slug',
        ]);
        $this->assertDatabaseHas('categories', [
            'store_id' => $storeB->id,
            'slug' => 'shared-slug',
        ]);
    }

    public function test_products_page_can_filter_by_taxonomy_category_for_current_store(): void
    {
        $user = $this->createMerchantUser('filter-cat@example.com');
        $store = $this->createMemberStore($user, 'Filter Cat Store', Store::ROLE_OWNER);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Taxonomy Filter',
            'slug' => 'taxonomy-filter',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $with = $this->createProduct($store, 'Product In Category');
        $with->categories()->sync([$category->id]);

        $without = $this->createProduct($store, 'Product Outside Category');

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['category' => $category->id]));

        $response->assertOk();
        $response->assertSeeText('Product In Category');
        $response->assertDontSeeText('Product Outside Category');
        $response->assertViewHas('activeTaxonomyCategoryFilter', fn ($c) => $c && $c->is($category));
    }

    public function test_cross_store_category_cannot_be_assigned_to_a_product(): void
    {
        $user = $this->createMerchantUser('cross-cat@example.com');
        $storeA = $this->createMemberStore($user, 'Cross Cat A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Cross Cat B', Store::ROLE_OWNER);

        $foreign = Category::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Foreign',
            'slug' => 'foreign-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('product.store'), [
                'name' => 'Bad Categories',
                'description' => 'x',
                'bulk_price' => 10,
                'sku' => 'BAD-CAT',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'category_ids' => [(string) $foreign->id],
            ]);

        $response->assertSessionHasErrors('category_ids.0');

        $this->assertDatabaseMissing('products', [
            'name' => 'Bad Categories',
        ]);
    }

    public function test_owner_or_manager_can_update_a_category(): void
    {
        $owner = $this->createMerchantUser('owner-upd-cat@example.com');
        $manager = $this->createMerchantUser('mgr-upd-cat@example.com');
        $store = $this->createMemberStore($owner, 'Update Cat Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Old',
            'slug' => 'old-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $ownerPatch = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('categories.update', $category), [
                '_editing_category_id' => (string) $category->id,
                'name' => 'Renamed Owner',
                'slug' => 'renamed-owner-cat',
                'status' => 'inactive',
                'sort_order' => 3,
            ]);

        $ownerPatch->assertRedirect(route('products'));
        $category->refresh();
        $this->assertSame('Renamed Owner', $category->name);

        $category2 = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Mgr Target',
            'slug' => 'mgr-target-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $mgrPatch = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('categories.update', $category2), [
                '_editing_category_id' => (string) $category2->id,
                'name' => 'Renamed Manager',
                'slug' => 'renamed-manager-cat',
                'status' => 'active',
                'sort_order' => 1,
            ]);

        $mgrPatch->assertRedirect(route('products'));
        $category2->refresh();
        $this->assertSame('Renamed Manager', $category2->name);
    }

    public function test_delete_is_blocked_when_category_is_still_assigned_to_products(): void
    {
        $owner = $this->createMerchantUser('del-block-cat@example.com');
        $store = $this->createMemberStore($owner, 'Del Block Cat Store', Store::ROLE_OWNER);

        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'In Use',
            'slug' => 'in-use-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $product = $this->createProduct($store, 'Linked For Category');
        $product->categories()->sync([$category->id]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('products'));
        $response->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_product_create_and_update_can_attach_categories_from_current_store_only(): void
    {
        $owner = $this->createMerchantUser('prod-cats@example.com');
        $store = $this->createMemberStore($owner, 'Prod Cat Store', Store::ROLE_OWNER);

        $c1 = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'One',
            'slug' => 'one-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $c2 = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Two',
            'slug' => 'two-cat',
            'parent_id' => null,
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $create = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Categorized Create',
                'description' => 'd',
                'bulk_price' => 11,
                'sku' => 'CC-1',
                'product_type' => 'physical',
                'bulk_stock' => 2,
                'stock_alert' => 1,
                'category_ids' => [(string) $c1->id, (string) $c2->id],
            ]);

        $create->assertRedirect(route('products'));

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Categorized Create')->firstOrFail();
        $this->assertEqualsCanonicalizing([$c1->id, $c2->id], $product->categories()->pluck('id')->all());

        $update = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Categorized Updated',
                'description' => 'd2',
                'base_price' => 22,
                'sku' => 'CU-1',
                'product_type' => 'digital',
                'stock_alert' => 2,
                'category_ids' => [(string) $c1->id],
            ]);

        $update->assertRedirect(route('products'));

        $product->refresh();
        $this->assertSame('Categorized Updated', $product->name);
        $this->assertSame('digital', $product->product_type);
        $this->assertEquals([(int) $c1->id], $product->categories()->pluck('id')->all());
    }

    public function test_product_type_and_taxonomy_category_are_independent_on_products_page(): void
    {
        $user = $this->createMerchantUser('ptype-cat@example.com');
        $store = $this->createMemberStore($user, 'Ptype Cat Store', Store::ROLE_OWNER);

        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Clothing Taxonomy',
            'slug' => 'clothing-tax',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $physicalInCat = $this->createProduct($store, 'Physical In Cat');
        $physicalInCat->update(['product_type' => 'physical']);
        $physicalInCat->categories()->sync([$cat->id]);

        $physicalPlain = $this->createProduct($store, 'Physical Plain');
        $physicalPlain->update(['product_type' => 'physical']);

        $digitalInCat = $this->createProduct($store, 'Digital In Cat');
        $digitalInCat->update(['product_type' => 'digital']);
        $digitalInCat->categories()->sync([$cat->id]);

        $byCategory = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['category' => $cat->id]));

        $byCategory->assertOk();
        $byCategory->assertSeeText('Physical In Cat');
        $byCategory->assertSeeText('Digital In Cat');
        $byCategory->assertDontSeeText('Physical Plain');

        $byType = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['product_type' => 'digital']));

        $byType->assertOk();
        $byType->assertSeeText('Digital In Cat');
        $byType->assertDontSeeText('Physical In Cat');
        $byType->assertDontSeeText('Physical Plain');
    }

    public function test_products_page_passes_management_categories_for_current_store(): void
    {
        $user = $this->createMerchantUser('mgmt-cat@example.com');
        $store = $this->createMemberStore($user, 'Mgmt Cat Store', Store::ROLE_OWNER);

        Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Listed Category',
            'slug' => 'listed-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertViewHas('managementCategories', function ($categories) {
            return $categories->contains(fn ($c) => $c->name === 'Listed Category');
        });
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name, string $role = Store::ROLE_OWNER): Store
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

        $store->members()->attach($user->id, ['role' => $role]);

        return $store;
    }

    protected function attachMember(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
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
