@php
    $visibleInviteRoles = ($currentUserStoreRole ?? null) === \App\Models\Store::ROLE_MANAGER
        ? [\App\Models\Store::ROLE_MANAGER, \App\Models\Store::ROLE_STAFF]
        : $memberRoleOptions;
@endphp

<div id="teamInviteOverlay" class="hidden fixed inset-0 z-40 bg-[#0F172A]/40 backdrop-blur-sm"></div>

<aside id="teamInviteDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-xl translate-x-full bg-white shadow-[0_20px_50px_rgba(15,23,42,0.16)] transition-transform duration-300">
    <div class="flex h-full flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-[#E9EEF5] px-6 py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#64748B]">Invite Flow</p>
                <h2 class="mt-2 text-2xl font-semibold text-[#0F172A] font-poppins">Invite Team Member</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">Add a teammate directly into the active store. Existing users are attached by email, and missing users are created as basic merchant accounts without email delivery yet.</p>
            </div>
            <button type="button" data-close-team-invite class="rounded-2xl bg-[#F8FAFC] p-3 text-[#475569] hover:bg-[#EEF4FF]" aria-label="Close invite drawer">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.33333 13.6L2.4 12.6667L7.06667 8L2.4 3.33333L3.33333 2.4L8 7.06667L12.6667 2.4L13.6 3.33333L8.93333 8L13.6 12.6667L12.6667 13.6L8 8.93333L3.33333 13.6Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('team-members.store') }}" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="_team_invite_modal" value="1">

            <div class="space-y-6 px-6 py-6">
                <div class="rounded-[24px] bg-[#EEF4FF] px-5 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Inviting Into</p>
                    <p class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $selectedStore->name }}</p>
                    <p class="mt-1 text-sm text-[#64748B]">The selected role will apply only inside this store, not across the whole platform.</p>
                </div>

                @if ($errors->has('name') || $errors->has('email') || $errors->has('role'))
                    <div class="rounded-[24px] border border-[#FFDAD6] bg-[#FFF6F5] px-5 py-4 text-sm text-[#BA1A1A]">
                        <ul class="space-y-1">
                            @foreach (['name', 'email', 'role'] as $field)
                                @error($field)
                                    <li>{{ $message }}</li>
                                @enderror
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-5">
                    <div>
                        <label for="invite-member-name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.1em] text-[#64748B]">Full Name</label>
                        <input id="invite-member-name" name="name" type="text" value="{{ old('name') }}" placeholder="Alicia Carter" class="h-12 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-sm text-[#0F172A] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>

                    <div>
                        <label for="invite-member-email" class="mb-2 block text-xs font-semibold uppercase tracking-[0.1em] text-[#64748B]">Work Email</label>
                        <input id="invite-member-email" name="email" type="email" value="{{ old('email') }}" placeholder="alicia@company.com" class="h-12 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-sm text-[#0F172A] placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>

                    <div>
                        <label for="invite-member-role" class="mb-2 block text-xs font-semibold uppercase tracking-[0.1em] text-[#64748B]">Store Role</label>
                        <select id="invite-member-role" name="role" class="h-12 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            @foreach ($visibleInviteRoles as $roleOption)
                                <option value="{{ $roleOption }}" @selected(old('role', $visibleInviteRoles[0] ?? null) === $roleOption)>{{ ucfirst($roleOption) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rounded-[24px] bg-[#F8FAFC] px-5 py-5">
                    <p class="text-sm font-semibold text-[#0F172A]">Current behavior</p>
                    <ul class="mt-3 space-y-2 text-sm text-[#64748B]">
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>The active store context is real and already resolved by middleware.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Existing users are attached by email, and missing users are created as basic merchant accounts.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#F97316]"></span><span>Email delivery, invite acceptance, and pending-invite tracking are intentionally deferred for a later sprint.</span></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-[#E9EEF5] px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-[#64748B]">This submits immediately to the active store. The user account stays global, while the selected role is applied only to this store.</p>
                    <div class="flex items-center gap-3">
                        <button type="button" data-close-team-invite class="rounded-2xl px-4 py-3 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-gradient-to-br from-[#003D9B] to-[#0052CC] px-5 py-3 text-sm font-bold text-white shadow-[0_12px_24px_rgba(0,82,204,0.18)]">Add To Store</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</aside>
