    <section class="space-y-4">
        <details class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <summary class="cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[#0F172A]">Ship-from locations</h3>
                        <p class="text-sm text-[#64748B]">Places where inventory is stored and orders ship from.</p>
                    </div>
                    <span class="text-xs font-semibold text-[#64748B]">Expand</span>
                </div>
            </summary>
            <div class="mt-4 border-t border-[#F1F5F9] pt-4">
                <section data-advanced-section="ship-from" class="scroll-mt-24">
                    <div class="mb-4">
                        <a href="{{ route('settings.locations.index') }}" class="text-sm font-semibold text-[#1D4ED8]">Open full locations page</a>
                    </div>
                    @include('user_view.shipping.tabs.locations')
                </section>
            </div>
        </details>

        <details class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <summary class="cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[#0F172A]">Delivery areas</h3>
                        <p class="text-sm text-[#64748B]">Country, region, and postal coverage rules.</p>
                    </div>
                    <span class="text-xs font-semibold text-[#64748B]">Expand</span>
                </div>
            </summary>
            <div class="mt-4 border-t border-[#F1F5F9] pt-4">
                <section data-advanced-section="areas" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.zones')
                </section>
            </div>
        </details>

        <details class="rounded-2xl border border-[#E2E8F0] bg-white p-4">
            <summary class="cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[#0F172A]">Delivery options</h3>
                        <p class="text-sm text-[#64748B]">Checkout labels, pricing, and visibility rules.</p>
                    </div>
                    <span class="text-xs font-semibold text-[#64748B]">Expand</span>
                </div>
            </summary>
            <div class="mt-4 border-t border-[#F1F5F9] pt-4">
                <section data-advanced-section="options" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.methods')
                </section>
            </div>
        </details>

        <details class="rounded-2xl border border-[color:var(--color-border)] bg-white p-4">
            <summary class="cursor-pointer list-none">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-[color:var(--color-ink)]">Connected providers</h3>
                        <p class="text-sm text-[color:var(--color-ink-muted)]">Carrier accounts for labels and rates.</p>
                    </div>
                    <span class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Expand</span>
                </div>
            </summary>
            <div class="mt-4 border-t border-[color:var(--color-border)] pt-4">
                <section data-advanced-section="providers" class="scroll-mt-24">
                    @include('user_view.shipping.tabs.carriers')
                    @include('user_view.shipping.partials.fedex_certification_tools')
                </section>
            </div>
        </details>
    </div>
</section>
