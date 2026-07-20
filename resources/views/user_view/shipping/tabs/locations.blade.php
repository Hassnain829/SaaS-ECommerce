<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-[#0F172A]">Fulfillment locations</h2>
            <p class="mt-1 text-sm text-[#64748B]">Ship-from addresses for carrier rates, labels, and fulfillment. Not the same as your store business address.</p>
        </div>
        <a href="{{ route('settings.locations.index') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Manage locations</a>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($locations as $location)
            @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
            <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-[#0F172A]">{{ $location->name }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if ($location->is_default)
                            <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Default origin</span>
                        @endif
                        @if ($readiness)
                            <span class="rounded-full {{ $readiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-2.5 py-1 text-xs font-bold">{{ $readiness->badgeLabel }}</span>
                        @endif
                    </div>
                </div>
                <p class="mt-2 text-sm text-[#64748B]">{{ $readiness?->displayAddress ?: collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') }}</p>
                @if ($readiness && ! $readiness->ready)
                    <p class="mt-2 text-xs text-[#64748B]">{{ $readiness->merchantMessage }}</p>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-8 text-center text-sm text-[#64748B]">No fulfillment locations yet.</div>
        @endforelse
    </div>

    <div class="mt-6 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
        <p class="font-semibold text-[#0F172A]">Automation</p>
        <p class="mt-1">Carrier routing automation and advanced fulfillment workflows will be added in later phases.</p>
    </div>
</section>
