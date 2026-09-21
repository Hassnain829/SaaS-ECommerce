<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use App\Notifications\StoreOwnershipTransferredNotification;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamPermissionP2HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_manager_cannot_suspend_peer_with_stronger_permissions(): void
    {
        $owner = $this->merchant('p2-owner@example.com');
        $lead = $this->merchant('p2-lead@example.com');
        $powerful = $this->merchant('p2-powerful@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $lead, [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage']);
        $this->grant($store, $powerful, StoreMemberAccess::presets()[StoreMemberAccess::PRESET_FULL_OPERATIONAL]);

        $this->actingAs($lead)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.status', ['user' => $powerful->id]), [
                'status' => StoreMemberAccess::STATUS_SUSPENDED,
            ])
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $powerful->id,
            'status' => StoreMemberAccess::STATUS_ACTIVE,
        ]);
    }

    public function test_team_manager_can_manage_strict_subset_peer(): void
    {
        $owner = $this->merchant('p2-owner2@example.com');
        $lead = $this->merchant('p2-lead2@example.com');
        $viewer = $this->merchant('p2-viewer@example.com');
        $store = $this->store($owner, 'Subset Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $lead, [
            ...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW],
            'products.edit',
            'team.manage',
        ]);
        $this->grant($store, $viewer, StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW]);

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.status', ['user' => $viewer->id]), [
                'status' => StoreMemberAccess::STATUS_SUSPENDED,
            ])
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $viewer->id,
            'status' => StoreMemberAccess::STATUS_SUSPENDED,
        ]);
    }

    public function test_multi_store_invite_rejects_unauthorized_store_ids(): void
    {
        $owner = $this->merchant('p2-multi-owner@example.com');
        $otherOwner = $this->merchant('p2-other-owner@example.com');
        $storeA = $this->store($owner, 'Store A');
        $storeB = $this->store($otherOwner, 'Store B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeB, $otherOwner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('team-members.store'), [
                'name' => 'Unauthorized Invite',
                'email' => 'unauthorized-invite@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$storeA->id, $storeB->id],
            ])
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHasErrors('store_ids');

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized-invite@example.com']);
    }

    public function test_team_manage_member_cannot_invite(): void
    {
        $owner = $this->merchant('p2-invite-owner@example.com');
        $lead = $this->merchant('p2-invite-lead@example.com');
        $store = $this->store($owner, 'Invite Lock Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $lead, [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage']);

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Should Fail',
                'email' => 'should-fail-invite@example.com',
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
                'store_ids' => [$store->id],
            ])
            ->assertForbidden();

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertDontSeeText('Add member');
    }

    public function test_non_owner_cannot_see_permission_details(): void
    {
        $owner = $this->merchant('p2-perm-owner@example.com');
        $member = $this->merchant('p2-perm-member@example.com');
        $store = $this->store($owner, 'Hidden Perms Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.view']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertSeeText('Only the store owner can review or change detailed permissions')
            ->assertDontSeeText('Store permissions')
            ->assertDontSeeText('Sensitive permissions')
            ->assertDontSeeText('Permission presets')
            ->assertDontSee('data-team-inspector', false);
    }

    public function test_owner_can_transfer_ownership_with_password_confirmation(): void
    {
        Notification::fake();

        $owner = $this->merchant('p2-xfer-owner@example.com');
        $member = $this->merchant('p2-xfer-member@example.com');
        $store = $this->store($owner, 'Transfer Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, StoreMemberAccess::presets()[StoreMemberAccess::PRESET_OPERATIONS]);

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->post(route('team-members.transfer-ownership', ['user' => $member->id]))
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_OWNER,
            'status' => StoreMemberAccess::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_MEMBER,
            'status' => StoreMemberAccess::STATUS_ACTIVE,
        ]);

        $this->assertTrue($member->fresh()->hasStorePermission($store->fresh(), StorePermission::TEAM_MANAGE));
        $this->assertFalse($owner->fresh()->hasStorePermission($store->fresh(), StorePermission::TEAM_MANAGE));
        $this->assertTrue($owner->fresh()->hasStorePermission($store->fresh(), StorePermission::CATALOG_MANAGE));

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'store_ownership_transferred',
            'target_user_id' => $member->id,
        ]);

        Notification::assertSentTo($owner, StoreOwnershipTransferredNotification::class);
        Notification::assertSentTo($member, StoreOwnershipTransferredNotification::class);

        $ownerCount = $store->members()->wherePivot('role', Store::ROLE_OWNER)->count();
        $this->assertSame(1, $ownerCount);
    }

    public function test_non_owner_cannot_transfer_ownership(): void
    {
        $owner = $this->merchant('p2-xfer-deny-owner@example.com');
        $lead = $this->merchant('p2-xfer-deny-lead@example.com');
        $member = $this->merchant('p2-xfer-deny-member@example.com');
        $store = $this->store($owner, 'Deny Transfer Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $lead, [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage']);
        $this->grant($store, $member, StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW]);

        $this->actingAs($lead)
            ->withSession([
                'current_store_id' => $store->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->post(route('team-members.transfer-ownership', ['user' => $member->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_team_page_exposes_sensitive_import_cancel_and_cutover(): void
    {
        $owner = $this->merchant('p2-ui-owner@example.com');
        $store = $this->store($owner, 'Advanced UI Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertSeeText('Sensitive permissions')
            ->assertSeeText('Import products')
            ->assertSeeText('Cancel orders')
            ->assertSeeText('Export orders')
            ->assertSeeText('Delete and anonymize customers')
            ->assertSeeText('Bulk force-set inventory')
            ->assertSeeText('Manage store notification delivery')
            ->assertSeeText('Activate or roll back cutover')
            ->assertSeeText('Transfer ownership')
            ->assertDontSeeText('Manage API keys')
            ->assertDontSeeText('Manage webhooks');
    }

    public function test_member_without_settings_view_is_forced_to_account_tab(): void
    {
        $owner = $this->merchant('p2-settings-owner@example.com');
        $member = $this->merchant('p2-settings-member@example.com');
        $store = $this->store($owner, 'Gated Settings Store');
        $store->forceFill([
            'address' => '123 Secret Warehouse Lane',
            'settings' => ['contact_email' => 'secret@store.test'],
        ])->save();
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['products.view', 'products.edit']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings', ['tab' => 'store']))
            ->assertOk()
            ->assertSeeText('Your account')
            ->assertDontSeeText('Save store settings')
            ->assertDontSeeText('123 Secret Warehouse Lane')
            ->assertDontSeeText('secret@store.test')
            ->assertDontSee('href="'.route('generalSettings', ['tab' => 'store']).'"', false);
    }

    public function test_manager_sees_redacted_store_settings_without_contact_details(): void
    {
        $owner = $this->merchant('p2-redact-owner@example.com');
        $manager = $this->merchant('p2-redact-manager@example.com');
        $store = $this->store($owner, 'Redacted Settings Store');
        $store->forceFill([
            'address' => '99 Hidden Business Ave',
            'settings' => ['contact_email' => 'hidden-contact@store.test'],
        ])->save();
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSeeText('Read-only for your role')
            ->assertSeeText('Hidden for your role')
            ->assertDontSeeText('Save store settings')
            ->assertDontSeeText('99 Hidden Business Ave')
            ->assertDontSeeText('hidden-contact@store.test');
    }

    public function test_location_ids_remain_unpersisted_and_absent_from_payload_contract(): void
    {
        $owner = $this->merchant('p2-location-owner@example.com');
        $store = $this->store($owner, 'Location Contract Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'No Location',
                'email' => 'p2-no-location@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
                'location_ids' => [1, 2, 3],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'p2-no-location@example.com')->firstOrFail();

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'location_ids' => null,
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('"location_ids"', $html);
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name = 'P2 Store'): Store
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
            'onboarding_completed' => true,
        ]);
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

    /**
     * @param  list<string>  $permissions
     */
    private function grant(Store $store, User $user, array $permissions): void
    {
        $this->attach($store, $user, Store::ROLE_MEMBER, StoreUser::STATUS_ACTIVE);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $user,
            $permissions,
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreUser::STATUS_ACTIVE,
        );
    }
}
