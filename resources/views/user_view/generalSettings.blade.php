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
    $categoryHeadline = \Illuminate\Support\Str::headline((string) $categoryLabel);
    $businessModelLine = $businessModels->isNotEmpty()
        ? $businessModels->map(fn ($model) => \Illuminate\Support\Str::headline((string) $model))->implode(', ')
        : $categoryHeadline.' products';
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
    <div class="gs-page w-full space-y-8">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
                {{ $errors->first() }}
            </div>
        @endif

        <nav class="gs-tabs" aria-label="Settings sections">
            <a
                href="{{ route('generalSettings', ['tab' => 'store']) }}"
                @class(['gs-tab', 'is-active' => $settingsTab === 'store'])
            >
                Store
            </a>
            <a
                href="{{ route('generalSettings', ['tab' => 'account']) }}"
                @class(['gs-tab', 'is-active' => $settingsTab === 'account'])
            >
                Your account
            </a>
        </nav>

        @if ($settingsTab === 'store')
            @unless ($store)
                <section class="gs-card p-6">
                    <h2 class="gs-card-title">No active store</h2>
                    <p class="gs-card-lead">Create or select a store before changing store settings.</p>
                    <a href="{{ route('store-management') }}" class="gs-btn-primary mt-4 inline-flex">Open store management</a>
                </section>
            @else
                <section class="gs-info-banner" role="note">
                    <span class="gs-info-banner-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.674M12 3v1.5m0 15V21m7.794-14.294-.954.954M5.16 18.84l-.954.954M21 12h-1.5M4.5 12H3m15.84 6.84-.954-.954M5.16 5.16l-.954-.954"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5a4.5 4.5 0 0 1 2.25 8.372V17.25h-4.5v-1.378A4.5 4.5 0 0 1 12 7.5Z"/>
                        </svg>
                    </span>
                    <p>Changes to currency and timezone apply to future activity; historical records remain unchanged.</p>
                </section>

                <section class="gs-card">
                    <div class="gs-card-header">
                        <div>
                            <h2 class="gs-card-title">Store Profile</h2>
                            <p class="gs-card-lead">Public identity and appearance of your storefront.</p>
                        </div>
                        @if ($canManageStoreSettings && $storeActionPayload)
                            <button
                                type="button"
                                class="js-open-edit-store-modal gs-btn-secondary"
                                data-store='@json($storeActionPayload)'
                            >
                                Edit profile
                            </button>
                        @elseif (! $canManageStoreSettings)
                            <p class="gs-readonly-note">Read-only for your role</p>
                        @endif
                    </div>
                    <div class="gs-card-body">
                        <div class="gs-profile-grid">
                            <div>
                                <p class="gs-label">Store Logo</p>
                                <div class="gs-logo-box">
                                    @if ($store->logo)
                                        <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-full w-full object-contain p-3">
                                    @else
                                        <span class="gs-logo-initial">{{ $storeInitial }}</span>
                                        <span class="gs-logo-caption">No Logo</span>
                                    @endif
                                </div>
                            </div>
                            <div class="gs-profile-fields">
                                <label class="gs-field">
                                    <span class="gs-label">Store Name</span>
                                    <input value="{{ $store->name }}" readonly class="gs-input">
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">Store Contact Email</span>
                                    <input value="{{ $contactEmailDisplay }}" readonly class="gs-input" placeholder="Not set">
                                </label>
                                <label class="gs-field gs-field-span">
                                    <span class="gs-label">Physical Address</span>
                                    <textarea readonly rows="3" class="gs-textarea">{{ $store->address ?: 'No store address saved' }}</textarea>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="gs-card">
                    <div class="gs-card-header">
                        <div>
                            <h2 class="gs-card-title">Regional &amp; Financials</h2>
                            <p class="gs-card-lead">Store defaults for dashboard totals, dates, and default selling context.</p>
                        </div>
                        @if ($canManageStoreSettings && $storeActionPayload)
                            <button
                                type="button"
                                class="js-open-edit-store-modal gs-btn-secondary"
                                data-store='@json($storeActionPayload)'
                            >
                                Edit settings
                            </button>
                        @elseif (! $canManageStoreSettings)
                            <p class="gs-readonly-note">Read-only for your role</p>
                        @endif
                    </div>
                    <div class="gs-card-body">
                        <div class="gs-metric-grid">
                            <div class="gs-metric-tile">
                                <p class="gs-label">Default Store Currency</p>
                                <p class="gs-metric-value">{{ $store->currency ?? 'USD' }}</p>
                                <p class="gs-metric-help">Base currency for future dashboard totals and default pricing.</p>
                            </div>
                            <div class="gs-metric-tile">
                                <p class="gs-label">Default Store Timezone</p>
                                <p class="gs-metric-value gs-metric-value-sm" title="{{ $store->timezone ?? 'UTC' }}">{{ $store->timezone ?? 'UTC' }}</p>
                                <p class="gs-metric-help">Used for future dashboard dates and store operations.</p>
                            </div>
                            <div class="gs-metric-tile">
                                <p class="gs-label">Primary Market</p>
                                <p class="gs-metric-value gs-metric-value-sm">{{ $primaryMarket }}</p>
                                <p class="gs-metric-help">Default selling region for this store. Manage via region settings.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="gs-card">
                    <div class="gs-card-header">
                        <div>
                            <h2 class="gs-card-title">Business Configuration</h2>
                            <p class="gs-card-lead">Operational status, store type, and primary inventory location.</p>
                        </div>
                        @if ($canManageStoreSettings && $storeActionPayload)
                            <button
                                type="button"
                                class="js-open-edit-store-modal gs-btn-secondary"
                                data-store='@json($storeActionPayload)'
                            >
                                Configure
                            </button>
                        @elseif (! $canManageStoreSettings)
                            <p class="gs-readonly-note">Read-only for your role</p>
                        @endif
                    </div>
                    <div class="gs-card-body space-y-6">
                        <div class="gs-business-grid">
                            <div class="gs-metric-tile">
                                <p class="gs-label">Main Category</p>
                                <p class="gs-metric-value gs-metric-value-md">{{ $categoryHeadline }}</p>
                                <p class="gs-metric-help">{{ $businessModelLine }}</p>
                            </div>
                            <div class="gs-metric-tile">
                                <p class="gs-label">Operational Status</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span @class(['gs-status-dot', 'is-live' => (bool) $store->onboarding_completed]) aria-hidden="true"></span>
                                    <p class="gs-metric-value gs-metric-value-md">{{ $store->onboarding_completed ? 'Live workspace' : 'Draft setup' }}</p>
                                </div>
                                <p class="gs-metric-help mt-1">{{ $store->onboarding_completed ? 'Store onboarding is complete.' : 'Finish onboarding before launch.' }}</p>
                            </div>
                        </div>

                        <div class="gs-metric-tile">
                            <p class="gs-label">Default Inventory Location</p>
                            <p class="gs-metric-value gs-metric-value-md">{{ $defaultLocation?->name ?? 'Main location' }}</p>
                            <p class="gs-metric-help">{{ $defaultLocationAddress ?: 'No address saved' }}</p>
                            <p class="gs-metric-footnote">Locations store and fulfill inventory. Markets and currency control how you sell.</p>
                        </div>
                    </div>
                </section>

                @include('user_view.partials.store_edit_modal')
            @endunless
        @else
            <div class="gs-account-layout">
                <div class="space-y-6">
                    <section class="gs-card gs-profile-summary">
                        <div class="gs-avatar">
                            @if ($profileUser?->avatar)
                                <img src="{{ asset('storage/'.$profileUser->avatar) }}" alt="{{ $profileUser->name }}" class="h-full w-full object-cover">
                            @else
                                {{ $profileInitial }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h2 class="gs-card-title truncate">{{ $profileUser?->name }}</h2>
                            <p class="mt-1 truncate text-sm text-[#64748B]">{{ $profileUser?->email }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="gs-badge gs-badge-success">
                                    {{ $profileUser?->is_active === false ? 'Deactivated' : 'Active account' }}
                                </span>
                                <span class="gs-badge gs-badge-muted">
                                    {{ $profileUser?->role?->name === 'admin' ? 'Platform admin' : 'Merchant user' }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="gs-card overflow-hidden">
                        <div class="gs-card-header">
                            <div>
                                <h3 class="gs-card-title">Personal information</h3>
                                <p class="gs-card-lead">Keep your merchant account contact details current. Changing your email address will require re-verification.</p>
                            </div>
                        </div>
                        <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <div class="gs-card-body gs-form-grid">
                                <label class="gs-field">
                                    <span class="gs-label">Full name</span>
                                    <input type="text" name="name" value="{{ old('name', $profileUser?->name) }}" required class="gs-input is-editable">
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">Email address</span>
                                    <input type="email" name="email" value="{{ old('email', $profileUser?->email) }}" required class="gs-input is-editable">
                                    <span class="gs-field-hint">* Changing this does not affect historical billing.</span>
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">Phone number</span>
                                    <input type="text" name="phone" value="{{ old('phone', $profileUser?->phone) }}" placeholder="+1 (555) 000-0000" class="gs-input is-editable">
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">Profile photo</span>
                                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="gs-file">
                                </label>
                            </div>
                            <div class="gs-card-footer">
                                <button type="submit" class="gs-btn-primary">Save changes</button>
                            </div>
                        </form>
                    </section>

                    <section id="password" class="gs-card overflow-hidden">
                        <div class="gs-card-header">
                            <div>
                                <h3 class="gs-card-title">Password</h3>
                                <p class="gs-card-lead">Use a strong password that is not shared with other tools.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('profile.password.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="gs-card-body gs-password-grid">
                                <label class="gs-field">
                                    <span class="gs-label">Current password</span>
                                    <input type="password" name="current_password" required autocomplete="current-password" class="gs-input is-editable">
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">New password</span>
                                    <input type="password" name="password" required autocomplete="new-password" class="gs-input is-editable">
                                </label>
                                <label class="gs-field">
                                    <span class="gs-label">Confirm password</span>
                                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="gs-input is-editable">
                                </label>
                            </div>
                            <div class="gs-card-footer">
                                <button type="submit" class="gs-btn-outline">Change password</button>
                            </div>
                        </form>
                    </section>
                </div>

                <aside>
                    <section class="gs-card overflow-hidden">
                        <div class="gs-card-header gs-card-header-compact">
                            <h3 class="text-base font-semibold text-[#0F172A]">Store access</h3>
                        </div>
                        <div class="space-y-3 p-4">
                            @forelse ($memberStores as $memberStore)
                                <div class="gs-access-item">
                                    <p class="text-sm font-medium text-[#0F172A]">{{ $memberStore->name }}</p>
                                    <p class="mt-1 text-xs text-[#64748B]">{{ \Illuminate\Support\Str::headline($memberStore->pivot?->role ?? 'member') }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-[#64748B]">You are not assigned to a store yet.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        @endif
    </div>
@endsection
