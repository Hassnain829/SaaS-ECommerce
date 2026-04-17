<div id="teamAccessOverlay" class="hidden fixed inset-0 z-40 bg-[#0F172A]/40 backdrop-blur-sm"></div>

<aside id="teamAccessPanel" class="fixed inset-y-0 right-0 z-50 w-full max-w-lg translate-x-full bg-white shadow-[0_20px_50px_rgba(15,23,42,0.16)] transition-transform duration-300">
    <div class="flex h-full flex-col">
        <div class="flex items-start justify-between gap-4 border-b border-[#E9EEF5] px-6 py-5">
            <div>
                
                <h2 class="mt-2 text-2xl font-semibold text-[#0F172A] font-poppins">Member Access Details</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">A read-only access panel for reviewing how a teammate fits into the active store's current role system.</p>
            </div>
            <button type="button" data-close-team-access class="rounded-2xl bg-[#F8FAFC] p-3 text-[#475569] hover:bg-[#EEF4FF]" aria-label="Close access panel">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M3.33333 13.6L2.4 12.6667L7.06667 8L2.4 3.33333L3.33333 2.4L8 7.06667L12.6667 2.4L13.6 3.33333L8.93333 8L13.6 12.6667L12.6667 13.6L8 8.93333L3.33333 13.6Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
            <div class="rounded-[24px] bg-[#F8FAFC] px-5 py-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Member</p>
                <h3 class="mt-2 text-xl font-semibold text-[#0F172A] font-poppins" data-access-member-name>Selected member</h3>
                <p class="mt-1 text-sm text-[#64748B]" data-access-member-email></p>
                <p class="mt-3 text-sm leading-6 text-[#475569]" data-access-member-description>Store-scoped access profile.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-[24px] bg-[#EEF4FF] px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#64748B]">Store Role</p>
                    <p class="mt-2 text-base font-semibold text-[#0F172A]" data-access-member-role>staff</p>
                </div>
                <div class="rounded-[24px] bg-[#F8FAFC] px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#64748B]">Global Role</p>
                    <p class="mt-2 text-base font-semibold text-[#0F172A]" data-access-member-global-role>user</p>
                </div>
                <div class="col-span-2 rounded-[24px] bg-[#F8FAFC] px-4 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-[#64748B]">Joined This Store</p>
                    <p class="mt-2 text-base font-semibold text-[#0F172A]" data-access-member-joined>Recently added</p>
                </div>
            </div>

            <div class="rounded-[24px] bg-white">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#64748B]">Practical Access</p>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-[#475569]" data-access-member-list>
                    <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Store-level access details will appear here.</span></li>
                </ul>
            </div>

            <div class="rounded-[24px] bg-[#FFF8E7] px-5 py-5 text-sm leading-6 text-[#8A5A00]">
                This panel stays read-only by design. Advanced audit logs, permission history, and a deeper permission center remain deferred for a later sprint.
            </div>
        </div>

        <div class="border-t border-[#E9EEF5] px-6 py-5">
            <button type="button" data-close-team-access class="w-full rounded-2xl bg-[#0F172A] px-5 py-3 text-sm font-bold text-white hover:bg-[#1E293B]">Done</button>
        </div>
    </div>
</aside>
