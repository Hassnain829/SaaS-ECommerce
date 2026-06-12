@extends('layouts.user.user-sidebar')

@section('title', 'Shipping & Delivery | BaaS Core')

@php
    $connectionLabels = [
        'manual' => 'Manual',
        'api' => 'API connection',
        'external' => 'External account',
    ];
    $accountStatusLabels = [
        'setup_required' => 'Setup required',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'internal_only' => 'Internal only',
    ];
    $connectionStatusLabels = [
        'not_connected' => 'Not connected',
        'setup_required' => 'Setup required',
        'pending_validation' => 'Pending validation',
        'connected' => 'Connected',
        'failed' => 'Failed',
        'blocked_by_fedex' => 'Carrier support required',
        'sandbox_platform_fallback' => 'Connected for testing',
        'disabled' => 'Disabled',
    ];
    $connectionStatusBadge = fn (string $status) => match ($status) {
        'connected' => 'bg-[#ECFDF5] text-[#047857]',
        'sandbox_platform_fallback' => 'bg-[#FFF7ED] text-[#C2410C]',
        'blocked_by_fedex' => 'bg-[#FEF2F2] text-[#991B1B]',
        'failed' => 'bg-[#FEF2F2] text-[#991B1B]',
        'disabled' => 'bg-[#F1F5F9] text-[#64748B]',
        default => 'bg-[#FEF3C7] text-[#92400E]',
    };
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

        @if ($isExternalManaged ?? false)
            <section class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 md:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-sky-800">External managed checkout is active</p>
                        <p class="mt-2 text-sm leading-6 text-sky-950">
                            Your external storefront currently manages checkout, payment, shipping, and fulfillment. The Shipping &amp; Delivery settings below apply to platform checkout or dashboard-managed fulfillment. External orders can still send shipping, tracking, and delivery snapshots into this dashboard.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('settings.payments.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-300 bg-white px-4 text-sm font-semibold text-sky-900 hover:bg-sky-100/60">Payments &amp; Channels</a>
                        @if (Route::has('developer-storefront.settings'))
                            <a href="{{ route('developer-storefront.settings') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-300 bg-white px-4 text-sm font-semibold text-sky-900 hover:bg-sky-100/60">View integration instructions</a>
                        @endif
                    </div>
                </div>
            </section>
        @elseif ($isPlatformManaged ?? false)
            <section class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-5 py-4 md:px-6">
                <p class="text-sm leading-6 text-emerald-950">These delivery methods can be shown to customers during platform checkout.</p>
            </section>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Store shipping setup</p>
                    <h2 class="mt-1 text-2xl font-poppins font-semibold text-[#0F172A]">Shipping &amp; Delivery</h2>
                    <p class="mt-2 text-sm leading-6 text-[#475569]">
                        Set where this store delivers, which delivery options customers can choose, and how orders are fulfilled.
                    </p>
                    <div class="mt-4 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Store business address vs. fulfillment origin</p>
                        <p class="mt-2 text-sm leading-6 text-[#475569]"><span class="font-semibold text-[#0F172A]">Store business address</span> is used for store profile, invoices, and admin context.</p>
                        <p class="mt-2 text-sm leading-6 text-[#475569]"><span class="font-semibold text-[#0F172A]">Fulfillment origin</span> is the ship-from address used for carrier rates, labels, and fulfillment. USPS and FedEx testing use the selected fulfillment location — not the store business address.</p>
                        <a href="{{ route('settings.locations.index') }}" class="mt-3 inline-flex text-sm font-semibold text-[#1D4ED8]">Manage fulfillment locations</a>
                    </div>
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
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Carrier accounts</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">Connect carrier accounts through the guided setup. Manual/local delivery, USPS testing, FedEx setup, and future carriers all start from the same setup flow.</p>
                    @if ($canManageShipping ?? false)
                        <div class="mt-4 space-y-2">
                            <a href="{{ route('shipping.carriers.connect.index') }}" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Connect carrier account</a>
                            <a href="{{ route('shipping.carriers.connect.show', 'manual') }}" class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Add manual/local delivery</a>
                        </div>
                    @endif
                    <div class="mt-4 space-y-3">
                        @forelse ($carrierAccounts as $account)
                            @include('user_view.partials.carrier_account_card', ['account' => $account, 'canManageShipping' => $canManageShipping ?? false])
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-center text-sm text-[#64748B]">No carrier accounts yet. Use the connection wizard to get started.</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">FedEx merchant account</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">
                        Connect a merchant-owned FedEx account for setup and testing. FedEx billing stays between you and FedEx. Labels and checkout live rates are not enabled in this phase.
                    </p>

                    @if (app()->environment(['local', 'testing']))
                        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                            <summary class="cursor-pointer font-semibold text-slate-900">Local testing details</summary>
                            <p class="mt-2">FedEx platform config: {{ ($fedExPlatformConfigured ?? false) ? 'present' : 'missing' }}</p>
                            <p class="mt-1">Account registration path: <code>{{ $fedExRegistrationPath ?? 'not configured' }}</code></p>
                            <p class="mt-1 text-slate-600">Must match the current Credential Registration path in your FedEx Developer Portal project.</p>
                            <p class="mt-1">Credential registration residential mode: <code>{{ $fedExRegistrationResidentialMode ?? 'omit' }}</code></p>
                            <p class="mt-1">Sandbox platform fallback: {{ ($fedExSandboxPlatformFallbackAllowed ?? false) ? 'enabled in this environment' : 'disabled' }}</p>
                        </details>
                    @elseif (! ($fedExEnabled ?? false) || ! ($fedExPlatformConfigured ?? false))
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                            FedEx account setup is not available on this platform environment yet. Contact the platform admin.
                        </div>
                    @endif

                    @if (session('fedex_connection_steps'))
                        <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
                            <p class="font-semibold text-[#0F172A]">Latest connection test steps</p>
                            <ul class="mt-2 space-y-1 text-xs text-[#475569]">
                                @foreach (session('fedex_connection_steps') as $step => $status)
                                    <li><span class="font-semibold text-[#0F172A]">{{ str($step)->replace('_', ' ')->title() }}:</span> {{ str($status)->replace('_', ' ')->title() }}</li>
                                @endforeach
                            </ul>
                            @if (session('fedex_connection_message'))
                                <p class="mt-2 text-xs text-[#64748B]">{{ session('fedex_connection_message') }}</p>
                            @endif
                        </div>
                    @endif

                    @if (($fedExEnabled ?? false) && ($fedExPlatformConfigured ?? false) && ($canManageShipping ?? false))
                        <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm text-[#475569]">
                            <p>Use the carrier connection wizard to add your merchant-owned FedEx account and choose a ship-from fulfillment location.</p>
                            <a href="{{ route('shipping.carriers.connect.show', 'fedex') }}" class="mt-3 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Start FedEx setup</a>
                        </div>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse (($fedExAccounts ?? collect()) as $account)
                            <article class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#0F172A]">{{ $account->display_name }}</p>
                                        <p class="mt-1 text-sm text-[#64748B]">FedEx | Account {{ $account->maskedAccountNumber() }}</p>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Sandbox</span>
                                        <span class="rounded-full {{ $connectionStatusBadge($account->connection_status) }} px-2.5 py-1 text-xs font-bold">
                                            {{ $connectionStatusLabels[$account->connection_status] ?? str($account->connection_status)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>
                                </div>
                                @if ($account->last_verified_at)
                                    <p class="mt-2 text-xs text-[#64748B]">Last verified {{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</p>
                                @endif
                                @if ($account->last_error_message && in_array($account->connection_status, ['failed', 'blocked_by_fedex'], true))
                                    <p class="mt-2 text-xs text-red-700">{{ $account->last_error_message }}</p>
                                @endif
                                @php($fedExOriginId = (int) data_get($account->settings, 'default_origin_location_id'))
                                @php($fedExOriginReadiness = $fedExOriginId > 0 ? ($originReadinessByLocationId[$fedExOriginId] ?? null) : null)
                                @if ($fedExOriginId > 0)
                                    <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <p class="font-semibold text-[#0F172A]">Default fulfillment origin</p>
                                        @if ($fedExOriginReadiness)
                                            <p class="mt-1">{{ collect($locations)->firstWhere('id', $fedExOriginId)?->name }} — {{ $fedExOriginReadiness->displayAddress ?: 'No ship-from address saved' }}</p>
                                            <p class="mt-1"><span class="rounded-full {{ $fedExOriginReadiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-2 py-0.5 font-bold">{{ $fedExOriginReadiness->badgeLabel }}</span></p>
                                            @if (! $fedExOriginReadiness->ready)
                                                <p class="mt-1">{{ $fedExOriginReadiness->merchantMessage }}</p>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                                @if ($account->connection_status === 'sandbox_platform_fallback')
                                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                                        Local sandbox fallback: this uses platform FedEx sandbox credentials only. It is not a production merchant-owned FedEx connection.
                                    </p>
                                @endif
                                @if ($account->connection_status === 'connected' && $account->isMerchantOwned())
                                    <p class="mt-2 text-xs text-[#047857]">Merchant-owned FedEx account connected for testing. Billing handled by merchant.</p>
                                @elseif ($account->connection_status === 'connected' && $account->isPlatformTesting())
                                    <p class="mt-2 text-xs text-[#C2410C]">Platform testing connection verified. This is not a merchant-owned FedEx account.</p>
                                @endif
                                @if (app()->environment(['local', 'testing']))
                                <p class="mt-2 text-xs text-[#64748B]">Residential setting: {{ data_get($account->settings, 'registration.residential') ? 'true' : 'false' }}</p>
                                @endif
                                @php($stepDiagnostics = ($fedExStepDiagnostics[$account->id] ?? []))
                                @if (app()->environment(['local', 'testing']) && $stepDiagnostics !== [])
                                    <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <p class="font-semibold text-[#0F172A]">Connection step details</p>
                                        <ul class="mt-2 space-y-1">
                                            @foreach ([
                                                'platform_oauth_token' => 'Platform connection check',
                                                'account_registration' => 'Account registration',
                                                'merchant_oauth_token' => 'Merchant connection check',
                                            ] as $stepKey => $stepLabel)
                                                @if (isset($stepDiagnostics[$stepKey]))
                                                    <li>
                                                        {{ $stepLabel }}:
                                                        <span class="font-semibold">{{ str($stepDiagnostics[$stepKey]['status'])->replace('_', ' ')->title() }}</span>
                                                        @if ($stepDiagnostics[$stepKey]['endpoint'])
                                                            · {{ $stepDiagnostics[$stepKey]['endpoint'] }}
                                                        @endif
                                                        @if ($stepDiagnostics[$stepKey]['http_status'])
                                                            · HTTP {{ $stepDiagnostics[$stepKey]['http_status'] }}
                                                        @endif
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @php($registrationDiagnostics = ($fedExRegistrationRequestDiagnostics[$account->id] ?? null))
                                @if (app()->environment(['local', 'testing']) && $registrationDiagnostics)
                                    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                        <p class="font-semibold text-slate-900">FedEx request diagnostics</p>
                                        <dl class="mt-2 grid gap-1 sm:grid-cols-2">
                                            <div><dt class="font-semibold text-slate-900">Endpoint</dt><dd>{{ data_get($registrationDiagnostics, 'request.endpoint', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Account digits</dt><dd>{{ data_get($registrationDiagnostics, 'request.account_number_digits_len', '—') }} · last4 {{ data_get($registrationDiagnostics, 'request.account_number_last4', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Customer name length</dt><dd>{{ data_get($registrationDiagnostics, 'request.customer_name_length', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Residential setting</dt><dd>{{ data_get($registrationDiagnostics, 'request.residential_setting') ? 'true' : 'false' }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Residential sent</dt><dd>{{ data_get($registrationDiagnostics, 'request.residential_sent') ? 'true' : 'false' }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Residential mode</dt><dd>{{ data_get($registrationDiagnostics, 'request.residential_mode', 'omit') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">City</dt><dd>{{ data_get($registrationDiagnostics, 'request.city', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">State</dt><dd>{{ data_get($registrationDiagnostics, 'request.state_or_province_code', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Postal code</dt><dd>{{ data_get($registrationDiagnostics, 'request.postal_code', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">Country</dt><dd>{{ data_get($registrationDiagnostics, 'request.country_code', '—') }}</dd></div>
                                            <div class="sm:col-span-2"><dt class="font-semibold text-slate-900">Registration field groups</dt><dd>{{ implode(', ', data_get($registrationDiagnostics, 'request.payload_root_keys', [])) }}</dd></div>
                                            <div class="sm:col-span-2"><dt class="font-semibold text-slate-900">Address keys</dt><dd>{{ implode(', ', data_get($registrationDiagnostics, 'request.address_keys', [])) }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">HTTP status</dt><dd>{{ data_get($registrationDiagnostics, 'response.http_status', '—') }}</dd></div>
                                            <div><dt class="font-semibold text-slate-900">FedEx txn</dt><dd>{{ Str::limit((string) data_get($registrationDiagnostics, 'response.fedex_transaction_id', '—'), 24) }}</dd></div>
                                            @if ($fedExError = data_get($registrationDiagnostics, 'response.errors.0.code'))
                                                <div class="sm:col-span-2"><dt class="font-semibold text-slate-900">FedEx error</dt><dd>HTTP {{ data_get($registrationDiagnostics, 'response.http_status') }} · {{ $fedExError }}</dd></div>
                                            @endif
                                        </dl>
                                    </div>
                                @endif
                                @if ($canManageShipping)
                                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.registration.update', $account) }}" class="mt-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">
                                        @csrf
                                        @php($isResidential = (bool) data_get($account->settings, 'registration.residential', false))
                                        <label class="flex items-start gap-2 text-xs text-[#475569]">
                                            <input type="hidden" name="residential" value="0">
                                            <input type="checkbox" name="residential" value="1" @checked($isResidential) class="mt-0.5">
                                            <span>Saved for future rate/label validation. It is not sent during FedEx credential registration unless diagnostics enable it.</span>
                                        </label>
                                        <button class="mt-2 rounded-lg border border-[#BFDBFE] bg-white px-3 py-1.5 text-xs font-semibold text-[#1D4ED8]">Save registration setting</button>
                                    </form>
                                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test', $account) }}">
                                            @csrf
                                            <button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]">Test connection</button>
                                        </form>
                                        @if (app()->environment(['local', 'testing']))
                                            <p class="mt-3 text-xs text-slate-600">Platform connection check success only confirms platform API access. FedEx account registration is a separate merchant account validation step.</p>
                                            <a href="{{ route('settings.shipping.carrier-accounts.fedex.debug-payload', $account) }}" target="_blank" rel="noopener" class="mt-2 inline-flex rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">Export redacted FedEx validation summary</a>
                                            <p class="mt-1 text-[11px] text-slate-600">Export a redacted validation summary for FedEx support. It does not include full account number, tokens, or secrets.</p>
                                        @endif
                                        @if (app()->environment(['local', 'testing']) && ($fedExSandboxPlatformFallbackAllowed ?? false) && ! $account->usesSandboxPlatformFallback())
                                            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.sandbox-platform-fallback', $account) }}">
                                                @csrf
                                                <button class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Enable sandbox platform fallback</button>
                                            </form>
                                        @endif
                                        @if ($account->connection_status !== 'disabled')
                                            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.disable', $account) }}">
                                                @csrf
                                                <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Disable</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-center text-sm text-[#64748B]">No FedEx sandbox accounts yet.</div>
                        @endforelse
                    </div>

                    @if (($fedExApiEvents ?? collect())->isNotEmpty())
                        <div class="mt-5 border-t border-[#F1F5F9] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Recent FedEx API activity</h3>
                            <div class="mt-3 space-y-2">
                                @foreach ($fedExApiEvents as $event)
                                    <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-semibold text-[#0F172A]">{{ str($event->action)->replace('_', ' ')->title() }}</span>
                                            <span class="rounded-full {{ $connectionStatusBadge($event->status === 'succeeded' ? 'connected' : ($event->status === 'failed' ? 'failed' : 'setup_required')) }} px-2 py-0.5 font-bold">{{ str($event->status)->title() }}</span>
                                        </div>
                                        <p class="mt-1">{{ $event->created_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}@if ($event->duration_ms) · {{ $event->duration_ms }} ms @endif @if ($event->request_id) · Ref {{ Str::limit($event->request_id, 12) }} @endif</p>
                                        @if (data_get($event->request_summary, 'endpoint'))
                                            <p class="mt-1">Endpoint: {{ data_get($event->request_summary, 'endpoint') }}</p>
                                        @endif
                                        @if (data_get($event->response_summary, 'http_status'))
                                            <p class="mt-1">HTTP {{ data_get($event->response_summary, 'http_status') }}@if (data_get($event->response_summary, 'fedex_transaction_id')) · FedEx txn {{ Str::limit((string) data_get($event->response_summary, 'fedex_transaction_id'), 16) }} @endif</p>
                                        @endif
                                        @if (app()->environment(['local', 'testing']) && is_array(data_get($event->response_summary, 'errors')))
                                            <ul class="mt-1 space-y-1">
                                                @foreach (data_get($event->response_summary, 'errors', []) as $fedExError)
                                                    <li>
                                                        @if (data_get($fedExError, 'code'))
                                                            <span class="font-semibold text-[#0F172A]">{{ data_get($fedExError, 'code') }}</span>
                                                        @endif
                                                        @if (data_get($fedExError, 'message'))
                                                            · {{ data_get($fedExError, 'message') }}
                                                        @endif
                                                        @if (data_get($fedExError, 'field') || data_get($fedExError, 'path') || data_get($fedExError, 'parameter'))
                                                            · {{ collect([data_get($fedExError, 'field'), data_get($fedExError, 'path'), data_get($fedExError, 'parameter')])->filter()->implode(' / ') }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if ($event->error_message)
                                            <p class="mt-1 text-red-700">{{ $event->error_message }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">USPS testing tools</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">
                        Test USPS address checks and domestic sample quotes using the platform testing connection. This does not buy labels, charge postage, or change order totals.
                    </p>

                    @if (! ($hasCarrierReadyOrigin ?? false))
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                            <p class="font-semibold">Set up a carrier-ready fulfillment origin first.</p>
                            <p class="mt-1">USPS address validation and test rate quotes need a complete US ship-from address on a fulfillment location (street, city, state, ZIP, country).</p>
                            <a href="{{ route('settings.locations.index') }}" class="mt-2 inline-flex text-sm font-semibold text-amber-900 underline">Manage fulfillment locations</a>
                        </div>
                    @endif

                    @if (app()->environment(['local', 'testing']))
                        <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                            <summary class="cursor-pointer font-semibold text-slate-900">Local testing details</summary>
                            <p class="mt-2">USPS platform config: {{ ($uspsPlatformConfigured ?? false) ? 'present' : 'missing' }}</p>
                            <p class="mt-1">Base URL: <code>{{ $uspsBaseUrl ?? 'not configured' }}</code></p>
                            <p class="mt-1">Connection check path: <code>{{ $uspsOAuthPath ?? '/oauth2/v3/token' }}</code></p>
                            <p class="mt-1">Labels enabled: {{ ($uspsLabelsEnabled ?? false) ? 'true' : 'false' }}</p>
                        </details>
                    @elseif (! ($uspsEnabled ?? false) || ! ($uspsPlatformConfigured ?? false))
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                            USPS testing connection is not available on this platform environment yet. Contact the platform admin.
                        </div>
                    @endif

                    @if (session('usps_connection_steps'))
                        <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
                            <p class="font-semibold text-[#0F172A]">Latest USPS connection test steps</p>
                            <ul class="mt-2 space-y-1 text-xs text-[#475569]">
                                @foreach (session('usps_connection_steps') as $step => $status)
                                    <li><span class="font-semibold text-[#0F172A]">{{ str($step)->replace('_', ' ')->title() }}:</span> {{ str($status)->replace('_', ' ')->title() }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (($uspsEnabled ?? false) && ($uspsPlatformConfigured ?? false) && ($canManageShipping ?? false))
                        <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm text-[#475569]">
                            <p>Use the carrier connection wizard to add a platform testing USPS account and choose a ship-from fulfillment location.</p>
                            <a href="{{ route('shipping.carriers.connect.show', 'usps') }}" class="mt-3 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Connect USPS for testing</a>
                        </div>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse (($uspsAccounts ?? collect()) as $account)
                            <article class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-[#0F172A]">{{ $account->display_name }}</p>
                                        <p class="mt-1 text-sm text-[#64748B]">Platform testing connection</p>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Testing</span>
                                        <span class="rounded-full {{ $connectionStatusBadge($account->connection_status) }} px-2.5 py-1 text-xs font-bold">
                                            {{ $connectionStatusLabels[$account->connection_status] ?? str($account->connection_status)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>
                                </div>
                                @if ($account->last_error_message && in_array($account->connection_status, ['failed'], true))
                                    <p class="mt-2 text-xs text-red-700">{{ $account->last_error_message }}</p>
                                @endif
                                @php($uspsOriginId = (int) data_get($account->settings, 'default_origin_location_id'))
                                @php($uspsOriginReadiness = $uspsOriginId > 0 ? ($originReadinessByLocationId[$uspsOriginId] ?? null) : null)
                                @if ($uspsOriginId > 0)
                                    <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <p class="font-semibold text-[#0F172A]">Default fulfillment origin</p>
                                        @if ($uspsOriginReadiness)
                                            <p class="mt-1">{{ collect($locations)->firstWhere('id', $uspsOriginId)?->name }} — {{ $uspsOriginReadiness->displayAddress ?: 'No ship-from address saved' }}</p>
                                            <p class="mt-1"><span class="rounded-full {{ $uspsOriginReadiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-2 py-0.5 font-bold">{{ $uspsOriginReadiness->badgeLabel }}</span></p>
                                            @if (! $uspsOriginReadiness->ready)
                                                <p class="mt-1">{{ $uspsOriginReadiness->merchantMessage }}</p>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <p class="mt-2 text-xs text-amber-800">No default fulfillment origin selected for this USPS account.</p>
                                @endif
                                @if ($canManageShipping)
                                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.usps.test', $account) }}">
                                            @csrf
                                            <button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]">Test connection</button>
                                        </form>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-center text-sm text-[#64748B]">No USPS testing accounts yet.</div>
                        @endforelse
                    </div>

                    @if (($uspsAccounts ?? collect())->isNotEmpty() && ($canManageShipping ?? false))
                        @php($primaryUspsAccount = ($uspsAccounts ?? collect())->first())
                        <details class="mt-5 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Testing tools — USPS package quote tester</summary>
                            <p class="mt-3 text-xs text-[#64748B]">Informational domestic quote only. Does not buy labels, authorize USPS payments, or change order totals.</p>
                        <form method="POST" action="{{ route('settings.shipping.usps.test-package-quote') }}" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="carrier_account_id" value="{{ $primaryUspsAccount->id }}">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="space-y-1 block">
                                    <span class="text-xs font-semibold text-[#64748B]">Origin location</span>
                                    <select name="origin_location_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" @disabled(! ($hasCarrierReadyOrigin ?? false)) required>
                                        <option value="">Select origin</option>
                                        @foreach ($locations as $location)
                                            @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
                                            <option value="{{ $location->id }}" @selected((string) old('origin_location_id') === (string) $location->id) @disabled(! ($readiness?->ready ?? false))>
                                                {{ $location->name }} — {{ $readiness?->displayAddress ?: 'No ship-from address saved' }}@if ($readiness && ! $readiness->ready) · {{ $readiness->badgeLabel }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @if (! ($hasCarrierReadyOrigin ?? false))
                                        <p class="text-[11px] leading-5 text-amber-800">Add a complete US ship-from address on a fulfillment location before requesting quotes.</p>
                                    @endif
                                </label>
                                <label class="space-y-1 block">
                                    <span class="text-xs font-semibold text-[#64748B]">Destination ZIP</span>
                                    <input name="destination_postal_code" value="{{ old('destination_postal_code') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                </label>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-4">
                                <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Weight (lb)</span><input name="weight_value" type="number" step="0.01" min="0.01" value="{{ old('weight_value', '1') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Length (in)</span><input name="length" type="number" step="0.01" min="0.01" value="{{ old('length', '9') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Width (in)</span><input name="width" type="number" step="0.01" min="0.01" value="{{ old('width', '6') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                                <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Height (in)</span><input name="height" type="number" step="0.01" min="0.01" value="{{ old('height', '2') }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                            </div>
                            <label class="space-y-1 block">
                                <span class="text-xs font-semibold text-[#64748B]">Mail class</span>
                                <input name="mail_class" value="{{ old('mail_class', 'USPS_GROUND_ADVANTAGE') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            </label>
                            <button class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50" @disabled(! ($hasCarrierReadyOrigin ?? false))>Get USPS test quote</button>
                            @if (! ($hasCarrierReadyOrigin ?? false))
                                <p class="text-xs text-[#64748B]"><a href="{{ route('settings.locations.index') }}" class="font-semibold text-[#1D4ED8]">Manage fulfillment locations</a> to complete the ship-from address.</p>
                            @endif
                        </form>
                        </details>
                    @endif

                    @if (($uspsRecentQuotes ?? collect())->isNotEmpty())
                        <div class="mt-5 border-t border-[#F1F5F9] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Recent USPS test quotes</h3>
                            <div class="mt-3 space-y-2">
                                @foreach ($uspsRecentQuotes as $quote)
                                    <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <p class="font-semibold text-[#0F172A]">{{ $quote->service_name ?? $quote->service_code ?? 'USPS quote' }}</p>
                                        <p class="mt-1">{{ $quote->origin_postal_code ?: 'Origin not set' }} → {{ $quote->destination_postal_code }} · {{ $quote->error_code === 'origin_not_ready' ? 'Local origin setup error' : str($quote->status)->title() }}</p>
                                        @if ($quote->amount !== null)
                                            <p class="mt-1">${{ number_format((float) $quote->amount, 2) }} {{ $quote->currency }}</p>
                                        @endif
                                        @if ($quote->error_message)
                                            <p class="mt-1 text-red-700">{{ $quote->error_message }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (($uspsApiEvents ?? collect())->isNotEmpty())
                        <div class="mt-5 border-t border-[#F1F5F9] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Recent USPS API activity</h3>
                            <div class="mt-3 space-y-2">
                                @foreach ($uspsApiEvents as $event)
                                    <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#475569]">
                                        <p class="font-semibold text-[#0F172A]">{{ str($event->action)->replace('_', ' ')->title() }} · {{ str($event->status)->title() }}</p>
                                        @if (data_get($event->response_summary, 'http_status'))
                                            <p class="mt-1">HTTP {{ data_get($event->response_summary, 'http_status') }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Fulfillment locations</h2>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">Inventory locations are where orders ship from. Carrier APIs use these addresses — not your store business address.</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($locations as $location)
                            @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
                            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-[#0F172A]">{{ $location->name }}</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($location->is_default)
                                            <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">Default fulfillment origin</span>
                                        @endif
                                        @if ($readiness)
                                            <span class="rounded-full {{ $readiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-2.5 py-1 text-xs font-bold">{{ $readiness->badgeLabel }}</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-[#64748B]">{{ $readiness?->displayAddress ?: (collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') ?: 'No address saved') }}</p>
                                @if ($readiness && ! $readiness->ready)
                                    <p class="mt-2 text-xs text-[#64748B]">{{ $readiness->merchantMessage }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-8 text-center text-sm text-[#64748B]">No inventory locations are available yet.</div>
                        @endforelse
                    </div>
                    <a href="{{ route('settings.locations.index') }}" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Manage locations</a>
                </section>

                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
                    <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Automation</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">FedEx sandbox rate quotes, label purchase, and routing automation will be added in later carrier phases after sandbox connection is verified.</p>
                </section>
            </aside>
        </div>
    </div>
@endsection
