@php
    use App\Support\StoreBusinessDefaults;

    $store = $store ?? null;
    $settings = is_array($store?->settings) ? $store->settings : [];
    $defaultLocation = $defaultLocation ?? null;
    $storeLocations = $storeLocations ?? collect();
    $requiresCatalogConversion = (bool) ($requiresCatalogConversion ?? false);
    $categoryValue = old('category', $store?->category ?: 'physical');
    $businessModels = collect(old('business_models', $settings['business_models'] ?? []))->filter()->values();
    $setupComplete = (bool) ($store?->onboarding_completed);
    $currentCurrency = strtoupper((string) old('currency', $store?->currency ?? 'USD'));
@endphp

<form
    method="POST"
    action="{{ route('store.update', ['storeId' => $store->id]) }}"
    enctype="multipart/form-data"
    class="space-y-8"
    id="general-settings-store-form"
    data-current-currency="{{ strtoupper((string) ($store->currency ?? 'USD')) }}"
    data-requires-catalog-conversion="{{ $requiresCatalogConversion ? '1' : '0' }}"
>
    @csrf
    @method('PUT')
    <input type="hidden" name="redirect_to" value="generalSettings">
    <input type="hidden" name="category" id="gs-store-category" value="{{ $categoryValue }}">

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            <p class="font-semibold">Could not save settings</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="gs-info-banner" role="note">
        <span class="gs-info-banner-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.674M12 3v1.5m0 15V21m7.794-14.294-.954.954M5.16 18.84l-.954.954M21 12h-1.5M4.5 12H3m15.84 6.84-.954-.954M5.16 5.16l-.954-.954"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5a4.5 4.5 0 0 1 2.25 8.372V17.25h-4.5v-1.378A4.5 4.5 0 0 1 12 7.5Z"/>
            </svg>
        </span>
        <div class="space-y-1">
            <p class="font-semibold text-[#334155]">What changing these settings affects</p>
            <p>
                Currency changes convert current catalog prices using a live exchange rate. Timezone only changes how dates are displayed.
                Past order amounts, currencies, and saved timestamps are never rewritten.
            </p>
        </div>
    </section>

    <section class="gs-card overflow-hidden">
        <div class="gs-card-header">
            <div>
                <h2 class="gs-card-title">Store Profile</h2>
                <p class="gs-card-lead">Name, contact email, logo, and business address for this store.</p>
            </div>
        </div>
        <div class="gs-card-body space-y-5">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="gs-field">
                    <span class="gs-label">Store name</span>
                    <input type="text" name="name" value="{{ old('name', $store->name) }}" required maxlength="120" class="gs-input is-editable">
                </label>
                <label class="gs-field">
                    <span class="gs-label">Store contact email</span>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $settings['contact_email'] ?? '') }}" placeholder="ops@example.com" maxlength="255" class="gs-input is-editable">
                    <span class="gs-field-hint">Optional operations contact. Separate from your login email.</span>
                </label>
            </div>

            <label class="gs-field">
                <span class="gs-label">Business address</span>
                <textarea name="address" rows="3" maxlength="1000" class="gs-input is-editable">{{ old('address', $store->address) }}</textarea>
                <span class="gs-field-hint">Business contact address. Ship-from inventory locations are managed separately.</span>
            </label>

            <div class="gs-field">
                <span class="gs-label">Store logo</span>
                @if ($store->logo)
                    <div class="mb-3 inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border border-[#E2E8F0] bg-white">
                        <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="max-h-full max-w-full object-contain p-1">
                    </div>
                @endif
                <input type="file" name="store_logo" accept=".jpg,.jpeg,.png,.webp" class="gs-file">
                <span class="gs-field-hint">JPG, PNG, or WebP up to 2MB. Replacing a logo removes the previous file.</span>
            </div>
        </div>
    </section>

    <section class="gs-card overflow-hidden">
        <div class="gs-card-header">
            <div>
                <h2 class="gs-card-title">Regional &amp; Financials</h2>
                <p class="gs-card-lead">Defaults for catalog pricing context and how dates appear in this workspace.</p>
            </div>
        </div>
        <div class="gs-card-body grid gap-5 md:grid-cols-2">
            <div class="gs-field">
                <label for="gs-currency" class="gs-label">Default store currency</label>
                <select id="gs-currency" name="currency" class="gs-input is-editable">
                    @foreach (StoreBusinessDefaults::currencies() as $currency)
                        <option value="{{ $currency }}" @selected($currentCurrency === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
                <p id="gs-currency-help" class="gs-field-hint">
                    @if ($requiresCatalogConversion)
                        Changing currency converts product and variant prices. Past orders keep their original currency and amounts.
                    @else
                        Labels catalog prices and new activity. You can change this freely before products exist.
                    @endif
                </p>
                <label id="gs-currency-confirm-wrap" class="mt-3 {{ ($errors->has('confirm_currency_conversion') || (old('currency') && old('currency') !== ($store->currency ?? 'USD'))) && $requiresCatalogConversion ? 'flex' : 'hidden' }} items-start gap-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]">
                    <input id="gs-confirm-currency-conversion" name="confirm_currency_conversion" type="checkbox" value="1" @checked(old('confirm_currency_conversion')) class="mt-0.5 rounded border-[#F59E0B] text-[#D97706] focus:ring-[#F59E0B]/30">
                    <span>Convert all product and variant prices to the new currency using a live exchange rate. Past orders will not change.</span>
                </label>
            </div>

            <label class="gs-field">
                <span class="gs-label">Default store timezone</span>
                <select name="timezone" class="gs-input is-editable">
                    @foreach (StoreBusinessDefaults::timezones() as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $store->timezone ?? 'UTC') === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
                <span class="gs-field-hint">Controls how dates are displayed. Saved timestamps are not rewritten.</span>
            </label>
        </div>
    </section>

    <section class="gs-card overflow-hidden">
        <div class="gs-card-header">
            <div>
                <h2 class="gs-card-title">Business Configuration</h2>
                <p class="gs-card-lead">Store type, business model tags, and default inventory location.</p>
            </div>
        </div>
        <div class="gs-card-body space-y-6">
            <div class="gs-metric-tile">
                <div class="flex items-center justify-between gap-3">
                    <p class="gs-label">Setup status</p>
                    <span class="gs-readonly-note">Read-only fact</span>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <span @class(['gs-status-dot', 'is-live' => $setupComplete]) aria-hidden="true"></span>
                    <p class="gs-metric-value gs-metric-value-md">{{ $setupComplete ? 'Setup complete' : 'Setup in progress' }}</p>
                </div>
                <p class="gs-metric-help mt-1">
                    {{ $setupComplete
                        ? 'Store onboarding is finished. This is not the same as a live WordPress storefront.'
                        : 'Finish onboarding to complete workspace setup.' }}
                </p>
            </div>

            <div>
                <p class="gs-label mb-2">Main category</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ([
                        ['physical', 'Physical Goods'],
                        ['digital', 'Digital Products'],
                        ['service', 'Services'],
                        ['subscription', 'Subscriptions'],
                        ['virtual', 'Memberships'],
                    ] as [$categoryKey, $label])
                        <button
                            type="button"
                            class="gs-category-btn rounded-xl border px-4 py-4 text-center text-xs font-semibold transition {{ $categoryValue === $categoryKey ? 'border-brand bg-[#EAF2FF] text-brand' : 'border-[#E2E8F0] bg-white text-[#0F172A] hover:border-brand' }}"
                            data-category="{{ $categoryKey }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <label class="gs-field">
                    <span class="gs-label">Custom category</span>
                    <input type="text" name="custom_category" value="{{ old('custom_category', $settings['custom_category'] ?? '') }}" maxlength="80" placeholder="Optional custom label" class="gs-input is-editable">
                </label>
                <div class="gs-field">
                    <span class="gs-label">Business model tags</span>
                    <div class="mt-1 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach (['Physical Goods', 'Digital Products', 'Services', 'Subscriptions', 'Memberships'] as $model)
                            <label class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#475569]">
                                <input type="checkbox" name="business_models[]" value="{{ $model }}" @checked($businessModels->contains($model)) class="rounded border-[#CBD5E1] text-brand focus:ring-brand/20">
                                <span>{{ $model }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="gs-field">
                <label for="gs-default-location" class="gs-label">Default inventory location</label>
                @if ($storeLocations->isNotEmpty())
                    <select id="gs-default-location" name="default_location_id" class="gs-input is-editable">
                        <option value="">Keep current default</option>
                        @foreach ($storeLocations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('default_location_id', $defaultLocation?->id) === (string) $location->id)>
                                {{ $location->name }}{{ $location->is_default ? ' (current default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="gs-field-hint">
                        Ship-from location used for inventory defaults.
                        <a href="{{ route('settings.locations.index') }}" class="font-semibold text-brand hover:underline">Manage locations</a>
                    </span>
                @else
                    <p class="gs-fact">Not set yet</p>
                    <span class="gs-field-hint">
                        Create a location when you are ready to stock and fulfill inventory.
                        <a href="{{ route('settings.locations.index') }}" class="font-semibold text-brand hover:underline">Manage locations</a>
                    </span>
                @endif
            </div>
        </div>
        <div class="gs-card-footer flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-[#64748B]">Store owners can change these defaults. Managers and staff can view them as read-only.</p>
            <button type="submit" class="gs-btn-primary">Save store settings</button>
        </div>
    </section>
</form>

<script>
    (() => {
        const form = document.getElementById('general-settings-store-form');
        if (!form) {
            return;
        }

        const categoryInput = document.getElementById('gs-store-category');
        const currencySelect = document.getElementById('gs-currency');
        const confirmWrap = document.getElementById('gs-currency-confirm-wrap');
        const confirmInput = document.getElementById('gs-confirm-currency-conversion');
        const currencyHelp = document.getElementById('gs-currency-help');
        const currentCurrency = (form.dataset.currentCurrency || 'USD').toUpperCase();
        const requiresConversion = form.dataset.requiresCatalogConversion === '1';

        const syncCurrencyPrompt = () => {
            if (!currencySelect || !confirmWrap) {
                return;
            }

            const changed = currencySelect.value.toUpperCase() !== currentCurrency;
            const show = requiresConversion && changed;
            confirmWrap.classList.toggle('hidden', !show);
            confirmWrap.classList.toggle('flex', show);

            if (confirmInput && !show) {
                confirmInput.checked = false;
            }

            if (currencyHelp) {
                currencyHelp.textContent = requiresConversion
                    ? 'Changing currency converts product and variant prices. Past orders stay unchanged.'
                    : 'Labels catalog prices and new activity. Does not rewrite past orders.';
            }
        };

        document.querySelectorAll('.gs-category-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const category = button.dataset.category || '';
                if (categoryInput) {
                    categoryInput.value = category;
                }

                document.querySelectorAll('.gs-category-btn').forEach((item) => {
                    const active = item === button;
                    item.classList.toggle('border-brand', active);
                    item.classList.toggle('bg-[#EAF2FF]', active);
                    item.classList.toggle('text-brand', active);
                    item.classList.toggle('border-[#E2E8F0]', !active);
                    item.classList.toggle('bg-white', !active);
                    item.classList.toggle('text-[#0F172A]', !active);
                });
            });
        });

        currencySelect?.addEventListener('change', syncCurrencyPrompt);
        syncCurrencyPrompt();
    })();
</script>
