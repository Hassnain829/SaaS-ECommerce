@php
    $packagePresets = $packagePresets ?? collect();
@endphp

<section class="rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-[#F1F5F9] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-[#0F172A]">Package sizes</h2>
            <p class="mt-1 text-sm text-[#64748B]">
                Default box sizes used for carrier rates when a product does not have its own dimensions.
            </p>
        </div>
    </div>

    <div class="divide-y divide-[#F1F5F9]">
        @forelse ($packagePresets as $preset)
            <article class="flex flex-col gap-3 p-5 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-[#0F172A]">{{ $preset->name }}</h3>
                        @if ($preset->is_default)
                            <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Default</span>
                        @endif
                        <span class="rounded-full {{ $statusBadge((bool) $preset->is_active) }} px-2.5 py-1 text-xs font-bold">
                            {{ $preset->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-[#64748B]">
                        {{ number_format((float) $preset->length, 1) }}
                        × {{ number_format((float) $preset->width, 1) }}
                        × {{ number_format((float) $preset->height, 1) }}
                        {{ $preset->dimension_unit ?: 'IN' }}
                        @if ($preset->weight_value)
                            · {{ number_format((float) $preset->weight_value, 2) }} {{ $preset->weight_unit ?: 'LB' }}
                        @endif
                        @if ($preset->package_type)
                            · {{ $preset->package_type }}
                        @endif
                    </p>
                </div>
                @if ($canManageShipping ?? false)
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if (! $preset->is_default)
                            <form method="POST" action="{{ route('settings.shipping.package-presets.default', $preset) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-xs font-semibold text-[#475569]">Make default</button>
                            </form>
                        @endif
                        <details class="relative">
                            <summary class="cursor-pointer list-none rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-xs font-semibold text-[#475569]">Edit</summary>
                            <form method="POST" action="{{ route('settings.shipping.package-presets.update', $preset) }}" class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-[#E2E8F0] bg-white p-3 shadow-lg">
                                @csrf
                                @method('PATCH')
                                <label class="mb-2 block text-xs font-semibold text-[#475569]">Name
                                    <input name="name" type="text" required maxlength="120" value="{{ $preset->name }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm">
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="block text-xs font-semibold text-[#475569]">L<input name="length" type="number" min="0.01" step="0.01" required value="{{ $preset->length }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm"></label>
                                    <label class="block text-xs font-semibold text-[#475569]">W<input name="width" type="number" min="0.01" step="0.01" required value="{{ $preset->width }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm"></label>
                                    <label class="block text-xs font-semibold text-[#475569]">H<input name="height" type="number" min="0.01" step="0.01" required value="{{ $preset->height }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm"></label>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <label class="block text-xs font-semibold text-[#475569]">Weight<input name="weight_value" type="number" min="0.01" step="0.01" value="{{ $preset->weight_value }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm"></label>
                                    <label class="block text-xs font-semibold text-[#475569]">Unit
                                        <select name="weight_unit" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-2 py-1.5 text-sm">
                                            <option value="LB" @selected(($preset->weight_unit ?: 'LB') === 'LB')>LB</option>
                                            <option value="KG" @selected($preset->weight_unit === 'KG')>KG</option>
                                        </select>
                                    </label>
                                </div>
                                <input type="hidden" name="dimension_unit" value="{{ $preset->dimension_unit ?: 'IN' }}">
                                <input type="hidden" name="is_active" value="{{ $preset->is_active ? '1' : '0' }}">
                                <button type="submit" class="mt-3 inline-flex h-8 w-full items-center justify-center rounded-lg bg-brand text-xs font-bold text-white">Save changes</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('settings.shipping.package-presets.destroy', $preset) }}" onsubmit="return confirm('Remove this package size?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="p-5 text-sm text-[#64748B]">
                No package sizes yet. Add a default box so FedEx live rates can use real dimensions.
            </div>
        @endforelse
    </div>

    @if ($canManageShipping ?? false)
        @unless ($hideShippingDefaults ?? false)
        @php($prefs = $shippingPreferences ?? [])
        <div class="border-t border-[#F1F5F9] px-5 py-5">
            <h3 class="text-sm font-semibold text-[#0F172A]">Shipping defaults</h3>
            <p class="mt-1 text-xs text-[#64748B]">Used for FedEx labels and checkout when a shipment does not override them.</p>
            <form method="POST" action="{{ route('settings.shipping.preferences.update') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="shipping_pref_label_format">Label format</label>
                    <select id="shipping_pref_label_format" name="default_label_format" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]">
                        @foreach (['PDF', 'PNG', 'ZPL'] as $format)
                            <option value="{{ $format }}" @selected(old('default_label_format', $prefs['default_label_format'] ?? 'PDF') === $format)>{{ $format }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="shipping_pref_handoff">Handoff to FedEx</label>
                    <select id="shipping_pref_handoff" name="default_handoff_type" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]">
                        <option value="DROPOFF_AT_FEDEX_LOCATION" @selected(old('default_handoff_type', $prefs['default_handoff_type'] ?? '') === 'DROPOFF_AT_FEDEX_LOCATION')>I will drop it off at FedEx</option>
                        <option value="USE_SCHEDULED_PICKUP" @selected(old('default_handoff_type', $prefs['default_handoff_type'] ?? '') === 'USE_SCHEDULED_PICKUP')>I already have a regular FedEx pickup</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="shipping_pref_signature">Signature option</label>
                    <select id="shipping_pref_signature" name="default_signature_option" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]">
                        <option value="SERVICE_DEFAULT" @selected(old('default_signature_option', $prefs['default_signature_option'] ?? 'SERVICE_DEFAULT') === 'SERVICE_DEFAULT')>No signature</option>
                        <option value="INDIRECT" @selected(old('default_signature_option', $prefs['default_signature_option'] ?? '') === 'INDIRECT')>Indirect signature</option>
                        <option value="DIRECT" @selected(old('default_signature_option', $prefs['default_signature_option'] ?? '') === 'DIRECT')>Direct signature</option>
                        <option value="ADULT" @selected(old('default_signature_option', $prefs['default_signature_option'] ?? '') === 'ADULT')>Adult signature</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                        <input type="hidden" name="saturday_delivery_default" value="0">
                        <input type="checkbox" name="saturday_delivery_default" value="1" class="rounded border-[#CBD5E1]"
                            @checked(old('saturday_delivery_default', ($prefs['saturday_delivery_default'] ?? false) ? '1' : '0') === '1')>
                        Saturday delivery by default
                    </label>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Save shipping defaults</button>
                </div>
            </form>
        </div>
        @endunless

        <div class="border-t border-[#F1F5F9] px-5 py-5">
            <h3 class="text-sm font-semibold text-[#0F172A]">Add package size</h3>
            <form method="POST" action="{{ route('settings.shipping.package-presets.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_name">Name</label>
                    <input id="package_preset_name" name="name" type="text" required maxlength="120" placeholder="e.g. Small box"
                        class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_length">Length</label>
                    <input id="package_preset_length" name="length" type="number" min="0.01" step="0.01" required
                        class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]" value="{{ old('length') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_width">Width</label>
                    <input id="package_preset_width" name="width" type="number" min="0.01" step="0.01" required
                        class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]" value="{{ old('width') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_height">Height</label>
                    <input id="package_preset_height" name="height" type="number" min="0.01" step="0.01" required
                        class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]" value="{{ old('height') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_dimension_unit">Dimension unit</label>
                    <select id="package_preset_dimension_unit" name="dimension_unit" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]">
                        <option value="IN" @selected(old('dimension_unit', 'IN') === 'IN')>Inches</option>
                        <option value="CM" @selected(old('dimension_unit') === 'CM')>Centimeters</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_weight">Weight (optional)</label>
                    <input id="package_preset_weight" name="weight_value" type="number" min="0.01" step="0.01"
                        class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]" value="{{ old('weight_value') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-[#475569]" for="package_preset_weight_unit">Weight unit</label>
                    <select id="package_preset_weight_unit" name="weight_unit" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm text-[#0F172A]">
                        <option value="LB" @selected(old('weight_unit', 'LB') === 'LB')>LB</option>
                        <option value="KG" @selected(old('weight_unit') === 'KG')>KG</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-[#CBD5E1]" @checked(old('is_active', '1') === '1')>
                        Active
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" class="rounded border-[#CBD5E1]" @checked(old('is_default') === '1')>
                        Set as default
                    </label>
                    <button type="submit" class="ml-auto inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">
                        Save package size
                    </button>
                </div>
            </form>
        </div>
    @endif
</section>
