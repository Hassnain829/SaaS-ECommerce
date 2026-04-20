<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_perform_owner_only_store_action(): void
    {
        $owner = $this->createUserWithGlobalRole('user', 'owner@example.com');
        $store = $this->createStore($owner, 'Owner Store');
        $this->attachMember($store, $owner, Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('store.destroy', ['storeId' => $store->id]));

        $response->assertRedirect(route('store-management'));

        $this->assertDatabaseMissing('stores', [
            'id' => $store->id,
        ]);
    }

    public function test_manager_can_perform_allowed_product_action(): void
    {
        $owner = $this->createUserWithGlobalRole('user', 'owner@example.com');
        $manager = $this->createUserWithGlobalRole('user', 'manager@example.com');
        $store = $this->createStore($owner, 'Manager Store');

        $this->attachMember($store, $owner, Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Manager Created Product',
                'description' => 'Created by manager.',
                'bulk_price' => 50,
                'sku' => 'MANAGER-001',
                'product_type' => 'physical',
                'bulk_stock' => 8,
                'stock_alert' => 2,
            ]);

        $response->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'name' => 'Manager Created Product',
            'store_id' => $store->id,
        ]);
    }

    public function test_staff_is_blocked_from_restricted_management_and_destructive_actions(): void
    {
        $owner = $this->createUserWithGlobalRole('user', 'owner@example.com');
        $staff = $this->createUserWithGlobalRole('user', 'staff@example.com');
        $store = $this->createStore($owner, 'Staff Store');
        $product = $this->createProduct($store, 'Protected Product');

        $this->attachMember($store, $owner, Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $updateResponse = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Illegal Store Update',
                'primary_market' => 'Global Market',
                'address' => 'Blocked Address',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
            ]);

        $updateResponse->assertForbidden();

        $deleteResponse = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.destroy', ['productId' => $product->id]));

        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Staff Store',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Protected Product',
        ]);
    }

    public function test_non_member_is_blocked_from_store_protected_action(): void
    {
        $owner = $this->createUserWithGlobalRole('user', 'owner@example.com');
        $outsider = $this->createUserWithGlobalRole('user', 'outsider@example.com');
        $store = $this->createStore($owner, 'Protected Store');

        $this->attachMember($store, $owner, Store::ROLE_OWNER);

        $response = $this->actingAs($outsider)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Outsider Update',
                'primary_market' => 'Global Market',
                'address' => 'Blocked Address',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
            ]);

        $response->assertNotFound();
    }

    public function test_global_admin_logic_remains_separate_from_tenant_store_role_logic(): void
    {
        $admin = $this->createUserWithGlobalRole('admin', 'admin@example.com');

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('products'))
            ->assertForbidden();
    }

    protected function createUserWithGlobalRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    protected function createStore(User $owner, string $name): Store
    {
        return Store::create([
            'user_id' => $owner->id,
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
