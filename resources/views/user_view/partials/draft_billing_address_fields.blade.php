@php
    $billing = $billing ?? [];
    $billingSameAsShipping = $billingSameAsShipping ?? true;
    $isEditable = $isEditable ?? true;
    $countryDatalistId = $countryDatalistId ?? 'draft-billing-country-codes';
@endphp

<div class="draft-billing-fields mt-4" data-draft-billing-fields>
    <p class="text-sm font-semibold text-[#0F172A]">Billing address</p>
    <p class="mt-1 text-xs text-[#64748B]">Required when billing is different from shipping.</p>
    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Billing recipient name</span>
            <input id="billing_name" name="billing_name" value="{{ old('billing_name', $billing['name'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_name') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_name') aria-invalid="true" aria-describedby="billing_name_error" @enderror>
            @error('billing_name')
                <p id="billing_name_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Billing phone</span>
            <input id="billing_phone" name="billing_phone" value="{{ old('billing_phone', $billing['phone'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_phone') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_phone') aria-invalid="true" aria-describedby="billing_phone_error" @enderror>
            @error('billing_phone')
                <p id="billing_phone_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-semibold text-[#64748B]">Billing address line 1</span>
            <input id="billing_address_line1" name="billing_address_line1" value="{{ old('billing_address_line1', $billing['address_line1'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_address_line1') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_address_line1') aria-invalid="true" aria-describedby="billing_address_line1_error" @enderror>
            @error('billing_address_line1')
                <p id="billing_address_line1_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-semibold text-[#64748B]">Billing address line 2</span>
            <input id="billing_address_line2" name="billing_address_line2" value="{{ old('billing_address_line2', $billing['address_line2'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_address_line2') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_address_line2') aria-invalid="true" aria-describedby="billing_address_line2_error" @enderror>
            @error('billing_address_line2')
                <p id="billing_address_line2_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Billing city</span>
            <input id="billing_city" name="billing_city" value="{{ old('billing_city', $billing['city'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_city') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_city') aria-invalid="true" aria-describedby="billing_city_error" @enderror>
            @error('billing_city')
                <p id="billing_city_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Billing state / region code</span>
            <input id="billing_state" name="billing_state" value="{{ old('billing_state', $billing['state'] ?? '') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase {{ $errors->has('billing_state') ? 'border-[#F87171]' : '' }}" placeholder="CA, NY, ON" @readonly(! $isEditable) @error('billing_state') aria-invalid="true" aria-describedby="billing_state_error" @enderror>
            @error('billing_state')
                <p id="billing_state_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Billing postal code</span>
            <input id="billing_postal_code" name="billing_postal_code" value="{{ old('billing_postal_code', $billing['postal_code'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $errors->has('billing_postal_code') ? 'border-[#F87171]' : '' }}" @readonly(! $isEditable) @error('billing_postal_code') aria-invalid="true" aria-describedby="billing_postal_code_error" @enderror>
            @error('billing_postal_code')
                <p id="billing_postal_code_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block md:col-span-2">
            <span class="text-xs font-semibold text-[#64748B]">Billing country code</span>
            <input
                id="billing_country"
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
                @error('billing_country') aria-invalid="true" aria-describedby="billing_country_error" @enderror
            >
            <datalist id="{{ $countryDatalistId }}">
                @include('user_view.partials.country_code_options')
            </datalist>
            @error('billing_country')
                <p id="billing_country_error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
    </div>
</div>
