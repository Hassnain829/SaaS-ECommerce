<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMemberFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_new_member_to_the_current_store(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'New Staff Member',
                'email' => 'new-staff@example.com',
                'role' => Store::ROLE_STAFF,
            ]);

        $response->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'new-staff@example.com')->first();

        $this->assertNotNull($member);
        $this->assertSame('New Staff Member', $member->name);
        $this->assertTrue($member->hasRole('user'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_STAFF,
        ]);
    }

    public function test_manager_can_add_staff_and_manager_but_not_owner(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $manager = $this->createMerchantUser('manager@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $staffResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Staff Invite',
                'email' => 'staff-invite@example.com',
                'role' => Store::ROLE_STAFF,
            ]);

        $staffResponse->assertRedirect(route('team-members.index'));

        $managerInviteResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Manager Invite',
                'email' => 'manager-invite@example.com',
                'role' => Store::ROLE_MANAGER,
            ]);

        $managerInviteResponse->assertRedirect(route('team-members.index'));

        $ownerInviteResponse = $this->actingAs($manager)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Owner Invite',
                'email' => 'owner-invite@example.com',
                'role' => Store::ROLE_OWNER,
            ]);

        $ownerInviteResponse->assertRedirect(route('team-members.index'));
        $ownerInviteResponse->assertSessionHasErrors('role');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => User::query()->where('email', 'staff-invite@example.com')->value('id'),
            'role' => Store::ROLE_STAFF,
        ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => User::query()->where('email', 'manager-invite@example.com')->value('id'),
            'role' => Store::ROLE_MANAGER,
        ]);

        $this->assertDatabaseMissing('store_user', [
            'store_id' => $store->id,
            'user_id' => User::query()->where('email', 'owner-invite@example.com')->value('id'),
        ]);
    }

    public function test_staff_cannot_add_members(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $staff = $this->createMerchantUser('staff@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $response = $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Blocked Invite',
                'email' => 'blocked@example.com',
                'role' => Store::ROLE_STAFF,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_owner_can_change_member_role(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('member@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $member, Store::ROLE_STAFF);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $member->id]), [
                'role' => Store::ROLE_MANAGER,
            ]);

        $response->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_MANAGER,
        ]);
    }

    public function test_manager_cannot_modify_owner_or_promote_someone_to_owner(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $manager = $this->createMerchantUser('manager@example.com');
        $staff = $this->createMerchantUser('staff@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $ownerResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $owner->id]), [
                'role' => Store::ROLE_MANAGER,
            ]);

        $ownerResponse->assertForbidden();

        $promoteResponse = $this->actingAs($manager)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $staff->id]), [
                'role' => Store::ROLE_OWNER,
            ]);

        $promoteResponse->assertRedirect(route('team-members.index'));
        $promoteResponse->assertSessionHasErrors('role');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $staff->id,
            'role' => Store::ROLE_STAFF,
        ]);
    }

    public function test_owner_cannot_remove_the_last_owner(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $owner->id]), [
                'role' => Store::ROLE_MANAGER,
            ]);

        $response->assertRedirect(route('team-members.index'));
        $response->assertSessionHasErrors('role');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_manager_can_remove_staff_only(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $manager = $this->createMerchantUser('manager@example.com');
        $staff = $this->createMerchantUser('staff@example.com');
        $peerManager = $this->createMerchantUser('peer-manager@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);
        $this->attachMember($store, $peerManager, Store::ROLE_MANAGER);

        $staffResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $staff->id]));

        $staffResponse->assertRedirect(route('team-members.index'));

        $managerResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $peerManager->id]));

        $managerResponse->assertForbidden();

        $ownerResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $owner->id]));

        $ownerResponse->assertForbidden();

        $this->assertDatabaseMissing('store_user', [
            'store_id' => $store->id,
            'user_id' => $staff->id,
        ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $peerManager->id,
            'role' => Store::ROLE_MANAGER,
        ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_removing_a_member_detaches_only_the_store_membership_not_the_user_account(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('member@example.com');
        $alphaStore = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $betaStore = $this->createMemberStore($owner, 'Beta Store', Store::ROLE_OWNER);
        $this->attachMember($alphaStore, $member, Store::ROLE_STAFF);
        $this->attachMember($betaStore, $member, Store::ROLE_STAFF);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->delete(route('team-members.destroy', ['user' => $member->id]));

        $response->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'email' => 'member@example.com',
        ]);

        $this->assertDatabaseMissing('store_user', [
            'store_id' => $alphaStore->id,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $betaStore->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_STAFF,
        ]);
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
