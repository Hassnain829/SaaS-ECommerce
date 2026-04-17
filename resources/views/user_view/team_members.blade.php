@extends('layouts.user.user-sidebar')

@section('title', 'Team Members - BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" data-team-search placeholder="Search team members, emails, or roles..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
    </div>

    <div class="flex items-center gap-3 shrink-0">
        @if (in_array($currentUserStoreRole, ['owner', 'manager'], true))
            <button type="button" data-open-team-invite class="hidden sm:inline-flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                </svg>
                <span>Invite Member</span>
            </button>
        @endif

        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
        </button>
    </div>
</header>
@endsection

@section('content')
@php
    $members = $members ?? collect();
    $roleLabels = [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'staff' => 'Staff',
    ];
    $roleBadgeClasses = [
        'owner' => 'bg-[#E8F0FF] text-[#003D9B]',
        'manager' => 'bg-[#EEF8F3] text-[#006A4E]',
        'staff' => 'bg-[#F3F4F6] text-[#475569]',
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
@endphp

<div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-8">
    @include('user_view.partials.flash_success')

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#64748B]">Store Team</p>
            <h1 class="mt-2 text-3xl font-medium text-[#0F172A] font-poppins">{{ $selectedStore->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#434654]">Manage the people who can operate inside your active store. This screen already reads from the live `store_user` memberships for the current store.</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="rounded-2xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Your Store Role</p>
                <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $roleLabels[$currentUserStoreRole] ?? ucfirst((string) $currentUserStoreRole) }}</p>
            </div>
            @if ($canInviteMembers)
                <button type="button" data-open-team-invite class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-br from-[#003D9B] to-[#0052CC] px-4 py-3 text-sm font-bold text-white shadow-[0_12px_24px_rgba(0,82,204,0.18)] transition hover:translate-y-[-1px]">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                    </svg>
                    <span>Invite Member</span>
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-4">
        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Total Members</p>
            <p class="mt-3 text-3xl font-semibold text-[#0F172A] font-poppins">{{ $members->count() }}</p>
            <p class="mt-2 text-sm text-[#64748B]">Loaded from the active store membership table.</p>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Leadership</p>
            <p class="mt-3 text-3xl font-semibold text-[#0F172A] font-poppins">{{ $ownersCount + $managersCount }}</p>
            <p class="mt-2 text-sm text-[#64748B]">{{ $ownersCount }} owners and {{ $managersCount }} managers.</p>
        </div>
        <div class="rounded-3xl bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Staff Seats</p>
            <p class="mt-3 text-3xl font-semibold text-[#0F172A] font-poppins">{{ $staffCount }}</p>
            <p class="mt-2 text-sm text-[#64748B]">Operational teammates with restricted permissions.</p>
        </div>
        <div class="rounded-3xl bg-[#EEF4FF] p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Day 5 Scope</p>
            <p class="mt-3 text-base font-semibold text-[#0F172A]">Team management is live</p>
            <p class="mt-2 text-sm leading-6 text-[#434654]">Add member, change role, and remove member now work against the active store. Advanced collaboration features stay deferred for later.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,0.9fr)]">
        <section class="rounded-[28px] bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-[#E9EEF5] pb-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-[#0F172A] font-poppins">Team Directory</h2>
                    <p class="mt-1 text-sm text-[#64748B]">This directory shows the live membership list for your current active store.</p>
                </div>
                @if (! $canInviteMembers)
                    <div class="rounded-2xl bg-[#FFF8E7] px-4 py-3 text-sm text-[#8A5A00]">
                        Staff accounts can review access, but only owners and managers can invite teammates.
                    </div>
                @endif
            </div>

            @if ($members->isEmpty())
                <div class="py-16">
                    <div class="mx-auto max-w-md rounded-[28px] bg-[#F8FAFC] px-8 py-10 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#E8F0FF] text-[#003D9B]">
                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                                <path d="M14 14C12.1667 14 10.5972 13.3472 9.29167 12.0417C7.98611 10.7361 7.33333 9.16667 7.33333 7.33333C7.33333 5.5 7.98611 3.93056 9.29167 2.625C10.5972 1.31944 12.1667 0.666667 14 0.666667C15.8333 0.666667 17.4028 1.31944 18.7083 2.625C20.0139 3.93056 20.6667 5.5 20.6667 7.33333C20.6667 9.16667 20.0139 10.7361 18.7083 12.0417C17.4028 13.3472 15.8333 14 14 14ZM2 27.3333V23.6C2 22.6556 2.24444 21.7889 2.73333 21C3.22222 20.2111 3.87222 19.6111 4.68333 19.2C6.41667 18.3333 8.17778 17.6833 9.96667 17.25C11.7556 16.8167 13.5667 16.6 15.4 16.6C17.2333 16.6 19.0444 16.8167 20.8333 17.25C22.6222 17.6833 24.3833 18.3333 26.1167 19.2C26.9278 19.6111 27.5778 20.2111 28.0667 21C28.5556 21.7889 28.8 22.6556 28.8 23.6V27.3333H2Z" fill="currentColor"/>
                            </svg>
                        </div>
                        <h3 class="mt-6 text-xl font-semibold text-[#0F172A] font-poppins">No teammates added yet</h3>
                        <p class="mt-3 text-sm leading-6 text-[#64748B]">This store does not have any additional team members yet. Invite someone when you are ready to share access.</p>
                        @if ($canInviteMembers)
                            <button type="button" data-open-team-invite class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-[#0052CC] px-5 py-3 text-sm font-bold text-white hover:bg-[#0047B3]">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                                </svg>
                                <span>Invite Your First Member</span>
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-6 space-y-4" data-team-directory>
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
                        <article data-member-row class="rounded-[24px] bg-[#F8FAFC] px-5 py-5 transition hover:bg-[#F1F6FB]">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F0FF] text-sm font-bold text-[#003D9B]">
                                        {{ $initials !== '' ? $initials : 'TM' }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-base font-semibold text-[#0F172A]">{{ $member->name }}</h3>
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $roleBadgeClasses[$member->pivot->role] ?? 'bg-[#F1F5F9] text-[#475569]' }}">
                                                {{ $roleLabels[$member->pivot->role] ?? ucfirst($member->pivot->role) }}
                                            </span>
                                            @if ((int) $member->id === (int) request()->user()->id)
                                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-[#64748B]">You</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-[#475569]">{{ $member->email }}</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-[#64748B]">
                                            <span>Global role: {{ ucfirst($member->role?->name ?? 'user') }}</span>
                                            <span>Joined store: {{ optional($member->pivot->created_at)->format('M d, Y') ?? 'Recently added' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <button type="button" data-open-team-access data-member='@json($memberPayload)' class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#0F172A] hover:bg-[#EEF4FF]">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M8 14.6667C6.15556 14.2 4.63333 13.1417 3.43333 11.4917C2.23333 9.84167 1.63333 8.01111 1.63333 6V1.16667L8 0L14.3667 1.16667V6C14.3667 8.01111 13.7667 9.84167 12.5667 11.4917C11.3667 13.1417 9.84444 14.2 8 14.6667ZM8 12.9967C9.28889 12.5967 10.3667 11.8083 11.2333 10.6317C12.1 9.455 12.6067 8.14167 12.7533 6.69167H8V1.7L3.2 3.5V6C3.2 6.14667 3.2 6.26667 3.2 6.36C3.2 6.45333 3.21333 6.57333 3.24 6.72H8V12.9967Z" fill="currentColor"/>
                                        </svg>
                                        <span>Access</span>
                                    </button>
                                    @if ($canChangeRoles && $canEditThisMember)
                                        <button
                                            type="button"
                                            data-open-team-role
                                            data-member='@json($memberPayload)'
                                            class="inline-flex items-center gap-2 rounded-xl bg-[#0F172A] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1E293B]"
                                        >
                                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                                <path d="M2 13.25H3.1875L10.95 5.4875L9.7625 4.3L2 12.0625V13.25ZM0.75 14.5V11.5375L10.95 1.35625C11.1042 1.21458 11.2743 1.10521 11.4604 1.02812C11.6465 0.951042 11.8424 0.9125 12.0479 0.9125C12.2535 0.9125 12.4526 0.951042 12.6452 1.02812C12.8378 1.10521 13.0047 1.22083 13.1458 1.375L14.2083 2.45625C14.3625 2.59792 14.4747 2.76484 14.545 2.95699C14.6153 3.14913 14.6504 3.34132 14.6504 3.53356C14.6504 3.73895 14.6153 3.9349 14.545 4.12141C14.4747 4.30792 14.3625 4.47812 14.2083 4.63125L4.025 14.5H0.75Z" fill="currentColor"/>
                                            </svg>
                                            <span>Change Role</span>
                                        </button>
                                    @endif
                                    @if ($canRemoveThisMember)
                                        <form method="POST" action="{{ route('team-members.destroy', ['user' => $member->id]) }}" onsubmit="return confirm('Remove {{ addslashes($member->name) }} from {{ addslashes($selectedStore->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-[#FECACA] bg-white px-4 py-2 text-sm font-semibold text-[#B42318] hover:bg-[#FFF5F5]">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                    <path d="M4.08333 12.25C3.7625 12.25 3.48785 12.1358 3.25938 11.9073C3.0309 11.6788 2.91667 11.4042 2.91667 11.0833V3.5H2.33333V2.33333H5.25V1.75H8.75V2.33333H11.6667V3.5H11.0833V11.0833C11.0833 11.4042 10.9691 11.6788 10.7406 11.9073C10.5122 12.1358 10.2375 12.25 9.91667 12.25H4.08333ZM9.91667 3.5H4.08333V11.0833H9.91667V3.5ZM5.25 9.91667H6.41667V4.66667H5.25V9.91667ZM7.58333 9.91667H8.75V4.66667H7.58333V9.91667Z" fill="currentColor"/>
                                                </svg>
                                                <span>Remove</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="space-y-5">
            <section class="rounded-[28px] bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#E8F0FF] text-[#003D9B]">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M10 18.3333C7.94444 17.8111 6.24722 16.6306 4.90833 14.7917C3.56944 12.9528 2.9 10.9111 2.9 8.66667V2.83333L10 1.66667L17.1 2.83333V8.66667C17.1 10.9111 16.4306 12.9528 15.0917 14.7917C13.7528 16.6306 12.0556 17.8111 10 18.3333ZM10 16.4583C11.4389 16.0139 12.6403 15.1361 13.6042 13.825C14.5681 12.5139 15.1306 11.05 15.2917 9.43333H10V3.86667L4.65 5.48333V8.66667C4.65 8.82778 4.65 8.96111 4.65 9.06667C4.65 9.17222 4.66481 9.30556 4.69444 9.46667H10V16.4583Z" fill="currentColor"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-[#0F172A] font-poppins">Role Guide</h2>
                        <p class="text-sm text-[#64748B]">Quick reference for how each store-level role behaves in the active store.</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @foreach ($memberRoleOptions as $roleOption)
                        <div class="rounded-2xl bg-[#F8FAFC] px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-[#0F172A]">{{ $roleLabels[$roleOption] ?? ucfirst($roleOption) }}</h3>
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] {{ $roleBadgeClasses[$roleOption] ?? 'bg-[#F1F5F9] text-[#475569]' }}">
                                    {{ $roleLabels[$roleOption] ?? ucfirst($roleOption) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $roleDescriptions[$roleOption] ?? 'Store-scoped access profile.' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[28px] bg-[#0F172A] p-6 text-white shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-white/60">Status</p>
                <h2 class="mt-3 text-xl font-semibold font-poppins">Team Members</h2>
                <ul class="mt-4 space-y-3 text-sm text-white/80">
                    <li>Team list is powered by the active store membership data.</li>
                    <li>Owners and managers can add teammates from the invite drawer.</li>
                    <li>Store roles can be updated safely with last-owner protection.</li>
                    <li>Remove-member actions revoke store access without deleting the account.</li>
                    <li>Access details stay read-only for fast review and onboarding.</li>
                    <li>Advanced invite delivery and audit history remain deferred for a later sprint.</li>
                </ul>
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
        var roleOverlay = document.getElementById('teamRoleOverlay');
        var roleModal = document.getElementById('teamRoleModal');
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
            if (!roleOverlay || !roleModal) return;
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

            roleOverlay.classList.remove('hidden');
            roleModal.classList.remove('hidden');
            roleModal.classList.remove('scale-95', 'opacity-0');
            lockBody(true);
        }

        function closeRoleModal() {
            if (!roleOverlay || !roleModal) return;
            roleOverlay.classList.add('hidden');
            roleModal.classList.add('scale-95', 'opacity-0');
            roleModal.classList.add('hidden');
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
                    return '<li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>' + item + '</span></li>';
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
