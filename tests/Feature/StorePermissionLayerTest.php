<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePermissionLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_roles_resolve_to_explicit_permissions(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $outsider = $this->merchant('outsider@example.com');
        $store = $this->store($owner);

        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->assertTrue($owner->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::BILLING_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));

        $this->assertTrue($manager->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::IMPORTS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::CUSTOMERS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::SETTINGS_VIEW));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::DEVELOPER_API_VIEW));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::SECURITY_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::TEAM_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::BILLING_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::BILLING_MANAGE));

        $this->assertTrue($staff->hasStorePermission($store, StorePermission::CATALOG_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::ORDERS_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::CUSTOMERS_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::SETTINGS_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::IMPORTS_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::IMPORTS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::CUSTOMERS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::TEAM_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::DEVELOPER_API_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SECURITY_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::BILLING_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::BILLING_MANAGE));

        $this->assertFalse($outsider->hasStorePermission($store, StorePermission::CATALOG_VIEW));
    }

    public function test_permission_middleware_blocks_staff_catalog_mutations_but_allows_manager(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $payload = [
            'name' => 'Permission Test Product',
            'description' => 'Created through permission middleware.',
            'bulk_price' => 25,
            'sku' => 'PERM-001',
            'product_type' => 'physical',
            'bulk_stock' => 5,
            'stock_alert' => 1,
        ];

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload)
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload)
            ->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'sku' => 'PERM-001',
        ]);
    }

    public function test_sensitive_routes_follow_the_final_permission_matrix(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('security'))
            ->assertOk();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('security'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('billingSubscription'))
            ->assertForbidden();
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Permission Store',
            'slug' => 'permission-store-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }
}
