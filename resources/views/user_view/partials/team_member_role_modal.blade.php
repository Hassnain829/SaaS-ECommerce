@php
    $visibleRoleOptions = ($currentUserStoreRole ?? null) === \App\Models\Store::ROLE_MANAGER
        ? [\App\Models\Store::ROLE_MANAGER, \App\Models\Store::ROLE_STAFF]
        : $memberRoleOptions;
@endphp

<div id="teamRoleModal" class="ui-modal-shell ui-modal-shell--nested hidden">
    <div class="ui-modal-panel ui-modal-panel--lg pointer-events-auto opacity-0 scale-95 transition-all duration-200" data-team-role-panel>
        <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-6 py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Role update</p>
                <h2 class="mt-2 text-xl font-semibold text-stone-900">Change store role</h2>
                <p class="mt-2 text-sm leading-6 text-stone-500">Update a teammate's store-level role for the active store while preserving owner, manager, and staff boundaries.</p>
            </div>
            <button type="button" data-close-team-role class="rounded-xl bg-stone-50 p-3 text-stone-600 transition hover:bg-stone-100" aria-label="Close role modal">
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

            <div class="ui-modal-body space-y-6 !px-6 !py-6">
                <div class="rounded-2xl bg-stone-50 px-5 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Selected member</p>
                    <p class="mt-2 text-lg font-semibold text-stone-900" data-role-member-name>{{ old('member_name', 'Selected member') }}</p>
                    <p class="mt-1 text-sm text-stone-500" data-role-member-email>{{ old('member_email') }}</p>
                </div>

                @error('role')
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <div>
                    <label for="team-role-select" class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-stone-500">New store role</label>
                    <select id="team-role-select" name="role" data-role-select class="h-11 w-full rounded-xl border border-stone-200 bg-stone-50 px-4 text-sm text-stone-900 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                        @foreach ($visibleRoleOptions as $roleOption)
                            <option value="{{ $roleOption }}" @selected(old('role', $visibleRoleOptions[0] ?? null) === $roleOption)>{{ ucfirst($roleOption) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-2xl bg-amber-50 px-5 py-5 text-sm leading-6 text-amber-800">
                    <p class="font-semibold text-stone-900">Permission boundary</p>
                    <p class="mt-2">Owners can assign any store role. Managers can only update non-owner teammates and can only assign manager or staff access. The last owner cannot be downgraded.</p>
                </div>
            </div>

            <div class="ui-modal-footer !justify-between">
                <p class="text-xs leading-5 text-stone-500">This updates only the active store membership for the selected person. It does not change their account in other stores.</p>
                <div class="flex items-center gap-3">
                    <button type="button" data-close-team-role class="rounded-xl px-4 py-2.5 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">Apply role</button>
                </div>
            </div>
        </form>
    </div>
</div>
