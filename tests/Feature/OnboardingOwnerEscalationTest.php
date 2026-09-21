<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingOwnerEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_member_cannot_become_owner_through_legacy_add_product_onboarding(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(Store::ROLE_MEMBER, ['products.view']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store.add-product', ['storeId' => $store->id]))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->post(route('onboarding-StoreDetails-1.store'), $this->storeDetailsPayload('Hijacked Store'))
            ->assertForbidden();

        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
        $this->assertDatabaseMissing('stores', ['name' => 'Hijacked Store']);
        $this->assertSame('Restricted Store', $store->fresh()->name);
    }

    public function test_manager_cannot_promote_themselves_to_owner_via_store_details_onboarding(): void
    {
        [$owner, $manager, $store] = $this->storeWithMember(Store::ROLE_MANAGER);

        $this->assertTrue($manager->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));

        $this->actingAs($manager)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->get(route('store.add-product', ['storeId' => $store->id]))
            ->assertRedirect(route('products.create'))
            ->assertSessionMissing('onboarding_store_id');

        $this->actingAs($manager)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->post(route('onboarding-StoreDetails-1.store'), $this->storeDetailsPayload('Manager Takeover'))
            ->assertForbidden();

        $this->assertMemberWasNotPromoted($store, $manager, Store::ROLE_MANAGER);
        $this->assertSame('Restricted Store', $store->fresh()->name);
    }

    public function test_add_product_does_not_bind_onboarding_store_or_change_role(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(Store::ROLE_MEMBER, ['products.edit']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store.add-product', ['storeId' => $store->id]))
            ->assertRedirect(route('products.create'))
            ->assertSessionMissing('onboarding_store_id');

        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
    }

    public function test_add_product_requires_the_current_store(): void
    {
        $owner = $this->merchant('owner-two-stores@example.com');
        $member = $this->merchant('member-two-stores@example.com');
        $storeA = $this->store($owner, 'Store A');
        $storeB = $this->store($owner, 'Store B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);
        $this->grant($storeA, $member, ['products.edit']);
        $this->grant($storeB, $member, ['products.edit']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('store.add-product', ['storeId' => $storeB->id]))
            ->assertNotFound();

        $this->assertMemberWasNotPromoted($storeB, $member, Store::ROLE_MEMBER);
    }

    public function test_updating_an_existing_store_does_not_assign_owner(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(Store::ROLE_MEMBER, ['products.edit']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Should Not Update',
                'primary_market' => 'Global Market',
                'address' => 'Blocked Address',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('store.update', ['storeId' => $store->id]), [
                'name' => 'Owner Updated Store',
                'primary_market' => 'Global Market',
                'address' => '10 Merchant Way',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'category' => 'physical',
                'redirect_to' => 'store-management',
            ])
            ->assertRedirect(route('store-management'));

        $this->assertSame('Owner Updated Store', $store->fresh()->name);
        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_suspended_member_cannot_revive_access_by_becoming_owner(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(
            Store::ROLE_MEMBER,
            ['products.edit', 'products.view'],
            StoreUser::STATUS_SUSPENDED,
        );

        $this->actingAs($member)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->get(route('store.add-product', ['storeId' => $store->id]))
            ->assertRedirect(route('store-management'))
            ->assertSessionMissing('onboarding_store_id');

        $this->actingAs($member)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->post(route('onboarding-StoreDetails-1.store'), $this->storeDetailsPayload('Revived Store'))
            ->assertForbidden();

        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreUser::STATUS_SUSPENDED,
        ]);
        $this->assertFalse($member->fresh()->hasStorePermission($store, 'products.edit'));
    }

    public function test_switching_stores_clears_onboarding_store_session(): void
    {
        $owner = $this->merchant('switch-owner@example.com');
        $storeA = $this->store($owner, 'Switch A');
        $storeB = $this->store($owner, 'Switch B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $storeA->id,
                'onboarding_store_id' => $storeA->id,
                'onboarding_store_draft' => ['name' => 'Switch A'],
            ])
            ->post(route('current-store.update'), ['store_id' => $storeB->id])
            ->assertSessionHas('current_store_id', $storeB->id)
            ->assertSessionMissing('onboarding_store_id')
            ->assertSessionMissing('onboarding_store_draft');
    }

    public function test_creating_a_store_still_assigns_owner_once(): void
    {
        $owner = $this->merchant('create-owner@example.com');

        $this->actingAs($owner)
            ->post(route('onboarding-StoreDetails-1.store'), $this->storeDetailsPayload('Brand New Store', mode: 'create'))
            ->assertRedirect(route('onboarding-Step2-AddProductVariations'))
            ->assertSessionHas('onboarding_store_id');

        $store = Store::query()->where('name', 'Brand New Store')->firstOrFail();

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
            'status' => StoreUser::STATUS_ACTIVE,
        ]);
        $this->assertSame($owner->id, (int) $store->user_id);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->post(route('onboarding-StoreDetails-1.store'), $this->storeDetailsPayload('Brand New Store Updated'))
            ->assertRedirect(route('onboarding-Step2-AddProductVariations'));

        $this->assertSame('Brand New Store Updated', $store->fresh()->name);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
        $this->assertSame(1, $store->members()->count());
    }

    public function test_product_onboarding_is_blocked_without_products_edit(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(Store::ROLE_MEMBER, ['products.view', 'orders.view']);

        $this->actingAs($member)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->get(route('onboarding-Step2-AddProductVariations'))
            ->assertForbidden();

        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
    }

    public function test_store_ready_completion_is_blocked_without_settings_manage(): void
    {
        [$owner, $member, $store] = $this->storeWithMember(Store::ROLE_MEMBER, ['products.edit']);

        $this->actingAs($member)
            ->withSession([
                'current_store_id' => $store->id,
                'onboarding_store_id' => $store->id,
            ])
            ->post(route('onboarding_StoreReady.complete'), ['dont_show_again' => '1'])
            ->assertForbidden();

        $this->assertFalse((bool) $store->fresh()->onboarding_completed);
        $this->assertMemberWasNotPromoted($store, $member, Store::ROLE_MEMBER);
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: User, 1: User, 2: Store}
     */
    private function storeWithMember(string $role, array $permissions = [], string $status = StoreUser::STATUS_ACTIVE): array
    {
        $owner = $this->merchant('escalation-owner@example.com');
        $member = $this->merchant('escalation-member@example.com');
        $store = $this->store($owner, 'Restricted Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        if ($permissions === []) {
            $this->attach($store, $member, $role, $status);
        } else {
            $this->grant($store, $member, $permissions, $role, $status);
        }

        return [$owner, $member, $store];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(
        Store $store,
        User $user,
        array $permissions,
        string $role = Store::ROLE_MEMBER,
        string $status = StoreUser::STATUS_ACTIVE,
    ): void {
        $this->attach($store, $user, $role, $status);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $user,
            $permissions,
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            $status,
        );
    }

    private function attach(Store $store, User $user, string $role, ?string $status = null): void
    {
        $pivot = ['role' => $role];
        if ($status !== null) {
            $pivot['status'] = $status;
        }

        $store->members()->syncWithoutDetaching([
            $user->id => $pivot,
        ]);
    }

    private function assertMemberWasNotPromoted(Store $store, User $member, string $expectedRole): void
    {
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => $expectedRole,
        ]);
        $this->assertFalse($member->fresh()->hasStoreRole($store, Store::ROLE_OWNER));
    }

    /**
     * @return array<string, mixed>
     */
    private function storeDetailsPayload(string $name, string $mode = 'edit'): array
    {
        return [
            'mode' => $mode,
            'name' => $name,
            'primary_market' => 'United States',
            'address' => '10 First Street',
            'currency' => 'USD',
            'timezone' => 'America/Chicago',
            'category' => 'physical',
            'business_models' => ['Physical Goods'],
        ];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
    }
}
