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
        <div class="shrink-0 px-3 pb-3" data-store-switcher>
            @if (count($availableStores) === 1)
                <p class="truncate rounded-md border border-border bg-surface-muted px-2.5 py-2 text-sm font-medium text-ink">{{ optional($currentStore)->name ?? $availableStores[0]->name }}</p>
            @else
                <form id="sidebar-store-switch-form" method="POST" action="{{ route('current-store.update') }}" class="hidden" data-turbo="false" autocomplete="off">
                    @csrf
                    <input type="hidden" name="store_id" id="sidebar-store-switch-id" value="{{ optional($currentStore)->id }}" autocomplete="off">
                </form>
                <div class="relative">
                    <button
                        type="button"
                        id="sidebar-store-switch-trigger"
                        class="flex w-full items-center justify-between gap-2 rounded-md border border-border bg-surface-muted px-2.5 py-2 text-left text-sm font-medium text-ink transition hover:bg-canvas focus:border-brand focus:bg-surface focus:outline-none focus:ring-2 focus:ring-brand/20"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                        aria-controls="sidebar-store-switch-menu"
                    >
                        <span id="sidebar-store-switch-label" class="min-w-0 truncate">{{ optional($currentStore)->name }}</span>
                        <svg class="shrink-0 text-ink-muted" width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                        </svg>
                    </button>
                    <div
                        id="sidebar-store-switch-menu"
                        class="absolute z-30 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-md border border-border bg-surface py-1 shadow-lg"
                        role="listbox"
                        aria-labelledby="sidebar-store-switch-trigger"
                    >
                        @foreach ($availableStores as $storeOption)
                            <button
                                type="button"
                                role="option"
                                class="block w-full px-3 py-2 text-left text-sm text-ink hover:bg-surface-muted @if (optional($currentStore)->id === $storeOption->id) bg-brand-soft font-semibold @endif"
                                data-store-switch-option
                                data-store-id="{{ $storeOption->id }}"
                                data-store-name="{{ $storeOption->name }}"
                                @if (optional($currentStore)->id === $storeOption->id) aria-selected="true" @else aria-selected="false" @endif
                            >
                                {{ $storeOption->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
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
            <a href="{{ route('shipments.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('shipments.index', 'shipments.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 4l7-2 7 2v5.5c0 3.6-2.4 6.9-7 8.5-4.6-1.6-7-4.9-7-8.5V4zm7 1.2L5 6.3v3.2c0 2.4 1.5 4.6 5 5.8 3.5-1.2 5-3.4 5-5.8V6.3l-5-1.1z"/></svg>
                <span>Shipments</span>
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
            <a href="{{ route('developer-storefront.settings') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('developer-storefront.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm6.3 7.5h-2.4A12.6 12.6 0 0012.5 4.6 6.51 6.51 0 0116.3 9.5zM10 3.5c.7 0 1.7 1.4 2.3 3.7.2.8.4 1.7.5 2.3H7.2c.1-.6.3-1.5.5-2.3C8.3 4.9 9.3 3.5 10 3.5zM3.7 9.5A6.51 6.51 0 017.5 4.6 12.6 12.6 0 006.1 9.5H3.7zm0 1h2.4a12.6 12.6 0 001.4 4.9A6.51 6.51 0 013.7 10.5zM10 16.5c-.7 0-1.7-1.4-2.3-3.7-.2-.8-.4-1.7-.5-2.3h5.6c-.1.6-.3 1.5-.5 2.3-.6 2.3-1.6 3.7-2.3 3.7zm2.5-1.1a12.6 12.6 0 001.4-4.9h2.4a6.51 6.51 0 01-3.8 4.9z"/></svg>
                <span>Website</span>
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
            <a href="{{ route('settings.taxes.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.taxes.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3h8l4 4v10a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zm7 1v3h3l-3-3zM6 10h8v1.5H6V10zm0 3h6v1.5H6V13z"/></svg>
                <span>Checkout &amp; tax</span>
            </a>
            <a href="{{ route('settings.payments.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.payments.*')])>
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm2 1v2h10V6H5zm0 5v4h10v-4H5z"/></svg>
                <span>Payments</span>
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
        <a href="{{ route('generalSettings', ['tab' => 'account']) }}" @class(['sidebar-footer-profile', 'sidebar-footer-profile-active' => request()->routeIs('generalSettings') && request('tab') === 'account'])>
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
        <x-secure-logout class="mt-1.5" />
    </div>
</aside>

<main class="flex min-w-0 flex-1 flex-col overflow-hidden bg-canvas">
    @hasSection('topbar')
        @yield('topbar')
    @else
        <x-ui.merchant-topbar />
    @endif

    <div class="merchant-app ui-page-enter flex-1 space-y-5 overflow-y-auto p-4 lg:p-6">
        @auth
            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                    Your email is not verified yet.
                    <a href="{{ route('verification.notice') }}" class="font-semibold underline">Verify now</a>
                    or resend the link from that page.
                </div>
            @endif
        @endauth
        @yield('content')
    </div>
</main>

@stack('overlays')
@if (!empty($availableStores) && count($availableStores) > 1)
<div id="storeSwitchConfirmModal" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="storeSwitchConfirmTitle">
    <div class="ui-modal-panel ui-modal-panel--md border-[#BFDBFE]">
        <div class="bg-[radial-gradient(circle_at_top,_rgba(37,99,235,0.16),_transparent_60%)] px-6 pb-4 pt-6">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#EFF6FF] text-[#1D4ED8] shadow-sm" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M4 7L6 3H18L20 7V19C20 20.1 19.1 21 18 21H6C4.9 21 4 20.1 4 19V7ZM6 9V19H18V9H6ZM8 11H16V13H8V11Z" fill="currentColor"/>
                </svg>
            </div>
            <h3 id="storeSwitchConfirmTitle" class="mt-5 text-2xl font-semibold text-[#0F172A]">Switch stores?</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">You are about to work in a different store. Catalog, orders, and settings will show that store instead.</p>
        </div>
        <div class="px-6 pb-6 pt-2">
            <div class="rounded-2xl border border-[#DBEAFE] bg-[#EFF6FF] px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#1E40AF]">Switch to</p>
                <p class="mt-2 text-sm text-[#1E3A8A]">You will open <span id="storeSwitchConfirmName" class="font-bold"></span>.</p>
            </div>
            <div id="storeSwitchCreateWarning" class="mt-3 hidden rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#92400E]">Unsaved product</p>
                <p class="mt-2 text-sm text-[#78350F]">You are still adding a product. Switching stores discards what you entered on this page.</p>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-store-switch-cancel>Keep this store</button>
                <button type="button" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover" data-store-switch-confirm>Switch store</button>
            </div>
        </div>
    </div>
</div>
@endif
@stack('scripts')
</body>
</html>
