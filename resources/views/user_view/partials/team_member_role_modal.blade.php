@php
    $visibleRoleOptions = ($currentUserStoreRole ?? null) === \App\Models\Store::ROLE_MANAGER
        ? [\App\Models\Store::ROLE_MANAGER, \App\Models\Store::ROLE_STAFF]
        : $memberRoleOptions;
@endphp

<div id="teamRoleOverlay" class="hidden fixed inset-0 z-40 bg-[#0F172A]/40 backdrop-blur-sm"></div>

<div id="teamRoleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 scale-95 transition-all duration-200">
    <div class="w-full max-w-lg rounded-[28px] bg-white shadow-[0_24px_60px_rgba(15,23,42,0.18)] pointer-events-auto">
        <div class="flex items-start justify-between gap-4 border-b border-[#E9EEF5] px-6 py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#64748B]">Role Update</p>
                <h2 class="mt-2 text-2xl font-semibold text-[#0F172A] font-poppins">Change Store Role</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">Update a teammate's store-level role for the active store while preserving the Day 5 owner, manager, and staff boundaries.</p>
            </div>
            <button type="button" data-close-team-role class="rounded-2xl bg-[#F8FAFC] p-3 text-[#475569] hover:bg-[#EEF4FF]" aria-label="Close role modal">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.33333 13.6L2.4 12.6667L7.06667 8L2.4 3.33333L3.33333 2.4L8 7.06667L12.6667 2.4L13.6 3.33333L8.93333 8L13.6 12.6667L12.6667 13.6L8 8.93333L3.33333 13.6Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="#" data-team-role-form data-action-template="{{ route('team-members.update', ['user' => '__USER_ID__']) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_team_role_modal" value="1">
            <input type="hidden" name="member_id" value="{{ old('member_id') }}" data-role-member-id>
            <input type="hidden" name="member_name" value="{{ old('member_name') }}" data-role-member-name-input>
            <input type="hidden" name="member_email" value="{{ old('member_email') }}" data-role-member-email-input>

            <div class="space-y-6 px-6 py-6">
                <div class="rounded-[24px] bg-[#F8FAFC] px-5 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Selected Member</p>
                    <p class="mt-2 text-lg font-semibold text-[#0F172A]" data-role-member-name>{{ old('member_name', 'Selected member') }}</p>
                    <p class="mt-1 text-sm text-[#64748B]" data-role-member-email>{{ old('member_email') }}</p>
                </div>

                @error('role')
                    <div class="rounded-[24px] border border-[#FFDAD6] bg-[#FFF6F5] px-5 py-4 text-sm text-[#BA1A1A]">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <label for="team-role-select" class="mb-2 block text-xs font-semibold uppercase tracking-[0.1em] text-[#64748B]">New Store Role</label>
                    <select id="team-role-select" name="role" data-role-select class="h-12 w-full rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        @foreach ($visibleRoleOptions as $roleOption)
                            <option value="{{ $roleOption }}" @selected(old('role', $visibleRoleOptions[0] ?? null) === $roleOption)>{{ ucfirst($roleOption) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-[24px] bg-[#FFF8E7] px-5 py-5 text-sm leading-6 text-[#8A5A00]">
                    <p class="font-semibold text-[#0F172A]">Permission boundary</p>
                    <p class="mt-2">Owners can assign any store role. Managers can only update non-owner teammates and can only assign manager or staff access. The last owner cannot be downgraded.</p>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-[#E9EEF5] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-[#64748B]">This updates only the active store's <code class="font-semibold text-[#0F172A]">store_user.role</code> value for the selected member. It does not affect their global user account or access in other stores.</p>
                <div class="flex items-center gap-3">
                    <button type="button" data-close-team-role class="rounded-2xl px-4 py-3 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                    <button type="submit" class="rounded-2xl bg-[#0F172A] px-5 py-3 text-sm font-bold text-white hover:bg-[#1E293B]">Apply Role</button>
                </div>
            </div>
        </form>
    </div>
</div>
