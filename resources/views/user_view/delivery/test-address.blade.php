@extends('layouts.user.user-sidebar')

@section('title', 'Test checkout shipping — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Test checkout shipping" lead="Preview the delivery options a customer would see for an address and package.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="mx-auto max-w-[960px] space-y-6">
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <p class="text-sm text-[#64748B]">This tool does not change orders, checkout, tax, or inventory. Live FedEx quotes use the package you enter — no fake package is invented.</p>

            <form method="POST" action="{{ route('settings.delivery.test-address') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                @php
                    $selectedCountry = strtoupper((string) ($input['country_code'] ?? 'US'));
                    $selectedRegion = strtoupper((string) ($input['region_code'] ?? ''));
                    $selectedPreset = (string) ($input['package_preset_id'] ?? '');
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

                <div class="sm:col-span-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Package for live rates</p>
                    <p class="mt-1 text-xs text-[#64748B]">Choose a package preset, or enter custom weight and dimensions. Required for FedEx live quotes.</p>

                    <label class="mt-3 block space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Package preset</span>
                        <select name="package_preset_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            <option value="">Use custom package below{{ ($packagePresets ?? collect())->isEmpty() ? ' (no presets yet)' : ' or store default' }}</option>
                            @foreach ($packagePresets ?? [] as $preset)
                                <option value="{{ $preset->id }}" @selected($selectedPreset === (string) $preset->id)>
                                    {{ $preset->name }}{{ $preset->is_default ? ' (default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                        <label class="block space-y-1 sm:col-span-1">
                            <span class="text-xs font-semibold text-[#64748B]">Weight</span>
                            <input name="package_weight" type="number" min="0.01" step="0.01" value="{{ $input['package_weight'] ?? '' }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1 sm:col-span-1">
                            <span class="text-xs font-semibold text-[#64748B]">Unit</span>
                            <select name="package_weight_unit" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                @foreach (['LB', 'KG'] as $unit)
                                    <option value="{{ $unit }}" @selected(strtoupper((string) ($input['package_weight_unit'] ?? 'LB')) === $unit)>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold text-[#64748B]">Length</span>
                            <input name="package_length" type="number" min="0.01" step="0.01" value="{{ $input['package_length'] ?? '' }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold text-[#64748B]">Width</span>
                            <input name="package_width" type="number" min="0.01" step="0.01" value="{{ $input['package_width'] ?? '' }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold text-[#64748B]">Height</span>
                            <input name="package_height" type="number" min="0.01" step="0.01" value="{{ $input['package_height'] ?? '' }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold text-[#64748B]">Dim unit</span>
                            <select name="package_dimension_unit" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                @foreach (['IN', 'CM'] as $unit)
                                    <option value="{{ $unit }}" @selected(strtoupper((string) ($input['package_dimension_unit'] ?? 'IN')) === $unit)>{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white">Test checkout shipping</button>
                </div>
            </form>
        </section>

        @if ($result !== null)
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#0F172A]">Results</h2>

                @if (! empty($result['ship_from']))
                    <p class="mt-2 text-sm text-[#64748B]">Ship from: {{ $result['ship_from']['name'] }}</p>
                @endif

                @if (! ($result['package']['ready'] ?? false))
                    <p class="mt-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-sm text-[#92400E]">
                        Package not ready for live FedEx quotes:
                        {{ match ($result['package']['reason'] ?? '') {
                            'missing_weight' => 'missing weight',
                            'missing_dimensions' => 'missing dimensions',
                            'missing_package_preset' => 'no package preset or custom package',
                            'preset_incomplete' => 'selected preset is incomplete',
                            'preset_not_found' => 'package preset not found',
                            default => ($result['package']['reason'] ?? 'provide a preset or custom package'),
                        } }}.
                    </p>
                @endif

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
                                <div class="text-right">
                                    @if ($option['status'] === 'available' && $option['amount'] !== null)
                                        <p class="text-sm font-semibold tabular-nums text-[#0F172A]">
                                            {{ $option['currency_code'] }} {{ number_format((float) $option['amount'], 2) }}
                                        </p>
                                    @endif
                                    <span class="text-xs font-bold uppercase tracking-wide {{ $option['status'] === 'available' ? 'text-[#047857]' : 'text-[#64748B]' }}">{{ $option['status'] === 'available' ? 'Available' : 'Unavailable' }}</span>
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-[#64748B]">{{ $option['message'] }}</p>
                            @if (! empty($option['estimated_label']))
                                <p class="mt-1 text-xs text-[#64748B]">{{ $option['estimated_label'] }}</p>
                            @endif
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
