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
    $activeStep = match (true) {
        ! $step1Done => 1,
        ! $step2Done => 2,
        default => 3,
    };
    $websiteHost = $websiteUrl ? (parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl) : null;
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

                <button type="button" @class(['wc-rail-step', 'is-active' => $activeStep === 2, 'is-done' => $step2Done]) data-wc-step="2">
                    <span class="wc-rail-marker" aria-hidden="true">
                        <span class="wc-rail-num">2</span>
                        <span class="wc-rail-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                    </span>
                    <span class="min-w-0">
                        <span class="wc-rail-name">Connection key</span>
                        <span class="wc-rail-sub" data-wc-sub="2">{{ $step2Done ? 'Active' : 'Not created' }}</span>
                    </span>
                </button>

                <button type="button" @class(['wc-rail-step', 'is-active' => $activeStep === 3, 'is-done' => $step3Done]) data-wc-step="3">
                    <span class="wc-rail-marker" aria-hidden="true">
                        <span class="wc-rail-num">3</span>
                        <span class="wc-rail-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                    </span>
                    <span class="min-w-0">
                        <span class="wc-rail-name">Connect your site</span>
                        <span class="wc-rail-sub" data-wc-sub="3">{{ $step3Done ? 'Connected' : 'Waiting' }}</span>
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
                    <h2 class="wc-panel-title">Where do your products go?</h2>
                    <p class="wc-panel-lead">Enter the home address of the website that will show your products. One website per store.</p>

                    <div class="wc-panel-body">
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
                            </form>
                            <p class="wc-note">Include <strong>https://</strong>. Changing this later means reconnecting your website with a new key.</p>
                        @elseif ($websiteUrl)
                            <p class="wc-fact-value">{{ $websiteUrl }}</p>
                            <p class="wc-note">Only the store owner can change this address.</p>
                        @else
                            <p class="wc-note">No address saved yet. Only the store owner can set it.</p>
                        @endif
                    </div>
                </section>

                {{-- Step 2 --}}
                <section @class(['wc-panel', 'is-active' => $activeStep === 2]) data-wc-panel="2">
                    <h2 class="wc-panel-title">Your connection key</h2>
                    <p class="wc-panel-lead">This private key is what lets your website read your products and send orders back to this portal.</p>

                    <div class="wc-panel-body">
                        @if ($plainToken)
                            <div class="wc-key-box">
                                <p>Copy this key now — it is shown only once.</p>
                                <div class="wc-copy-row">
                                    <code id="wc-key" class="wc-code">{{ $plainToken }}</code>
                                    <button type="button" class="wc-btn wc-btn-primary" data-copy-target="wc-key">Copy key</button>
                                </div>
                            </div>
                        @else
                            <span class="wc-status-line {{ $tokenConfigured ? 'is-on' : 'is-off' }}">
                                {{ $tokenConfigured ? 'Key active' : 'No key yet' }}
                            </span>
                        @endif

                        @if ($canManageKey)
                            <div class="wc-actions mt-4">
                                <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false" @if($tokenConfigured) onsubmit="return confirm('Create a new key? Your website stops working until you paste the new one.');" @endif>
                                    @csrf
                                    <button type="submit" class="wc-btn wc-btn-primary" @disabled(! $step1Done)>
                                        {{ $tokenConfigured ? 'Replace key' : 'Create key' }}
                                    </button>
                                </form>
                                @if ($tokenConfigured)
                                    <form method="post" action="{{ route('developer-storefront.token.revoke') }}" data-turbo="false" onsubmit="return confirm('Remove the key? Your website stops showing this store’s products.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="wc-btn wc-btn-danger">Remove key</button>
                                    </form>
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
                    <h2 class="wc-panel-title">Connect your site</h2>
                    <p class="wc-panel-lead">Pick how your website is built.</p>

                    <div class="wc-panel-body">
                        <div class="wc-seg" role="tablist">
                            <button type="button" class="wc-seg-btn is-active" data-wc-plat="wp">WordPress</button>
                            <button type="button" class="wc-seg-btn" data-wc-plat="custom">Custom website</button>
                        </div>

                        <div class="wc-plat is-active mt-4" data-wc-plat-panel="wp">
                            <div class="wc-howto">
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">1</span>
                                    <div>
                                        <h4>Install the plugin</h4>
                                        <p>In WordPress: Plugins → Add New → Upload Plugin → Activate.</p>
                                        <a href="{{ route('developer-storefront.plugin.download') }}" class="wc-btn wc-btn-secondary mt-2" data-turbo="false" download>Download plugin</a>
                                    </div>
                                </div>
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">2</span>
                                    <div>
                                        <h4>Open Settings → Eco Portal</h4>
                                        <p>Paste this portal address into the first field.</p>
                                        <div class="wc-copy-row">
                                            <code id="wc-portal" class="wc-code">{{ $portalAddress }}</code>
                                            <button type="button" class="wc-btn wc-btn-ghost" data-copy-target="wc-portal">Copy</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">3</span>
                                    <div>
                                        <h4>Paste your key</h4>
                                        <p>Use the connection key from step 2, then save.</p>
                                    </div>
                                </div>
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">4</span>
                                    <div>
                                        <h4>Click Test connection</h4>
                                        <p>It shows your store name and product count. Your shop, cart and checkout pages are created for you.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wc-plat mt-4" data-wc-plat-panel="custom">
                            <div class="wc-howto">
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">1</span>
                                    <div>
                                        <h4>Send your developer this address</h4>
                                        <p>Their site reads your catalog from here using the key from step 2.</p>
                                        <div class="wc-copy-row">
                                            <code id="wc-api" class="wc-code">{{ $catalogApiUrl }}</code>
                                            <button type="button" class="wc-btn wc-btn-ghost" data-copy-target="wc-api">Copy</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="wc-howto-item">
                                    <span class="wc-howto-num" aria-hidden="true">2</span>
                                    <div>
                                        <h4>They call it with your key</h4>
                                        <p>Sent as a Bearer token. Checkout and payment stay in this portal, so no card data touches your site.</p>
                                        <div class="wc-api-list">
                                            <p><code>GET /catalog</code>products and variants</p>
                                            <p><code>GET /api/v1/site/health</code>connection check</p>
                                            <p><code>POST /api/v1/checkout</code>start a checkout</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const root = document.querySelector('[data-wc-console]');
            if (!root) return;

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

            /* Step switching */
            const steps = root.querySelectorAll('[data-wc-step]');
            const panels = root.querySelectorAll('[data-wc-panel]');
            steps.forEach((step) => {
                step.addEventListener('click', () => {
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

            window.setInterval(() => {
                if (document.visibilityState === 'visible') loadStatus(false);
            }, 20000);
        })();
    </script>
@endpush
