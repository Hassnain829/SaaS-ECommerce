<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name').' — Dashboard')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="merchant-shell flex min-h-screen flex-col overflow-x-hidden font-sans md:h-screen md:flex-row md:overflow-hidden">
<div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-ink/40 md:hidden" onclick="closeSidebar()" aria-hidden="true"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex h-full min-h-0 w-[15rem] shrink-0 -translate-x-full flex-col border-r border-border bg-surface text-ink transition-transform duration-200 ease-out md:static md:z-auto md:translate-x-0">
    <div class="flex shrink-0 items-center gap-2.5 px-3.5 py-3.5">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand text-white" aria-hidden="true">
            @hasSection('sidebar_logo')
                @yield('sidebar_logo')
            @else
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 7L6 3H18L20 7V19C20 20.1 19.1 21 18 21H6C4.9 21 4 20.1 4 19V7ZM6 9V19H18V9H6ZM8 11H16V13H8V11Z" fill="currentColor"/>
                </svg>
            @endif
        </div>
        <div class="min-w-0">
            <div class="truncate text-[15px] font-semibold leading-tight text-ink">@yield('sidebar_brand_title', config('app.name'))</div>
            <div class="truncate text-[11px] text-ink-muted">Merchant admin</div>
        </div>
    </div>

    @if (!empty($availableStores) && count($availableStores) > 0)
        <div class="shrink-0 px-3 pb-3">
            <form method="POST" action="{{ route('current-store.update') }}" class="relative" data-turbo="false">
                @csrf
                <label for="sidebar-store-switcher" class="sr-only">Current store</label>
                <select
                    id="sidebar-store-switcher"
                    name="store_id"
                    onchange="this.form.submit()"
                    class="w-full cursor-pointer appearance-none rounded-md border border-border bg-surface-muted px-2.5 py-2 pr-8 text-sm font-medium text-ink transition hover:bg-canvas focus:border-brand focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
                    @foreach ($availableStores as $storeOption)
                        <option value="{{ $storeOption->id }}" @selected(optional($currentStore)->id === $storeOption->id)>
                            {{ $storeOption->name }}
                        </option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-ink-muted" width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                </svg>
            </form>
        </div>
    @elseif (request()->user()?->hasRole('user'))
        <div class="shrink-0 px-3 pb-3">
            <a href="{{ route('store-management') }}" class="block rounded-md border border-dashed border-border bg-surface-muted px-3 py-2.5 text-xs font-semibold text-brand hover:bg-brand-soft">
                Create your first store
            </a>
        </div>
    @endif

    <div class="mx-3 border-t border-border" aria-hidden="true"></div>

    <nav id="merchantNav" class="sidebar-nav-scroll flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto overscroll-y-contain px-2.5 py-3">
        <div class="sidebar-nav-group">
            <a href="{{ route('dashboard') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('dashboard')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3h6v6H3V3zm8 0h6v4h-6V3zM3 11h6v6H3v-6zm8 2h6v4h-6v-4z"/></svg>
                <span>Home</span>
            </a>
            <a href="{{ route('orders') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('orders', 'orderViewDetails', 'orders.create', 'draft-orders.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 2h12a1 1 0 011 1v14l-3-2-3 2-3-2-3 2-3-2V3a1 1 0 011-1zm2 4v2h8V6H6zm0 4v2h5v-2H6z"/></svg>
                <span>Orders</span>
            </a>
            <a href="{{ route('products') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('products', 'products.*', 'catalog.attributes.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4h14v2H3V4zm0 5h14v2H3V9zm0 5h10v2H3v-2z"/></svg>
                <span>Products</span>
            </a>
            <a href="{{ route('customers') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('customers', 'customersProfile')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 10a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM3 17a7 7 0 0114 0v1H3v-1z"/></svg>
                <span>Customers</span>
            </a>
        </div>

        <div class="sidebar-nav-group">
            <p class="sidebar-nav-label">Sales channels</p>
            <a href="{{ route('analytics') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('analytics')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 17V9h3v8H3zm5 0V3h3v14H8zm5 0v-5h3v5h-3z"/></svg>
                <span>Analytics</span>
            </a>
            <a href="{{ route('developer-storefront.settings') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('developer-storefront.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 4h12v2H4V4zm0 4h12v8H4V8zm2 2v4h8v-4H6z"/></svg>
                <span>Test storefront</span>
            </a>
        </div>

        <div class="sidebar-nav-group">
            <p class="sidebar-nav-label">Store</p>
            <a href="{{ route('store-management') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('store-management')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 7l2-4h10l2 4v9a1 1 0 01-1 1H4a1 1 0 01-1-1V7zm2 2v7h10V9H5z"/></svg>
                <span>Stores</span>
            </a>
            <a href="{{ route('settings.locations.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.locations.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a6 6 0 016 6c0 4.5-6 10-6 10S4 12.5 4 8a6 6 0 016-6zm0 8a2 2 0 100-4 2 2 0 000 4z"/></svg>
                <span>Locations</span>
            </a>
            <a href="{{ route('shippingAutomation') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('shippingAutomation', 'settings.shipping.*', 'shipping.*', 'settings.delivery.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 6h11v7H2V6zm11 2h3l2 3v2h-1.5a2 2 0 11-3.9 0H8.4a2 2 0 11-3.9 0H3V6h9v2z"/></svg>
                <span>Delivery</span>
            </a>
            <a href="{{ route('settings.payments.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.payments.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5h14a1 1 0 011 1v8a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm1 3v5h12V8H4zm0-2v1h12V6H4z"/></svg>
                <span>Payments</span>
            </a>
            <a href="{{ route('settings.taxes.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.taxes.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h8l4 4v10a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm7 1v3h3l-3-3zM6 10h8v1.5H6V10zm0 3h6v1.5H6V13z"/></svg>
                <span>Checkout &amp; tax</span>
            </a>
            <a href="{{ route('settings.coupons.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.coupons.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 8a2 2 0 012-2h4.6l6.1 6.1a2 2 0 01-2.8 0L4 11.6V8zm3.2-.2a1.2 1.2 0 100-2.4 1.2 1.2 0 000 2.4z"/></svg>
                <span>Discounts</span>
            </a>
        </div>

        <div class="sidebar-nav-group">
            <p class="sidebar-nav-label">Account</p>
            <a href="{{ route('team-members.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('team-members.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 9a3 3 0 100-6 3 3 0 000 6zm6-1a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM2 16a5 5 0 0110 0v1H2v-1zm11-.5c0-1.2.4-2.3 1.1-3.2A5.5 5.5 0 0118 15.5V16h-5v-.5z"/></svg>
                <span>Team</span>
            </a>
            <a href="{{ route('billingSubscription') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('billingSubscription')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5h14v2H3V5zm0 4h14v6H3V9zm2 2v2h4v-2H5z"/></svg>
                <span>Billing</span>
            </a>
            <a href="{{ route('generalSettings') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('generalSettings')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 7.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM8.2 2h3.6l.4 2.1a6 6 0 011.5.9l2-.9 1.8 3.1-1.6 1.4c.1.5.1 1 0 1.5l1.6 1.4-1.8 3.1-2-.9a6 6 0 01-1.5.9L11.8 18H8.2l-.4-2.1a6 6 0 01-1.5-.9l-2 .9L2.5 12.8l1.6-1.4a6 6 0 010-1.5L2.5 8.5 4.3 5.4l2 .9a6 6 0 011.5-.9L8.2 2z"/></svg>
                <span>Settings</span>
            </a>
            <a href="{{ route('security') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('security')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2l7 3v5c0 4.2-2.8 7.9-7 9-4.2-1.1-7-4.8-7-9V5l7-3zm0 4a3 3 0 00-3 3v1H6v5h8v-5h-1V9a3 3 0 00-3-3zm0 2a1 1 0 011 1v1H9V9a1 1 0 011-1z"/></svg>
                <span>Security</span>
            </a>
            <a href="{{ route('notifications') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('notifications')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 18a2 2 0 01-2-2h4a2 2 0 01-2 2zM5 14V9a5 5 0 1110 0v5l1.5 1.5H3.5L5 14z"/></svg>
                <span>Notifications</span>
            </a>
        </div>
    </nav>

    @php
        $sidebarUser = auth()->user();
        $sidebarInitial = $sidebarUser ? \Illuminate\Support\Str::of($sidebarUser->name)->trim()->substr(0, 1)->upper() : '?';
    @endphp
    <div class="sidebar-footer shrink-0 border-t border-border p-2.5">
        <a href="{{ route('profileSettings') }}" @class(['sidebar-footer-profile', 'sidebar-footer-profile-active' => request()->routeIs('profileSettings')])>
            <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-muted text-xs font-semibold text-ink ring-1 ring-border">
                @if ($sidebarUser?->avatar)
                    <img src="{{ asset('storage/'.$sidebarUser->avatar) }}" alt="" class="h-full w-full object-cover">
                @else
                    {{ $sidebarInitial }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-ink">{{ $sidebarUser?->name ?? 'Account' }}</div>
                <div id="sidebar-store-label" class="truncate text-[11px] text-ink-muted">{{ optional($currentStore)->name ?? 'Profile' }}</div>
            </div>
        </a>
        <a href="{{ route('logout') }}" class="sidebar-footer-logout mt-1.5" data-turbo="false">Sign out</a>
    </div>
</aside>

<main class="flex min-w-0 flex-1 flex-col overflow-hidden bg-canvas">
    @hasSection('topbar')
        @yield('topbar')
    @else
        <x-ui.merchant-topbar />
    @endif

    <div class="merchant-app ui-page-enter flex-1 space-y-5 overflow-y-auto p-4 lg:p-6">
        @yield('content')
    </div>
</main>

@stack('scripts')
</body>
</html>
