<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_tag_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner-tags@example.com');
        $store = $this->createMemberStore($owner, 'Owner Tag Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('tags.store'), [
                'name' => 'Summer Drop',
                'slug' => 'summer-drop',
                'status' => 'active',
                'sort_order' => 0,
                '_open_tag_add_modal' => '1',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('tags', [
            'store_id' => $store->id,
            'name' => 'Summer Drop',
            'slug' => 'summer-drop',
        ]);
    }

    public function test_manager_can_create_a_tag_in_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner-mgr-tags@example.com');
        $manager = $this->createMerchantUser('manager-tags@example.com');
        $store = $this->createMemberStore($owner, 'Managed Tag Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('tags.store'), [
                'name' => 'Manager Tag',
                'slug' => 'manager-tag',
                'status' => 'inactive',
                'sort_order' => 2,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('tags', [
            'store_id' => $store->id,
            'slug' => 'manager-tag',
        ]);
    }

    public function test_staff_cannot_create_a_tag(): void
    {
        $owner = $this->createMerchantUser('owner-staff-tag@example.com');
        $staff = $this->createMerchantUser('staff-tag@example.com');
        $store = $this->createMemberStore($owner, 'Staff Tag Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('tags.store'), [
                'name' => 'Blocked',
                'slug' => 'blocked-tag',
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('tags', [
            'slug' => 'blocked-tag',
        ]);
    }

    public function test_staff_cannot_update_or_delete_a_tag(): void
    {
        $owner = $this->createMerchantUser('owner-staff-tag2@example.com');
        $staff = $this->createMerchantUser('staff-tag2@example.com');
        $store = $this->createMemberStore($owner, 'Staff Tag Store 2', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Staff Block',
            'slug' => 'staff-block-tag',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $upd = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('tags.update', $tag), [
                '_editing_tag_id' => $tag->id,
                'name' => 'Hijack',
                'slug' => 'hijack-tag',
                'status' => 'active',
            ]);

        $upd->assertForbidden();
        $tag->refresh();
        $this->assertSame('Staff Block', $tag->name);

        $del = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('tags.destroy', $tag));

        $del->assertForbidden();
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_slug_is_unique_per_store_not_globally(): void
    {
        $user = $this->createMerchantUser('slug-tag@example.com');
        $storeA = $this->createMemberStore($user, 'Tag Slug A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Tag Slug B', Store::ROLE_OWNER);

        Tag::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Shared',
            'slug' => 'shared',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('tags.store'), [
                'name' => 'Also Shared',
                'slug' => 'shared',
                'status' => 'active',
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('tags', [
            'store_id' => $storeB->id,
            'slug' => 'shared',
        ]);
    }

    public function test_products_page_can_filter_by_current_store_tag(): void
    {
        $user = $this->createMerchantUser('filter-tag@example.com');
        $store = $this->createMemberStore($user, 'Filter Tag Store', Store::ROLE_OWNER);

        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Filter Me',
            'slug' => 'filter-me',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $tagged = $this->createProduct($store, 'Tagged Product');
        $tagged->tags()->sync([$tag->id]);

        $plain = $this->createProduct($store, 'Plain Product');

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['tag' => $tag->id]));

        $response->assertOk();
        $response->assertSeeText($tagged->name);
        $response->assertDontSeeText($plain->name);
    }

    public function test_cross_store_tag_cannot_be_assigned_to_a_product(): void
    {
        $user = $this->createMerchantUser('cross-tag@example.com');
        $storeA = $this->createMemberStore($user, 'Cross Tag A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Cross Tag B', Store::ROLE_OWNER);

        $foreignTag = Tag::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Foreign',
            'slug' => 'foreign-tag',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('product.store'), [
                'name' => 'Bad Tags',
                'description' => 'x',
                'bulk_price' => 10,
                'sku' => 'BAD-TAG',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'tag_ids' => [(string) $foreignTag->id],
            ]);

        $response->assertSessionHasErrors('tag_ids.0');

        $this->assertDatabaseMissing('products', [
            'name' => 'Bad Tags',
        ]);
    }

    public function test_owner_or_manager_can_update_a_tag(): void
    {
        $owner = $this->createMerchantUser('owner-upd-tag@example.com');
        $manager = $this->createMerchantUser('mgr-upd-tag@example.com');
        $store = $this->createMemberStore($owner, 'Update Tag Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Old',
            'slug' => 'old-slug',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $ownerPatch = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('tags.update', $tag), [
                '_editing_tag_id' => $tag->id,
                'name' => 'Renamed Owner',
                'slug' => 'renamed-owner',
                'status' => 'inactive',
                'sort_order' => 3,
            ]);

        $ownerPatch->assertRedirect(route('products'));
        $tag->refresh();
        $this->assertSame('Renamed Owner', $tag->name);

        $tag2 = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Mgr Target',
            'slug' => 'mgr-target',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $mgrPatch = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('tags.update', $tag2), [
                '_editing_tag_id' => $tag2->id,
                'name' => 'Renamed Manager',
                'slug' => 'renamed-manager',
                'status' => 'active',
                'sort_order' => 1,
            ]);

        $mgrPatch->assertRedirect(route('products'));
        $tag2->refresh();
        $this->assertSame('Renamed Manager', $tag2->name);
    }

    public function test_deleting_a_tag_removes_pivot_rows_and_the_tag(): void
    {
        $owner = $this->createMerchantUser('del-tag@example.com');
        $store = $this->createMemberStore($owner, 'Delete Tag Store', Store::ROLE_OWNER);

        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $product = $this->createProduct($store, 'Labeled Product');
        $product->tags()->sync([$tag->id]);

        $this->assertDatabaseHas('product_tags', [
            'product_id' => $product->id,
            'tag_id' => $tag->id,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('tags.destroy', $tag));

        $response->assertRedirect(route('products'));

        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
        ]);

        $this->assertDatabaseMissing('product_tags', [
            'product_id' => $product->id,
            'tag_id' => $tag->id,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
        ]);
    }

    public function test_product_create_and_update_can_attach_tags_from_current_store_only(): void
    {
        $owner = $this->createMerchantUser('prod-tags@example.com');
        $store = $this->createMemberStore($owner, 'Prod Tag Store', Store::ROLE_OWNER);

        $t1 = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'One',
            'slug' => 'one',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $t2 = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Two',
            'slug' => 'two',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $create = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Tagged Create',
                'description' => 'd',
                'bulk_price' => 11,
                'sku' => 'TC-1',
                'product_type' => 'physical',
                'bulk_stock' => 2,
                'stock_alert' => 1,
                'tag_ids' => [(string) $t1->id, (string) $t2->id],
            ]);

        $create->assertRedirect(route('products'));

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Tagged Create')->firstOrFail();
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $product->tags()->pluck('id')->all());

        $update = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Tagged Updated',
                'description' => 'd2',
                'base_price' => 22,
                'sku' => 'TU-1',
                'product_type' => 'physical',
                'stock_alert' => 2,
                'tag_ids' => [(string) $t1->id],
            ]);

        $update->assertRedirect(route('products'));

        $product->refresh();
        $this->assertSame('Tagged Updated', $product->name);
        $this->assertEquals([(int) $t1->id], $product->tags()->pluck('id')->all());
    }

    public function test_tag_management_list_only_includes_current_store_tags(): void
    {
        $user = $this->createMerchantUser('mgmt-tags@example.com');
        $storeA = $this->createMemberStore($user, 'Mgmt A', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($user, 'Mgmt B', Store::ROLE_OWNER);

        Tag::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Only A',
            'slug' => 'only-a-tag',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        Tag::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Only B',
            'slug' => 'only-b-tag',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('products'));

        $response->assertOk();
        $response->assertViewHas('managementTags', function ($tags) {
            return $tags->contains(fn ($t) => $t->name === 'Only A')
                && ! $tags->contains(fn ($t) => $t->name === 'Only B');
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
