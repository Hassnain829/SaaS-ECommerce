@extends('layouts.user.user-sidebar')

@section('title', 'Team Members — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Team members" lead="Control who can access this store and what they can do.">
        <x-slot:search>
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" data-team-search placeholder="Search team members, emails, or job titles..." class="w-full rounded-lg border border-stone-200 bg-stone-50 py-2 pl-10 pr-4 text-sm text-stone-900 placeholder:text-stone-500 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
    </x-slot:search>
        <x-slot:actions>
        @if ($canInviteMembers ?? false)
            <button type="button" data-open-team-invite class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                <span>Add member</span>
            </button>
        @endif
    </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $members = $members ?? collect();
    $memberAccess = $memberAccess ?? [];
    $recentTeamActivity = $recentTeamActivity ?? collect();
    $canManageTeam = $canManageTeam ?? false;
    $isStoreOwner = $isStoreOwner ?? false;
    $canInviteMembers = $canInviteMembers ?? $isStoreOwner;
    $canViewTeamPermissions = $canViewTeamPermissions ?? $isStoreOwner;
    $catalog = $canViewTeamPermissions
        ? ($teamAccessCatalog ?? \App\Support\StoreMemberAccess::catalog())
        : ($teamAccessCatalog ?? [
            'presets' => [],
            'groups' => [],
            'modules' => [],
            'sensitive' => [],
            'sensitive_permissions' => [],
            'advanced' => [],
            'advanced_permissions' => [],
            'dependencies' => [],
            'children' => [],
            'job_titles' => [],
        ]);
    $activeCount = $members->filter(fn ($member) => ($memberAccess[$member->id]['status'] ?? 'active') === 'active')->count();
    $invitedCount = $members->filter(fn ($member) => ($memberAccess[$member->id]['status'] ?? '') === 'invited')->count();
    $suspendedCount = $members->filter(fn ($member) => ($memberAccess[$member->id]['status'] ?? '') === 'suspended')->count();
    $selectedMemberId = $canViewTeamPermissions
        ? (int) (old('member_id', $members->first()?->id))
        : 0;

    $formatActivity = static function ($log): array {
        $target = $log->targetUser?->name;
        $meta = is_array($log->metadata) ? $log->metadata : [];
        $preset = $meta['preset'] ?? null;

        $message = match ($log->event_type) {
            'team_member_invited' => ($target ?: 'New member').' invited with '.\App\Support\StoreMemberAccess::accessSummary(\App\Models\Store::ROLE_MEMBER, $preset),
            'team_member_invite_resent' => ($target ?: 'A teammate').' invitation resent',
            'team_member_joined' => ($target ?: 'A teammate').' accepted their invitation',
            'role_changed' => ($target ?: 'A teammate').' access updated',
            'team_member_removed' => ($target ?: 'A member').' removed from this store',
            'team_member_suspended' => ($target ?: 'A teammate').' suspended',
            'team_member_reactivated' => ($target ?: 'A teammate').' reactivated',
            'store_ownership_transferred' => 'Ownership transferred to '.($target ?: 'a teammate'),
            default => 'Team membership updated',
        };

        return [
            'title' => $message,
            'copy' => $log->user?->name ? 'By '.$log->user->name : 'Store-scoped team change',
            'when' => optional($log->created_at)?->diffForHumans() ?? 'Recently',
            'type' => $log->event_type,
        ];
    };

    $initialsFor = static function (string $name): string {
        return collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'TM';
    };
@endphp

<div
    class="settings-workspace-fluid team-console {{ $canViewTeamPermissions ? '' : 'team-console-directory-only' }}"
    data-team-page
    data-team-can-manage="{{ $canManageTeam && $canViewTeamPermissions ? '1' : '0' }}"
    data-team-selected="{{ $canViewTeamPermissions ? $selectedMemberId : '' }}"
    data-team-access-catalog='@json($canViewTeamPermissions ? $catalog : [])'
    @if ($canInviteMembers && old('_team_invite_modal')) data-team-open-invite="1" @endif
    @if ($canViewTeamPermissions && (old('_team_access_modal') || old('_team_role_modal')))
        data-team-open-access="1"
        data-team-access-member="{{ e((string) old('member_id', '')) }}"
    @endif
>
    @include('user_view.partials.flash_success')

    <div class="team-ws-heading">
        <div>
            <h2>People &amp; access</h2>
            <p>
                @if ($canViewTeamPermissions)
                    Owner stays protected. Everyone else is a Team Member, with Custom Access when you need precise control.
                @else
                    See who belongs to this store. Only the store owner can review or change detailed permissions.
                @endif
            </p>
        </div>
        <div class="team-ws-heading-actions">
            <div class="team-ws-segments" data-team-status-tabs>
                <button type="button" class="is-active" data-team-status="all">All <span class="team-ws-count" data-team-count="all">{{ $members->count() }}</span></button>
                <button type="button" data-team-status="active">Active <span class="team-ws-count" data-team-count="active">{{ $activeCount }}</span></button>
                <button type="button" data-team-status="invited">Invited <span class="team-ws-count" data-team-count="invited">{{ $invitedCount }}</span></button>
                <button type="button" data-team-status="suspended">Suspended <span class="team-ws-count" data-team-count="suspended">{{ $suspendedCount }}</span></button>
            </div>
            @if ($canViewTeamPermissions)
                <button type="button" class="team-ws-btn" data-open-team-presets>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    Permission presets
                </button>
            @endif
        </div>
    </div>

    <section class="team-ws-workspace" aria-label="Team member access workspace">
        <div class="team-ws-directory">
            <div class="team-ws-panel-head">
                <div class="team-ws-panel-title">
                    Team directory
                    <span class="team-ws-pill-count" data-team-member-count>{{ $members->count() }} {{ \Illuminate\Support\Str::plural('member', $members->count()) }}</span>
                </div>
                <div class="team-ws-tools">
                    <label class="team-ws-search">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        <input type="search" data-team-search placeholder="Search team members…">
                    </label>
                    <div class="team-ws-filter-wrap" data-team-filter-wrap>
                        <button type="button" class="team-ws-btn team-ws-btn-sm" data-team-filter-toggle aria-expanded="false">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Filter
                        </button>
                        <div class="team-ws-filter-popover hidden" data-team-filter-popover>
                            <label for="team-access-filter">Access type</label>
                            <select id="team-access-filter" data-team-access-filter>
                                <option value="all">All access types</option>
                                <option value="owner">Owner</option>
                                <option value="full_operational">Full operational</option>
                                <option value="operations">Operations</option>
                                <option value="view">View only</option>
                                <option value="custom">Custom Access</option>
                            </select>
                            <div class="team-ws-check-row">
                                <input id="team-include-suspended" type="checkbox" data-team-include-suspended>
                                <label for="team-include-suspended" class="normal-case tracking-normal text-[13px] font-medium text-stone-700" style="padding:0;text-transform:none;letter-spacing:0">Include suspended members</label>
                            </div>
                            <button type="button" class="team-ws-btn team-ws-btn-sm mt-2 w-full" data-team-clear-filters>Clear filters</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($members->isEmpty())
                <div class="team-ws-empty">
                    <div>
                        <div class="team-ws-empty-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </div>
                        <h3>No teammates added yet</h3>
                        <p>Add someone when you are ready to share access to this store.</p>
                        @if ($canInviteMembers)
                            <button type="button" data-open-team-invite class="team-ws-btn team-ws-btn-primary mt-4">Add member</button>
                        @endif
                    </div>
                </div>
            @else
                <div class="team-ws-table-head">
                    <span>Member</span>
                    <span>Access</span>
                    <span>Store scope</span>
                    <span>Status</span>
                    <span>Last active</span>
                    <span class="text-right">Actions</span>
                </div>
                <div class="team-ws-rows" data-team-directory>
                    @foreach ($members as $member)
                        @php
                            $access = $memberAccess[$member->id] ?? [
                                'id' => $member->id,
                                'name' => $member->name,
                                'email' => $member->email,
                                'membership_label' => 'Team Member',
                                'access_summary' => 'Store-scoped access profile.',
                                'permissions' => [],
                                'status' => 'active',
                                'scope' => $selectedStore->name,
                                'last_active' => '—',
                                'is_owner' => $member->pivot->role === 'owner',
                            ];
                            $canEditThisMember = $canManageTeam && ! empty($access['can_manage']);
                            $canResendInvite = $canInviteMembers && ($access['status'] ?? '') === 'invited' && empty($access['is_owner']) && empty($access['is_you']);
                            $canSuspendMember = $canEditThisMember && ($access['status'] ?? '') === 'active';
                            $canReactivateMember = $canEditThisMember && ($access['status'] ?? '') === 'suspended';
                            $canTransferOwnership = ($isStoreOwner ?? false)
                                && empty($access['is_owner'])
                                && empty($access['is_you'])
                                && ($access['status'] ?? '') === 'active';
                        @endphp
                        <div
                            class="team-ws-row {{ (int) $access['id'] === $selectedMemberId ? 'is-selected' : '' }}"
                            data-member-row
                            data-select-member="{{ $access['id'] }}"
                            data-member='@json($access)'
                            data-status="{{ $access['status'] ?? 'active' }}"
                            data-access-filter="{{ $access['access_filter'] ?? '' }}"
                            tabindex="0"
                        >
                            <div class="team-ws-member">
                                <div class="team-ws-avatar team-ws-avatar-sm">{{ $initialsFor($member->name) }}</div>
                                <div class="min-w-0">
                                    <div class="team-ws-member-name">
                                        {{ $member->name }}
                                        @if (! empty($access['is_you']))
                                            <span class="team-ws-you">You</span>
                                        @endif
                                    </div>
                                    <div class="team-ws-member-email">{{ $member->email }}</div>
                                </div>
                            </div>
                            <div>
                                <span class="team-ws-access-badge">{{ $access['membership_label'] }}</span>
                            </div>
                            <div class="team-ws-muted">{{ $access['scope'] ?? $selectedStore->name }}</div>
                            <div>
                                <span class="team-ws-status is-{{ $access['status'] ?? 'active' }}">
                                    <span class="team-ws-dot"></span>{{ \Illuminate\Support\Str::title($access['status'] ?? 'active') }}
                                </span>
                            </div>
                            <div class="team-ws-muted">{{ $access['last_active'] ?? '—' }}</div>
                            <div class="team-ws-row-actions">
                                @if ($canViewTeamPermissions && $canEditThisMember)
                                    <button type="button" class="team-ws-edit-inline" data-open-team-access data-member='@json($access)' aria-label="Edit access">Edit access</button>
                                @endif
                                @if ($canViewTeamPermissions)
                                <button type="button" class="team-ws-icon-btn" data-team-row-menu="{{ $access['id'] }}" aria-label="Member actions" aria-expanded="false">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                </button>
                                <div class="team-ws-row-menu hidden" data-row-menu>
                                    @if (! empty($access['is_owner']) || ! empty($access['is_you']) || ! $canManageTeam)
                                        <button type="button" data-open-team-access data-member='@json($access)'>
                                            @if (! empty($access['is_owner']))
                                                View protected access
                                            @elseif (! empty($access['is_you']))
                                                View your access
                                            @else
                                                View access
                                            @endif
                                        </button>
                                    @else
                                        <button type="button" data-open-team-access data-member='@json($access)'>Edit access</button>
                                        <button type="button" data-copy-email="{{ $member->email }}">Copy email</button>
                                        @if ($canResendInvite)
                                            <form method="POST" action="{{ route('team-members.resend-invite', ['user' => $member->id]) }}">
                                                @csrf
                                                <button type="submit" class="w-full">Resend invitation</button>
                                            </form>
                                        @endif
                                        @if ($canSuspendMember)
                                            <form method="POST" action="{{ route('team-members.status', ['user' => $member->id]) }}" data-ui-confirm="{{ $member->name }} will lose access to {{ $selectedStore->name }} until you reactivate them." data-ui-confirm-title="Suspend this teammate?" data-ui-confirm-action="Suspend">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="suspended">
                                                <button type="submit" class="w-full">Suspend access</button>
                                            </form>
                                        @endif
                                        @if ($canReactivateMember)
                                            <form method="POST" action="{{ route('team-members.status', ['user' => $member->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="w-full">Reactivate access</button>
                                            </form>
                                        @endif
                                        @if ($canEditThisMember)
                                            <hr>
                                            <form method="POST" action="{{ route('team-members.destroy', ['user' => $member->id]) }}" data-ui-confirm="Remove {{ $member->name }} from {{ $selectedStore->name }}?" data-ui-confirm-title="Remove this teammate?" data-ui-confirm-action="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="is-danger w-full">Remove from store</button>
                                            </form>
                                        @endif
                                        @if ($canTransferOwnership)
                                            <hr>
                                            <form method="POST" action="{{ route('team-members.transfer-ownership', ['user' => $member->id]) }}" data-ui-confirm="{{ $member->name }} will become the owner of {{ $selectedStore->name }}. You will keep full operational access as a team member." data-ui-confirm-title="Transfer ownership?" data-ui-confirm-action="Transfer ownership">
                                                @csrf
                                                <button type="submit" class="w-full">Transfer ownership</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="team-ws-empty hidden" data-team-directory-empty>
                    <div>
                        <div class="team-ws-empty-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/></svg>
                        </div>
                        <h3>No matching members</h3>
                        <p>Change the search or filters, or invite a teammate to this store.</p>
                        @if ($canInviteMembers)
                            <button type="button" data-open-team-invite class="team-ws-btn team-ws-btn-primary mt-4">Add member</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if ($canViewTeamPermissions)
        <aside class="team-ws-inspector" data-team-inspector>
            <form
                method="POST"
                action="{{ $selectedMemberId ? route('team-members.update', ['user' => $selectedMemberId]) : '#' }}"
                data-team-access-form
                data-team-perm-root
                data-action-template="{{ route('team-members.update', ['user' => '__USER_ID__']) }}"
                class="flex min-h-0 flex-1 flex-col"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="_team_access_modal" value="1">
                <input type="hidden" name="member_id" value="{{ $selectedMemberId }}" data-access-member-id>
                <input type="hidden" name="access_preset" value="view" data-team-preset-value>
                <input type="hidden" name="job_title" value="" data-access-job-title>

                <div class="sr-only" aria-hidden="true">
                    @foreach ($catalog['presets'] as $preset)
                        <input type="radio" name="member_access_preset_choice" value="{{ $preset['key'] }}" data-team-preset="{{ $preset['key'] }}">
                    @endforeach
                    @foreach ($catalog['groups'] as $group)
                        @foreach ($group['permissions'] as $permission)
                            <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" data-team-permission="{{ $permission['key'] }}">
                        @endforeach
                    @endforeach
                    @foreach ($catalog['sensitive_permissions'] as $permission)
                        <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" data-team-permission="{{ $permission['key'] }}">
                    @endforeach
                    @foreach (($catalog['advanced_permissions'] ?? []) as $permission)
                        <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" data-team-permission="{{ $permission['key'] }}">
                    @endforeach
                </div>

                <div class="team-ws-inspector-scroll" data-inspector-scroll>
                    <div class="team-ws-empty" data-inspector-empty>
                        <div>
                            <h3>Select a member</h3>
                            <p>Choose a teammate to review and change their store access.</p>
                        </div>
                    </div>

                    <div class="hidden" data-inspector-content>
                        <div class="team-ws-profile">
                            <div class="team-ws-profile-main">
                                <div class="team-ws-avatar team-ws-avatar-lg" data-access-initials>TM</div>
                                <div class="min-w-0">
                                    <div class="team-ws-profile-name" data-access-member-name>Selected member</div>
                                    <div class="team-ws-member-email" data-access-member-email></div>
                                </div>
                            </div>
                            <div class="team-ws-profile-badges">
                                <span class="team-ws-status is-active" data-access-status><span class="team-ws-dot"></span>Active</span>
                                <span class="team-ws-custom-badge" data-access-badge>Team Member</span>
                            </div>
                        </div>

                        <div class="team-ws-inspector-body">
                            @error('access')
                                <div class="team-ws-note is-warn mb-4">{{ $message }}</div>
                            @enderror
                            @error('permissions')
                                <div class="team-ws-note is-warn mb-4">{{ $message }}</div>
                            @enderror

                            <div class="team-ws-starting" data-access-edit-fields>
                                <div>
                                    <span class="team-ws-label-strong">Starting point</span>
                                    <p class="team-ws-hint">Presets replace unsaved module permissions.</p>
                                </div>
                                <div class="team-ws-preset-tabs" role="tablist" aria-label="Starting access">
                                    @foreach ($catalog['presets'] as $preset)
                                        <button type="button" data-inspector-preset="{{ $preset['key'] }}">{{ $preset['short'] ?? $preset['label'] }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <h3 class="team-ws-section-heading">Store permissions</h3>
                            <p class="team-ws-hint">Pick one level per area. Off means no access. View means look only. Edit means they can make changes. Some areas are work-only (Off / On).</p>
                            <div class="team-ws-permission-list">
                                @foreach ($catalog['modules'] as $module)
                                    @php
                                        $moduleHasView = ($module['view'] ?? []) !== [];
                                        $moduleHasManage = ($module['manage'] ?? []) !== [];
                                        $segmentMode = $moduleHasView && $moduleHasManage
                                            ? 'full'
                                            : ($moduleHasManage ? 'work' : 'view');
                                    @endphp
                                    <div class="team-ws-permission-row">
                                        <div class="team-ws-permission-name">
                                            <span class="team-ws-module-symbol">{{ $module['symbol'] }}</span>
                                            <div class="team-ws-permission-copy">
                                                <span>{{ $module['label'] }}</span>
                                                @if ($segmentMode === 'work')
                                                    <span class="team-ws-permission-meta">Work access</span>
                                                @elseif ($segmentMode === 'view')
                                                    <span class="team-ws-permission-meta">View-only area</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div
                                            class="team-ws-seg"
                                            data-module-seg="{{ $module['key'] }}"
                                            data-seg-mode="{{ $segmentMode }}"
                                            role="group"
                                            aria-label="Access for {{ $module['label'] }}"
                                        >
                                            <button type="button" data-module="{{ $module['key'] }}" data-level="none">Off</button>
                                            @if ($segmentMode === 'full')
                                                <button type="button" data-module="{{ $module['key'] }}" data-level="view">View</button>
                                                <button type="button" data-module="{{ $module['key'] }}" data-level="manage">Edit</button>
                                            @elseif ($segmentMode === 'work')
                                                <button type="button" data-module="{{ $module['key'] }}" data-level="manage">On</button>
                                            @else
                                                <button type="button" data-module="{{ $module['key'] }}" data-level="view">View</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="team-ws-protected">
                                <div class="team-ws-protected-head">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="#a55d00" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="#a55d00" stroke-width="1.8"/></svg>
                                    <div>
                                        <div class="team-ws-protected-title">Sensitive permissions</div>
                                        <div class="team-ws-protected-copy">These stay off for new Team Member access unless you turn them on. Owner-only actions stay locked.</div>
                                    </div>
                                </div>
                                @foreach ($catalog['sensitive_permissions'] as $permission)
                                    <div class="team-ws-protected-row">
                                        <span>{{ $permission['label'] }}</span>
                                        <span class="team-ws-toggle" style="width:11rem">
                                            <button type="button" data-sensitive-toggle="{{ $permission['key'] }}" data-level="off">Off</button>
                                            <button type="button" data-sensitive-toggle="{{ $permission['key'] }}" data-level="on">On</button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            @if (($catalog['advanced_permissions'] ?? []) !== [])
                                <div class="team-ws-protected mt-3">
                                    <div class="team-ws-protected-head">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="#475569" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        <div>
                                            <div class="team-ws-protected-title">Advanced capabilities</div>
                                            <div class="team-ws-protected-copy">Less common controls that are not part of the module toggles above. Turn each on only when this teammate needs it.</div>
                                        </div>
                                    </div>
                                    @foreach ($catalog['advanced_permissions'] as $permission)
                                        <div class="team-ws-protected-row">
                                            <span>{{ $permission['label'] }}</span>
                                            <span class="team-ws-toggle" style="width:11rem">
                                                <button type="button" data-sensitive-toggle="{{ $permission['key'] }}" data-level="off">Off</button>
                                                <button type="button" data-sensitive-toggle="{{ $permission['key'] }}" data-level="on">On</button>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="team-ws-protected mt-3">
                                <div class="team-ws-protected-head">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="#a55d00" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="#a55d00" stroke-width="1.8"/></svg>
                                    <div>
                                        <div class="team-ws-protected-title">Owner-only</div>
                                        <div class="team-ws-protected-copy">These cannot be granted through Team Member permissions.</div>
                                    </div>
                                </div>
                                <div class="team-ws-protected-row"><span>Transfer ownership</span><span class="team-ws-locked">Owner only</span></div>
                                <div class="team-ws-protected-row"><span>Close or delete the store</span><span class="team-ws-locked">Owner only</span></div>
                                <div class="team-ws-protected-row"><span>Platform subscription &amp; billing authority</span><span class="team-ws-locked">Owner only</span></div>
                            </div>

                            <div class="team-ws-note hidden" data-access-owner-note>
                                <strong>Owner access is protected.</strong><br>
                                Owners always retain full store access. Transfer ownership before changing this person’s permissions.
                            </div>
                            <div class="team-ws-note hidden" data-access-self-note>
                                <strong>You cannot change your own access.</strong><br>
                                Ask the store owner if you need different permissions.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="team-ws-inspector-footer" data-inspector-footer>
                    <div class="team-ws-inspector-summary" data-inspector-summary>Select a teammate to review access.</div>
                    <div class="team-ws-footer-actions" data-access-edit-actions>
                        <button type="button" class="team-ws-btn" data-access-discard disabled>Discard</button>
                        <button type="submit" class="team-ws-btn team-ws-btn-primary" data-access-save disabled>Save access</button>
                    </div>
                </div>
            </form>
        </aside>
        @endif
    </section>

    @if ($canViewTeamPermissions)
    <div class="team-ws-audit">
        <div class="team-ws-audit-left">
            <span class="team-ws-audit-check" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span>Permission changes are logged in <strong class="text-stone-700">Security activity</strong>.</span>
        </div>
        <button type="button" class="team-ws-link" data-open-team-activity>
            View activity
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>
    @endif
</div>

<div class="team-ws-toast-stack" data-team-toasts aria-live="polite"></div>

@push('overlays')
@if ($canInviteMembers)
@include('user_view.partials.team_member_invite_drawer')
@endif
@if ($canViewTeamPermissions)
@include('user_view.partials.team_member_presets_drawer')
@include('user_view.partials.team_member_activity_drawer')
@endif
@endpush
@endsection
