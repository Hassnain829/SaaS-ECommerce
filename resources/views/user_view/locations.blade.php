@extends('layouts.user.user-sidebar')

@section('title', 'Locations | BaaS Core')

@php
    $typeLabels = [
        'warehouse' => 'Warehouse',
        'store' => 'Store / shop',
        'third_party' => 'Third-party storage',
        'other' => 'Other',
    ];
@endphp

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold">Locations</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Places where {{ $selectedStore?->name ?? 'your store' }} keeps inventory.</p>
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

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Inventory settings</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Inventory locations</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations are places where you store or fulfill inventory, such as a warehouse, shop, stock room, restaurant branch, or third-party storage.</p>
                    <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations control where stock is stored. Markets and currencies control where and how you sell. Market-specific selling settings will be added later.</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">What locations are used for</h3>
                            <ul class="mt-3 space-y-2 text-sm text-[#64748B]">
                                <li>Inventory levels</li>
                                <li>Reservations</li>
                                <li>Stock movements</li>
                                <li>Future fulfillment origin</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">What locations do not control</h3>
                            <ul class="mt-3 space-y-2 text-sm text-[#64748B]">
                                <li>Customer markets</li>
                                <li>Selling currencies</li>
                                <li>Language</li>
                                <li>Regional pricing</li>
                                <li>Storefront availability</li>
                            </ul>
                        </div>
                    </div>
                </div>
                @if ($canManageLocations)
                    <form method="POST" action="{{ route('settings.locations.store') }}" class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 lg:max-w-xl">
                        @csrf
                        <p class="text-sm font-semibold text-[#0F172A]">Add location</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Name</span>
                                <input name="name" value="{{ old('name') }}" placeholder="Main warehouse" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Type</span>
                                <select name="type" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                    @foreach ($locationTypes as $type)
                                        <option value="{{ $type }}">{{ $typeLabels[$type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $type)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 sm:col-span-2">
                                <span class="text-xs font-semibold text-[#64748B]">Address</span>
                                <input name="address_line1" value="{{ old('address_line1') }}" placeholder="Street, building, or storage note" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">City</span>
                                <input name="city" value="{{ old('city') }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Country code</span>
                                <input name="country_code" value="{{ old('country_code') }}" maxlength="2" placeholder="US" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                            </label>
                        </div>
                        <button type="submit" class="mt-4 inline-flex rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Add location</button>
                    </form>
                @else
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                        You can view locations. Store owners manage location changes.
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#F1F5F9] px-5 py-4">
                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Current locations</h2>
                <p class="mt-1 text-sm text-[#64748B]">One active default location is used when imports, quick add, product edits, and storefront orders need a stock location.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#F8FAFC] text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                        <tr>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Address</th>
                            <th class="px-5 py-3">Default</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach ($locations as $location)
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-[#0F172A]">{{ $location->name }}</p>
                                    <p class="mt-1 text-xs text-[#94A3B8]">{{ $location->inventory_levels_count }} inventory row(s)</p>
                                </td>
                                <td class="px-5 py-4 align-top text-[#334155]">{{ $typeLabels[$location->type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $location->type)) }}</td>
                                <td class="px-5 py-4 align-top text-[#64748B]">
                                    {{ collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') ?: 'No address saved' }}
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if ($location->is_default)
                                        <span class="rounded-full bg-[#ECFDF5] px-2.5 py-1 text-xs font-bold text-[#047857]">Default</span>
                                    @else
                                        <span class="text-xs text-[#94A3B8]">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <span class="rounded-full {{ $location->is_active ? 'bg-[#EFF6FF] text-[#1D4ED8]' : 'bg-[#F1F5F9] text-[#64748B]' }} px-2.5 py-1 text-xs font-bold">{{ $location->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if ($canManageLocations)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if (! $location->is_default)
                                                <form method="POST" action="{{ route('settings.locations.make-default', $location) }}">
                                                    @csrf
                                                    <button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-1.5 text-xs font-semibold text-[#1D4ED8]">Make default</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('settings.locations.deactivate', $location) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">{{ $location->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                            <details class="basis-full text-right">
                                                <summary class="inline-flex cursor-pointer rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">Edit</summary>
                                                <form method="POST" action="{{ route('settings.locations.update', $location) }}" class="mt-3 grid gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-left sm:grid-cols-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Name</span>
                                                        <input name="name" value="{{ old('name', $location->name) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Type</span>
                                                        <select name="type" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                            @foreach ($locationTypes as $type)
                                                                <option value="{{ $type }}" @selected(old('type', $location->type) === $type)>{{ $typeLabels[$type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $type)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="space-y-1 sm:col-span-2">
                                                        <span class="text-xs font-semibold text-[#64748B]">Address</span>
                                                        <input name="address_line1" value="{{ old('address_line1', $location->address_line1) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">City</span>
                                                        <input name="city" value="{{ old('city', $location->city) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Country code</span>
                                                        <input name="country_code" value="{{ old('country_code', $location->country_code) }}" maxlength="2" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                                    </label>
                                                    <div class="sm:col-span-2">
                                                        <button class="rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-bold text-white">Save location</button>
                                                    </div>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <span class="block text-right text-xs text-[#94A3B8]">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
