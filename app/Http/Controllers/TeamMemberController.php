<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
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
            ->orderByRaw("CASE store_user.role WHEN 'owner' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->orderBy('users.name')
            ->get();

        return view('user_view.team_members', [
            'selectedStore' => $currentStore,
            'members' => $members,
            'currentUserStoreRole' => $request->user()->roleInStore($currentStore),
            'memberRoleOptions' => Store::memberRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $actorStoreRole = $request->user()?->roleInStore($currentStore);
        $allowedRoles = $this->allowedInviteRoles($actorStoreRole);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        $member = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $member) {
            $userRole = Role::query()->where('name', 'user')->first();

            if (! $userRole) {
                abort(500, 'The default user role is missing. Please seed roles before adding team members.');
            }

            $member = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bin2hex(random_bytes(24)),
                'role_id' => $userRole->id,
            ]);
        }

        if (! $member->hasRole('user')) {
            return back()
                ->withErrors(['email' => 'Only merchant user accounts can be added to a store team.'])
                ->withInput();
        }

        if ($currentStore->members()->where('users.id', $member->id)->exists()) {
            return back()
                ->withErrors(['email' => 'That user is already a member of the current store.'])
                ->withInput();
        }

        $currentStore->members()->attach($member->id, [
            'role' => $validated['role'],
        ]);

        return redirect()
            ->route('team-members.index')
            ->with('success', "{$member->name} was added to {$currentStore->name}.")
            ->with('success_title', 'Team member added')
            ->with('success_meta', ucfirst($validated['role']) . ' access granted');
    }

    public function updateRole(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $actorStoreRole = $request->user()?->roleInStore($currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $currentRole = $member->pivot?->role;
        $allowedRoles = $this->allowedUpdateRoles($actorStoreRole, $currentRole);

        if (empty($allowedRoles)) {
            abort(403, 'You are not authorized to change this member\'s role in the current store.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ]);

        $newRole = $validated['role'];

        if ($currentRole === Store::ROLE_OWNER && $newRole !== Store::ROLE_OWNER && ! $this->storeHasAnotherOwner($currentStore, $member->id)) {
            return back()->withErrors([
                'role' => 'You cannot demote the last owner of the current store.',
            ])->withInput();
        }

        $currentStore->members()->updateExistingPivot($member->id, [
            'role' => $newRole,
        ]);

        return redirect()
            ->route('team-members.index')
            ->with('success', "{$member->name}'s store role was updated.")
            ->with('success_title', 'Role updated')
            ->with('success_meta', 'Now assigned as ' . ucfirst($newRole));
    }

    public function update(Request $request, int $userId): RedirectResponse
    {
        return $this->updateRole($request, $userId);
    }

    public function destroy(Request $request, int $userId): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $actorStoreRole = $request->user()?->roleInStore($currentStore);

        $member = $currentStore->members()
            ->where('users.id', $userId)
            ->firstOrFail();

        $memberRole = $member->pivot?->role;

        if (! $this->canRemoveMember($actorStoreRole, $memberRole)) {
            abort(403, 'You are not authorized to remove this member from the current store.');
        }

        if ($memberRole === Store::ROLE_OWNER && ! $this->storeHasAnotherOwner($currentStore, $member->id)) {
            return back()->withErrors([
                'member' => 'You cannot remove the last owner from the current store.',
            ]);
        }

        $currentStore->members()->detach($member->id);

        return redirect()
            ->route('team-members.index')
            ->with('success', "{$member->name} was removed from {$currentStore->name}.")
            ->with('success_title', 'Team member removed')
            ->with('success_meta', 'Store access revoked');
    }

    private function requireCurrentStore(Request $request): Store
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore instanceof Store) {
            abort(404, 'No active store was found for this request.');
        }

        return $currentStore;
    }

    private function storeHasAnotherOwner(Store $store, int $excludedUserId): bool
    {
        return $store->members()
            ->wherePivot('role', Store::ROLE_OWNER)
            ->where('users.id', '!=', $excludedUserId)
            ->exists();
    }

    private function allowedInviteRoles(?string $actorStoreRole): array
    {
        return match ($actorStoreRole) {
            Store::ROLE_OWNER => Store::memberRoles(),
            Store::ROLE_MANAGER => [
                Store::ROLE_MANAGER,
                Store::ROLE_STAFF,
            ],
            default => [],
        };
    }

    private function allowedUpdateRoles(?string $actorStoreRole, ?string $targetRole): array
    {
        if ($actorStoreRole === Store::ROLE_OWNER) {
            return Store::memberRoles();
        }

        if ($actorStoreRole === Store::ROLE_MANAGER && $targetRole !== Store::ROLE_OWNER) {
            return [
                Store::ROLE_MANAGER,
                Store::ROLE_STAFF,
            ];
        }

        return [];
    }

    private function canRemoveMember(?string $actorStoreRole, ?string $targetRole): bool
    {
        if ($actorStoreRole === Store::ROLE_OWNER) {
            return in_array($targetRole, [
                Store::ROLE_MANAGER,
                Store::ROLE_STAFF,
            ], true);
        }

        if ($actorStoreRole === Store::ROLE_MANAGER) {
            return $targetRole === Store::ROLE_STAFF;
        }

        return false;
    }
}
