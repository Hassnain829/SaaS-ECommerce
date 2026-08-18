@extends('layouts.user.user-sidebar')

@php
    use App\Support\StorePermission;

    $canManageKey = auth()->user()->hasStorePermission($selectedStore, StorePermission::DEVELOPER_API_MANAGE);
    $storeCurrency = strtoupper((string) ($selectedStore->currency ?? 'USD'));
    $badge = match ($connectionState) {
        \App\Models\Store::WEBSITE_CONNECTED => ['label' => 'Connected', 'class' => 'is-connected'],
        \App\Models\Store::WEBSITE_WAITING => ['label' => 'Waiting for website', 'class' => 'is-setup'],
        \App\Models\Store::WEBSITE_DISCONNECTED => ['label' => 'Disconnected', 'class' => 'is-attention'],
        default => ['label' => 'Not started', 'class' => 'is-idle'],
    };
    $step1Done = $tokenConfigured || $connectionState === \App\Models\Store::WEBSITE_DISCONNECTED;
    $websiteBound = filled($websiteUrl);
    $step2Done = $websiteBound && $tokenConfigured;
    $step3Done = $tokenConfigured && $lastSeenAt;
    $connectedSiteHealth = is_array($connectedSiteHealth ?? null) ? $connectedSiteHealth : [];
    $connectedSiteScopeLabels = is_array($connectedSiteScopeLabels ?? null) ? $connectedSiteScopeLabels : [];
    $catalogSync = is_array($catalogSync ?? null) ? $catalogSync : [];
@endphp

@section('title', 'Connect your website — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Connect your website" lead="Sell your products on WordPress. Manage catalog, orders, and shipping in this portal.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="website-connect max-w-6xl mx-auto px-4 lg:px-0 space-y-6">
        <p class="website-connect-eyebrow">Website for {{ $selectedStore->name }}</p>

        @if (session('success'))
            <div class="rounded-xl border border-[#BBF7D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#166534]">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B91C1C]">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <div class="min-w-0 space-y-6 lg:col-span-8">
                <section class="website-connect-card">
                    <h2 class="website-connect-card-title">How this works</h2>
                    <ol class="website-connect-explainer">
                        <li>You add products here.</li>
                        <li>WordPress shows those products to shoppers.</li>
                        <li>When someone buys, the order appears in Orders and stock updates here.</li>
                    </ol>
                    <p class="website-connect-help">
                        WordPress shows your products and this portal’s Stripe checkout. Connect Stripe in Payments and add a checkout-enabled delivery method before shoppers can pay.
                    </p>
                </section>

                @if ($publishedProductCount < 1)
                    <div class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                        Add a product first, or WordPress will show an empty shop.
                        <a href="{{ route('products.create') }}" class="font-semibold underline underline-offset-2">Add a product</a>
                    </div>
                @endif

                <section class="website-connect-card" aria-label="Setup steps">
                    <h2 class="website-connect-card-title">Setup steps</h2>
                    <ol class="website-connect-stepper">
                        <li @class(['website-connect-step', 'is-current' => $currentStep === 1, 'is-done' => $step1Done && $currentStep !== 1])>
                            <div class="website-connect-step-marker" aria-hidden="true">
                                @if ($step1Done && $currentStep !== 1)
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                @else
                                    1
                                @endif
                            </div>
                            <div class="website-connect-step-body">
                                <h3>Get the WordPress plugin</h3>
                                <p>Install this connector on the WordPress site you already have. You do not need WooCommerce.</p>
                                <a href="{{ route('developer-storefront.plugin.download') }}" class="website-connect-btn website-connect-btn-primary" data-turbo="false" download>Download plugin</a>
                                <p class="website-connect-help">In WordPress: Plugins → Add New → Upload Plugin → Activate.</p>
                            </div>
                        </li>

                        <li @class(['website-connect-step', 'is-current' => $currentStep === 2, 'is-done' => $step2Done && $currentStep !== 2])>
                            <div class="website-connect-step-marker" aria-hidden="true">
                                @if ($step2Done && $currentStep !== 2)
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                @else
                                    2
                                @endif
                            </div>
                            <div class="website-connect-step-body">
                                <h3>Save the website address and create a key</h3>
                                <p>Each store needs its own website connection. Save the exact WordPress home address first, then create the key that loads this store’s products.</p>
                                @if ($canManageKey)
                                    <form method="post" action="{{ route('developer-storefront.website.update') }}" class="website-connect-url-form" data-turbo="false">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="setup_website_url">WordPress website address</label>
                                        <input
                                            id="setup_website_url"
                                            type="url"
                                            name="website_url"
                                            value="{{ old('website_url', $websiteUrl) }}"
                                            placeholder="http://localhost:8080/wordpress"
                                            class="website-connect-input"
                                            required
                                        >
                                        <button type="submit" class="website-connect-btn website-connect-btn-secondary">Save address</button>
                                    </form>
                                    <p class="website-connect-help">One WordPress site can point to one portal store at a time. Switching stores requires using the new store’s key.</p>
                                @endif
                                @if ($plainToken)
                                    <div class="website-connect-key-alert">
                                        <p class="font-semibold text-[#92400E]">Copy this key now. It will not be shown again.</p>
                                        <code id="website-connection-key-step" class="website-connect-key">{{ $plainToken }}</code>
                                        <button type="button" class="website-connect-btn website-connect-btn-primary" data-copy-target="website-connection-key-step">Copy key</button>
                                    </div>
                                @endif
                                @if ($canManageKey)
                                    <div class="website-connect-actions">
                                        <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false" @if($tokenConfigured) onsubmit="return confirm('Create a new connection key? The old key stops working.');" @endif>
                                            @csrf
                                            <button type="submit" class="website-connect-btn website-connect-btn-primary" @disabled(! $websiteBound)>
                                                {{ $tokenConfigured ? 'Create new key' : 'Create connection key' }}
                                            </button>
                                        </form>
                                        @if ($tokenConfigured)
                                            <form method="post" action="{{ route('developer-storefront.token.revoke') }}" data-turbo="false" onsubmit="return confirm('Remove the connection key? Your website will stop loading this store’s products until you create a new one.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="website-connect-btn website-connect-btn-danger">Remove key</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <p class="website-connect-help">Only store owners can create or remove a connection key.</p>
                                @endif
                            </div>
                        </li>

                        <li @class(['website-connect-step', 'is-current' => $currentStep === 3, 'is-done' => $step3Done && $currentStep !== 3])>
                            <div class="website-connect-step-marker" aria-hidden="true">
                                @if ($step3Done && $currentStep !== 3)
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                @else
                                    3
                                @endif
                            </div>
                            <div class="website-connect-step-body">
                                <h3>Paste the key in WordPress</h3>
                                <ol class="website-connect-nested">
                                    <li>Open WordPress admin</li>
                                    <li>Go to <strong>Settings → Eco Portal</strong></li>
                                    <li>
                                        Portal website address:
                                        <span class="website-connect-copy-row">
                                            <code id="website-portal-address">{{ $portalAddress }}</code>
                                            <button type="button" class="website-connect-btn website-connect-btn-ghost" data-copy-target="website-portal-address">Copy</button>
                                        </span>
                                        <span class="website-connect-help">This portal address</span>
                                    </li>
                                    <li>Paste the connection key</li>
                                    <li>Save, then click <strong>Test connection</strong></li>
                                </ol>
                                <p class="website-connect-help">A successful test shows your store name and how many products WordPress can see.</p>
                            </div>
                        </li>

                        <li @class(['website-connect-step', 'is-current' => $currentStep === 4, 'is-done' => false])>
                            <div class="website-connect-step-marker" aria-hidden="true">4</div>
                            <div class="website-connect-step-body">
                                <h3>Open the shop and place a test order</h3>
                                <p>WordPress creates shop, cart, and checkout pages. Buy one product as a test. Then open Orders here.</p>
                                <div class="website-connect-actions">
                                    <a href="{{ route('products') }}" class="website-connect-btn website-connect-btn-secondary">Open Products</a>
                                    <a href="{{ route('orders') }}" class="website-connect-btn website-connect-btn-secondary">Open Orders</a>
                                </div>
                                <p class="website-connect-help">Shipping and labels stay in this portal after the order arrives.</p>
                            </div>
                        </li>
                    </ol>
                </section>

                <section class="website-connect-card" id="connection-key">
                    <h2 class="website-connect-card-title">Connection key</h2>

                    @if ($plainToken)
                        <div class="website-connect-key-alert">
                            <p class="font-semibold text-[#92400E]">Copy this key now. It will not be shown again.</p>
                            <code id="website-connection-key" class="website-connect-key">{{ $plainToken }}</code>
                            <button type="button" class="website-connect-btn website-connect-btn-primary" data-copy-target="website-connection-key">Copy key</button>
                        </div>
                    @elseif ($tokenConfigured)
                        <p class="text-sm text-[#475569]">A key is already set. Create a new key only if you need to replace the old one.</p>
                    @else
                        <p class="text-sm text-[#475569]">No connection key yet. Create one so WordPress can load this store’s products.</p>
                    @endif

                    <div class="website-connect-actions pt-2">
                        @if ($canManageKey)
                            <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false" @if($tokenConfigured) onsubmit="return confirm('Create a new connection key? The old key stops working.');" @endif>
                                @csrf
                                <button type="submit" class="website-connect-btn website-connect-btn-primary">
                                    {{ $tokenConfigured ? 'Create new key' : 'Create connection key' }}
                                </button>
                            </form>
                            @if ($tokenConfigured)
                                <form method="post" action="{{ route('developer-storefront.token.revoke') }}" data-turbo="false" onsubmit="return confirm('Remove the connection key? Your website will stop loading this store’s products until you create a new one.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="website-connect-btn website-connect-btn-danger">Remove key</button>
                                </form>
                            @endif
                        @else
                            <p class="text-sm text-[#64748B]">Only store owners can create or remove a connection key.</p>
                        @endif
                    </div>
                </section>

                <section class="website-connect-card">
                    <h2 class="website-connect-card-title">Your website address</h2>
                    <p class="text-sm text-[#64748B] mb-3">Required for the connection key. Save the exact WordPress home address for this store.</p>
                    @if ($canManageKey)
                        <form method="post" action="{{ route('developer-storefront.website.update') }}" class="website-connect-url-form" data-turbo="false">
                            @csrf
                            @method('PATCH')
                            <label class="sr-only" for="website_url">Website address</label>
                            <input
                                id="website_url"
                                type="url"
                                name="website_url"
                                value="{{ old('website_url', $websiteUrl) }}"
                                placeholder="http://127.0.0.1:8080"
                                class="website-connect-input"
                                required
                            >
                            <button type="submit" class="website-connect-btn website-connect-btn-secondary">Save</button>
                        </form>
                    @elseif ($websiteUrl)
                        <p class="text-sm font-medium text-[#0F172A] break-all">{{ $websiteUrl }}</p>
                    @else
                        <p class="text-sm text-[#64748B]">No website address saved yet.</p>
                    @endif
                </section>

                <section class="website-connect-card">
                    <h2 class="website-connect-card-title">What to do after connecting</h2>
                    <ul class="website-connect-next-links">
                        <li><a href="{{ route('products') }}">Products</a> — publish products with stock or options first.</li>
                        <li><a href="{{ route('orders') }}">Orders</a> — website purchases appear here.</li>
                        <li><a href="{{ route('settings.payments.index') }}">Payments</a> — connect Stripe before shoppers can pay.</li>
                        <li><a href="{{ route('shippingAutomation') }}">Delivery</a> — shipping and labels stay in this portal.</li>
                    </ul>
                </section>

                @if ($connectedSite)
                    <section class="website-connect-card" aria-label="Connection details">
                        <h2 class="website-connect-card-title">Connection details</h2>
                        <dl class="grid gap-3 text-sm">
                            <div>
                                <dt class="website-connect-rail-label">Connection ID</dt>
                                <dd class="website-connect-rail-value font-mono break-all">{{ $connectedSite->public_id }}</dd>
                            </div>
                            <div>
                                <dt class="website-connect-rail-label">Status</dt>
                                <dd class="website-connect-rail-value">
                                    @if ($connectedSite->isActive())
                                        Active
                                    @else
                                        Removed{{ $connectedSite->revoked_at ? ' '.$connectedSite->revoked_at->diffForHumans() : '' }}
                                    @endif
                                </dd>
                            </div>
                            @if ($connectedSite->credential_rotated_at)
                                <div>
                                    <dt class="website-connect-rail-label">Last key replacement</dt>
                                    <dd class="website-connect-rail-value">{{ $connectedSite->credential_rotated_at->diffForHumans() }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="website-connect-rail-label">Plugin version</dt>
                                <dd class="website-connect-rail-value">{{ $connectedSite->plugin_version ?: 'Waiting for WordPress' }}</dd>
                            </div>
                            <div>
                                <dt class="website-connect-rail-label">Website address check</dt>
                                <dd class="website-connect-rail-value">
                                    @php $urlMatch = $connectedSiteHealth['url_match'] ?? null; @endphp
                                    @if ($urlMatch === true)
                                        Matches the saved WordPress address
                                    @elseif ($urlMatch === false)
                                        Does not match the saved WordPress address
                                    @else
                                        Not checked yet
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="website-connect-rail-label">Last health check</dt>
                                <dd class="website-connect-rail-value">
                                    @if ($connectedSite->last_health_at)
                                        {{ $connectedSite->last_health_at->diffForHumans() }}
                                    @else
                                        WordPress has not run a connection test yet
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="website-connect-rail-label">Products on the website</dt>
                                <dd class="website-connect-rail-value">
                                    @php
                                        $websiteMatches = $catalogSync['website_matches_portal'] ?? null;
                                        $pendingDeliveries = (int) ($catalogSync['pending_deliveries'] ?? 0);
                                    @endphp
                                    @if ($websiteMatches === false)
                                        WordPress still has an older product list — it will refresh on the next check
                                    @elseif ($pendingDeliveries > 0)
                                        {{ $pendingDeliveries }} product {{ $pendingDeliveries === 1 ? 'update is' : 'updates are' }} waiting to reach WordPress
                                    @elseif ($websiteMatches === true)
                                        Showing the current product list
                                    @else
                                        Waiting for WordPress to report its product list
                                    @endif
                                </dd>
                            </div>
                            @if (! empty($catalogSync['site_last_rebuild_at']))
                                <div>
                                    <dt class="website-connect-rail-label">Last website catalog rebuild</dt>
                                    <dd class="website-connect-rail-value">{{ $catalogSync['site_last_rebuild_at'] }}</dd>
                                </div>
                            @endif
                            @if (! empty($catalogSync['last_delivered_at']))
                                <div>
                                    <dt class="website-connect-rail-label">Last catalog update sent</dt>
                                    <dd class="website-connect-rail-value">{{ \Illuminate\Support\Carbon::parse($catalogSync['last_delivered_at'])->diffForHumans() }}</dd>
                                </div>
                            @endif
                            @if ($connectedSiteScopeLabels !== [])
                                <div>
                                    <dt class="website-connect-rail-label">This website can</dt>
                                    <dd class="website-connect-rail-value">{{ implode(', ', $connectedSiteScopeLabels) }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (! empty($connectedSiteHealth['conflicts']) && is_array($connectedSiteHealth['conflicts']))
                            <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                                <p class="font-semibold">WordPress is not ready for live shoppers</p>
                                <p class="mt-1">This portal did not turn anything off on the website. Follow these steps in WordPress, then click Test connection again.</p>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($connectedSiteHealth['conflicts'] as $conflict)
                                        <li>
                                            <strong>{{ $conflict['title'] ?? 'Website conflict' }}</strong>
                                            <span class="block text-[#78350F]">{{ $conflict['instruction'] ?? '' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif (array_key_exists('production_ready', $connectedSiteHealth) && $connectedSiteHealth['production_ready'] === true)
                            <p class="mt-4 text-sm text-[#166534]">WordPress reported no WooCommerce checkout, payment, or cache conflicts.</p>
                        @endif
                        @if (! empty($connectedSiteHealth['messages']) && is_array($connectedSiteHealth['messages']))
                            <ul class="mt-4 space-y-1 text-sm text-[#92400E]">
                                @foreach ($connectedSiteHealth['messages'] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            </div>

            <aside class="website-connect-rail lg:col-span-4">
                <div class="website-connect-card website-connect-rail-card">
                    <p class="website-connect-rail-label">Connection status</p>
                    <span class="delivery-status-pill {{ $badge['class'] }}">
                        <span class="delivery-status-dot" aria-hidden="true"></span>
                        {{ $badge['label'] }}
                    </span>

                    <p class="website-connect-rail-label mt-5">Store currency</p>
                    <p class="website-connect-rail-value">{{ $storeCurrency }}</p>

                    <p class="website-connect-rail-label mt-5">Last catalog request</p>
                    <p class="website-connect-rail-value">
                        @if ($lastSeenAt)
                            WordPress last checked your products {{ $lastSeenAt->diffForHumans() }}.
                        @else
                            Not connected yet
                        @endif
                    </p>

                    @if (! empty($catalogSync['catalog_version']))
                        <p class="website-connect-rail-label mt-5">Product list on the website</p>
                        <p class="website-connect-rail-value">
                            @php $websiteMatches = $catalogSync['website_matches_portal'] ?? null; @endphp
                            @if ($websiteMatches === false)
                                Refresh pending
                            @elseif ((int) ($catalogSync['pending_deliveries'] ?? 0) > 0)
                                Updates are on the way
                            @elseif ($websiteMatches === true)
                                Up to date
                            @else
                                Waiting for WordPress
                            @endif
                        </p>
                    @endif

                    @if ($websiteUrl)
                        <p class="website-connect-rail-label mt-5">Website</p>
                        <p class="website-connect-rail-value break-all">{{ $websiteUrl }}</p>
                    @endif

                    @if ($connectedSite?->plugin_version)
                        <p class="website-connect-rail-label mt-5">WordPress plugin</p>
                        <p class="website-connect-rail-value">Version {{ $connectedSite->plugin_version }}</p>
                    @endif

                    <div class="mt-6">
                        @if (! $canManageKey && in_array($connectionState, [\App\Models\Store::WEBSITE_NOT_STARTED, \App\Models\Store::WEBSITE_DISCONNECTED], true))
                            <p class="text-sm text-[#64748B]">Ask a store owner to create a connection key.</p>
                        @elseif ($connectionState === \App\Models\Store::WEBSITE_CONNECTED)
                            <a href="{{ route('orders') }}" class="website-connect-btn website-connect-btn-primary w-full justify-center">Open Orders</a>
                        @elseif ($connectionState === \App\Models\Store::WEBSITE_WAITING)
                            <button type="button" class="website-connect-btn website-connect-btn-primary w-full justify-center" data-copy-target="website-portal-address">Copy portal address</button>
                        @elseif ($canManageKey)
                            <form method="post" action="{{ route('developer-storefront.token.generate') }}" data-turbo="false" @if($tokenConfigured) onsubmit="return confirm('Create a new connection key? The old key stops working.');" @endif>
                                @csrf
                                <button type="submit" class="website-connect-btn website-connect-btn-primary w-full justify-center">
                                    {{ $tokenConfigured ? 'Create new key' : 'Create connection key' }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('developer-storefront.plugin.download') }}" class="website-connect-btn website-connect-btn-primary w-full justify-center" data-turbo="false" download>Download plugin</a>
                        @endif
                    </div>
                </div>
            </aside>
        </div>

        <details class="website-connect-advanced">
            <summary>Advanced details</summary>
            <div class="website-connect-advanced-body space-y-6 text-sm text-[#475569]">
                <div>
                    <h3 class="text-sm font-semibold text-[#0F172A]">Local React test app</h3>
                    <p class="mt-1">Optional. For developers only. WordPress above is the website merchants connect.</p>
                    <p class="mt-3">In the repository folder <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded text-[#0F172A]">dev-test-storefront</code>, create <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">.env</code> with:</p>
                    <pre class="text-xs bg-[#0F172A] text-[#E2E8F0] rounded-lg p-4 overflow-x-auto mt-3">VITE_API_BASE={{ rtrim(config('app.url'), '/') }}/api/developer-storefront
VITE_CHECKOUT_API_BASE={{ rtrim(config('app.url'), '/') }}/api/v1/checkout
VITE_STOREFRONT_TOKEN=your_token_here</pre>
                    <p class="mt-3">Then run <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm install</code> and <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm run dev</code>.</p>
                </div>

                <div class="rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] p-4 space-y-2 font-mono text-xs break-all">
                    <p class="font-sans text-sm font-semibold text-[#0F172A] mb-2">Catalog API</p>
                    <code class="text-[#0052CC]">{{ rtrim(config('app.url'), '/') }}/api/developer-storefront</code>
                    <ul class="list-disc pl-5 mt-3 space-y-1 font-sans text-sm text-[#475569]">
                        <li><code class="text-[#0F172A]">GET /catalog</code> - active products with variants (Bearer token)</li>
                        <li><code class="text-[#0F172A]">GET /api/v1/site/health</code> - connection health for the WordPress plugin</li>
                        <li><code class="text-[#0F172A]">GET /api/v1/site/events/config</code> - catalog event signing for the plugin</li>
                        <li><code class="text-[#0F172A]">GET /api/v1/catalog/events</code> - missed catalog updates for WordPress to repair</li>
                    </ul>
                </div>

                <div class="rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] p-4 space-y-3">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-[#0F172A]">Platform checkout sandbox</p>
                            <p class="text-sm text-[#64748B]">Use this endpoint when this SaaS creates a Stripe sandbox payment for the storefront. Card details stay in Stripe.js.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#1D4ED8]">
                            {{ strtoupper($stripeConfig['mode'] ?? 'test') }}
                        </span>
                    </div>
                    <code class="block font-mono text-xs text-[#0052CC] break-all">{{ rtrim(config('app.url'), '/') }}/api/v1/checkout</code>
                    <div class="grid gap-2 text-xs sm:grid-cols-3">
                        @foreach([
                            'publishable_key' => 'Publishable key',
                            'secret_key' => 'Secret key',
                            'webhook_secret' => 'Webhook secret',
                        ] as $key => $label)
                            <div class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">
                                <p class="font-semibold text-[#0F172A]">{{ $label }}</p>
                                <p class="{{ ($stripeConfig[$key] ?? false) ? 'text-[#059669]' : 'text-[#B91C1C]' }}">
                                    {{ ($stripeConfig[$key] ?? false) ? 'Configured' : 'Missing' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-[#64748B]">Stripe webhooks should post to <code class="text-[#0F172A]">/api/webhooks/stripe</code>. Secret values are never shown here.</p>
                </div>
            </div>
        </details>
    </div>

@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.getAttribute('data-copy-target'));
                if (!target) return;
                try {
                    await navigator.clipboard.writeText(target.textContent.trim());
                    const original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(() => { button.textContent = original; }, 1500);
                } catch (error) {
                    // Clipboard may be blocked in older browsers; the value stays visible to copy manually.
                }
            });
        });
    </script>
@endpush
