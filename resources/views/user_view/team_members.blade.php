@extends('layouts.user.user-sidebar')

@section('title', 'Team Members - BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Team members" :lead="'Manage people who can operate '.$selectedStore->name.'.'">
        <x-slot:search>
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" data-team-search placeholder="Search team members, emails, or roles..." class="w-full rounded-lg border border-stone-200 bg-stone-50 py-2 pl-10 pr-4 text-sm text-stone-900 placeholder:text-stone-500 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
    </x-slot:search>
        <x-slot:actions>
        @if (in_array($currentUserStoreRole, ['owner', 'manager'], true))
            <button type="button" data-open-team-invite class="hidden sm:inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                <span>Invite Member</span>
            </button>
        @endif
    </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $members = $members ?? collect();
    $recentTeamActivity = $recentTeamActivity ?? collect();
    $roleLabels = [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'staff' => 'Staff',
    ];
    $roleBadgeClasses = [
        'owner' => 'bg-brand/10 text-brand-ink',
        'manager' => 'bg-[#EEF8F3] text-[#006A4E]',
        'staff' => 'bg-[#F4F2FC] text-[#454652]',
    ];
    $roleGuideMeta = [
        'owner' => [
            'badge' => 'Primary',
            'badge_class' => 'bg-brand text-white',
            'card_class' => 'team-console-role-card team-console-role-card-primary',
            'description' => 'Full platform control, including billing access, API management, and destructive actions.',
        ],
        'manager' => [
            'badge' => 'Standard',
            'badge_class' => 'bg-[#E3E1EA] text-[#454652]',
            'card_class' => 'team-console-role-card',
            'description' => 'Can manage catalog, inventory, and day-to-day store operations without billing access.',
        ],
        'staff' => [
            'badge' => 'Limited',
            'badge_class' => 'bg-[#E3E1EA] text-[#454652]',
            'card_class' => 'team-console-role-card',
            'description' => 'Read-first access with limited management permissions for specific departments.',
        ],
    ];
    $roleDescriptions = [
        'owner' => 'Full store control, billing access, and destructive actions.',
        'manager' => 'Can manage catalog and day-to-day store operations.',
        'staff' => 'Read-first access with limited management permissions.',
    ];
    $canInviteMembers = in_array($currentUserStoreRole, ['owner', 'manager'], true);
    $canChangeRoles = in_array($currentUserStoreRole, ['owner', 'manager'], true);
    $ownersCount = $members->where('pivot.role', 'owner')->count();
    $managersCount = $members->where('pivot.role', 'manager')->count();
    $staffCount = $members->where('pivot.role', 'staff')->count();
    $leadershipCount = $ownersCount + $managersCount;

    $formatActivity = static function ($log): array {
        $actor = $log->user?->name ?? 'A teammate';
        $target = $log->targetUser?->name;
        $meta = is_array($log->metadata) ? $log->metadata : [];

        $message = match ($log->event_type) {
            'team_member_invited' => ($target ?: 'New member').' invited as '.ucfirst((string) ($meta['role'] ?? 'member')),
            'role_changed' => ($target ?: $actor).' updated to Role: '.ucfirst((string) ($meta['new_role'] ?? 'member')),
            'team_member_removed' => ($target ?: 'A member').' removed from this store',
            default => 'Team membership updated',
        };

        return [
            'message' => $message,
            'when' => optional($log->created_at)?->diffForHumans() ?? 'Recently',
        ];
    };
@endphp

<div class="settings-workspace-fluid team-console">
    @include('user_view.partials.flash_success')

    <div class="team-console-metrics">
        <div class="team-console-metric">
            <div class="team-console-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
            </div>
            <div>
                <p class="team-console-metric-label">Total Members</p>
                <p class="team-console-metric-value">{{ $members->count() }}</p>
                <p class="team-console-metric-meta">{{ $ownersCount }} {{ \Illuminate\Support\Str::plural('owner', $ownersCount) }}</p>
            </div>
        </div>

        <div class="team-console-metric">
            <div class="team-console-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                    <path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                </svg>
            </div>
            <div>
                <p class="team-console-metric-label">Leadership</p>
                <p class="team-console-metric-value">
                    {{ $leadershipCount }}
                    <span class="team-console-metric-inline">{{ $ownersCount }} owner{{ $ownersCount === 1 ? '' : 's' }}, {{ $managersCount }} manager{{ $managersCount === 1 ? '' : 's' }}</span>
                </p>
            </div>
        </div>

        <div class="team-console-metric">
            <div class="team-console-metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                    <path d="M20 7h-5V4c0-1.1-.9-2-2-2h-2c-1.1 0-2 .9-2 2v3H4c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V9c0-1.1-.9-2-2-2zM9 12c.83 0 1.5.67 1.5 1.5S9.83 15 9 15s-1.5-.67-1.5-1.5S8.17 12 9 12zm3 6H6v-.75c0-1 2-1.5 3-1.5s3 .5 3 1.5V18zm1-9h-2V4h2v5zm5 7.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm2.5 2.5h-5v-.75c0-1 2-1.5 2.5-1.5s2.5.5 2.5 1.5V18z"/>
                </svg>
            </div>
            <div>
                <p class="team-console-metric-label">Staff Seats</p>
                <p class="team-console-metric-value">{{ $staffCount }}</p>
                <p class="team-console-metric-meta">{{ $staffCount }} operational teammate{{ $staffCount === 1 ? '' : 's' }}</p>
            </div>
        </div>

        <div class="team-console-activity">
            <div class="team-console-activity-head">
                <span>Recent Activity</span>
                @if (Route::has('security'))
                    <a href="{{ route('security') }}">View Logs</a>
                @endif
            </div>
            @if ($recentTeamActivity->isEmpty())
                <p class="team-console-activity-empty">No recent team changes yet. Invites and role updates will appear here.</p>
            @else
                <ul class="team-console-activity-list">
                    @foreach ($recentTeamActivity as $index => $log)
                        @php $activity = $formatActivity($log); @endphp
                        <li>
                            <span @class(['team-console-activity-dot', 'team-console-activity-dot-active' => $index === 0]) aria-hidden="true"></span>
                            <div>
                                <p>{{ $activity['message'] }}</p>
                                <time>{{ $activity['when'] }}</time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="team-console-layout">
        <section class="team-console-card team-console-directory">
            <div class="team-console-card-header">
                <div>
                    <h2 class="team-console-card-title">Team Directory</h2>
                    <p class="team-console-card-lead">Live membership list for your current active store.</p>
                </div>
                <div class="team-console-card-tools">
                    @if (! $canInviteMembers)
                        <div class="team-console-staff-note">
                            Staff accounts can review access, but only owners and managers can invite teammates.
                        </div>
                    @endif
                    <span class="team-console-tool-chip" title="Your store role">
                        {{ $roleLabels[(string) $currentUserStoreRole] ?? ucfirst((string) $currentUserStoreRole) }}
                    </span>
                </div>
            </div>

            @if ($members->isEmpty())
                <div class="team-console-empty">
                    <div class="team-console-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="40" height="40">
                            <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <h3>No teammates added yet</h3>
                    <p>This store does not have any additional team members yet. Invite someone when you are ready to share access.</p>
                    @if ($canInviteMembers)
                        <button type="button" data-open-team-invite class="team-console-btn team-console-btn-primary mt-5">
                            Invite Your First Member
                        </button>
                    @endif
                </div>
            @else
                <div class="team-console-table-wrap hidden md:block">
                    <table class="team-console-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Role</th>
                                <th>Join Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-team-directory>
                            @foreach ($members as $member)
                                @php
                                    $memberPayload = [
                                        'id' => $member->id,
                                        'name' => $member->name,
                                        'email' => $member->email,
                                        'role' => $member->pivot->role,
                                        'global_role' => $member->role?->name ?? 'user',
                                        'joined_at' => optional($member->pivot->created_at)->format('M d, Y') ?: 'Recently added',
                                        'description' => $roleDescriptions[$member->pivot->role] ?? 'Store-level access is available for this teammate.',
                                    ];
                                    $initials = collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                                    $canEditThisMember = $currentUserStoreRole === 'owner'
                                        || ($currentUserStoreRole === 'manager' && $member->pivot->role !== 'owner');
                                    $canRemoveThisMember = ($currentUserStoreRole === 'owner' && in_array($member->pivot->role, ['manager', 'staff'], true))
                                        || ($currentUserStoreRole === 'manager' && $member->pivot->role === 'staff');
                                @endphp
                                <tr data-member-row>
                                    <td>
                                        <div class="team-console-member">
                                            <div class="team-console-avatar">{{ $initials !== '' ? $initials : 'TM' }}</div>
                                            <div>
                                                <p class="team-console-member-name">
                                                    {{ $member->name }}
                                                    @if ((int) $member->id === (int) request()->user()->id)
                                                        <span class="team-console-you">You</span>
                                                    @endif
                                                </p>
                                                <p class="team-console-member-email">{{ $member->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="team-console-role-pill {{ $roleBadgeClasses[$member->pivot->role] ?? 'bg-[#F4F2FC] text-[#454652]' }}">
                                            {{ $roleLabels[$member->pivot->role] ?? ucfirst($member->pivot->role) }}
                                        </span>
                                    </td>
                                    <td class="team-console-date">
                                        {{ optional($member->pivot->created_at)->format('M j, Y') ?? 'Recently added' }}
                                    </td>
                                    <td>
                                        <div class="team-console-actions">
                                            <button type="button" data-open-team-access data-member='@json($memberPayload)' class="team-console-btn team-console-btn-ghost">
                                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                                                    <path d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
                                                </svg>
                                                Access
                                            </button>
                                            @if ($canChangeRoles && $canEditThisMember)
                                                <button type="button" data-open-team-role data-member='@json($memberPayload)' class="team-console-btn team-console-btn-dark">
                                                    <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" aria-hidden="true">
                                                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                                    </svg>
                                                    Change Role
                                                </button>
                                            @endif
                                            @if ($canRemoveThisMember)
                                                <form method="POST" action="{{ route('team-members.destroy', ['user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from {{ addslashes($selectedStore->name) }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="team-console-btn team-console-btn-danger">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="team-console-mobile-list md:hidden" data-team-directory-mobile>
                    @foreach ($members as $member)
                        @php
                            $memberPayload = [
                                'id' => $member->id,
                                'name' => $member->name,
                                'email' => $member->email,
                                'role' => $member->pivot->role,
                                'global_role' => $member->role?->name ?? 'user',
                                'joined_at' => optional($member->pivot->created_at)->format('M d, Y') ?: 'Recently added',
                                'description' => $roleDescriptions[$member->pivot->role] ?? 'Store-level access is available for this teammate.',
                            ];
                            $initials = collect(explode(' ', $member->name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                            $canEditThisMember = $currentUserStoreRole === 'owner'
                                || ($currentUserStoreRole === 'manager' && $member->pivot->role !== 'owner');
                            $canRemoveThisMember = ($currentUserStoreRole === 'owner' && in_array($member->pivot->role, ['manager', 'staff'], true))
                                || ($currentUserStoreRole === 'manager' && $member->pivot->role === 'staff');
                        @endphp
                        <article data-member-row class="team-console-mobile-card">
                            <div class="team-console-member">
                                <div class="team-console-avatar">{{ $initials !== '' ? $initials : 'TM' }}</div>
                                <div>
                                    <p class="team-console-member-name">
                                        {{ $member->name }}
                                        @if ((int) $member->id === (int) request()->user()->id)
                                            <span class="team-console-you">You</span>
                                        @endif
                                    </p>
                                    <p class="team-console-member-email">{{ $member->email }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="team-console-role-pill {{ $roleBadgeClasses[$member->pivot->role] ?? 'bg-[#F4F2FC] text-[#454652]' }}">
                                            {{ $roleLabels[$member->pivot->role] ?? ucfirst($member->pivot->role) }}
                                        </span>
                                        <span class="text-xs text-stone-500">{{ optional($member->pivot->created_at)->format('M j, Y') ?? 'Recently added' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="team-console-actions mt-4">
                                <button type="button" data-open-team-access data-member='@json($memberPayload)' class="team-console-btn team-console-btn-ghost">Access</button>
                                @if ($canChangeRoles && $canEditThisMember)
                                    <button type="button" data-open-team-role data-member='@json($memberPayload)' class="team-console-btn team-console-btn-dark">Change Role</button>
                                @endif
                                @if ($canRemoveThisMember)
                                    <form method="POST" action="{{ route('team-members.destroy', ['user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from {{ addslashes($selectedStore->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="team-console-btn team-console-btn-danger">Remove</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($canInviteMembers && $members->count() < 3)
                    <div class="team-console-expand">
                        <div class="team-console-expand-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="36" height="36">
                                <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <h4>Expand your team</h4>
                        <p>Invite managers and staff to help scale your operations across multiple regions.</p>
                    </div>
                @endif
            @endif
        </section>

        <aside class="team-console-aside">
            <section class="team-console-card team-console-role-guide">
                <div class="team-console-aside-head">
                    <div class="team-console-aside-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                            <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="team-console-card-title">Role Guide</h2>
                        <p class="team-console-card-lead">Quick reference for platform roles.</p>
                    </div>
                </div>

                <div class="team-console-role-list">
                    @foreach ($memberRoleOptions as $roleOption)
                        @php $guide = $roleGuideMeta[$roleOption] ?? null; @endphp
                        <div class="{{ $guide['card_class'] ?? 'team-console-role-card' }}">
                            <div class="flex items-center justify-between gap-3">
                                <h3>{{ $roleLabels[$roleOption] ?? ucfirst($roleOption) }}</h3>
                                <span class="{{ $guide['badge_class'] ?? 'bg-[#E3E1EA] text-[#454652]' }} team-console-guide-badge">
                                    {{ $guide['badge'] ?? ($roleLabels[$roleOption] ?? ucfirst($roleOption)) }}
                                </span>
                            </div>
                            <p>{{ $guide['description'] ?? ($roleDescriptions[$roleOption] ?? 'Store-scoped access profile.') }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="team-console-status">
                <h2>
                    <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    Status Overview
                </h2>
                <ul>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Team list is powered by the active store membership data.</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Owners and managers can add teammates from the invite drawer.</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Store roles can be updated safely with last-owner protection.</span>
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Remove-member actions revoke access without deleting the global account.</span>
                    </li>
                </ul>
                <div class="team-console-status-tip">
                    <p class="team-console-status-tip-label">Pro Tip</p>
                    <p>Advanced invite delivery and audit history remain deferred for a later sprint.</p>
                </div>
            </section>
        </aside>
    </div>
</div>

@include('user_view.partials.team_member_invite_drawer', [
    'selectedStore' => $selectedStore,
    'memberRoleOptions' => $memberRoleOptions,
    'currentUserStoreRole' => $currentUserStoreRole,
])
@include('user_view.partials.team_member_role_modal', [
    'memberRoleOptions' => $memberRoleOptions,
    'currentUserStoreRole' => $currentUserStoreRole,
])
@include('user_view.partials.team_member_access_panel')

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var body = document.body;
        var searchInput = document.querySelector('[data-team-search]');
        var memberRows = Array.from(document.querySelectorAll('[data-member-row]'));
        var inviteOverlay = document.getElementById('teamInviteOverlay');
        var inviteDrawer = document.getElementById('teamInviteDrawer');
        var roleModal = document.getElementById('teamRoleModal');
        var rolePanel = document.querySelector('[data-team-role-panel]');
        var accessOverlay = document.getElementById('teamAccessOverlay');
        var accessPanel = document.getElementById('teamAccessPanel');

        function lockBody(locked) {
            body.classList.toggle('overflow-hidden', locked);
        }

        function openInviteDrawer() {
            if (!inviteOverlay || !inviteDrawer) return;
            inviteOverlay.classList.remove('hidden');
            inviteDrawer.classList.remove('translate-x-full');
            lockBody(true);
        }

        function closeInviteDrawer() {
            if (!inviteOverlay || !inviteDrawer) return;
            inviteOverlay.classList.add('hidden');
            inviteDrawer.classList.add('translate-x-full');
            lockBody(false);
        }

        function openRoleModal(member) {
            if (!roleModal) return;
            document.querySelectorAll('[data-role-member-name]').forEach(function (el) {
                el.textContent = member.name || 'Selected member';
            });
            document.querySelectorAll('[data-role-member-email]').forEach(function (el) {
                el.textContent = member.email || '';
            });
            var roleSelect = document.querySelector('[data-role-select]');
            if (roleSelect && member.role) roleSelect.value = member.role;
            var roleForm = document.querySelector('[data-team-role-form]');
            if (roleForm && member.id) {
                roleForm.action = roleForm.dataset.actionTemplate.replace('__USER_ID__', member.id);
            }
            var hiddenMemberId = document.querySelector('[data-role-member-id]');
            if (hiddenMemberId) hiddenMemberId.value = member.id || '';
            var hiddenMemberName = document.querySelector('[data-role-member-name-input]');
            if (hiddenMemberName) hiddenMemberName.value = member.name || '';
            var hiddenMemberEmail = document.querySelector('[data-role-member-email-input]');
            if (hiddenMemberEmail) hiddenMemberEmail.value = member.email || '';

            roleModal.classList.remove('hidden');
            roleModal.classList.add('flex');
            if (rolePanel) {
                rolePanel.classList.remove('scale-95', 'opacity-0');
            }
            lockBody(true);
        }

        function closeRoleModal() {
            if (!roleModal) return;
            if (rolePanel) {
                rolePanel.classList.add('scale-95', 'opacity-0');
            }
            roleModal.classList.add('hidden');
            roleModal.classList.remove('flex');
            lockBody(false);
        }

        function renderAccessItems(role) {
            var itemsByRole = {
                owner: [
                    'Can update store settings and destructive store actions',
                    'Can create, edit, and delete products',
                    'Can manage managers and staff roles'
                ],
                manager: [
                    'Can create, edit, and delete products',
                    'Can update normal store operations',
                    'Cannot delete the store or claim owner-only actions'
                ],
                staff: [
                    'Can review store context and member access',
                    'Blocked from destructive management actions',
                    'Blocked from protected product management flows for now'
                ]
            };

            return (itemsByRole[role] || ['Store-level access details are not available for this role yet.'])
                .map(function (item) {
                    return '<li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-brand"></span><span>' + item + '</span></li>';
                })
                .join('');
        }

        function openAccessPanel(member) {
            if (!accessOverlay || !accessPanel) return;
            document.querySelectorAll('[data-access-member-name]').forEach(function (el) {
                el.textContent = member.name || 'Selected member';
            });
            document.querySelectorAll('[data-access-member-email]').forEach(function (el) {
                el.textContent = member.email || '';
            });
            document.querySelectorAll('[data-access-member-role]').forEach(function (el) {
                el.textContent = member.role || 'staff';
            });
            document.querySelectorAll('[data-access-member-global-role]').forEach(function (el) {
                el.textContent = member.global_role || 'user';
            });
            document.querySelectorAll('[data-access-member-joined]').forEach(function (el) {
                el.textContent = member.joined_at || 'Recently added';
            });
            document.querySelectorAll('[data-access-member-description]').forEach(function (el) {
                el.textContent = member.description || 'Store-scoped access profile.';
            });
            document.querySelectorAll('[data-access-member-list]').forEach(function (el) {
                el.innerHTML = renderAccessItems(member.role || 'staff');
            });

            accessOverlay.classList.remove('hidden');
            accessPanel.classList.remove('translate-x-full');
            lockBody(true);
        }

        function closeAccessPanel() {
            if (!accessOverlay || !accessPanel) return;
            accessOverlay.classList.add('hidden');
            accessPanel.classList.add('translate-x-full');
            lockBody(false);
        }

        document.querySelectorAll('[data-open-team-invite]').forEach(function (button) {
            button.addEventListener('click', openInviteDrawer);
        });
        document.querySelectorAll('[data-close-team-invite]').forEach(function (button) {
            button.addEventListener('click', closeInviteDrawer);
        });

        document.querySelectorAll('[data-open-team-role]').forEach(function (button) {
            button.addEventListener('click', function () {
                openRoleModal(JSON.parse(button.dataset.member || '{}'));
            });
        });
        document.querySelectorAll('[data-close-team-role]').forEach(function (button) {
            button.addEventListener('click', closeRoleModal);
        });

        document.querySelectorAll('[data-open-team-access]').forEach(function (button) {
            button.addEventListener('click', function () {
                openAccessPanel(JSON.parse(button.dataset.member || '{}'));
            });
        });
        document.querySelectorAll('[data-close-team-access]').forEach(function (button) {
            button.addEventListener('click', closeAccessPanel);
        });

        inviteOverlay?.addEventListener('click', closeInviteDrawer);
        accessOverlay?.addEventListener('click', closeAccessPanel);
        roleModal?.addEventListener('click', function (event) {
            if (event.target === roleModal) {
                closeRoleModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (roleModal && !roleModal.classList.contains('hidden')) {
                closeRoleModal();
                return;
            }
            if (inviteDrawer && !inviteDrawer.classList.contains('translate-x-full')) {
                closeInviteDrawer();
                return;
            }
            if (accessPanel && !accessPanel.classList.contains('translate-x-full')) {
                closeAccessPanel();
            }
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var query = searchInput.value.trim().toLowerCase();

                memberRows.forEach(function (row) {
                    var matches = row.textContent.toLowerCase().includes(query);
                    row.classList.toggle('hidden', !matches);
                });
            });
        }

        @if (old('_team_invite_modal'))
            openInviteDrawer();
        @endif

        @if (old('_team_role_modal'))
            openRoleModal({
                id: '{{ old('member_id') }}',
                name: @json(old('member_name', 'Selected member')),
                email: @json(old('member_email', '')),
                role: @json(old('role', 'staff'))
            });
        @endif
    });
</script>
@endsection
