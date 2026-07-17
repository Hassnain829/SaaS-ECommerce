@extends('layouts.user.user-sidebar')

@section('title', 'General Settings | BaaS Core')

@php
    $store = $selectedStore ?? $currentStore ?? null;
    $settings = is_array($store?->settings) ? $store->settings : [];
    $primaryMarket = $settings['primary_market'] ?? 'Global Market';
    $businessModels = collect($settings['business_models'] ?? [])->filter()->values();
    $categoryLabel = $settings['custom_category'] ?? $store?->category ?? 'General';
    $contactEmail = $store?->user?->email ?? auth()->user()?->email ?? 'Not set';
    $defaultLocationAddress = $defaultLocation
        ? collect([$defaultLocation->address_line1, $defaultLocation->city, $defaultLocation->state, $defaultLocation->postal_code, $defaultLocation->country_code])->filter()->implode(', ')
        : null;
    $canManageStoreSettings = $store && (auth()->user()?->hasStorePermission($store, \App\Support\StorePermission::SETTINGS_MANAGE) ?? false);
    $storeInitial = $store ? \Illuminate\Support\Str::of($store->name)->trim()->substr(0, 1)->upper() : '?';
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="General settings" lead="Store identity, defaults, and operational preferences.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-8">
        @include('user_view.partials.flash_success')

        @unless ($store)
            <section class="rounded-xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-[#0F172A] font-[Poppins]">No active store</h2>
                <p class="mt-2 text-sm text-[#64748B]">Create or select a store before changing settings.</p>
                <a href="{{ route('store-management') }}" class="mt-4 inline-flex rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Open store management</a>
            </section>
        @else
            <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                <div class="border-b border-[#F1F5F9] px-5 py-4">
                    <h2 class="text-2xl font-poppins">Store Profile</h2>
                    <p class="text-sm text-[#64748B]">Public identity and appearance of your storefront.</p>
                </div>
                <div class="p-5 space-y-6">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-[128px_minmax(0,1fr)]">
                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Store Logo</p>
                            <div class="flex h-32 w-32 flex-col items-center justify-center gap-2 overflow-hidden rounded-xl border-2 border-dashed border-[#CBD5E1] bg-[#F8FAFC] text-[#94A3B8]">
                                @if ($store->logo)
                                    <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-full w-full object-contain p-3">
                                @else
                                    <span class="text-3xl font-bold text-[#64748B]">{{ $storeInitial }}</span>
                                    <span class="text-center text-[10px] font-bold uppercase tracking-[0.8px]">No logo</span>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <label class="space-y-1.5">
                                    <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Store Name</span>
                                    <input value="{{ $store->name }}" readonly class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                                </label>
                                <label class="space-y-1.5">
                                    <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Contact Email</span>
                                    <input value="{{ $contactEmail }}" readonly class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                                </label>
                            </div>
                            <label class="block space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Physical Address</span>
                                <textarea readonly class="w-full min-h-20 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#0F172A]">{{ $store->address ?: 'No store address saved' }}</textarea>
                            </label>
                        </div>
                    </div>

                    <hr class="border-[#F1F5F9]">
                    <div>
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-[0.7px] font-poppins">Branding</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex items-center gap-4 rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                                <div class="rounded-lg border border-[#E2E8F0] bg-white p-2"><div class="h-6 w-7 rounded-[2px] bg-[#0052CC]"></div></div>
                                <div>
                                    <p class="font-semibold">Primary color</p>
                                    <p class="text-xs text-[#64748B]">Current dashboard action color. Branding controls will be editable later.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                                <div class="rounded-lg border border-[#E2E8F0] bg-white p-2"><div class="h-6 w-7 rounded-[2px] bg-[#0F172A]"></div></div>
                                <div>
                                    <p class="font-semibold">Secondary color</p>
                                    <p class="text-xs text-[#64748B]">Current navigation and text accent. Branding controls will be editable later.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                <div class="border-b border-[#F1F5F9] px-5 py-4">
                    <h2 class="text-2xl font-poppins">Regional &amp; Financials</h2>
                    <p class="text-sm text-[#64748B]">Store defaults for dashboard totals, dates, and default selling context.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
                    <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Default store currency</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $store->currency ?? 'USD' }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">This is your store's base currency for dashboard totals and default pricing. Market-specific currencies will be added later.</p>
                    </div>
                    <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Default store timezone</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $store->timezone ?? 'UTC' }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">This timezone is used for dashboard dates, reports, and store operations. Location-specific cutoff times can be added later when fulfillment is enabled.</p>
                    </div>
                    <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Primary market</p>
                        <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $primaryMarket }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">This is your default selling region. Full multi-market selling, regional currencies, and price lists will be added later.</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                <div class="border-b border-[#F1F5F9] px-5 py-4">
                    <h2 class="text-2xl font-poppins">Business Configuration</h2>
                    <p class="text-sm text-[#64748B]">Operational status, store type, inventory location, and future fulfillment preview.</p>
                </div>
                <div class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Main category</p>
                                <p class="mt-2 text-lg font-semibold text-[#0F172A]">{{ \Illuminate\Support\Str::headline((string) $categoryLabel) }}</p>
                                <p class="mt-2 text-xs text-[#64748B]">{{ $businessModels->isNotEmpty() ? $businessModels->implode(', ') : 'No extra business model tags saved' }}</p>
                            </div>
                            <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Operational status</p>
                                <p class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $store->onboarding_completed ? 'Live workspace' : 'Draft setup' }}</p>
                                <p class="mt-2 text-xs text-[#64748B]">{{ $store->onboarding_completed ? 'Store onboarding is complete.' : 'Finish onboarding before launch.' }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Default inventory location</p>
                                    <h3 class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $defaultLocation?->name ?? 'Main location' }}</h3>
                                    <p class="mt-2 text-sm text-[#64748B]">{{ $defaultLocationAddress ?: 'No address saved' }}</p>
                                </div>
                                <a href="{{ route('settings.locations.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Manage locations</a>
                            </div>
                        </div>

                        <div class="rounded-xl border border-[#BFD5FF] bg-[#F5F9FF] p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#2563EB]">Inventory foundation</p>
                            <h3 class="mt-2 text-lg font-semibold text-[#0F172A]">Locations are for stock, not selling rules</h3>
                            <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations are places where you store or fulfill inventory, such as a warehouse, shop, stock room, restaurant branch, or third-party storage.</p>
                            <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations control where stock is stored. Markets and currencies control where and how you sell.</p>
                        </div>
                    </div>

                    <aside class="rounded-xl border border-[#D8E1EC] bg-[#F8FAFC] p-5">
                        <div class="flex items-start gap-4">
                            <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#EFF6FF] text-[#0052CC]">
                                <svg width="22" height="22" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M6 17C5.45 17 4.97917 16.8042 4.5875 16.4125C4.19583 16.0208 4 15.55 4 15C4 14.45 4.19583 13.9792 4.5875 13.5875C4.97917 13.1958 5.45 13 6 13C6.55 13 7.02083 13.1958 7.4125 13.5875C7.80417 13.9792 8 14.45 8 15C8 15.55 7.80417 16.0208 7.4125 16.4125C7.02083 16.8042 6.55 17 6 17ZM14 17C13.45 17 12.9792 16.8042 12.5875 16.4125C12.1958 16.0208 12 15.55 12 15C12 14.45 12.1958 13.9792 12.5875 13.5875C12.9792 13.1958 13.45 13 14 13C14.55 13 15.0208 13.1958 15.4125 13.5875C15.8042 13.9792 16 14.45 16 15C16 15.55 15.8042 16.0208 15.4125 16.4125C15.0208 16.8042 14.55 17 14 17ZM3 3H7L9 7H17C17.2833 7 17.5208 7.09583 17.7125 7.2875C17.9042 7.47917 18 7.71667 18 8C18 8.08333 17.9917 8.17083 17.975 8.2625C17.9583 8.35417 17.925 8.44167 17.875 8.525L16.1 11.75C15.9167 12.0833 15.6708 12.3333 15.3625 12.5C15.0542 12.6667 14.7167 12.75 14.35 12.75H8.25C7.88333 12.75 7.56667 12.675 7.3 12.525C7.03333 12.375 6.83333 12.1667 6.7 11.9L3 4H1V2H3V3Z" fill="currentColor"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#94A3B8]">Delivery setup</p>
                                <h3 class="mt-1 text-xl font-semibold text-[#0F172A]">Delivery</h3>
                                <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Set ship-from locations, delivery areas, checkout delivery options, and optional delivery providers.</p>
                            </div>
                        </div>
                        <a href="{{ route('shippingAutomation') }}" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Open delivery setup</a>
                    </aside>
                </div>
            </section>
        @endunless
    </div>
@endsection
