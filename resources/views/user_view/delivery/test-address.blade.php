@extends('layouts.user.user-sidebar')

@section('title', 'Test a customer address | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Test a customer address" lead="Preview delivery areas and checkout options for an address.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="mx-auto max-w-[960px] space-y-6">
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#64748B]">This tool does not change orders, checkout, tax, or inventory. It explains which delivery options would apply for the address you enter.</p>

            <form method="POST" action="{{ route('settings.delivery.test-address') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                @php
                    $selectedCountry = strtoupper((string) ($input['country_code'] ?? 'US'));
                    $selectedRegion = strtoupper((string) ($input['region_code'] ?? ''));
                @endphp
                <x-geo.country-select name="country_code" :selected="$selectedCountry" :countries="$countries" required />
                <x-geo.region-select name="region_code" :country-code="$selectedCountry" :selected="$selectedRegion" label="State / province (optional)" />
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">ZIP / postal code</span>
                    <input name="postal_code" value="{{ $input['postal_code'] ?? '' }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Order subtotal (optional)</span>
                    <input name="order_subtotal" type="number" min="0" step="0.01" value="{{ $input['order_subtotal'] ?? '' }}" placeholder="50.00" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-bold text-white">Test address</button>
                </div>
            </form>
        </section>

        @if ($result !== null)
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#0F172A]">Results</h2>

                @if ($result['has_matching_area'])
                    <p class="mt-2 text-sm text-[#64748B]">Matched delivery area(s):
                        {{ collect($result['matched_areas'])->pluck('name')->implode(', ') ?: 'None' }}
                    </p>
                @else
                    <p class="mt-2 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-sm text-[#991B1B]">No active delivery area matches this address.</p>
                @endif

                <ul class="mt-4 space-y-3">
                    @forelse ($result['options'] as $option)
                        <li class="rounded-xl border px-4 py-3 {{ $option['status'] === 'available' ? 'border-[#BBF7D0] bg-[#F0FDF4]' : 'border-[#E2E8F0] bg-[#F8FAFC]' }}">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-semibold text-[#0F172A]">{{ $option['name'] }}</p>
                                <span class="text-xs font-bold uppercase tracking-wide {{ $option['status'] === 'available' ? 'text-[#047857]' : 'text-[#64748B]' }}">{{ $option['status'] === 'available' ? 'Available' : 'Unavailable' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-[#64748B]">{{ $option['message'] }}</p>
                            @if (! empty($option['delivery_area']))
                                <p class="mt-1 text-xs text-[#94A3B8]">Delivery area: {{ $option['delivery_area'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-[#64748B]">No delivery options are configured for this store.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
@endsection
