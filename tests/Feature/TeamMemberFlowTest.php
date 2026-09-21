<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamMemberInvitedNotification;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
            $this->assertStringContainsString('/team-invites/', $notification->inviteUrl($member));
            $this->assertStringNotContainsString('/team-invites/'.$member->id, $notification->inviteUrl($member));
            $this->assertDatabaseHas('team_invitations', [
                'user_id' => $member->id,
                'token_hash' => hash('sha256', $notification->inviteToken),
            ]);

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
        $inviteToken = null;
        Notification::assertSentTo($member, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use (&$inviteToken): bool {
            $inviteToken = $notification->inviteToken;

            return true;
        });

        $this->get(route('team-invites.show', ['token' => 'notarealtoken']))
            ->assertOk()
            ->assertSeeText('This invitation link expired');

        Auth::logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->get(route('team-invites.show', ['token' => $inviteToken]))
            ->assertOk()
            ->assertSeeText('Set password and join')
            ->assertSeeText($store->name);

        $this->post(route('team-invites.store', ['token' => $inviteToken]), [
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
        $this->assertNotNull(TeamInvitation::query()->where('user_id', $member->id)->whereNotNull('accepted_at')->first());
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

        $inviteToken = null;
        Notification::assertSentTo($existing, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use (&$inviteToken): bool {
            $inviteToken = $notification->inviteToken;

            return true;
        });

        Auth::logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->get(route('team-invites.show', ['token' => $inviteToken]))
            ->assertOk()
            ->assertSeeText('Sign in to accept')
            ->assertDontSeeText('Set password and join');

        $this->post(route('team-invites.store', ['token' => $inviteToken]))
            ->assertRedirect(route('signin'));

        $this->actingAs($existing)
            ->get(route('team-invites.show', ['token' => $inviteToken]))
            ->assertOk()
            ->assertSeeText('Accept invitation');

        $this->actingAs($existing)
            ->post(route('team-invites.store', ['token' => $inviteToken]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($existing);
        $this->assertTrue($existing->fresh()->hasStorePermission($store, StorePermission::CATALOG_VIEW));
        $this->assertTrue(Hash::check('password', $existing->fresh()->password));
    }

    public function test_existing_account_invite_does_not_login_a_different_signed_in_user(): void
    {
        Notification::fake();
        $owner = $this->createMerchantUser('owner@example.com');
        $existing = $this->createMerchantUser('existing-staff@example.com');
        $other = $this->createMerchantUser('other-user@example.com');
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

        $inviteToken = null;
        Notification::assertSentTo($existing, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use (&$inviteToken): bool {
            $inviteToken = $notification->inviteToken;

            return true;
        });

        $this->actingAs($other)
            ->get(route('team-invites.show', ['token' => $inviteToken]))
            ->assertOk()
            ->assertSeeText('Wrong account');

        $this->actingAs($other)
            ->post(route('team-invites.store', ['token' => $inviteToken]))
            ->assertRedirect(route('team-invites.show', ['token' => $inviteToken]));

        $this->assertAuthenticatedAs($other);
        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $existing->id,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);
    }

    public function test_resending_an_invitation_revokes_the_previous_token(): void
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
        $firstToken = null;
        Notification::assertSentTo($member, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use (&$firstToken): bool {
            $firstToken = $notification->inviteToken;

            return true;
        });

        Notification::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.resend-invite', ['user' => $member->id]))
            ->assertRedirect(route('team-members.index'));

        $secondToken = null;
        Notification::assertSentTo($member, TeamMemberInvitedNotification::class, function (TeamMemberInvitedNotification $notification) use (&$secondToken): bool {
            $secondToken = $notification->inviteToken;

            return true;
        });

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNotNull(TeamInvitation::query()
            ->where('token_hash', hash('sha256', $firstToken))
            ->whereNotNull('revoked_at')
            ->first());

        Auth::logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->get(route('team-invites.show', ['token' => $firstToken]))
            ->assertOk()
            ->assertSeeText('This invitation link expired');

        $this->get(route('team-invites.show', ['token' => $secondToken]))
            ->assertOk()
            ->assertSeeText('Set password and join');
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
            ->assertSee('value="team.view"', false)
            ->assertSee('data-level="view"', false)
            ->assertDontSee('Create new stores')
            ->assertSee('id="teamInviteDrawer"', false)
            ->assertSee('id="teamPresetsDrawer"', false)
            ->assertSee('id="teamActivityDrawer"', false)
            ->assertDontSee('Invite managers and staff')
            ->assertDontSee('Change Role');
    }

    public function test_non_owner_cannot_see_permission_inspector(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('member-viewer@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $member, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'team.manage'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertSeeText('People & access')
            ->assertSeeText('Only the store owner can review or change detailed permissions')
            ->assertDontSeeText('Store permissions')
            ->assertDontSeeText('Sensitive permissions')
            ->assertDontSeeText('Permission presets')
            ->assertDontSeeText('Advanced capabilities')
            ->assertDontSee('data-team-inspector', false)
            ->assertDontSee('"permissions":["products.view"', false);
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

    public function test_invalid_invite_token_cannot_accept(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $member = $this->createMerchantUser('invitee@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $store->members()->attach($member->id, [
            'role' => Store::ROLE_MEMBER,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);

        $this->actingAs($member)
            ->post(route('team-invites.store', ['token' => 'forgedtokenvaluehere123456789012']))
            ->assertRedirect(route('signin'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_INVITED,
        ]);
    }

    public function test_team_lead_cannot_invite_members_even_with_team_manage(): void
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
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk()
            ->assertDontSeeText('Add member');
    }

    public function test_team_lead_cannot_escalate_permissions_when_updating_a_subset_peer(): void
    {
        $owner = $this->createMerchantUser('owner@example.com');
        $lead = $this->createMerchantUser('lead@example.com');
        $viewer = $this->createMerchantUser('viewer@example.com');
        $store = $this->createMemberStore($owner, 'Alpha Store', Store::ROLE_OWNER);
        $this->attachMember($store, $lead, Store::ROLE_MEMBER);
        $this->attachMember($store, $viewer, Store::ROLE_MEMBER);
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $lead,
            [...StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW], 'products.edit', 'team.manage'],
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );
        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $viewer,
            StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW],
            StoreMemberAccess::PRESET_VIEW,
            null,
            null,
            StoreMemberAccess::STATUS_ACTIVE,
        );

        $this->actingAs($lead)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $viewer->id]), [
                'access_preset' => StoreMemberAccess::PRESET_FULL_OPERATIONAL,
                'permissions' => [],
            ])
            ->assertRedirect(route('team-members.index'));

        $stored = StoreMemberPermission::query()
            ->where('store_id', $store->id)
            ->where('user_id', $viewer->id)
            ->pluck('permission')
            ->all();

        $this->assertContains('products.view', $stored);
        $this->assertContains('products.edit', $stored);
        $this->assertContains('orders.view', $stored);
        $this->assertContains('customers.view', $stored);
        $this->assertNotContains('customers.refunds', $stored);
        $this->assertNotContains('team.manage', $stored);
        $this->assertNotContains('settings.payments', $stored);
        $this->assertFalse($viewer->fresh()->hasStorePermission($store, StorePermission::TEAM_MANAGE));
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
}
