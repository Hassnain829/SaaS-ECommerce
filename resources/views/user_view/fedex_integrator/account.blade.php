@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Account Details | BaaS Core')

@section('content')
    <div class="mx-auto max-w-[900px] space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">Step 3 — FedEx account details</h2>
            <p class="mt-2 text-sm text-[#64748B]">Use the exact account address on file with FedEx. If it does not match, FedEx will reject registration.</p>
            @if ($validationModeEnabled && $validationPrefill)
                <p class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900">Validation mode: you may use the FedEx US sandbox validation test account from the integrator baseline.</p>
            @endif
            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.account.submit', $session) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf
                @php($prefill = $validationPrefill ?? [])
                <label class="sm:col-span-2 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">FedEx account number</span><input name="provider_account_number" required value="{{ old('provider_account_number', $prefill['account_number'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Account / company name</span><input name="company_name" required value="{{ old('company_name', $prefill['company_name'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Contact name</span><input name="contact_name" value="{{ old('contact_name') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Email</span><input name="email" type="email" value="{{ old('email') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Phone</span><input name="phone" value="{{ old('phone') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="sm:col-span-2 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 1</span><input name="address_line1" required value="{{ old('address_line1', $prefill['address_line1'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="sm:col-span-2 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 2</span><input name="address_line2" value="{{ old('address_line2') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">City</span><input name="city" required value="{{ old('city', $prefill['city'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">State</span><input name="state" required value="{{ old('state', $prefill['state'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal code</span><input name="postal_code" required value="{{ old('postal_code', $prefill['postal_code'] ?? '') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Country</span><input name="country_code" required maxlength="2" value="{{ old('country_code', $prefill['country_code'] ?? 'US') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="residential" value="1" class="rounded border-[#CBD5E1]"> Residential address</label>
                <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Submit to FedEx registration</button></div>
            </form>
        </section>
    </div>
@endsection
