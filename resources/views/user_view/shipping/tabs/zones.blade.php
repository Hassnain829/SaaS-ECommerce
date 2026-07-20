@php
    $zonePresenter = app(\App\Services\Delivery\DeliveryAreaInputNormalizer::class);
@endphp
<section class="rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-[#F1F5F9] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-[#0F172A]">Delivery areas</h2>
            <p class="mt-1 text-sm text-[#64748B]">Define where customers can receive delivery. Delivery options match the customer address to a delivery area.</p>
        </div>
        @if ($canManageShipping)
            <button type="button" data-open-drawer="zone-add" class="inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Add delivery area</button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-[#F8FAFC] text-left text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                <tr>
                    <th class="px-5 py-3">Delivery area</th>
                    <th class="px-5 py-3">Coverage</th>
                    <th class="px-5 py-3">Options</th>
                    <th class="px-5 py-3">Status</th>
                    @if ($canManageShipping)<th class="px-5 py-3 text-right">Actions</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F1F5F9]">
                @forelse ($shippingZones as $zone)
                    <tr>
                        <td class="px-5 py-4 font-semibold text-[#0F172A]">{{ $zone->name }}</td>
                        <td class="px-5 py-4 text-[#64748B]">
                            {{ collect($zone->countries)->filter()->implode(', ') ?: 'Any' }}
                            @if (collect($zone->regions)->filter()->isNotEmpty()) · {{ collect($zone->regions)->filter()->implode(', ') }} @endif
                            @if (collect($zone->postal_patterns)->filter()->isNotEmpty()) · {{ collect($zone->postal_patterns)->filter()->implode(', ') }} @endif
                        </td>
                        <td class="px-5 py-4 text-[#64748B]">{{ $zone->shippingMethods->count() }}</td>
                        <td class="px-5 py-4"><span class="rounded-full {{ $statusBadge((bool) $zone->is_active) }} px-2.5 py-1 text-xs font-bold">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span></td>
                        @if ($canManageShipping)
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button" class="zone-edit-btn rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]"
                                        data-action="{{ route('settings.shipping.zones.update', $zone) }}"
                                        data-zone-form="{{ e(json_encode($zonePresenter->presentationFromZone($zone))) }}">Edit</button>
                                    <form method="POST" action="{{ route('settings.shipping.zones.destroy', $zone) }}" onsubmit="return confirm('Remove this delivery zone?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-1.5 text-xs font-semibold text-[#991B1B]">Remove</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManageShipping ? 5 : 4 }}" class="px-5 py-10 text-center text-[#64748B]">No delivery zones yet. Create United States, Local delivery area, or International.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
