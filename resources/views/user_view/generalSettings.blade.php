@extends('layouts.user.user-sidebar')

@section('title', 'Settings — '.config('app.name'))

@php
    $store = $selectedStore ?? $currentStore ?? null;
    $settings = is_array($store?->settings) ? $store->settings : [];
    $primaryMarket = $settings['primary_market'] ?? 'Global Market';
    $businessModels = collect($settings['business_models'] ?? [])->filter()->values();
    $categoryLabel = $settings['custom_category'] ?? $store?->category ?? 'General';
    $contactEmail = trim((string) ($settings['contact_email'] ?? ''));
    $contactEmailDisplay = $contactEmail !== '' ? $contactEmail : 'Not set';
    $defaultLocationAddress = $defaultLocation
        ? collect([$defaultLocation->address_line1, $defaultLocation->city, $defaultLocation->state, $defaultLocation->postal_code, $defaultLocation->country_code])->filter()->implode(', ')
        : null;
    $canManageStoreSettings = $store && (auth()->user()?->hasStorePermission($store, \App\Support\StorePermission::SETTINGS_MANAGE) ?? false);
    $storeInitial = $store ? \Illuminate\Support\Str::of($store->name)->trim()->substr(0, 1)->upper() : '?';
    $stores = $stores ?? ($store ? collect([$store]) : collect());
    $profileUser = $profileUser ?? auth()->user();
    $memberStores = $memberStores ?? collect();
    $settingsTab = ($settingsTab ?? 'store') === 'account' || $errors->any() ? 'account' : 'store';
    $profileInitial = $profileUser
        ? \Illuminate\Support\Str::of($profileUser->name)->trim()->substr(0, 1)->upper()
        : '?';
    $storeActionPayload = $store ? [
        'id' => $store->id,
        'name' => $store->name,
        'contact_email' => $settings['contact_email'] ?? '',
        'primary_market' => $primaryMarket,
        'currency' => $store->currency,
        'timezone' => $store->timezone,
        'address' => $store->address,
        'category' => $store->category,
        'custom_category' => $settings['custom_category'] ?? '',
        'business_models' => $settings['business_models'] ?? [],
        'logo_url' => $store->logoPublicUrl(),
        'update_url' => route('store.update', ['storeId' => $store->id]),
        'delete_url' => route('store.destroy', ['storeId' => $store->id]),
        'redirect_to' => 'generalSettings',
    ] : null;
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Settings" lead="Store and account preferences for this workspace.">
        <x-slot:actions>
            @if ($settingsTab === 'store' && $canManageStoreSettings && $storeActionPayload)
                <button
                    type="button"
                    class="js-open-edit-store-modal inline-flex items-center rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover"
                    data-store='@json($storeActionPayload)'
                >
                    Edit store
                </button>
            @elseif ($settingsTab === 'account')
                <button type="submit" form="profileForm" class="inline-flex items-center rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                    Save profile
                </button>
            @endif
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="mx-auto max-w-9xl space-y-6 px-4 lg:px-0">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
                {{ $errors->first() }}
            </div>
        @endif

        <nav class="flex flex-wrap gap-2 border-b border-[#E2E8F0] pb-3" aria-label="Settings sections">
            <a
                href="{{ route('generalSettings', ['tab' => 'store']) }}"
                @class([
                    'inline-flex items-center rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                    'bg-[#EFF6FF] text-[#1D4ED8]' => $settingsTab === 'store',
                    'text-[#64748B] hover:bg-[#F8FAFC] hover:text-[#0F172A]' => $settingsTab !== 'store',
                ])
            >
                Store
            </a>
            <a
                href="{{ route('generalSettings', ['tab' => 'account']) }}"
                @class([
                    'inline-flex items-center rounded-lg px-3.5 py-2 text-sm font-semibold transition',
                    'bg-[#EFF6FF] text-[#1D4ED8]' => $settingsTab === 'account',
                    'text-[#64748B] hover:bg-[#F8FAFC] hover:text-[#0F172A]' => $settingsTab !== 'account',
                ])
            >
                Your account
            </a>
        </nav>

        @if ($settingsTab === 'store')
            @unless ($store)
                <section class="rounded-xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-[#0F172A]">No active store</h2>
                    <p class="mt-2 text-sm text-[#64748B]">Create or select a store before changing store settings.</p>
                    <a href="{{ route('store-management') }}" class="mt-4 inline-flex rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white">Open store management</a>
                </section>
            @else
                <section class="rounded-xl border border-[#DBEAFE] bg-[#EFF6FF] px-5 py-4 text-sm text-[#1E3A8A]">
                    Default currency and timezone apply to future activity and reports. Past orders keep the currency, totals, and timestamps they were saved with.
                </section>

                <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-[#F1F5F9] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-2xl">Store Profile</h2>
                            <p class="text-sm text-[#64748B]">Public identity and appearance of your storefront.</p>
                        </div>
                        @if ($canManageStoreSettings && $storeActionPayload)
                            <button
                                type="button"
                                class="js-open-edit-store-modal inline-flex h-10 items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]"
                                data-store='@json($storeActionPayload)'
                            >
                                Edit store
                            </button>
                        @elseif (! $canManageStoreSettings)
                            <p class="text-xs font-semibold uppercase tracking-[0.06em] text-[#94A3B8]">Read-only for your role</p>
                        @endif
                    </div>
                    <div class="space-y-6 p-5">
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
                                        <input value="{{ $store->name }}" readonly class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                                    </label>
                                    <label class="space-y-1.5">
                                        <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Store contact email</span>
                                        <input value="{{ $contactEmailDisplay }}" readonly class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                                    </label>
                                </div>
                                <label class="block space-y-1.5">
                                    <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Physical Address</span>
                                    <textarea readonly class="min-h-20 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#0F172A]">{{ $store->address ?: 'No store address saved' }}</textarea>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#F1F5F9] px-5 py-4">
                        <h2 class="text-2xl">Regional &amp; Financials</h2>
                        <p class="text-sm text-[#64748B]">Store defaults for dashboard totals, dates, and default selling context.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Default store currency</p>
                            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $store->currency ?? 'USD' }}</p>
                            <p class="mt-3 text-sm leading-relaxed text-[#64748B]">Base currency for future dashboard totals and default pricing. Historical orders keep their saved currency and totals.</p>
                        </div>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Default store timezone</p>
                            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $store->timezone ?? 'UTC' }}</p>
                            <p class="mt-3 text-sm leading-relaxed text-[#64748B]">Used for future dashboard dates and store operations. Past order timestamps stay unchanged.</p>
                        </div>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Primary market</p>
                            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $primaryMarket }}</p>
                            <p class="mt-3 text-sm leading-relaxed text-[#64748B]">Default selling region for this store. Change it from the store editor when you have permission.</p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                    <div class="border-b border-[#F1F5F9] px-5 py-4">
                        <h2 class="text-2xl">Business Configuration</h2>
                        <p class="text-sm text-[#64748B]">Operational status, store type, inventory location, delivery, and payments.</p>
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
                                        <p class="mt-2 text-xs text-[#64748B]">Locations store and fulfill inventory. Markets and currency control how you sell.</p>
                                    </div>
                                    <a href="{{ route('settings.locations.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Manage locations</a>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <aside class="rounded-xl border border-[#D8E1EC] bg-[#F8FAFC] p-5">
                                <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#94A3B8]">Delivery setup</p>
                                <h3 class="mt-1 text-xl font-semibold text-[#0F172A]">Delivery</h3>
                                <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Set ship-from locations, delivery areas, checkout delivery options, and optional delivery providers.</p>
                                <a href="{{ route('shippingAutomation') }}" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Open delivery setup</a>
                            </aside>

                            <aside class="rounded-xl border border-[#D8E1EC] bg-[#F8FAFC] p-5">
                                <p class="text-xs font-bold uppercase tracking-[0.6px] text-[#94A3B8]">Checkout payments</p>
                                <h3 class="mt-1 text-xl font-semibold text-[#0F172A]">Payments</h3>
                                <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Connect Stripe and manage how this store collects payment for platform and external checkout. SaaS subscription billing stays separate and is not shown here.</p>
                                <a href="{{ route('settings.payments.index') }}" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Open payments settings</a>
                            </aside>
                        </div>
                    </div>
                </section>

                @include('user_view.partials.store_edit_modal')
            @endunless
        @else
            <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#F1F5F9] px-5 py-4 sm:flex-row sm:items-center">
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-[#E2E8F0] bg-[#F8FAFC] text-xl font-bold text-[#475569]">
                        @if ($profileUser?->avatar)
                            <img src="{{ asset('storage/'.$profileUser->avatar) }}" alt="{{ $profileUser->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $profileInitial }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-2xl text-[#0F172A]">{{ $profileUser?->name }}</h2>
                        <p class="truncate text-sm text-[#64748B]">{{ $profileUser?->email }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-[#D1FAE5] px-2.5 py-0.5 text-xs font-semibold text-[#047857]">
                                {{ $profileUser?->is_active === false ? 'Deactivated' : 'Active account' }}
                            </span>
                            <span class="inline-flex rounded-full border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-0.5 text-xs font-semibold text-[#475569]">
                                {{ $profileUser?->role?->name === 'admin' ? 'Platform admin' : 'Merchant user' }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                        <div class="border-b border-[#F1F5F9] px-5 py-4">
                            <h3 class="text-xl text-[#0F172A]">Personal information</h3>
                            <p class="text-sm text-[#64748B]">Keep your merchant account contact details current.</p>
                        </div>
                        <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Full name</span>
                                <input type="text" name="name" value="{{ old('name', $profileUser?->name) }}" required class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Email address</span>
                                <input type="email" name="email" value="{{ old('email', $profileUser?->email) }}" required class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Phone number</span>
                                <input type="text" name="phone" value="{{ old('phone', $profileUser?->phone) }}" class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Profile photo</span>
                                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-[#475569] file:mr-3 file:h-10 file:rounded-lg file:border-0 file:bg-[#EFF6FF] file:px-3 file:font-semibold file:text-[#0052CC]">
                            </label>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover">Save profile</button>
                            </div>
                        </form>
                    </section>

                    <section id="password" class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                        <div class="border-b border-[#F1F5F9] px-5 py-4">
                            <h3 class="text-xl text-[#0F172A]">Password</h3>
                            <p class="text-sm text-[#64748B]">Use a strong password that is not shared with other tools.</p>
                        </div>
                        <form method="POST" action="{{ route('profile.password.update') }}" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Current password</span>
                                <input type="password" name="current_password" required autocomplete="current-password" class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">New password</span>
                                <input type="password" name="password" required autocomplete="new-password" class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs font-bold uppercase tracking-[0.6px] text-[#64748B]">Confirm password</span>
                                <input type="password" name="password_confirmation" required autocomplete="new-password" class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm text-[#0F172A]">
                            </label>
                            <div class="md:col-span-3 flex justify-end">
                                <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Change password</button>
                            </div>
                        </form>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                        <div class="border-b border-[#F1F5F9] bg-[#F8FAFC] px-5 py-4">
                            <h3 class="text-lg text-[#0F172A]">Store access</h3>
                        </div>
                        <div class="space-y-3 p-5">
                            @forelse ($memberStores as $memberStore)
                                <div class="rounded-lg border border-[#E2E8F0] bg-white p-3">
                                    <p class="font-semibold text-[#0F172A]">{{ $memberStore->name }}</p>
                                    <p class="text-sm text-[#64748B]">{{ \Illuminate\Support\Str::headline($memberStore->pivot?->role ?? 'member') }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-[#64748B]">You are not assigned to a store yet.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                        <div class="border-b border-[#F1F5F9] px-5 py-4">
                            <h3 class="text-lg text-[#0F172A]">Account checks</h3>
                        </div>
                        <div class="space-y-4 p-5 text-sm">
                            <div>
                                <p class="font-semibold text-[#0F172A]">Email verification</p>
                                <p class="text-[#64748B]">{{ $profileUser?->email_verified_at ? 'Verified '.$profileUser->email_verified_at->diffForHumans() : 'Verification is pending.' }}</p>
                                @unless ($profileUser?->email_verified_at)
                                    <a href="{{ route('verification.notice') }}" class="mt-2 inline-flex text-sm font-semibold text-[#0052CC] hover:underline">Verify email</a>
                                @endunless
                            </div>
                            <div>
                                <p class="font-semibold text-[#0F172A]">Last sign-in</p>
                                <p class="text-[#64748B]">{{ $profileUser?->last_login_at?->diffForHumans() ?? 'Not recorded yet' }}</p>
                            </div>
                            <a href="{{ route('security') }}" class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover">Open security</a>
                        </div>
                    </section>
                </aside>
            </div>
        @endif
    </div>
@endsection
