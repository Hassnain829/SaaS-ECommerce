@extends('layouts.user.user-sidebar')

@section('title', 'Shipping & Delivery | BaaS Core')

@php
    $connectionLabels = [
        'manual' => 'Manual',
        'api' => 'API connection later',
        'external' => 'External account',
    ];
    $accountStatusLabels = [
        'setup_required' => 'Setup required',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'internal_only' => 'Internal only',
    ];
    $rateLabels = [
        'flat' => 'Flat rate',
        'free' => 'Free',
        'manual' => 'Manual price',
        'carrier_calculated_later' => 'Carrier calculated later',
    ];
    $statusBadge = fn (bool $active) => $active
        ? 'bg-[#ECFDF5] text-[#047857]'
        : 'bg-[#F1F5F9] text-[#64748B]';
@endphp

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div class="min-w-0">
            <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Shipping &amp; Delivery</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Delivery areas, delivery methods, carriers, and fulfillment origins.</p>
        </div>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[1360px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Store shipping setup</p>
                    <h2 class="mt-1 text-2xl font-poppins font-semibold text-[#0F172A]">Shipping &amp; Delivery</h2>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">
                        Set where this store delivers, which delivery options customers can choose, and how orders are fulfilled.
                    </p>
                </div>
                <div class="grid gap-3 text-sm sm:grid-cols-3 lg:min-w-[520px]">
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Zones</p>
                        <p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $shippingZones->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Methods</p>
                        <p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $shippingMethods->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Carriers</p>
                        <p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $carrierAccounts->count() }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
                    <div class="border-b border-[#F1F5F9] px-5 py-4">
                        <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Shipping zones</h2>
                        <p class="mt-1 text-sm text-[#64748B]">Choose where this store delivers. Delivery methods are shown only when they match the customer's address.</p>
                    </div>

                    @if ($canManageShipping)
                        <form method="POST" action="{{ route('settings.shipping.zones.store') }}" class="grid gap-3 border-b border-[#F1F5F9] bg-[#F8FAFC] p-5 md:grid-cols-6">
                            @csrf
                            <label class="space-y-1 md:col-span-1">
                                <span class="text-xs font-semibold text-[#64748B]">Zone name</span>
                                <input name="name" value="{{ old('name') }}" placeholder="United States" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1 md:col-span-1">
                                <span class="text-xs font-semibold text-[#64748B]">Countries</span>
                                <input name="countries" value="{{ old('countries') }}" placeholder="US, CA" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1 md:col-span-1">
                                <span class="text-xs font-semibold text-[#64748B]">Regions</span>
                                <input name="regions" value="{{ old('regions') }}" placeholder="California, Ontario" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1 md:col-span-1">
                                <span class="text-xs font-semibold text-[#64748B]">Postal codes</span>
                                <input name="postal_patterns" value="{{ old('postal_patterns') }}" placeholder="941*, 10001" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1 md:col-span-1">
                                <span class="text-xs font-semibold text-[#64748B]">Sort</span>
                                <input name="sort_order" value="{{ old('sort_order', 0) }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <div class="flex items-end">
                                <input type="hidden" name="is_active" value="1">
                                <button class="h-10 w-full rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Add zone</button>
                            </div>
                        </form>
                    @endif

                    <div class="divide-y divide-[#F1F5F9]">
                        @forelse ($shippingZones as $zone)
                            <article class="p-5">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-[#0F172A]">{{ $zone->name }}</h3>
                                            <span class="rounded-full {{ $statusBadge((bool) $zone->is_active) }} px-2.5 py-1 text-xs font-bold">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-[#64748B]">
                                            Countries: {{ collect($zone->countries)->filter()->implode(', ') ?: 'Not limited' }}
                                            @if (collect($zone->regions)->filter()->isNotEmpty())
                                                <span class="text-[#CBD5E1]">|</span> Regions: {{ collect($zone->regions)->filter()->implode(', ') }}
                                            @endif
                                            @if (collect($zone->postal_patterns)->filter()->isNotEmpty())
                                                <span class="text-[#CBD5E1]">|</span> Postal: {{ collect($zone->postal_patterns)->filter()->implode(', ') }}
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-[#94A3B8]">{{ $zone->shippingMethods->count() }} delivery method(s)</p>
                                    </div>
                                    @if ($canManageShipping)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <details class="text-right">
                                                <summary class="inline-flex cursor-pointer rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-xs font-semibold text-[#475569]">Edit</summary>
                                                <form method="POST" action="{{ route('settings.shipping.zones.update', $zone) }}" class="mt-3 grid min-w-[300px] gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-left">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="is_active" value="0">
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Name</span><input name="name" value="{{ old('name', $zone->name) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Countries</span><input name="countries" value="{{ collect($zone->countries)->filter()->implode(', ') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Regions</span><input name="regions" value="{{ collect($zone->regions)->filter()->implode(', ') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal codes</span><input name="postal_patterns" value="{{ collect($zone->postal_patterns)->filter()->implode(', ') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Sort</span><input name="sort_order" value="{{ $zone->sort_order }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="is_active" value="1" @checked($zone->is_active) class="rounded border-[#CBD5E1]"> Active</label>
                                                    <button class="rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-bold text-white">Save zone</button>
                                                </form>
                                            </details>
                                            <form method="POST" action="{{ route('settings.shipping.zones.destroy', $zone) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center">
                                <p class="font-semibold text-[#0F172A]">No shipping zones yet.</p>
                                <p class="mt-1 text-sm text-[#64748B]">Add a zone such as United States, Local delivery area, or International.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
                    <div class="border-b border-[#F1F5F9] px-5 py-4">
                        <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Delivery methods</h2>
                        <p class="mt-1 text-sm text-[#64748B]">These are the delivery choices customers can select at checkout, such as Standard delivery, Express delivery, Local delivery, or Store pickup.</p>
                    </div>

                    @if ($canManageShipping)
                        <form method="POST" action="{{ route('settings.shipping.methods.store') }}" class="grid gap-3 border-b border-[#F1F5F9] bg-[#F8FAFC] p-5 md:grid-cols-4">
                            @csrf
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Method name</span>
                                <input name="name" value="{{ old('name') }}" placeholder="Standard delivery" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Zone</span>
                                <select name="shipping_zone_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    @foreach ($shippingZones as $zone)
                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Carrier account</span>
                                <select name="carrier_account_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    <option value="">No carrier account</option>
                                    @foreach ($carrierAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Rate type</span>
                                <select name="rate_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    @foreach ($rateTypes as $rateType)
                                        <option value="{{ $rateType }}">{{ $rateLabels[$rateType] ?? str($rateType)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Customer label</span>
                                <input name="delivery_speed_label" value="{{ old('delivery_speed_label') }}" placeholder="2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Flat rate</span>
                                <input name="flat_rate" value="{{ old('flat_rate', '0.00') }}" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Free over</span>
                                <input name="free_over_amount" value="{{ old('free_over_amount') }}" type="number" min="0" step="0.01" placeholder="Optional" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Order range</span>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="min_order_amount" value="{{ old('min_order_amount') }}" type="number" min="0" step="0.01" class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" placeholder="Min">
                                    <input name="max_order_amount" value="{{ old('max_order_amount') }}" type="number" min="0" step="0.01" class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" placeholder="Max">
                                </div>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Estimated days</span>
                                <div class="grid grid-cols-2 gap-2">
                                    <input name="estimated_min_days" value="{{ old('estimated_min_days') }}" type="number" min="0" class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" placeholder="Min">
                                    <input name="estimated_max_days" value="{{ old('estimated_max_days') }}" type="number" min="0" class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" placeholder="Max">
                                </div>
                            </label>
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs font-semibold text-[#64748B]">Description</span>
                                <input name="description" value="{{ old('description') }}" placeholder="Arrives in 2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Sort</span>
                                <input name="sort_order" value="{{ old('sort_order', 0) }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <div class="flex items-end gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="enabled_for_checkout" value="1" checked class="rounded border-[#CBD5E1]"> Checkout</label>
                                <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="is_active" value="1" checked class="rounded border-[#CBD5E1]"> Active</label>
                                <button class="ml-auto h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Add method</button>
                            </div>
                        </form>
                    @endif

                    <div class="divide-y divide-[#F1F5F9]">
                        @forelse ($shippingMethods as $method)
                            <article class="p-5">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-lg font-semibold text-[#0F172A]">{{ $method->name }}</h3>
                                            <span class="rounded-full {{ $statusBadge((bool) $method->is_active) }} px-2.5 py-1 text-xs font-bold">{{ $method->is_active ? 'Active' : 'Inactive' }}</span>
                                            @if ($method->enabled_for_checkout)
                                                <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Checkout</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-sm text-[#64748B]">
                                            {{ $method->shippingZone?->name ?? 'No zone' }}
                                            <span class="text-[#CBD5E1]">|</span>
                                            {{ $rateLabels[$method->rate_type] ?? str($method->rate_type)->replace('_', ' ')->title() }}
                                            @if ((float) $method->flat_rate > 0)
                                                <span class="text-[#CBD5E1]">|</span> {{ $selectedStore->currency ?? 'USD' }} {{ number_format((float) $method->flat_rate, 2) }}
                                            @endif
                                            @if ((float) $method->free_over_amount > 0)
                                                <span class="text-[#CBD5E1]">|</span> Free over {{ $selectedStore->currency ?? 'USD' }} {{ number_format((float) $method->free_over_amount, 2) }}
                                            @endif
                                        </p>
                                        <p class="mt-1 text-xs text-[#94A3B8]">
                                            {{ collect([
                                                $method->delivery_speed_label,
                                                $method->carrierAccount?->display_name ?? 'No carrier account assigned',
                                                $method->estimated_min_days !== null && $method->estimated_max_days !== null ? $method->estimated_min_days.'-'.$method->estimated_max_days.' days' : null,
                                            ])->filter()->implode(' | ') }}
                                        </p>
                                    </div>
                                    @if ($canManageShipping)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <details class="text-right">
                                                <summary class="inline-flex cursor-pointer rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-xs font-semibold text-[#475569]">Edit</summary>
                                                <form method="POST" action="{{ route('settings.shipping.methods.update', $method) }}" class="mt-3 grid min-w-[340px] gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-left">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="enabled_for_checkout" value="0">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Name</span><input name="name" value="{{ $method->name }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Zone</span><select name="shipping_zone_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">@foreach ($shippingZones as $zone)<option value="{{ $zone->id }}" @selected($zone->id === $method->shipping_zone_id)>{{ $zone->name }}</option>@endforeach</select></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Carrier account</span><select name="carrier_account_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"><option value="">No carrier account</option>@foreach ($carrierAccounts as $account)<option value="{{ $account->id }}" @selected($account->id === $method->carrier_account_id)>{{ $account->display_name }}</option>@endforeach</select></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Rate type</span><select name="rate_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">@foreach ($rateTypes as $rateType)<option value="{{ $rateType }}" @selected($rateType === $method->rate_type)>{{ $rateLabels[$rateType] ?? str($rateType)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Customer label</span><input name="delivery_speed_label" value="{{ $method->delivery_speed_label }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Flat rate</span><input name="flat_rate" value="{{ $method->flat_rate }}" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free over</span><input name="free_over_amount" value="{{ $method->free_over_amount }}" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min order</span><input name="min_order_amount" value="{{ $method->min_order_amount }}" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max order</span><input name="max_order_amount" value="{{ $method->max_order_amount }}" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    </div>
                                                    <div class="grid grid-cols-3 gap-2">
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min days</span><input name="estimated_min_days" value="{{ $method->estimated_min_days }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max days</span><input name="estimated_max_days" value="{{ $method->estimated_max_days }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                        <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Sort</span><input name="sort_order" value="{{ $method->sort_order }}" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    </div>
                                                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Description</span><input name="description" value="{{ $method->description }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                                    <div class="flex items-center gap-4">
                                                        <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="enabled_for_checkout" value="1" @checked($method->enabled_for_checkout) class="rounded border-[#CBD5E1]"> Checkout</label>
                                                        <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="is_active" value="1" @checked($method->is_active) class="rounded border-[#CBD5E1]"> Active</label>
                                                    </div>
                                                    <button class="rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-bold text-white">Save method</button>
                                                </form>
                                            </details>
                                            <form method="POST" action="{{ route('settings.shipping.methods.destroy', $method) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center">
                                <p class="font-semibold text-[#0F172A]">No delivery methods yet.</p>
                                <p class="mt-1 text-sm text-[#64748B]">Add Standard delivery, Express delivery, Local delivery, or Store pickup.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Carriers &amp; accounts</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">Add the courier services this store can use. Live carrier API connections will be added later; manual carriers work now.</p>

                    @if ($canManageShipping)
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.store') }}" class="mt-4 space-y-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            @csrf
                            <label class="space-y-1 block">
                                <span class="text-xs font-semibold text-[#64748B]">Carrier</span>
                                <select name="carrier_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    @foreach ($carriers as $carrier)
                                        <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 block">
                                <span class="text-xs font-semibold text-[#64748B]">Display name</span>
                                <input name="display_name" value="{{ old('display_name') }}" placeholder="Main DHL account" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="space-y-1 block">
                                    <span class="text-xs font-semibold text-[#64748B]">Connection</span>
                                    <select name="connection_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                        @foreach ($connectionTypes as $type)
                                            <option value="{{ $type }}">{{ $connectionLabels[$type] ?? str($type)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1 block">
                                    <span class="text-xs font-semibold text-[#64748B]">Status</span>
                                    <select name="status" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                        @foreach ($carrierAccountStatuses as $status)
                                            <option value="{{ $status }}" @selected($status === 'enabled')>{{ $accountStatusLabels[$status] ?? str($status)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <label class="space-y-1 block">
                                <span class="text-xs font-semibold text-[#64748B]">Supported countries</span>
                                <input name="supported_countries" value="{{ old('supported_countries') }}" placeholder="US, CA, PK" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="enabled_for_checkout" value="1" checked class="rounded border-[#CBD5E1]"> Available for checkout</label>
                            <button class="w-full rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Add carrier account</button>
                        </form>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse ($carrierAccounts as $account)
                            <article class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#0F172A]">{{ $account->display_name }}</p>
                                        <p class="mt-1 text-sm text-[#64748B]">{{ $account->carrier?->name ?? 'Carrier removed' }} | {{ $connectionLabels[$account->connection_type] ?? str($account->connection_type)->replace('_', ' ')->title() }}</p>
                                    </div>
                                    <span class="rounded-full {{ $account->status === 'enabled' ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#F1F5F9] text-[#64748B]' }} px-2.5 py-1 text-xs font-bold">
                                        {{ $accountStatusLabels[$account->status] ?? str($account->status)->replace('_', ' ')->title() }}
                                    </span>
                                </div>
                                <p class="mt-3 text-xs text-[#94A3B8]">Countries: {{ collect($account->supported_countries)->filter()->implode(', ') ?: 'Not limited' }}</p>
                                @if ($canManageShipping)
                                    <div class="mt-3 flex justify-end">
                                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.destroy', $account) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button>
                                        </form>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-8 text-center">
                                <p class="font-semibold text-[#0F172A]">No carrier accounts yet.</p>
                                <p class="mt-1 text-sm text-[#64748B]">Add Manual delivery, Store pickup, DHL, UPS, FedEx, USPS, or Local courier.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Fulfillment locations</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">Inventory locations are where orders can ship from.</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($locations as $location)
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-[#0F172A]">{{ $location->name }}</p>
                                    @if ($location->is_default)
                                        <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Default</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-[#64748B]">{{ collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') ?: 'No address saved' }}</p>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-8 text-center text-sm text-[#64748B]">No inventory locations are available yet.</div>
                        @endforelse
                    </div>
                    <a href="{{ route('settings.locations.index') }}" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Manage locations</a>
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Automation</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Carrier labels, live rates, pickup scheduling, and routing automation will be available after manual fulfillment and delivery methods are stable.</p>
                </section>
            </aside>
        </div>
    </div>
@endsection
