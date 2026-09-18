@extends('layouts.user.user-sidebar')

@php
    use App\Support\StorePermission;

    $canManageKey = auth()->user()->hasStorePermission($selectedStore, StorePermission::DEVELOPER_API_MANAGE);
    $heroClass = match ($connectionState) {
        \App\Models\Store::WEBSITE_CONNECTED => 'is-connected',
        \App\Models\Store::WEBSITE_WAITING => 'is-waiting',
        \App\Models\Store::WEBSITE_DISCONNECTED => 'is-disconnected',
        default => 'is-idle',
    };
    $activeStep = (int) ($activeStep ?? 1);
    $editingWebsite = (bool) ($editingWebsite ?? false);
    $showWebsiteForm = ! $step1Done || $editingWebsite;
    $step2Locked = ! $step1Done;
    $step3Locked = ! $step2Done || filled($plainToken);
    $websiteHost = $websiteUrl ? (parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl) : null;
    $connectStep = static function (int $step, bool $edit = false): string {
        return route('developer-storefront.settings', array_filter([
            'step' => $step,
            'edit' => $edit ? 1 : null,
        ]));
    };
@endphp

@section('title', 'Connect your website — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Connect your website" lead="Put your products on your own website in three steps.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="wc-console" data-wc-console data-status-url="{{ route('developer-storefront.status') }}">

        @if (session('success'))
            <div class="wc-alert is-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="wc-alert is-error">{{ $errors->first() }}</div>
        @endif

        @if ($publishedProductCount < 1)
            <div class="wc-alert is-warn">
                You have no published products yet, so your website would show an empty shop.
                <a href="{{ route('products.create') }}">Add a product</a>
            </div>
        @endif

        @if ($siteIssues !== [])
            <div class="wc-alert is-warn">
                <p class="wc-alert-title">Your website reported a problem</p>
                <ul class="wc-alert-list">
                    @foreach ($siteIssues as $issue)
                        <li><strong>{{ $issue['title'] }}</strong> {{ $issue['instruction'] }}</li>
                    @endforeach
                </ul>
                <p class="wc-alert-foot">Fix these on your website, then run its connection test again. Nothing on your website was changed from here.</p>
            </div>
        @endif

        {{-- Live status --}}
        <div class="wc-hero {{ $heroClass }}" data-wc-hero>
            <div class="wc-hero-status">
                <span class="wc-hero-ring" aria-hidden="true"><span class="wc-hero-dot"></span></span>
                <div class="min-w-0">
                    <p class="wc-hero-label" data-wc-label>{{ $stateLabel }}</p>
                    <p class="wc-hero-detail" data-wc-detail>{{ $stateDetail }}</p>
                </div>
            </div>

            <div class="wc-hero-facts">
                <div>
                    <p class="wc-fact-label">Your website</p>
                    <p class="wc-fact-value" data-wc-website>{{ $websiteHost ?? 'Not set yet' }}</p>
                </div>
                <div>
                    <p class="wc-fact-label">Last contact</p>
                    <p class="wc-fact-value" data-wc-lastseen>{{ $lastSeenAt ? $lastSeenAt->diffForHumans() : 'Never' }}</p>
                </div>
                <div>
                    <p class="wc-fact-label">Live products</p>
                    <p class="wc-fact-value" data-wc-products>{{ $publishedProductCount }}</p>
                </div>
                @if ($catalogStatus)
                    <div>
                        <p class="wc-fact-label">Catalog</p>
                        <p class="wc-fact-value" data-wc-catalog>{{ $catalogStatus }}</p>
                    </div>
                @endif
            </div>

            <div class="wc-hero-actions">
                <button type="button" class="wc-btn wc-btn-secondary" data-wc-refresh>
                    <span class="wc-spin" aria-hidden="true"></span>
                    <span data-wc-refresh-text>Check now</span>
                </button>
                <a href="{{ route('orders') }}" class="wc-btn wc-btn-primary">Open Orders</a>
            </div>
        </div>

        {{-- Steps + panel --}}
        <div class="wc-main">
            <nav class="wc-rail" aria-label="Setup steps">
                <p class="wc-rail-title">Setup</p>

                <button type="button" @class(['wc-rail-step', 'is-active' => $activeStep === 1, 'is-done' => $step1Done]) data-wc-step="1">
                    <span class="wc-rail-marker" aria-hidden="true">
                        <span class="wc-rail-num">1</span>
                        <span class="wc-rail-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                    </span>
                    <span class="min-w-0">
                        <span class="wc-rail-name">Website address</span>
                        <span class="wc-rail-sub" data-wc-sub="1">{{ $step1Done ? 'Saved' : 'Not set' }}</span>
                    </span>
                </button>

                <button
                    type="button"
                    @class(['wc-rail-step', 'is-active' => $activeStep === 2, 'is-done' => $step2Done && ! filled($plainToken), 'is-locked' => $step2Locked])
                    data-wc-step="2"
                    @disabled($step2Locked)
                    @if ($step2Locked) data-wc-locked="1" aria-disabled="true" @endif
                >
                    <span class="wc-rail-marker" aria-hidden="true">
                        <span class="wc-rail-num">2</span>
                        <span class="wc-rail-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                    </span>
                    <span class="min-w-0">
                        <span class="wc-rail-name">Connection key</span>
                        <span class="wc-rail-sub" data-wc-sub="2">{{ filled($plainToken) ? 'Copy this key' : ($step2Done ? 'Active' : 'Not created') }}</span>
                    </span>
                </button>

                <button
                    type="button"
                    @class(['wc-rail-step', 'is-active' => $activeStep === 3, 'is-done' => $step3Done, 'is-locked' => $step3Locked])
                    data-wc-step="3"
                    @disabled($step3Locked)
                    @if ($step3Locked) data-wc-locked="1" aria-disabled="true" @endif
                >
                    <span class="wc-rail-marker" aria-hidden="true">
                        <span class="wc-rail-num">3</span>
                        <span class="wc-rail-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                    </span>
                    <span class="min-w-0">
                        <span class="wc-rail-name">Connect your site</span>
                        <span class="wc-rail-sub" data-wc-sub="3">{{ $step3Done ? 'Connected' : ($step2Done && ! filled($plainToken) ? 'Waiting' : 'Finish step 2 first') }}</span>
                    </span>
                </button>

                <div class="wc-rail-foot">
                    <a href="{{ route('products') }}" class="wc-rail-link">Products <span>{{ $publishedProductCount }} live</span></a>
                    <a href="{{ route('settings.payments.index') }}" class="wc-rail-link">Payments <span>Stripe</span></a>
                    <a href="{{ route('shippingAutomation') }}" class="wc-rail-link">Delivery <span>Rates</span></a>
                </div>
            </nav>

            <div class="wc-panels">
                {{-- Step 1 --}}
                <section @class(['wc-panel', 'is-active' => $activeStep === 1]) data-wc-panel="1">
                    <h2 class="wc-panel-title">{{ $showWebsiteForm ? 'Where do your products go?' : 'Website address' }}</h2>
                    <p class="wc-panel-lead">
                        @if ($showWebsiteForm)
                            Enter the home address of the website that will show your products. One website per store.
                        @else
                            This store publishes products to the website below. Change it only if you are moving to a new site.
                        @endif
                    </p>

                    <div class="wc-panel-body">
                        @if ($showWebsiteForm)
                            @if ($canManageKey)
                                <form method="post" action="{{ route('developer-storefront.website.update') }}" class="wc-field" data-turbo="false">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="website_url">Website address</label>
                                    <input
                                        id="website_url"
                                        type="url"
                                        name="website_url"
                                        value="{{ old('website_url', $websiteUrl) }}"
                                        placeholder="https://yourshop.com"
                                        class="wc-input"
                                        required
                                    >
                                    <button type="submit" class="wc-btn wc-btn-primary">Save address</button>
                                    @if ($step1Done)
                                        <a href="{{ $connectStep(1) }}" class="wc-btn wc-btn-secondary">Cancel</a>
                                    @endif
                                </form>
                                <p class="wc-note">Include <strong>https://</strong>. Changing this later means reconnecting your website with a new key.</p>
                            @elseif ($websiteUrl)
                                <p class="wc-fact-value">{{ $websiteUrl }}</p>
                                <p class="wc-note">Only the store owner can change this address.</p>
                            @else
                                <p class="wc-note">No address saved yet. Only the store owner can set it.</p>
                            @endif
                        @else
                            <div class="wc-review">
                                <div>
                                    <p class="wc-fact-label">Saved website</p>
                                    <p class="wc-review-url">{{ $websiteUrl }}</p>
                                </div>
                                <div class="wc-review-actions">
                                    @if ($canManageKey)
                                        <a href="{{ $connectStep(1, true) }}" class="wc-btn wc-btn-secondary">Change address</a>
                                    @endif
                                    @if (! $step2Done)
                                        <a href="{{ $connectStep(2) }}" class="wc-btn wc-btn-primary">Continue to connection key</a>
                                    @elseif ($step3Done)
                                        <a href="{{ $connectStep(3) }}" class="wc-btn wc-btn-primary">View connection</a>
                                    @else
                                        <a href="{{ $connectStep(3) }}" class="wc-btn wc-btn-primary">Continue setup</a>
                                    @endif
                                </div>
                            </div>
                            @unless ($canManageKey)
                                <p class="wc-note">Only the store owner can change this address.</p>
                            @endunless
                        @endif
                    </div>
                </section>

                {{-- Step 2 --}}
                <section @class(['wc-panel', 'is-active' => $activeStep === 2]) data-wc-panel="2">
                    <h2 class="wc-panel-title">{{ filled($plainToken) ? 'Copy your connection key' : 'Your connection key' }}</h2>
                    <p class="wc-panel-lead">
                        @if (filled($plainToken))
                            Copy this private key now and paste it on your website. It is shown only once.
                        @else
                            This private key is what lets your website read your products and send orders back to this portal.
                        @endif
                    </p>

                    <div class="wc-panel-body">
                        @if ($plainToken)
                            <div class="wc-key-box">
                                <p>Copy this key now — it is shown only once.</p>
                                <div class="wc-copy-row">
                                    <code id="wc-key" class="wc-code">{{ $plainToken }}</code>
                                    <button type="button" class="wc-btn wc-btn-primary" data-copy-target="wc-key">Copy key</button>
                                </div>
                            </div>
                            <div class="wc-actions mt-4">
                                <a href="{{ $connectStep(3) }}" class="wc-btn wc-btn-primary">Continue to connect your site</a>
                            </div>
                            <p class="wc-note">Paste the key in WordPress (Settings → Eco Portal) before you leave this page. After you continue, the key cannot be shown again.</p>
                        @else
                            <span class="wc-status-line {{ $tokenConfigured ? 'is-on' : 'is-off' }}">
                                {{ $tokenConfigured ? 'Key active' : 'No key yet' }}
                            </span>
                            @if ($tokenConfigured)
                                <p class="wc-note mt-3">The full key is not stored here. If you lost it, replace it and paste the new key on your website.</p>
                            @endif
                        @endif

                        @if ($canManageKey)
                            <div class="wc-actions mt-4">
                                @if ($tokenConfigured)
                                    <button type="button" class="wc-btn wc-btn-secondary" data-wc-open-replace-key @disabled(! $step1Done)>Replace key</button>
                                    <button type="button" class="wc-btn wc-btn-danger" data-wc-open-remove-key>Remove key</button>
                                @else
                                    <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false">
                                        @csrf
                                        <button type="submit" class="wc-btn wc-btn-primary" @disabled(! $step1Done)>Create key</button>
                                    </form>
                                @endif
                                @if ($tokenConfigured && ! filled($plainToken))
                                    <a href="{{ $connectStep(3) }}" class="wc-btn wc-btn-primary">{{ $step3Done ? 'View connection' : 'Continue to connect your site' }}</a>
                                @endif
                            </div>
                            <p class="wc-note">
                                @if (! $step1Done)
                                    Save your website address in step 1 first.
                                @else
                                    Keep this key private. Anyone with it can read this store’s catalog.
                                @endif
                            </p>
                        @else
                            <p class="wc-note">Only the store owner can create or remove the key.</p>
                        @endif
                    </div>
                </section>

                {{-- Step 3 --}}
                <section @class(['wc-panel', 'is-active' => $activeStep === 3]) data-wc-panel="3">
                    @if ($step3Done)
                        <h2 class="wc-panel-title">Your website is connected</h2>
                        <p class="wc-panel-lead">Products from this store are loading on {{ $websiteHost ?? 'your website' }}. Change the address or key only when you need to reconnect.</p>

                        <div class="wc-panel-body">
                            <div class="wc-manage-grid">
                                <article class="wc-manage-card">
                                    <p class="wc-fact-label">Website</p>
                                    <p class="wc-manage-value">{{ $websiteUrl }}</p>
                                    @if ($canManageKey)
                                        <a href="{{ $connectStep(1, true) }}" class="wc-btn wc-btn-ghost mt-3">Change address</a>
                                    @endif
                                </article>
                                <article class="wc-manage-card">
                                    <p class="wc-fact-label">Connection key</p>
                                    <p class="wc-manage-value">Active</p>
                                    <p class="wc-note" style="margin-top: 0.35rem;">The full key is not shown again.</p>
                                    @if ($canManageKey)
                                        <a href="{{ $connectStep(2) }}" class="wc-btn wc-btn-ghost mt-3">Replace or remove key</a>
                                    @endif
                                </article>
                                <article class="wc-manage-card">
                                    <p class="wc-fact-label">Last contact</p>
                                    <p class="wc-manage-value">{{ $lastSeenAt ? $lastSeenAt->diffForHumans() : 'Never' }}</p>
                                    <p class="wc-note" style="margin-top: 0.35rem;">{{ $catalogStatus ?: 'Use Check now in the header if something looks off.' }}</p>
                                </article>
                            </div>

                            <details class="wc-reconnect">
                                <summary>Need to reconnect WordPress or a custom site?</summary>
                                <p class="wc-panel-lead" style="margin-top: 0.75rem;">Use the same plugin and key steps if you reinstall WordPress or move hosts.</p>
                                @include('user_view.partials.website_connect_howto')
                            </details>
                        </div>
                    @else
                        <h2 class="wc-panel-title">Connect your site</h2>
                        <p class="wc-panel-lead">Install the plugin, paste your key, then test the connection. This portal waits until your website checks in.</p>

                        <div class="wc-panel-body">
                            @include('user_view.partials.website_connect_howto')
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.bootMerchantPage('website-connect', function () {
            return document.querySelector('[data-wc-console]');
        }, function (root) {

            /* Copy buttons */
            root.querySelectorAll('[data-copy-target]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(button.getAttribute('data-copy-target'));
                    if (!target) return;
                    try {
                        await navigator.clipboard.writeText(target.textContent.trim());
                        const original = button.textContent;
                        button.textContent = 'Copied';
                        window.setTimeout(() => { button.textContent = original; }, 1500);
                    } catch (error) {
                        /* Clipboard blocked: the value stays on screen to copy by hand. */
                    }
                });
            });

            /* Step switching — locked steps stay closed until earlier work is done. */
            const steps = root.querySelectorAll('[data-wc-step]');
            const panels = root.querySelectorAll('[data-wc-panel]');
            steps.forEach((step) => {
                step.addEventListener('click', () => {
                    if (step.disabled || step.hasAttribute('data-wc-locked')) return;
                    const id = step.getAttribute('data-wc-step');
                    steps.forEach((s) => s.classList.toggle('is-active', s === step));
                    panels.forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-wc-panel') === id));
                });
            });

            /* WordPress / custom switch */
            const platButtons = root.querySelectorAll('[data-wc-plat]');
            const platPanels = root.querySelectorAll('[data-wc-plat-panel]');
            platButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const id = button.getAttribute('data-wc-plat');
                    platButtons.forEach((b) => b.classList.toggle('is-active', b === button));
                    platPanels.forEach((p) => p.classList.toggle('is-active', p.getAttribute('data-wc-plat-panel') === id));
                });
            });

            /* Live connection status */
            const hero = root.querySelector('[data-wc-hero]');
            const refresh = root.querySelector('[data-wc-refresh]');
            const refreshText = root.querySelector('[data-wc-refresh-text]');
            const statusUrl = root.getAttribute('data-status-url');
            const stateClasses = {
                connected: 'is-connected',
                waiting: 'is-waiting',
                disconnected: 'is-disconnected',
                not_started: 'is-idle',
            };
            const setText = (selector, value) => {
                const node = root.querySelector(selector);
                if (node && value !== null && value !== undefined) node.textContent = value;
            };

            let checking = false;

            async function loadStatus(manual) {
                if (checking) return;
                checking = true;
                if (manual && refresh) refresh.classList.add('is-checking');

                try {
                    const response = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const data = await response.json();

                    hero.className = 'wc-hero ' + (stateClasses[data.state] || 'is-idle');
                    setText('[data-wc-label]', data.label);
                    setText('[data-wc-detail]', data.detail);
                    setText('[data-wc-lastseen]', data.last_seen_human || 'Never');
                    setText('[data-wc-products]', data.published_products);
                    setText('[data-wc-catalog]', data.catalog_status);
                    if (data.website_url) {
                        try {
                            setText('[data-wc-website]', new URL(data.website_url).host);
                        } catch (error) {
                            setText('[data-wc-website]', data.website_url);
                        }
                    }

                    const subs = { 1: ['Saved', 'Not set'], 2: ['Active', 'Not created'], 3: ['Connected', 'Waiting'] };
                    Object.keys(subs).forEach((id) => {
                        const done = !!(data.steps_done && data.steps_done[id]);
                        const step = root.querySelector('[data-wc-step="' + id + '"]');
                        if (step) step.classList.toggle('is-done', done);
                        setText('[data-wc-sub="' + id + '"]', done ? subs[id][0] : subs[id][1]);
                    });

                    if (manual && refreshText) {
                        refreshText.textContent = 'Checked ' + data.checked_at;
                        window.setTimeout(() => { refreshText.textContent = 'Check now'; }, 2500);
                    }
                } catch (error) {
                    /* Offline or blocked: keep whatever the page already shows. */
                } finally {
                    checking = false;
                    if (refresh) refresh.classList.remove('is-checking');
                }
            }

            if (refresh) refresh.addEventListener('click', () => loadStatus(true));
        });
        window.bindMerchantDocOnce('website-connect:poll', function () {
            window.setInterval(function () {
                if (document.visibilityState !== 'visible') return;
                var refresh = document.querySelector('[data-wc-console] [data-wc-refresh]');
                if (refresh) refresh.click();
            }, 20000);
        });
    </script>
@endpush

@push('overlays')
    @if ($canManageKey && $tokenConfigured)
        <div id="websiteReplaceKeyModal" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="websiteReplaceKeyTitle">
            <div class="ui-modal-panel ui-modal-panel--md border-[#FDE68A]">
                <div class="bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.18),_transparent_60%)] px-6 pb-4 pt-6">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFFBEB] text-[#D97706] shadow-sm" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64 18.3 1.55 18.65 1.55 19C1.55 19.35 1.64 19.7 1.81 20C1.99 20.31 2.24 20.56 2.54 20.74C2.85 20.92 3.19 21.02 3.54 21.02H20.46C20.81 21.02 21.15 20.92 21.46 20.74C21.76 20.56 22.01 20.31 22.19 20C22.36 19.7 22.45 19.35 22.45 19C22.45 18.65 22.36 18.3 22.18 18L13.71 3.86C13.53 3.56 13.28 3.32 12.97 3.15C12.67 2.98 12.33 2.89 11.98 2.89C11.64 2.89 11.3 2.98 10.99 3.15C10.69 3.32 10.44 3.57 10.26 3.86L10.29 3.86Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 id="websiteReplaceKeyTitle" class="mt-5 text-section font-semibold text-[#0F172A]">Replace this connection key?</h3>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Your website stops loading products from this store until you paste the new key.</p>
                </div>
                <div class="px-6 pb-6 pt-2">
                    <div class="rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#92400E]">Current key</p>
                        <p class="mt-2 text-sm text-[#78350F]">The current key stops working immediately. Copy the new one on the next screen and paste it in WordPress before shoppers hit an empty catalog.</p>
                    </div>
                    <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false" class="mt-6">
                        @csrf
                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-wc-key-cancel>Keep current key</button>
                            <button type="submit" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover">Replace key</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="websiteRemoveKeyModal" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="websiteRemoveKeyTitle">
            <div class="ui-modal-panel ui-modal-panel--md border-[#FECACA]">
                <div class="bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.18),_transparent_60%)] px-6 pb-4 pt-6">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64 18.3 1.55 18.65 1.55 19C1.55 19.35 1.64 19.7 1.81 20C1.99 20.31 2.24 20.56 2.54 20.74C2.85 20.92 3.19 21.02 3.54 21.02H20.46C20.81 21.02 21.15 20.92 21.46 20.74C21.76 20.56 22.01 20.31 22.19 20C22.36 19.7 22.45 19.35 22.45 19C22.45 18.65 22.36 18.3 22.18 18L13.71 3.86C13.53 3.56 13.28 3.32 12.97 3.15C12.67 2.98 12.33 2.89 11.98 2.89C11.64 2.89 11.3 2.98 10.99 3.15C10.69 3.32 10.44 3.57 10.26 3.86L10.29 3.86Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 id="websiteRemoveKeyTitle" class="mt-5 text-section font-semibold text-[#0F172A]">Remove this connection key?</h3>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Your website will stop showing this store’s products until you create a new key and paste it on the site.</p>
                </div>
                <div class="px-6 pb-6 pt-2">
                    <div class="rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]">Warning</p>
                        <p class="mt-2 text-sm text-[#7F1D1D]">Removing the key disconnects {{ $websiteHost ?? 'your website' }} immediately. Orders and products in this portal are not deleted.</p>
                    </div>
                    <form method="post" action="{{ route('developer-storefront.token.revoke') }}" data-turbo="false" class="mt-6">
                        @csrf
                        @method('DELETE')
                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-wc-key-cancel>Keep key</button>
                            <button type="submit" class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]">Remove key</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush
