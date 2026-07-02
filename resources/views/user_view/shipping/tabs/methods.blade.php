<section class="rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-[#F1F5F9] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Delivery options</h2>
            <p class="mt-1 text-sm text-[#64748B]">Choices customers see at checkout, such as Standard delivery, Express, Local delivery, or Store pickup.</p>
        </div>
        @if ($canManageShipping && $shippingZones->isNotEmpty())
            <button type="button" data-open-drawer="method-add" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Add delivery option</button>
        @endif
    </div>

    <div class="divide-y divide-[#F1F5F9]">
        @forelse ($shippingMethods as $method)
            <article class="flex flex-col gap-3 p-5 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-[#0F172A]">{{ $method->name }}</h3>
                        <span class="rounded-full {{ $statusBadge((bool) $method->is_active) }} px-2.5 py-1 text-xs font-bold">{{ $method->is_active ? 'Active' : 'Inactive' }}</span>
                        @if ($method->enabled_for_checkout)<span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">At checkout</span>@endif
                        @if ($method->is_active && ! $method->enabled_for_checkout)
                            <span class="rounded-full bg-[#FEF3C7] px-2.5 py-1 text-xs font-bold text-[#92400E]">Hidden from checkout</span>
                        @elseif (! $method->is_active && $method->enabled_for_checkout)
                            <span class="rounded-full bg-[#FEF3C7] px-2.5 py-1 text-xs font-bold text-[#92400E]">Inactive at checkout</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-[#64748B]">
                        Delivery area: {{ $method->shippingZone?->name ?? '—' }}
                        · {{ $rateLabels[$method->rate_type] ?? $method->rate_type }}
                        · Provider: {{ $method->carrierAccount?->display_name ?? 'Manual delivery' }}
                    </p>
                    <p class="mt-1 text-xs text-[#94A3B8]">
                        @if ((float) $method->flat_rate > 0) {{ $selectedStore->currency ?? 'USD' }} {{ number_format((float) $method->flat_rate, 2) }} @endif
                        @if ($method->delivery_speed_label) · {{ $method->delivery_speed_label }} @endif
                        @if ($method->estimated_min_days !== null && $method->estimated_max_days !== null) · {{ $method->estimated_min_days }}-{{ $method->estimated_max_days }} days @endif
                    </p>
                </div>
                @if ($canManageShipping)
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @php
                            $priceMode = $method->rate_type === 'free'
                                ? 'free'
                                : ((float) ($method->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
                            $flagMismatch = $method->is_active !== $method->enabled_for_checkout;
                        @endphp
                        <button type="button" class="method-edit-btn rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-xs font-semibold text-[#475569]"
                            data-action="{{ route('settings.shipping.methods.update', $method) }}"
                            data-name="{{ $method->name }}"
                            data-zone="{{ $method->shipping_zone_id }}"
                            data-carrier="{{ $method->carrier_account_id }}"
                            data-rate-type="{{ $method->rate_type }}"
                            data-price-mode="{{ $priceMode }}"
                            data-label="{{ $method->delivery_speed_label }}"
                            data-flat="{{ $method->flat_rate }}"
                            data-free-over="{{ $method->free_over_amount }}"
                            data-min-order="{{ $method->min_order_amount }}"
                            data-max-order="{{ $method->max_order_amount }}"
                            data-min-days="{{ $method->estimated_min_days }}"
                            data-max-days="{{ $method->estimated_max_days }}"
                            data-description="{{ $method->description }}"
                            data-sort="{{ $method->sort_order }}"
                            data-checkout="{{ $method->enabled_for_checkout ? '1' : '0' }}"
                            data-active="{{ $method->is_active ? '1' : '0' }}"
                            data-flag-mismatch="{{ $flagMismatch ? '1' : '0' }}">Edit</button>
                        <form method="POST" action="{{ route('settings.shipping.methods.destroy', $method) }}" onsubmit="return confirm('Remove this delivery method?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="p-10 text-center text-[#64748B]">No delivery methods yet.</div>
        @endforelse
    </div>
</section>
