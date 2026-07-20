<div id="teamAccessOverlay" class="ui-modal-overlay hidden"></div>

<aside id="teamAccessPanel" class="ui-drawer-panel translate-x-full">
    <div class="flex h-full flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-6 py-5">
            <div>
                <h2 class="mt-2 text-xl font-semibold text-stone-900">Member access details</h2>
                <p class="mt-2 text-sm leading-6 text-stone-500">A read-only access panel for reviewing how a teammate fits into the active store's current role system.</p>
            </div>
            <button type="button" data-close-team-access class="rounded-xl bg-stone-50 p-3 text-stone-600 transition hover:bg-stone-100" aria-label="Close access panel">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.33333 13.6L2.4 12.6667L7.06667 8L2.4 3.33333L3.33333 2.4L8 7.06667L12.6667 2.4L13.6 3.33333L8.93333 8L13.6 12.6667L12.6667 13.6L8 8.93333L3.33333 13.6Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
            <div class="rounded-2xl bg-stone-50 px-5 py-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Member</p>
                <h3 class="mt-2 text-lg font-semibold text-stone-900" data-access-member-name>Selected member</h3>
                <p class="mt-1 text-sm text-stone-500" data-access-member-email></p>
                <p class="mt-3 text-sm leading-6 text-stone-600" data-access-member-description>Store-scoped access profile.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-brand/10 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Store role</p>
                    <p class="mt-2 text-base font-semibold text-stone-900" data-access-member-role>staff</p>
                </div>
                <div class="rounded-2xl bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Global role</p>
                    <p class="mt-2 text-base font-semibold text-stone-900" data-access-member-global-role>user</p>
                </div>
                <div class="col-span-2 rounded-2xl bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Joined this store</p>
                    <p class="mt-2 text-base font-semibold text-stone-900" data-access-member-joined>Recently added</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Practical access</p>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-stone-600" data-access-member-list>
                    <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-brand"></span><span>Store-level access details will appear here.</span></li>
                </ul>
            </div>

            <div class="rounded-2xl bg-amber-50 px-5 py-5 text-sm leading-6 text-amber-800">
                This panel stays read-only by design. Advanced audit logs, permission history, and a deeper permission center remain deferred for a later sprint.
            </div>
        </div>

        <div class="border-t border-stone-200 px-6 py-5">
            <button type="button" data-close-team-access class="w-full rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">Done</button>
        </div>
    </div>
</aside>
