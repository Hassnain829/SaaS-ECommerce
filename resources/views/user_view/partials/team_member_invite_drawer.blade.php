@php
    $visibleInviteRoles = ($currentUserStoreRole ?? null) === \App\Models\Store::ROLE_MANAGER
        ? [\App\Models\Store::ROLE_MANAGER, \App\Models\Store::ROLE_STAFF]
        : $memberRoleOptions;
@endphp

<div id="teamInviteOverlay" class="ui-modal-overlay hidden"></div>

<aside id="teamInviteDrawer" class="ui-drawer-panel ui-drawer-panel--lg translate-x-full">
    <div class="flex h-full flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-6 py-5">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Invite flow</p>
                <h2 class="mt-2 text-xl font-semibold text-stone-900">Invite team member</h2>
                <p class="mt-2 text-sm leading-6 text-stone-500">Add a teammate directly into the active store. Existing users are attached by email, and missing users are created as basic merchant accounts without email delivery yet.</p>
            </div>
            <button type="button" data-close-team-invite class="rounded-xl bg-stone-50 p-3 text-stone-600 transition hover:bg-stone-100" aria-label="Close invite drawer">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.33333 13.6L2.4 12.6667L7.06667 8L2.4 3.33333L3.33333 2.4L8 7.06667L12.6667 2.4L13.6 3.33333L8.93333 8L13.6 12.6667L12.6667 13.6L8 8.93333L3.33333 13.6Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('team-members.store') }}" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="_team_invite_modal" value="1">

            <div class="space-y-6 px-6 py-6">
                <div class="rounded-2xl bg-brand/10 px-5 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Inviting into</p>
                    <p class="mt-2 text-lg font-semibold text-stone-900">{{ $selectedStore->name }}</p>
                    <p class="mt-1 text-sm text-stone-500">The selected role will apply only inside this store, not across the whole platform.</p>
                </div>

                @if ($errors->has('name') || $errors->has('email') || $errors->has('role'))
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
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
                        <label for="invite-member-name" class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-stone-500">Full name</label>
                        <input id="invite-member-name" name="name" type="text" value="{{ old('name') }}" placeholder="Alicia Carter" class="h-11 w-full rounded-xl border border-stone-200 bg-stone-50 px-4 text-sm text-stone-900 placeholder:text-stone-400 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>

                    <div>
                        <label for="invite-member-email" class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-stone-500">Work email</label>
                        <input id="invite-member-email" name="email" type="email" value="{{ old('email') }}" placeholder="alicia@company.com" class="h-11 w-full rounded-xl border border-stone-200 bg-stone-50 px-4 text-sm text-stone-900 placeholder:text-stone-400 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                    </div>

                    <div>
                        <label for="invite-member-role" class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-stone-500">Store role</label>
                        <select id="invite-member-role" name="role" class="h-11 w-full rounded-xl border border-stone-200 bg-stone-50 px-4 text-sm text-stone-900 focus:border-brand/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                            @foreach ($visibleInviteRoles as $roleOption)
                                <option value="{{ $roleOption }}" @selected(old('role', $visibleInviteRoles[0] ?? null) === $roleOption)>{{ ucfirst($roleOption) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rounded-2xl bg-stone-50 px-5 py-5">
                    <p class="text-sm font-semibold text-stone-900">Current behavior</p>
                    <ul class="mt-3 space-y-2 text-sm text-stone-500">
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-brand"></span><span>The active store context is real and already resolved by middleware.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-brand"></span><span>Existing users are attached by email, and missing users are created as basic merchant accounts.</span></li>
                        <li class="flex items-start gap-2"><span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-amber-500"></span><span>Email delivery, invite acceptance, and pending-invite tracking are intentionally deferred for a later sprint.</span></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-stone-200 px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-stone-500">This submits immediately to the active store. The user account stays global, while the selected role is applied only to this store.</p>
                    <div class="flex items-center gap-3">
                        <button type="button" data-close-team-invite class="rounded-xl px-4 py-2.5 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">Cancel</button>
                        <button type="submit" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">Add to store</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</aside>
