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
    $step2Done = $tokenConfigured;
    $step3Done = $tokenConfigured && $lastSeenAt;
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
                        @if (\App\Support\CheckoutMode::forStore($selectedStore) === \App\Support\CheckoutMode::PLATFORM)
                            This store uses platform checkout. WordPress will show this portal’s delivery rates and Stripe payment. Add a checkout-enabled delivery method first.
                        @else
                            This store uses website payment. WordPress collects payment, then sends the order here. Switch to platform checkout in Payments if you want WordPress to use this portal’s rates and Stripe.
                        @endif
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
                                <h3>Create a connection key</h3>
                                <p>This key lets your website load this store’s products. Treat it like a password.</p>
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
                    <p class="text-sm text-[#64748B] mb-3">Optional. Save the WordPress site you connected so you can find it later.</p>
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
                        <li><a href="{{ route('settings.payments.index') }}">Payments</a> — choose whether website orders reduce stock here.</li>
                        <li><a href="{{ route('shippingAutomation') }}">Delivery</a> — shipping and labels stay in this portal.</li>
                    </ul>
                    @unless ($usesPlatformInventory)
                        <p class="website-connect-help mt-3">This store is set to keep dashboard stock unchanged when website orders arrive. Change that in Payments if you want stock to reduce here.</p>
                    @endunless
                </section>
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

                    @if ($websiteUrl)
                        <p class="website-connect-rail-label mt-5">Website</p>
                        <p class="website-connect-rail-value break-all">{{ $websiteUrl }}</p>
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
VITE_EXTERNAL_API_BASE={{ rtrim(config('app.url'), '/') }}/api/v1/external
VITE_CHECKOUT_API_BASE={{ rtrim(config('app.url'), '/') }}/api/v1/checkout
VITE_STOREFRONT_TOKEN=your_token_here</pre>
                    <p class="mt-3">Then run <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm install</code> and <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm run dev</code>.</p>
                </div>

                <div class="rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] p-4 space-y-2 font-mono text-xs break-all">
                    <p class="font-sans text-sm font-semibold text-[#0F172A] mb-2">Catalog and legacy dev order API</p>
                    <code class="text-[#0052CC]">{{ rtrim(config('app.url'), '/') }}/api/developer-storefront</code>
                    <ul class="list-disc pl-5 mt-3 space-y-1 font-sans text-sm text-[#475569]">
                        <li><code class="text-[#0F172A]">GET /catalog</code> - active products with variants (Bearer token)</li>
                        <li><code class="text-[#0F172A]">POST /orders</code> - legacy direct test order endpoint for the local simulator</li>
                    </ul>
                </div>

                <div class="rounded-lg bg-[#EFF6FF] border border-[#BFDBFE] p-4 space-y-2 font-mono text-xs break-all">
                    <p class="font-sans text-sm font-semibold text-[#0F172A] mb-2">External checkout sync</p>
                    <code class="text-[#0052CC]">{{ rtrim(config('app.url'), '/') }}/api/v1/external/orders</code>
                    <p class="font-sans text-sm text-[#475569]">
                        Use this endpoint when another website or marketplace already handled checkout. Send customer, address, items, payment status, gateway, and payment reference. Never send raw card data.
                    </p>
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
