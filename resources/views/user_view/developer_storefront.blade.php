@extends('layouts.user.user-sidebar')

@section('title', 'Developer test storefront | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Developer test storefront" lead="Local API connection for catalog and order testing.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="max-w-4xl mx-auto px-4 lg:px-0 space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-[#BBF7D0] bg-[#ECFDF5] px-4 py-3 text-sm text-[#166534]">
                {{ session('success') }}
            </div>
        @endif

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Connect a React dev app</h2>
                <p class="text-sm text-[#64748B] mt-1">
                    Use this flow to verify catalog data and external checkout sync against your live store data. Intended for developers and staging; treat the token like a password.
                </p>
            </div>

            <div class="p-5 space-y-6 text-sm text-[#475569]">
                <p>
                    Active store: <span class="font-semibold text-[#0F172A]">{{ $selectedStore->name }}</span>
                    @if ($tokenConfigured && $tokenCreatedAt)
                        <span class="text-[#64748B]">- token created {{ $tokenCreatedAt->diffForHumans() }}</span>
                    @endif
                </p>

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

                @if ($plainToken)
                    <div class="rounded-xl border-2 border-[#F59E0B] bg-[#FFFBEB] p-4 space-y-3">
                        <p class="font-semibold text-[#92400E]">Copy this token now</p>
                        <code id="dev-storefront-token" class="block w-full text-xs break-all bg-white border border-[#FDE68A] rounded-lg p-3 text-[#0F172A]">{{ $plainToken }}</code>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('dev-storefront-token').textContent)" class="h-9 px-4 rounded-lg bg-[#0F172A] text-white text-sm font-semibold">
                            Copy token
                        </button>
                    </div>
                @elseif ($tokenConfigured)
                    <p class="text-[#64748B]">A token is configured for this store. It is not shown again for security. Generate a new token to replace it, which revokes the previous token.</p>
                @else
                    <p class="text-[#64748B]">No token yet. Store owners can generate one below.</p>
                @endif

                <div class="flex flex-wrap gap-3 pt-2">
                    @if (auth()->user()->hasStorePermission($selectedStore, \App\Support\StorePermission::DEVELOPER_API_MANAGE))
                        <form method="post" action="{{ route('developer-storefront.token.generate') }}">
                            @csrf
                            <button type="submit" class="h-10 px-5 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">
                                {{ $tokenConfigured ? 'Regenerate token' : 'Generate token' }}
                            </button>
                        </form>
                        @if ($tokenConfigured)
                            <form method="post" action="{{ route('developer-storefront.token.revoke') }}" onsubmit="return confirm('Revoke the developer storefront token? Any connected dev app will stop working until a new token is generated.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="h-10 px-5 rounded-lg border border-[#FECACA] bg-[#FEF2F2] text-[#B91C1C] text-sm font-semibold">
                                    Revoke token
                                </button>
                            </form>
                        @endif
                    @else
                        <p class="text-sm text-[#64748B]">Only store owners can create or revoke this token.</p>
                    @endif
                </div>

                <hr class="border-[#F1F5F9]" />

                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-[#64748B] mb-2">React sample app</h3>
                    <p class="mb-3">In the repository folder <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded text-[#0F172A]">dev-test-storefront</code>, create <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">.env</code> with:</p>
                    <pre class="text-xs bg-[#0F172A] text-[#E2E8F0] rounded-lg p-4 overflow-x-auto">VITE_API_BASE={{ rtrim(config('app.url'), '/') }}/api/developer-storefront
VITE_EXTERNAL_API_BASE={{ rtrim(config('app.url'), '/') }}/api/v1/external
VITE_CHECKOUT_API_BASE={{ rtrim(config('app.url'), '/') }}/api/v1/checkout
VITE_STOREFRONT_TOKEN=your_token_here</pre>
                    <p class="mt-3">Then run <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm install</code> and <code class="bg-[#F1F5F9] px-1.5 py-0.5 rounded">npm run dev</code>.</p>
                </div>
            </div>
        </section>
    </div>
@endsection
