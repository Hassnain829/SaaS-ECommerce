@php
    $billing = $billing ?? [];
    $billingSameAsShipping = $billingSameAsShipping ?? true;
    $isEditable = $isEditable ?? true;
    $countryDatalistId = $countryDatalistId ?? 'draft-billing-country-codes';
    $fieldPrefix = $fieldPrefix ?? 'billing';
@endphp

<div class="draft-billing-fields mt-4 {{ $billingSameAsShipping ? 'hidden' : '' }}" data-draft-billing-fields>
    <p class="text-sm font-semibold text-[#0F172A]">Billing address</p>
    <p class="mt-1 text-xs text-[#64748B]">Required when billing is different from shipping.</p>
    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach([
            'billing_name' => 'Billing recipient name',
            'billing_phone' => 'Billing phone',
            'billing_address_line1' => 'Billing address line 1',
            'billing_address_line2' => 'Billing address line 2',
            'billing_city' => 'Billing city',
        ] as $name => $placeholder)
            @php($key = str_replace('billing_', '', $name))
            <input
                name="{{ $name }}"
                value="{{ old($name, $billing[$key] ?? '') }}"
                class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ str_contains($name, 'address_line') ? 'md:col-span-2' : '' }} {{ $errors->has($name) ? 'border-[#F87171]' : '' }}"
                placeholder="{{ $placeholder }}"
                @readonly(! $isEditable)
                @error($name) aria-invalid="true" @enderror
            >
            @error($name)
                <p class="-mt-3 text-xs text-[#B91C1C] {{ str_contains($name, 'address_line') ? 'md:col-span-2' : '' }}">{{ $message }}</p>
            @enderror
        @endforeach
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Billing state / region code</span>
            <input name="billing_state" value="{{ old('billing_state', $billing['state'] ?? '') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase {{ $errors->has('billing_state') ? 'border-[#F87171]' : '' }}" placeholder="CA, NY, ON" @readonly(! $isEditable) @error('billing_state') aria-invalid="true" @enderror>
            @error('billing_state')
                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Billing postal code</span>
            <input name="billing_postal_code" value="{{ old('billing_postal_code', $billing['postal_code'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_postal_code') ? 'border-[#F87171]' : '' }}" placeholder="Postal code" @readonly(! $isEditable) @error('billing_postal_code') aria-invalid="true" @enderror>
            @error('billing_postal_code')
                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Billing country code</span>
            <input
                name="billing_country"
                value="{{ old('billing_country', $billing['country'] ?? '') }}"
                list="{{ $countryDatalistId }}"
                maxlength="2"
                pattern="[A-Za-z]{2}"
                autocomplete="off"
                title="Enter a two-letter country code such as US, CA, GB, or AU."
                class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase {{ $errors->has('billing_country') ? 'border-[#F87171]' : '' }}"
                placeholder="US, CA, GB, AU"
                @readonly(! $isEditable)
                @error('billing_country') aria-invalid="true" @enderror
            >
            <datalist id="{{ $countryDatalistId }}">
                @include('user_view.partials.country_code_options')
            </datalist>
            @error('billing_country')
                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
    </div>
</div>
