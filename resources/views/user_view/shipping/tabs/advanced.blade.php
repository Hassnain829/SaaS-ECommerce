<section class="space-y-6">
    <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-5 py-4">
        <h2 class="text-lg font-semibold text-[#0F172A]">Advanced delivery settings</h2>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-[#64748B]">
            Use these sections when you need multiple ship-from locations, overlapping delivery areas, delivery provider connections, or fulfillment routing rules.
            Most stores can stay on the setup overview.
        </p>
    </div>

    <div class="space-y-8">
        <section data-advanced-section="ship-from" class="scroll-mt-24">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-[#0F172A]">Ship-from locations</h3>
                    <p class="text-sm text-[#64748B]">Places where inventory is stored and orders ship from.</p>
                </div>
                <a href="{{ route('settings.locations.index') }}" class="text-sm font-semibold text-[#1D4ED8]">Open full locations page</a>
            </div>
            @include('user_view.shipping.tabs.locations')
        </section>

        <section data-advanced-section="areas" class="scroll-mt-24 border-t border-[#F1F5F9] pt-8">
            @include('user_view.shipping.tabs.zones')
        </section>

        <section data-advanced-section="options" class="scroll-mt-24 border-t border-[#F1F5F9] pt-8">
            @include('user_view.shipping.tabs.methods')
        </section>

        <section data-advanced-section="providers" class="scroll-mt-24 border-t border-[#F1F5F9] pt-8">
            @include('user_view.shipping.tabs.carriers')
        </section>
    </div>
</section>
