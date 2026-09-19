<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\StoreMemberPermission;
use App\Models\User;
use App\Services\SecurityLogRecorder;
use App\Services\Settings\StoreMemberInvitationService;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use App\Support\StorePermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function __construct(
        private readonly StoreMemberPermissionSync $permissionSync,
        private readonly StoreMemberInvitationService $invitations,
    ) {}

    public function index(Request $request): RedirectResponse|View
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please select a store before managing team members.']);
        }

        $members = $currentStore->members()
            ->with('role')
            ->orderByRaw("CASE store_user.role WHEN 'owner' THEN 1 ELSE 2 END")
            ->orderBy('users.name')
            ->get();

        $permissionMap = $this->permissionMapFor($currentStore, $members->pluck('id')->all());
        $user = $request->user();
        $canManageTeam = (bool) $user?->hasStorePermission($currentStore, StorePermission::TEAM_MANAGE);

        $inviteStores = $user
            ? $user->activeMemberStores()
                ->orderBy('stores.name')
                ->get(['stores.id', 'stores.name'])
                ->filter(fn (Store $store): bool => (bool) $user->hasStorePermission($store, StorePermission::TEAM_MANAGE))
                ->values()
            : collect();

        $catalog = StoreMemberAccess::catalog();
        if ($user) {
            $catalog['grantable'] = StoreMemberAccess::grantableKeysFor($user, $currentStore);
            $catalog['can_grant_team_manage'] = StoreMemberAccess::canGrantTeamManage($user, $currentStore);
        }

        return view('user_view.team_members', [
            'selectedStore' => $currentStore,
            'members' => $members,
            'memberAccess' => $this->memberAccessPayloads($currentStore, $members, $permissionMap),
            'currentUserStoreRole' => $user?->roleInStore($currentStore),
            'canManageTeam' => $canManageTeam,
            'teamAccessCatalog' => $catalog,
            'inviteStores' => $inviteStores,
            'inviteLocations' => collect(),
            'recentTeamActivity' => $this->recentTeamActivity($currentStore),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->assertCanManageTeam($request, $currentStore);

        $validated = $this->validateAccessPayload($request, inviting: true);
        $email = Str::lower(trim((string) $validated['email']));
        $actor = $request->user();

        if ($actor && Str::lower((string) $actor->email) === $email) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself.',
            ]);
        }

        $targetStores = $this->targetStores($request, $currentStore, $validated['store_ids'] ?? [$currentStore->id]);
        $requested = $this->requestedPermissions($validated);

        $added = [];
        $resent = [];
        $createdAccount = false;
        $member = DB::transaction(function () use ($request, $validated, $email, $requested, $targetStores, $actor, &$added, &$resent, &$createdAccount): User {
            $member = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->first();

            if ($member && ! $member->hasRole('user')) {
                throw ValidationException::withMessages([
                    'email' => 'Only merchant user accounts can be added to a store team.',
                ]);
            }

            if ($member && $member->is_active === false && ! $member->must_set_password) {
                throw ValidationException::withMessages([
                    'email' => 'This account is deactivated and cannot be invited.',
                ]);
            }

            if (! $member) {
                $userRole = Role::query()->where('name', 'user')->first();

                if (! $userRole) {
                    abort(500, 'The default user role is missing. Please seed roles before adding team members.');
                }

                $member = User::create([
                    'name' => $validated['name'],
                    'email' => $email,
                    'password' => Str::password(32),
                    'must_set_password' => true,
                    'role_id' => $userRole->id,
                    'is_active' => false,
                ]);
                $createdAccount = true;
            }

            foreach ($targetStores as $store) {
                $existing = $store->members()->where('users.id', $member->id)->first();
                $grantable = StoreMemberAccess::grantableKeysFor($actor, $store);

                if ($existing) {
                    if (StoreMemberAccess::isOwnerRole($existing->pivot?->role)) {
                        continue;
                    }

                    if ((string) ($existing->pivot?->status ?: '') === StoreMemberAccess::STATUS_INVITED) {
                        $merged = StoreMemberAccess::mergeEditablePermissions(
                            $this->storedPermissions($store, $member),
                            $requested,
                            $grantable,
                        );

                        if ($merged === []) {
                            throw ValidationException::withMessages([
                                'permissions' => 'Choose access you are allowed to grant in '.$store->name.'.',
                            ]);
                        }

                        $this->permissionSync->sync(
                            $store,
                            $member,
                            $merged,
                            StoreMemberAccess::PRESET_CUSTOM,
                            $validated['job_title'] ?? null,
                            null,
                            StoreMemberAccess::STATUS_INVITED,
                        );

                        $resent[] = $store;
                    }

                    continue;
                }

                $merged = StoreMemberAccess::mergeEditablePermissions([], $requested, $grantable);

                if ($merged === []) {
                    throw ValidationException::withMessages([
                        'permissions' => 'Choose access you are allowed to grant in '.$store->name.'.',
                    ]);
                }

                $store->members()->attach($member->id, [
                    'role' => Store::ROLE_MEMBER,
                    'status' => StoreMemberAccess::STATUS_INVITED,
                ]);

                $permissions = $this->permissionSync->sync(
                    $store,
                    $member,
                    $merged,
                    StoreMemberAccess::PRESET_CUSTOM,
                    $validated['job_title'] ?? null,
                    null,
                    StoreMemberAccess::STATUS_INVITED,
                );

                app(SecurityLogRecorder::class)->record(
                    $request,
                    'team_member_invited',
                    store: $store,
                    targetUser: $member,
                    metadata: [
                        'preset' => StoreMemberAccess::matchPreset($permissions),
                        'permissions' => $permissions,
                    ]
                );

                $added[] = $store;
            }

            return $member;
        });

        $touched = collect([...$added, ...$resent])->unique('id')->values();

        if ($touched->isEmpty()) {
            return back()
                ->withErrors(['email' => 'That user is already a member of the selected store.'])
                ->withInput();
        }

        $mailWarning = null;
        try {
            $this->invitations->send($member, $request->user(), $touched);
        } catch (\Throwable $exception) {
            report($exception);
            $mailWarning = 'The teammate was added, but the invitation email could not be sent. Use Resend invitation from the team page.';
        }

        $presetLabel = collect(StoreMemberAccess::presetOptions())
            ->firstWhere('key', $validated['access_preset'])['label'] ?? 'Custom access';

        $message = $added === []
            ? "A new invitation was sent to {$member->email}."
            : ($createdAccount
                ? "Invitation sent to {$member->email}."
                : "{$member->name} was invited to ".$this->invitations->storeLabel(collect($added)).'.');

        $redirect = redirect()
            ->route('team-members.index')
            ->with('success', $message)
            ->with('success_title', 'Invitation sent')
            ->with('success_meta', $presetLabel);

        if ($mailWarning) {
            $redirect->with('error', $mailWarning)->with('error_title', 'Invitation email failed');
        }

        return $redirect;
    }

    public function updateRole(Request $request, int $userId): RedirectResponse
    {
        return $this->update($request, $userId);
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->assertCanManageTeam($request, $currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $this->assertCanEditMember($request, $member, 'access');

        $validated = $this->validateAccessPayload($request, inviting: false);
        $merged = StoreMemberAccess::mergeEditablePermissions(
            $this->storedPermissions($currentStore, $member),
            $this->requestedPermissions($validated),
            StoreMemberAccess::grantableKeysFor($request->user(), $currentStore),
        );

        if ($merged === []) {
            throw ValidationException::withMessages([
                'permissions' => 'Choose at least one permission for custom access.',
            ]);
        }

        $previous = StorePermissionResolver::granularFor($member, $currentStore);
        if ($previous === []) {
            $previous = $this->storedPermissions($currentStore, $member);
        }

        $permissions = $this->permissionSync->sync(
            $currentStore,
            $member,
            $merged,
            StoreMemberAccess::PRESET_CUSTOM,
            $validated['job_title'] ?? null,
        );

        app(SecurityLogRecorder::class)->record(
            $request,
            'role_changed',
            store: $currentStore,
            targetUser: $member,
            metadata: [
                'previous_permissions' => $previous,
                'new_permissions' => $permissions,
                'preset' => StoreMemberAccess::matchPreset($permissions),
            ]
        );

        return redirect()
            ->route('team-members.index')
            ->with('success', "{$member->name}'s store access was updated.")
            ->with('success_title', 'Access updated')
            ->with('success_meta', StoreMemberAccess::accessSummary(Store::ROLE_MEMBER, StoreMemberAccess::matchPreset($permissions)));
    }

    public function updateStatus(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->assertCanManageTeam($request, $currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $this->assertCanEditMember($request, $member, 'member');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([StoreMemberAccess::STATUS_ACTIVE, StoreMemberAccess::STATUS_SUSPENDED])],
        ]);

        $currentStatus = (string) ($member->pivot?->status ?: StoreMemberAccess::STATUS_ACTIVE);

        if ($currentStatus === StoreMemberAccess::STATUS_INVITED) {
            return back()->withErrors([
                'member' => 'Invited teammates cannot be suspended. Remove them, or wait until they accept.',
            ]);
        }

        $nextStatus = $validated['status'];
        if ($currentStatus === $nextStatus) {
            return redirect()->route('team-members.index');
        }

        $currentStore->members()->updateExistingPivot($member->id, [
            'status' => $nextStatus,
        ]);
        StorePermissionResolver::forget($member, $currentStore);

        $suspended = $nextStatus === StoreMemberAccess::STATUS_SUSPENDED;

        app(SecurityLogRecorder::class)->record(
            $request,
            $suspended ? 'team_member_suspended' : 'team_member_reactivated',
            store: $currentStore,
            targetUser: $member,
            metadata: ['status' => $nextStatus]
        );

        return redirect()
            ->route('team-members.index')
            ->with('success', $suspended
                ? "{$member->name} can no longer use {$currentStore->name}."
                : "{$member->name} can use {$currentStore->name} again.")
            ->with('success_title', $suspended ? 'Teammate suspended' : 'Teammate reactivated');
    }

    public function destroy(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->assertCanManageTeam($request, $currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $this->assertCanEditMember($request, $member, 'member');

        $memberRole = $member->pivot?->role;
        $removedName = $member->name;

        $this->permissionSync->forget($currentStore, $member);

        app(SecurityLogRecorder::class)->record(
            $request,
            'team_member_removed',
            store: $currentStore,
            targetUser: $member,
            metadata: ['previous_role' => $memberRole]
        );

        $this->invitations->discardUnusedInviteAccount($member);

        return redirect()
            ->route('team-members.index')
            ->with('success', "{$removedName} was removed from {$currentStore->name}.")
            ->with('success_title', 'Team member removed')
            ->with('success_meta', 'Store access revoked');
    }

    public function resendInvite(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->assertCanManageTeam($request, $currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $this->assertCanEditMember($request, $member, 'member');

        $status = (string) ($member->pivot?->status ?: StoreMemberAccess::STATUS_ACTIVE);

        if ($status !== StoreMemberAccess::STATUS_INVITED) {
            return back()->withErrors([
                'member' => 'This teammate already has an account. Ask them to sign in, or use Forgot password if they need a new password.',
            ]);
        }

        try {
            $this->invitations->send($member, $request->user(), collect([$currentStore]));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'member' => 'The invitation email could not be sent. Check mail settings and try again.',
            ]);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'team_member_invite_resent',
            store: $currentStore,
            targetUser: $member,
        );

        return redirect()
            ->route('team-members.index')
            ->with('success', "A new invitation was sent to {$member->email}.")
            ->with('success_title', 'Invitation sent');
    }

    private function requireCurrentStore(Request $request): Store
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore instanceof Store) {
            abort(404, 'No active store was found for this request.');
        }

        return $currentStore;
    }

    private function assertCanManageTeam(Request $request, Store $store): void
    {
        if (! $request->user()?->hasStorePermission($store, StorePermission::TEAM_MANAGE)) {
            abort(403, 'You are not authorized to manage team members in the current store.');
        }
    }

    private function assertCanEditMember(Request $request, User $member, string $errorKey = 'access'): void
    {
        if (StoreMemberAccess::isOwnerRole($member->pivot?->role)) {
            throw ValidationException::withMessages([
                $errorKey => $errorKey === 'member'
                    ? 'You cannot remove an owner from the current store. Transfer ownership first.'
                    : 'Owner access cannot be changed from team permissions. Transfer ownership first.',
            ]);
        }

        if ((int) $member->id === (int) $request->user()?->id) {
            throw ValidationException::withMessages([
                $errorKey => 'You cannot change your own store access from here.',
            ]);
        }
    }

    /**
     * @return array{name?: string, email?: string, access_preset: string, permissions: list<string>, job_title: ?string, location_ids: list<int>, store_ids?: list<int>}
     */
    private function validateAccessPayload(Request $request, bool $inviting): array
    {
        $rules = [
            'access_preset' => ['required', 'string', Rule::in(StoreMemberAccess::presetKeys())],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(StoreMemberAccess::teamGrantableKeys())],
            'job_title' => ['nullable', 'string', 'max:80'],
        ];

        if ($inviting) {
            $rules['name'] = ['required', 'string', 'max:120'];
            $rules['email'] = ['required', 'email', 'max:255'];
            $rules['store_ids'] = ['nullable', 'array'];
            $rules['store_ids.*'] = ['integer'];
        }

        $validated = $request->validate($rules);
        $validated['permissions'] = array_values($validated['permissions'] ?? []);
        $validated['location_ids'] = [];
        $validated['job_title'] = filled($validated['job_title'] ?? null) ? trim((string) $validated['job_title']) : null;

        if (($validated['access_preset'] ?? null) === StoreMemberAccess::PRESET_CUSTOM && $validated['permissions'] === []) {
            if ($inviting) {
                $validated['permissions'] = StoreMemberAccess::presets()[StoreMemberAccess::PRESET_VIEW];
            } else {
                throw ValidationException::withMessages([
                    'permissions' => 'Choose at least one permission for custom access.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * @param  array{access_preset: string, permissions: list<string>}  $validated
     * @return list<string>
     */
    private function requestedPermissions(array $validated): array
    {
        $preset = $validated['access_preset'] ?? StoreMemberAccess::PRESET_CUSTOM;

        if ($preset !== StoreMemberAccess::PRESET_CUSTOM && isset(StoreMemberAccess::presets()[$preset])) {
            return StoreMemberAccess::presets()[$preset];
        }

        return $validated['permissions'] ?? [];
    }

    /**
     * @return list<string>
     */
    private function storedPermissions(Store $store, User $member): array
    {
        return StoreMemberAccess::normalize(
            StoreMemberPermission::query()
                ->where('store_id', $store->id)
                ->where('user_id', $member->id)
                ->pluck('permission')
                ->all()
        );
    }

    /**
     * @param  list<int>  $storeIds
     * @return Collection<int, Store>
     */
    private function targetStores(Request $request, Store $currentStore, array $storeIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $storeIds)));
        if ($ids === []) {
            $ids = [$currentStore->id];
        }

        $stores = Store::query()->whereIn('id', $ids)->get();
        $allowed = $stores->filter(function (Store $store) use ($request): bool {
            return (bool) $request->user()?->hasStorePermission($store, StorePermission::TEAM_MANAGE);
        })->values();

        if ($allowed->isEmpty()) {
            abort(403, 'You are not authorized to add members to the selected stores.');
        }

        return $allowed;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    private function permissionMapFor(Store $store, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = StoreMemberPermission::query()
            ->where('store_id', $store->id)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'permission']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = $row->permission;
        }

        return $map;
    }

    /**
     * @param  Collection<int, User>  $members
     * @param  array<int, list<string>>  $permissionMap
     * @return array<int, array<string, mixed>>
     */
    private function memberAccessPayloads(Store $store, Collection $members, array $permissionMap): array
    {
        $payloads = [];
        foreach ($members as $member) {
            $role = $member->pivot?->role;
            $stored = StoreMemberAccess::normalize($permissionMap[$member->id] ?? []);
            $permissions = $stored !== []
                ? $stored
                : StorePermissionResolver::granularFor($member, $store);
            $preset = $member->pivot?->access_preset ?: StoreMemberAccess::matchPreset($permissions);
            if (StoreMemberAccess::isOwnerRole($role)) {
                $preset = StoreMemberAccess::PRESET_FULL_OPERATIONAL;
                $permissions = StoreMemberAccess::keys();
            }

            $status = in_array((string) $member->pivot?->status, ['active', 'invited', 'suspended'], true)
                ? (string) $member->pivot->status
                : 'active';

            $payloads[$member->id] = [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $role,
                'membership_label' => StoreMemberAccess::membershipLabel($role, $preset),
                'access_summary' => StoreMemberAccess::accessSummary($role, $preset),
                'job_title' => $member->pivot?->job_title,
                'access_preset' => $preset,
                'access_filter' => StoreMemberAccess::isOwnerRole($role) ? 'owner' : $preset,
                'permissions' => $permissions,
                'location_ids' => $member->pivot?->location_ids ?? [],
                'joined_at' => optional($member->pivot?->created_at)->format('M d, Y') ?: 'Recently added',
                'last_active' => optional($member->last_login_at)?->diffForHumans() ?: '—',
                'scope' => StoreMemberAccess::isOwnerRole($role) ? 'All store access' : $store->name,
                'status' => $status,
                'is_owner' => StoreMemberAccess::isOwnerRole($role),
                'is_you' => (int) $member->id === (int) auth()->id(),
            ];
        }

        return $payloads;
    }

    private function recentTeamActivity(Store $store)
    {
        return SecurityLog::query()
            ->with(['user:id,name', 'targetUser:id,name'])
            ->where('store_id', $store->id)
            ->whereIn('event_type', [
                'team_member_invited',
                'team_member_invite_resent',
                'team_member_joined',
                'role_changed',
                'team_member_removed',
                'team_member_suspended',
                'team_member_reactivated',
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }
}
