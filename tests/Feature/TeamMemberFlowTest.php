<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\User;
use App\Notifications\TeamMemberInvitedNotification;
use App\Services\Settings\StoreMemberInvitationService;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TeamMemberFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_new_member_with_view_access(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'New Staff Member',
                'email' => 'new-staff@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ]);

        $response->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'new-staff@example.com')->first();

        $this->assertNotNull($member);
        $this->assertSame('New Staff Member', $member->name);
        $this->assertTrue($member->hasRole('user'));
        $this->assertTrue($member->must_set_password);
        $this->assertFalse($member->is_active);
        $this->assertNull($member->email_verified_at);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_MEMBER,
            'access_preset' => StoreMemberAccess::PRESET_VIEW,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);

        foreach (['products.view', 'orders.view', 'customers.view'] as $permission) {
            $this->assertDatabaseHas('store_member_permissions', [
                'store_id' => $store->id,
                'user_id' => $member->id,
                'permission' => $permission,
            ]);
        }

        $this->assertFalse($member->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, StorePermission::CATALOG_VIEW));

        Notification::assertSentTo($member, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use ($member): bool {
            $this->assertTrue($notification->needsPassword);
            $this->assertStringContainsString('/team-invites/'.$member->id, $notification->inviteUrl($member));

            return true;
        });
    }

    public function test_invited_member_sets_a_password_and_joins_the_store(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'New Staff Member',
                'email' => 'new-staff@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'new-staff@example.com')->firstOrFail();
        $inviteUrl = null;
        Notification::assertSentTo($member, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use ($member, &$inviteUrl): bool {
            $inviteUrl = $notification->inviteUrl($member);

            return true;
        });

        $this->get(route('team-invites.show', $member))
            ->assertOk()
            ->assertSeeText('This invitation link expired')
            ->assertDontSee($member->email);

        $this->get($inviteUrl)
            ->assertOk()
            ->assertSeeText('Set password and join')
            ->assertSeeText($store->name);

        $this->post($this->signedInviteAcceptUrl($member, $store), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $member->refresh();
        $this->assertFalse($member->must_set_password);
        $this->assertTrue($member->is_active);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue(Hash::check('password123', $member->password));
        $this->assertAuthenticatedAs($member);
        $this->assertTrue($member->hasStorePermission($store, StorePermission::CATALOG_VIEW));
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_ACTIVE,
        ]);
    }

    public function test_existing_account_can_accept_an_invite_without_resetting_their_password(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $existing = $this->createMerchantUser('existing-staff@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Existing Staff',
                'email' => $existing->email,
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $existing->refresh();
        $this->assertFalse($existing->must_set_password);
        $this->assertTrue(Hash::check('password', $existing->password));

        $inviteUrl = URL::temporarySignedRoute('team-invites.show', now()->addDays(7), [
            'user' => $existing->id,
            'stores' => StoreMemberInvitationService::encodeStoreIds([$store]),
        ]);

        $this->get($inviteUrl)
            ->assertOk()
            ->assertSeeText('Accept invitation')
            ->assertDontSeeText('Set password and join');

        $this->post($this->signedInviteAcceptUrl($existing, $store))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
        $this->assertTrue($existing->fresh()->hasStorePermission($store, StorePermission::CATALOG_VIEW));
    }

    public function test_owner_can_resend_an_invitation(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Resend Member',
                'email' => 'resend-staff@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'resend-staff@example.com')->firstOrFail();
        Notification::assertSentToTimes($member, TeamMemberInvitedNotification::class, 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.resend-invite', ['user' => $member->id]))
            ->assertRedirect(route('team-members.index'));

        Notification::assertSentToTimes($member, TeamMemberInvitedNotification::class, 2);
    }

    public function test_custom_permissions_expand_dependencies_on_the_server(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Refunds Member',
                'email' => 'refunds@example.com',
                'access_preset' => StoreMemberAccess::PRESET_CUSTOM,
                'permissions' => ['customers.refunds'],
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'refunds@example.com')->first();
        $stored = StoreMemberPermission::query()
            ->where('store_id', $store->id)
            ->where('user_id', $member->id)
            ->pluck('permission')
            ->all();

        $this->assertContains('customers.refunds', $stored);
        $this->assertContains('customers.view', $stored);
        $this->assertContains('orders.view', $stored);
        $this->assertContains('orders.payments', $stored);
        $this->assertFalse($member->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
    }

    public function test_manager_cannot_add_team_members(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $manager = $this->createMerchantUser('manager@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);

        $response = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Staff Invite',
                'email' => 'staff-invite@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'staff-invite@example.com',
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
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_owner_can_update_member_access(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('member@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $member, Store::ROLE_STAFF);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $member->id]), [
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
            ]);

        $response->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'role' => Store::ROLE_MEMBER,
            'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
        ]);

        $member->refresh();
        $this->assertTrue($member->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, StorePermission::BILLING_MANAGE));
    }

    public function test_manager_cannot_modify_team_members(): void
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
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
            ]);

        $ownerResponse->assertForbidden();

        $promoteResponse = $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $staff->id]), [
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
            ]);

        $promoteResponse->assertForbidden();

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

    public function test_owner_cannot_change_owner_access_from_team_permissions(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $owner->id]), [
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
            ]);

        $response->assertRedirect(route('team-members.index'));
        $response->assertSessionHasErrors('access');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => Store::ROLE_OWNER,
        ]);
    }

    public function test_manager_cannot_remove_team_members(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $manager = $this->createMerchantUser('manager@example.com');
        $staff = $this->createMerchantUser('staff@example.com');
        $peerManager = $this->createMerchantUser('peer-manager@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $manager, Store::ROLE_MANAGER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);
        $this->attachMember($store, $peerManager, Store::ROLE_MANAGER);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $staff->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $peerManager->id]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $owner->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $staff->id,
            'role' => Store::ROLE_STAFF,
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

    public function test_team_page_uses_permission_catalog_not_manager_staff_labels(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertSee('People & access')
            ->assertSee('Full operational access')
            ->assertSee('Operations access')
            ->assertSee('View only')
            ->assertSee('Custom access')
            ->assertSee('Sensitive permissions')
            ->assertSee('Issue refunds')
            ->assertDontSee('Manage billing')
            ->assertDontSee('View billing')
            ->assertSee('Owner')
            ->assertSee('Team Member')
            ->assertSee('Custom Access')
            ->assertSee('Add team member')
            ->assertSee('Send invitation')
            ->assertSee('Invitation email')
            ->assertSee('Permission presets')
            ->assertSee('Team activity')
            ->assertSee('data-module="team"', false)
            ->assertDontSee('Create new stores')
            ->assertSee('id="teamInviteDrawer"', false)
            ->assertSee('id="teamPresetsDrawer"', false)
            ->assertSee('id="teamActivityDrawer"', false)
            ->assertDontSee('Invite managers and staff')
            ->assertDontSee('Change Role');
    }

    public function test_owner_cannot_invite_themselves(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Owner Clone',
                'email' => 'Owner@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHasErrors('email');
    }

    public function test_invite_matches_existing_accounts_case_insensitively(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $existing = $this->createMerchantUser('Existing.Staff@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Ignored Name',
                'email' => 'existing.staff@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $this->assertSame(1, User::query()->whereRaw('lower(email) = ?', ['existing.staff@example.com'])->count());
        $this->assertSame($existing->id, User::query()->whereRaw('lower(email) = ?', ['existing.staff@example.com'])->value('id'));
        $this->assertSame($existing->name, $existing->fresh()->name);
        $this->assertFalse($existing->fresh()->must_set_password);
    }

    public function test_inviting_an_already_invited_member_resends_instead_of_erroring(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $payload = [
            'name' => 'Repeat Member',
            'email' => 'repeat@example.com',
            'access_preset' => StoreMemberAccess::PRESET_VIEW,
            'store_ids' => [$store->id],
        ];

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), $payload)
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'repeat@example.com')->firstOrFail();
        Notification::assertSentToTimes($member, TeamMemberInvitedNotification::class, 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), $payload)
            ->assertRedirect(route('team-members.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $store->members()->where('users.id', $member->id)->count());
        Notification::assertSentToTimes($member, TeamMemberInvitedNotification::class, 2);
    }

    public function test_unsigned_accept_is_rejected(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('invitee@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $store->members()->attach($member->id, [
            'role' => Store::ROLE_MEMBER,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);

        $this->post(route('team-invites.store', $member))->assertForbidden();
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);
    }

    public function test_team_lead_cannot_grant_permissions_they_do_not_have(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $lead = $this->createMerchantUser('lead@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $lead, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $lead,
            [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Escalation Attempt',
                'email' => 'escalate@example.com',
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $invitee = User::query()->where('email', 'escalate@example.com')->firstOrFail();
        $stored = StoreMemberPermission::query()
            ->where('store_id', $store->id)
            ->where('user_id', $invitee->id)
            ->pluck('permission')
            ->all();

        $this->assertContains('products.view', $stored);
        $this->assertContains('orders.view', $stored);
        $this->assertContains('customers.view', $stored);
        $this->assertNotContains('products.edit', $stored);
        $this->assertNotContains('customers.refunds', $stored);
        $this->assertNotContains('team.manage', $stored);
        $this->assertNotContains('settings.payments', $stored);
        $this->assertNotContains('settings.delivery', $stored);
        $this->assertFalse($invitee->hasStorePermission($store, StorePermission::TEAM_MANAGE));
    }

    public function test_member_cannot_change_their_own_access_or_remove_themselves(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $lead = $this->createMerchantUser('lead@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $lead, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $lead,
            [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($lead)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $lead->id]), [
                'access_preset' => StoreMemberAccess::PRESET_CUSTOM,
                'permissions' => ['customers.refunds'],
            ])
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHasErrors('access');

        $this->actingAs($lead)
            ->from(route('team-members.index'))
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $lead->id]))
            ->assertRedirect(route('team-members.index'))
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $lead->id,
        ]);
        $this->assertFalse($lead->fresh()->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
    }

    public function test_owner_can_suspend_and_reactivate_a_member(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('member@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $member, Store::ROLE_STAFF);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.status', ['user' => $member->id]), [
                'status' => StoreMemberAccess::STATUS_SUSPENDED,
            ])
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_SUSPENDED,
        ]);
        $this->assertFalse($member->fresh()->hasStorePermission($store, StorePermission::CATALOG_VIEW));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.status', ['user' => $member->id]), [
                'status' => StoreMemberAccess::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_ACTIVE,
        ]);
        $this->assertTrue($member->fresh()->hasStorePermission($store, StorePermission::CATALOG_VIEW));
    }

    public function test_removing_an_unused_invite_account_deletes_the_placeholder_user(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Unused Invite',
                'email' => 'unused-invite@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'unused-invite@example.com')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('team-members.destroy', ['user' => $member->id]))
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_invited_placeholder_cannot_sign_in_before_accepting(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'Pending Login',
                'email' => 'pending-login@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'pending-login@example.com')->firstOrFail();
        $member->forceFill(['password' => 'password123'])->save();

        Auth::logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->from(route('signin'))
            ->post(route('signin.attempt'), [
                'email' => 'pending-login@example.com',
                'password' => 'password123',
            ])
            ->assertRedirect(route('signin'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
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
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
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

    private function signedInviteAcceptUrl(User $member, Store ...$stores): string
    {
        return URL::temporarySignedRoute(
            'team-invites.store',
            now()->addDays(7),
            [
                'user' => $member->id,
                'stores' => StoreMemberInvitationService::encodeStoreIds($stores),
            ]
        );
    }
}
