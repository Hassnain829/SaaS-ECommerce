<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_brand_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner-brands@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('brands.store'), [
                'name' => 'Acme Co',
                'slug' => 'acme-co',
                'status' => 'active',
                'sort_order' => 0,
                'featured' => '0',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('brands', [
            'store_id' => $store->id,
            'name' => 'Acme Co',
            'slug' => 'acme-co',
        ]);
    }

    public function test_manager_can_create_a_brand_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner2@example.com');
        $manager = $this->createMerchantUser('manager2@example.com');
        $store = $this->createMemberStore($owner, 'Managed Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('brands.store'), [
                'name' => 'Manager Brand',
                'slug' => 'manager-brand',
                'status' => 'draft',
                'sort_order' => 2,
                'featured' => '1',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('brands', [
            'store_id' => $store->id,
            'slug' => 'manager-brand',
        ]);
    }

    public function test_staff_cannot_create_a_brand(): void
    {
        $owner = $this->createMerchantUser('owner3@example.com');
        $staff = $this->createMerchantUser('staff3@example.com');
        $store = $this->createMemberStore($owner, 'Staff Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('brands.store'), [
                'name' => 'Blocked',
                'slug' => 'blocked',
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('brands', [
            'slug' => 'blocked',
        ]);
    }

    public function test_staff_cannot_update_a_brand(): void
    {
        $owner = $this->createMerchantUser('owner-staff-upd@example.com');
        $staff = $this->createMerchantUser('staff-upd@example.com');
        $store = $this->createMemberStore($owner, 'Staff Upd Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Staff Block',
            'slug' => 'staff-block-upd',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('brands.update', $brand), [
                '_editing_brand_id' => $brand->id,
                'name' => 'Hijack',
                'slug' => 'hijack',
                'status' => 'active',
            ]);

        $response->assertForbidden();
        $brand->refresh();
        $this->assertSame('Staff Block', $brand->name);
    }

    public function test_staff_cannot_delete_a_brand(): void
    {
        $owner = $this->createMerchantUser('owner-staff-del@example.com');
        $staff = $this->createMerchantUser('staff-del@example.com');
        $store = $this->createMemberStore($owner, 'Staff Del Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Staff Del Block',
            'slug' => 'staff-block-del',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('brands.destroy', $brand));

        $response->assertForbidden();
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'deleted_at' => null,
        ]);
    }

    public function test_brand_list_only_shows_brands_for_the_active_store(): void
    {
        $user = $this->createMerchantUser('multi@example.com');
        $storeA = $this->createMemberStore($user, 'Store A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Store B', Store::ROLE_OWNER);

        Brand::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Only A',
            'slug' => 'only-a',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        Brand::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Only B',
            'slug' => 'only-b',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertViewHas('managementBrands', function ($brands) {
            return $brands->contains(fn ($b) => $b->name === 'Only A')
                && ! $brands->contains(fn ($b) => $b->name === 'Only B');
        });
    }

    public function test_duplicate_slug_in_the_same_store_fails_validation(): void
    {
        $owner = $this->createMerchantUser('dup-slug@example.com');
        $store = $this->createMemberStore($owner, 'Dup Slug Store', Store::ROLE_OWNER);

        Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Taken',
            'slug' => 'taken-slug',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products'))
            ->post(route('brands.store'), [
                'name' => 'Another',
                'slug' => 'taken-slug',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('products'));
        $response->assertSessionHasErrors('slug');
    }

    public function test_slug_is_unique_per_store_not_globally(): void
    {
        $user = $this->createMerchantUser('slug@example.com');
        $storeA = $this->createMemberStore($user, 'Slug Store A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Slug Store B', Store::ROLE_OWNER);

        Brand::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Shared Slug',
            'slug' => 'shared',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('brands.store'), [
                'name' => 'Another Shared',
                'slug' => 'shared',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('brands', [
            'store_id' => $storeB->id,
            'slug' => 'shared',
        ]);
    }

    public function test_user_cannot_update_a_brand_outside_the_current_store(): void
    {
        $user = $this->createMerchantUser('cross@example.com');
        $storeA = $this->createMemberStore($user, 'Cross A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Cross B', Store::ROLE_OWNER);

        $foreignBrand = Brand::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->patch(route('brands.update', $foreignBrand), [
                '_editing_brand_id' => $foreignBrand->id,
                'name' => 'Hijacked',
                'slug' => 'hijacked',
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('brands', [
            'id' => $foreignBrand->id,
            'name' => 'Foreign',
            'slug' => 'foreign',
        ]);
    }

    public function test_owner_or_manager_can_update_a_current_store_brand(): void
    {
        $owner = $this->createMerchantUser('upd-owner@example.com');
        $manager = $this->createMerchantUser('upd-manager@example.com');
        $store = $this->createMemberStore($owner, 'Update Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Original',
            'slug' => 'original',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $ownerResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('brands.update', $brand), [
                '_editing_brand_id' => $brand->id,
                'name' => 'Owner Renamed',
                'slug' => 'owner-renamed',
                'status' => 'inactive',
                'sort_order' => 5,
                'featured' => '0',
            ]);

        $ownerResponse->assertRedirect(route('products'));

        $brand->refresh();
        $this->assertSame('Owner Renamed', $brand->name);
        $this->assertSame('owner-renamed', $brand->slug);

        $managerResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('brands.update', $brand), [
                '_editing_brand_id' => $brand->id,
                'name' => 'Manager Renamed',
                'slug' => 'manager-renamed',
                'status' => 'draft',
                'sort_order' => 1,
                'featured' => '1',
            ]);

        $managerResponse->assertRedirect(route('products'));

        $brand->refresh();
        $this->assertSame('Manager Renamed', $brand->name);
        $this->assertTrue($brand->featured);
    }

    public function test_delete_is_blocked_when_current_store_products_still_reference_the_brand(): void
    {
        $owner = $this->createMerchantUser('del-block@example.com');
        $store = $this->createMemberStore($owner, 'Del Block Store', Store::ROLE_OWNER);

        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'In Use',
            'slug' => 'in-use',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        Product::query()->create([
            'store_id' => $store->id,
            'brand_id' => $brand->id,
            'name' => 'Linked Product',
            'slug' => 'linked-product-' . uniqid(),
            'description' => null,
            'base_price' => 10,
            'sku' => null,
            'product_type' => 'physical',
            'status' => true,
            'meta' => null,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('brands.destroy', $brand));

        $response->assertRedirect(route('products'));
        $response->assertSessionHasErrors('brand');

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'deleted_at' => null,
        ]);
    }

    public function test_owner_or_manager_can_delete_an_unused_current_store_brand(): void
    {
        $owner = $this->createMerchantUser('del-ok-owner@example.com');
        $manager = $this->createMerchantUser('del-ok-mgr@example.com');
        $store = $this->createMemberStore($owner, 'Del OK Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $brandOwner = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Unused Owner',
            'slug' => 'unused-owner',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $ownerDel = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('brands.destroy', $brandOwner));

        $ownerDel->assertRedirect(route('products'));
        $this->assertSoftDeleted('brands', ['id' => $brandOwner->id]);

        $brandManager = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Unused Manager',
            'slug' => 'unused-manager',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $mgrDel = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('brands.destroy', $brandManager));

        $mgrDel->assertRedirect(route('products'));
        $this->assertSoftDeleted('brands', ['id' => $brandManager->id]);
    }

    public function test_products_page_passes_catalog_brands_for_current_store(): void
    {
        $user = $this->createMerchantUser('catalog-br@example.com');
        $store = $this->createMemberStore($user, 'Catalog Store', Store::ROLE_OWNER);
        Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Listed Brand',
            'slug' => 'listed-brand',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertViewHas('catalogBrands', function ($brands) {
            return $brands->contains(fn ($b) => $b->name === 'Listed Brand');
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
}
