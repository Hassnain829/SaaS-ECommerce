@extends('layouts.user.user-sidebar')

@section('title', 'Taxes | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-8">
        <button id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm md:hidden" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div>
            <h1 class="font-poppins text-lg font-semibold md:text-xl">Taxes</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Configure the basic tax rates used by your platform checkout.</p>
        </div>
        <a href="{{ route('generalSettings') }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">General settings</a>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[1280px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] p-5 shadow-sm">
            <p class="text-sm font-semibold text-[#0F172A]">Tax configuration is being prepared</p>
            <p class="mt-2 text-sm leading-relaxed text-[#475569]">Checkout calculation will be enabled after the tax calculation phase is completed. Saving settings here stores your preferences for when platform checkout tax goes live.</p>
            <p class="mt-3 text-sm leading-relaxed text-[#475569]">These are basic configurable tax rates and are not tax or legal advice. Confirm the correct rates and rules with your accountant or tax adviser.</p>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Store tax settings</p>
            <h2 class="mt-1 font-[Poppins] text-xl font-semibold text-[#0F172A]">Platform checkout tax</h2>
            <p class="mt-2 text-sm text-[#64748B]">Enabling tax does not change historical orders. Open checkouts will recalculate only when shipping or address details change after tax calculation is wired in a later release.</p>

            @if ($canManageTax)
                <form method="POST" action="{{ route('settings.taxes.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="calculation_address" value="shipping">

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="enabled" value="1" class="mt-1" @checked(old('enabled', $taxSetting->enabled))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Enable tax calculation for platform checkout</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Turns on tax when checkout calculation is released.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="prices_include_tax" value="1" class="mt-1" @checked(old('prices_include_tax', $taxSetting->prices_include_tax))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Product prices include tax</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Catalog prices already include tax when this is on.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="default_product_taxable" value="1" class="mt-1" @checked(old('default_product_taxable', $taxSetting->default_product_taxable))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">New products are taxable by default</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Applies to new products created after you save this setting.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="shipping_taxable" value="1" class="mt-1" @checked(old('shipping_taxable', $taxSetting->shipping_taxable))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Charge tax on shipping</span>
                        </span>
                    </label>

                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Calculation address</p>
                        <p class="mt-1 text-sm text-[#64748B]">Customer shipping address</p>
                    </div>

                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-semibold text-white hover:bg-[#0047B3]">Save tax settings</button>
                </form>
            @else
                <div class="mt-6 space-y-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm text-[#475569]">
                    <p><span class="font-semibold text-[#0F172A]">Enable tax:</span> {{ $taxSetting->enabled ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Prices include tax:</span> {{ $taxSetting->prices_include_tax ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">New products taxable by default:</span> {{ $taxSetting->default_product_taxable ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Charge tax on shipping:</span> {{ $taxSetting->shipping_taxable ? 'Yes' : 'No' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Calculation address:</span> Customer shipping address</p>
                    <p class="pt-2 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Tax rates</p>
                    <h2 class="mt-1 font-[Poppins] text-xl font-semibold text-[#0F172A]">Country and region rates</h2>
                    <p class="mt-2 text-sm text-[#64748B]">Leave region blank to apply the rate country-wide. A specific region rate will take priority over the country-wide rate when checkout tax calculation is live.</p>
                </div>
            </div>

            @if ($canManageTax)
                <form method="POST" action="{{ route('settings.taxes.rates.store') }}" class="mt-6 grid gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 md:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <label class="space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Rate name</span>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Country code</span>
                        <input type="text" name="country_code" value="{{ old('country_code') }}" required maxlength="2" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm uppercase">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Region / state (optional)</span>
                        <input type="text" name="region_code" value="{{ old('region_code') }}" maxlength="32" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm uppercase">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Rate %</span>
                        <input type="text" name="rate_percent" value="{{ old('rate_percent') }}" required class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Priority</span>
                        <input type="number" name="priority" value="{{ old('priority', 100) }}" min="0" max="65535" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                    </label>
                    <label class="flex items-center gap-2 self-end pb-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span class="text-sm font-semibold text-[#0F172A]">Active</span>
                    </label>
                    <div class="md:col-span-2 lg:col-span-3">
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-semibold text-white hover:bg-[#0047B3]">Add tax rate</button>
                    </div>
                </form>
            @endif

            @if ($taxRates->isEmpty())
                <div class="mt-6 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-8 text-center text-sm text-[#64748B]">
                    No tax rates have been added yet.<br>
                    Add a country-wide or regional rate when you are ready.
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#E2E8F0] text-xs uppercase tracking-wide text-[#64748B]">
                            <tr>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Jurisdiction</th>
                                <th class="px-3 py-2">Rate</th>
                                <th class="px-3 py-2">Priority</th>
                                <th class="px-3 py-2">Status</th>
                                @if ($canManageTax)
                                    <th class="px-3 py-2">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach ($taxRates as $rate)
                                <tr>
                                    <td class="px-3 py-3 font-semibold text-[#0F172A]">{{ $rate->name }}</td>
                                    <td class="px-3 py-3 text-[#475569]">{{ $rate->country_code }} · {{ $rate->region_code === '' ? 'All regions' : $rate->region_code }}</td>
                                    <td class="px-3 py-3 text-[#475569]">{{ $rate->rate_percent }}%</td>
                                    <td class="px-3 py-3 text-[#475569]">{{ $rate->priority }}</td>
                                    <td class="px-3 py-3 text-[#475569]">{{ $rate->is_active ? 'Active' : 'Inactive' }}</td>
                                    @if ($canManageTax)
                                        <td class="px-3 py-3">
                                            <details class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                                                <summary class="cursor-pointer text-sm font-semibold text-[#0052CC]">Edit</summary>
                                                <form method="POST" action="{{ route('settings.taxes.rates.update', $rate) }}" class="mt-3 grid gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="text" name="name" value="{{ old('name', $rate->name) }}" required maxlength="120" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <input type="text" name="country_code" value="{{ old('country_code', $rate->country_code) }}" required maxlength="2" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm uppercase">
                                                        <input type="text" name="region_code" value="{{ old('region_code', $rate->region_code) }}" maxlength="32" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm uppercase" placeholder="Region optional">
                                                    </div>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <input type="text" name="rate_percent" value="{{ old('rate_percent', $rate->rate_percent) }}" required class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                                        <input type="number" name="priority" value="{{ old('priority', $rate->priority) }}" min="0" max="65535" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm">
                                                    </div>
                                                    <label class="flex items-center gap-2">
                                                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rate->is_active))>
                                                        <span class="text-sm">Active</span>
                                                    </label>
                                                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white">Save rate</button>
                                                </form>
                                                <form method="POST" action="{{ route('settings.taxes.rates.destroy', $rate) }}" class="mt-2" onsubmit="return confirm('Remove this tax rate? Existing checkout snapshots are not changed.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-700">Delete rate</button>
                                                </form>
                                            </details>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (! $canManageTax)
                <p class="mt-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
            @endif
        </section>
    </div>
@endsection
