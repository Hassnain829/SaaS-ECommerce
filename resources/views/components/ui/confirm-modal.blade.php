<div id="uiConfirmModal" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="uiConfirmTitle">
    <div class="ui-modal-panel ui-modal-panel--md border-[#FECACA]" data-ui-confirm-panel>
        <div class="px-6 pb-4 pt-6" data-ui-confirm-hero>
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm" data-ui-confirm-icon aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64 18.3 1.55 18.65 1.55 19C1.55 19.35 1.64 19.7 1.81 20C1.99 20.31 2.24 20.56 2.54 20.74C2.85 20.92 3.19 21.02 3.54 21.02H20.46C20.81 21.02 21.15 20.92 21.46 20.74C21.76 20.56 22.01 20.31 22.19 20C22.36 19.7 22.45 19.35 22.45 19C22.45 18.65 22.36 18.3 22.18 18L13.71 3.86C13.53 3.56 13.28 3.32 12.97 3.15C12.67 2.98 12.33 2.89 11.98 2.89C11.64 2.89 11.3 2.98 10.99 3.15C10.69 3.32 10.44 3.57 10.26 3.86L10.29 3.86Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 id="uiConfirmTitle" class="mt-5 text-section font-semibold text-[#0F172A]">Please confirm</h3>
            <p id="uiConfirmLead" class="mt-2 text-sm leading-6 text-[#64748B]"></p>
        </div>
        <div class="px-6 pb-6 pt-2">
            <div class="hidden rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4" data-ui-confirm-callout>
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]" data-ui-confirm-callout-label>Warning</p>
                <p class="mt-2 text-sm text-[#7F1D1D]" data-ui-confirm-callout-body></p>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-ui-confirm-cancel>Cancel</button>
                <button type="button" class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]" data-ui-confirm-ok>Confirm</button>
            </div>
        </div>
    </div>
</div>
