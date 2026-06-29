<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name').' — Dashboard')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="merchant-shell flex min-h-screen flex-col overflow-x-hidden font-sans md:h-screen md:flex-row md:overflow-hidden">
<div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-stone-950/50 backdrop-blur-sm md:hidden" onclick="closeSidebar()" aria-hidden="true"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex h-full min-h-0 w-[17.25rem] shrink-0 -translate-x-full flex-col border-r border-zinc-800/90 bg-gradient-to-b from-zinc-900 via-zinc-900 to-zinc-950 text-zinc-300 shadow-2xl shadow-black/40 transition-transform duration-300 ease-out md:static md:z-auto md:translate-x-0 md:shadow-none">
    <div class="flex items-center gap-3 border-b border-zinc-800/80 bg-zinc-950/25 px-4 py-4 shrink-0">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md shadow-indigo-950/50 ring-1 ring-white/10">
            @hasSection('sidebar_logo')
                @yield('sidebar_logo')
            @else
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21L13 15L15 13L19 17L21 15L23 17L19 21ZM13 17L9 13L10 12L13 15L19 9L21 11L13 19L8 14L9 13L13 17ZM5 21C4.45 21 3.97917 20.8042 3.5875 20.4125C3.19583 20.0208 3 19.55 3 19V5C3 4.45 3.19583 3.97917 3.5875 3.5875C3.97917 3.19583 4.45 3 5 3H19C19.55 3 20.0208 3.19583 20.4125 3.5875C20.8042 3.97917 21 4.45 21 5V11L19 9V5H5V19H13L15 21H5Z" fill="white"/>
                </svg>
            @endif
        </div>
        <div class="min-w-0">
            <div class="truncate text-[15px] font-semibold leading-tight tracking-tight text-white">@yield('sidebar_brand_title', config('app.name'))</div>
            <div class="mt-0.5 truncate text-[11px] font-medium leading-snug text-zinc-500">@yield('sidebar_brand_subtitle', optional($currentStore)->name ?? 'Your stores')</div>
        </div>
    </div>

    @if (!empty($availableStores) && count($availableStores) > 0)
        <div class="shrink-0 px-3 pb-3 pt-2">
            <div class="rounded-lg border border-zinc-800/80 bg-zinc-950/50 p-3 shadow-inner shadow-black/30">
            <form method="POST" action="{{ route('current-store.update') }}">
                @csrf
                <label for="sidebar-store-switcher" class="mb-1.5 block text-[10px] font-semibold uppercase tracking-widest text-zinc-500">
                    Current store
                </label>
                <div class="relative">
                    <select
                        id="sidebar-store-switcher"
                        name="store_id"
                        onchange="this.form.submit()"
                        class="w-full cursor-pointer appearance-none rounded-md border border-zinc-700/90 bg-zinc-900/90 px-3 py-2.5 pr-9 text-sm font-medium text-zinc-100 shadow-sm transition focus:border-indigo-500/55 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    >
                        @foreach ($availableStores as $storeOption)
                            <option value="{{ $storeOption->id }}" @selected(optional($currentStore)->id === $storeOption->id)>
                                {{ $storeOption->name }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500" width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                        <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                    </svg>
                </div>
            </form>
            </div>
        </div>
    @elseif (request()->user()?->hasRole('user'))
        <div class="shrink-0 px-3 pb-3 pt-2">
            <div class="rounded-lg border border-amber-500/20 bg-amber-950/35 px-3 py-3">
                <p class="text-sm font-semibold text-amber-100">No stores available</p>
                <p class="mt-1 text-xs text-amber-200/80">Create a store or seed the demo merchant stores to enable switching.</p>
                <a href="{{ route('store-management') }}" class="mt-3 inline-flex items-center text-xs font-semibold text-indigo-300 hover:text-indigo-200 hover:underline">
                    Open Store Management
                </a>
            </div>
        </div>
    @endif

    @php
        $navSelling = request()->routeIs('products', 'products.' . 'create', 'orders', 'orderViewDetails', 'customers');
        $navActivity = request()->routeIs('team-members.index', 'analytics', 'notifications');
        $navSettings = request()->routeIs('billingSubscription', 'generalSettings', 'settings.payments.*', 'settings.locations.*', 'settings.taxes.*', 'shippingAutomation', 'settings.shipping.*', 'developer-storefront.*', 'security');
    @endphp

    <nav id="merchantNav" class="sidebar-nav-scroll flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overscroll-y-contain px-2.5 pb-3 pt-1">
        <div class="sidebar-nav-panel space-y-0.5">
            <a href="{{ route('dashboard') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('dashboard')])>
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M10 6V0H18V6H10ZM0 10V0H8V10H0ZM10 18V8H18V18H10ZM0 18V12H8V18H0Z" fill="currentColor"/>
                </svg>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('store-management') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('store-management')])>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M2 7L3.75 2.625C3.9 2.24167 4.12917 1.9375 4.4375 1.7125C4.74583 1.4875 5.1 1.375 5.5 1.375H14.5C14.9 1.375 15.2542 1.4875 15.5625 1.7125C15.8708 1.9375 16.1 2.24167 16.25 2.625L18 7V16.25C18 16.7333 17.8292 17.1458 17.4875 17.4875C17.1458 17.8292 16.7333 18 16.25 18H3.75C3.26667 18 2.85417 17.8292 2.5125 17.4875C2.17083 17.1458 2 16.7333 2 16.25V7ZM4.05 7H15.95L14.625 3.75H5.375L4.05 7ZM7.5 10.75V14.5H12.5V10.75H7.5Z" fill="currentColor"/>
                </svg>
                <span>Stores</span>
            </a>
        </div>

        <details class="sidebar-nav-section" @if ($navSelling) open @endif>
            <summary class="sidebar-nav-summary flex cursor-pointer select-none items-center justify-between px-3 py-2.5 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500/40">
                <span>Selling</span>
                <svg class="sidebar-nav-chevron shrink-0 text-zinc-600" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </summary>
            <div class="space-y-0.5 p-1 pt-0">
                <a href="{{ route('products') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('products', 'products.' . 'create', 'products.' . 'show', 'products.' . 'edit')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z" fill="currentColor"/>
                    </svg>
                    <span>Products</span>
                </a>
                <a href="{{ route('orders') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('orders') || request()->routeIs('orderViewDetails')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M6 20C5.45 20 4.97917 19.8042 4.5875 19.4125C4.19583 19.0208 4 18.55 4 18C4 17.45 4.19583 16.9792 4.5875 16.5875C4.97917 16.1958 5.45 16 6 16C6.55 16 7.02083 16.1958 7.4125 16.5875C7.80417 16.9792 8 17.45 8 18C8 18.55 7.80417 19.0208 7.4125 19.4125C7.02083 19.8042 6.55 20 6 20ZM16 20C15.45 20 14.9792 19.8042 14.5875 19.4125C14.1958 19.0208 14 18.55 14 18C14 17.45 14.1958 16.9792 14.5875 16.5875C14.9792 16.1958 15.45 16 16 16C16.55 16 17.0208 16.1958 17.4125 16.5875C17.8042 16.9792 18 17.45 18 18C18 18.55 17.8042 19.0208 17.4125 19.4125C17.0208 19.8042 16.55 20 16 20ZM5.15 4L7.55 9H14.55L17.3 4H5.15ZM4.2 2H18.95C19.3333 2 19.625 2.17083 19.825 2.5125C20.025 2.85417 20.0333 3.2 19.85 3.55L16.3 9.95C16.1167 10.2833 15.8708 10.5417 15.5625 10.725C15.2542 10.9083 14.9167 11 14.55 11H7.1L6 13H18V15H6C5.25 15 4.68333 14.6708 4.3 14.0125C3.91667 13.3542 3.9 12.7 4.25 12.05L5.6 9.6L2 2H0V0H3.25L4.2 2Z" fill="currentColor"/>
                    </svg>
                    <span>Orders</span>
                </a>
                <a href="{{ route('customers') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('customers')])>
                    <svg class="shrink-0" width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0ZM18 16V13C18 12.2667 17.7958 11.5625 17.3875 10.8875C16.9792 10.2125 16.4 9.63333 15.65 9.15C16.5 9.25 17.3 9.42083 18.05 9.6625C18.8 9.90417 19.5 10.2 20.15 10.55C20.75 10.8833 21.2083 11.2542 21.525 11.6625C21.8417 12.0708 22 12.5167 22 13V16H18ZM8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8Z" fill="currentColor"/>
                    </svg>
                    <span>Customers</span>
                </a>
            </div>
        </details>

        <details class="sidebar-nav-section" @if ($navActivity) open @endif>
            <summary class="sidebar-nav-summary flex cursor-pointer select-none items-center justify-between px-3 py-2.5 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500/40">
                <span>Team &amp; activity</span>
                <svg class="sidebar-nav-chevron shrink-0 text-zinc-600" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </summary>
            <div class="space-y-0.5 p-1 pt-0">
                <a href="{{ route('team-members.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('team-members.index')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M6 10C4.9 10 3.95833 9.60833 3.175 8.825C2.39167 8.04167 2 7.1 2 6C2 4.9 2.39167 3.95833 3.175 3.175C3.95833 2.39167 4.9 2 6 2C7.1 2 8.04167 2.39167 8.825 3.175C9.60833 3.95833 10 4.9 10 6C10 7.1 9.60833 8.04167 8.825 8.825C8.04167 9.60833 7.1 10 6 10ZM14 9C13.1667 9 12.4583 8.70833 11.875 8.125C11.2917 7.54167 11 6.83333 11 6C11 5.16667 11.2917 4.45833 11.875 3.875C12.4583 3.29167 13.1667 3 14 3C14.8333 3 15.5417 3.29167 16.125 3.875C16.7083 4.45833 17 5.16667 17 6C17 6.83333 16.7083 7.54167 16.125 8.125C15.5417 8.70833 14.8333 9 14 9ZM6 12C7.38333 12 8.67917 12.2625 9.8875 12.7875C11.0958 13.3125 12.0417 14.025 12.725 14.925C12.9083 15.1583 13 15.4167 13 15.7V18H0V15.7C0 15.4167 0.0916667 15.1583 0.275 14.925C0.958333 14.025 1.90417 13.3125 3.1125 12.7875C4.32083 12.2625 5.61667 12 7 12H6ZM14 11C14.9667 11 15.9042 11.1625 16.8125 11.4875C17.7208 11.8125 18.5 12.2833 19.15 12.9C19.4167 13.15 19.6333 13.4333 19.8 13.75C19.9333 14.0167 20 14.3083 20 14.625V18H15V15.7C15 14.9167 14.7708 14.2083 14.3125 13.575C13.8542 12.9417 13.25 12.4333 12.5 12.05C12.7333 12.0167 12.9708 11.9958 13.2125 11.9875C13.4542 11.9792 13.7167 11.9833 14 12V11Z" fill="currentColor"/>
                    </svg>
                    <span>Team members</span>
                </a>
                <a href="{{ route('analytics') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('analytics')])>
                    <svg class="shrink-0" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 14H6V9H4V14ZM12 14H14V4H12V14ZM8 14H10V11H8V14ZM8 9H10V7H8V9ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="currentColor"/>
                    </svg>
                    <span>Analytics</span>
                </a>
                <a href="{{ route('notifications') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('notifications')])>
                    <svg class="shrink-0" width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/>
                    </svg>
                    <span>Notifications</span>
                </a>
            </div>
        </details>

        <details class="sidebar-nav-section" @if ($navSettings) open @endif>
            <summary class="sidebar-nav-summary flex cursor-pointer select-none items-center justify-between px-3 py-2.5 outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500/40">
                <span>Settings</span>
                <svg class="sidebar-nav-chevron shrink-0 text-zinc-600" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <path d="M3.5 5.25L7 8.75L10.5 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </summary>
            <div class="space-y-0.5 p-1 pt-0">
                <a href="{{ route('billingSubscription') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('billingSubscription')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M2 5C2 3.89543 2.89543 3 4 3H16C17.1046 3 18 3.89543 18 5V15C18 16.1046 17.1046 17 16 17H4C2.89543 17 2 16.1046 2 15V5ZM4 7H16V5H4V7ZM4 11V15H16V11H4Z" fill="currentColor"/>
                    </svg>
                    <span>Billing</span>
                </a>
                <a href="{{ route('generalSettings') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('generalSettings')])>
                    <svg class="shrink-0" width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M7.3 20L6.9 16.8C6.68333 16.7167 6.47917 16.6167 6.2875 16.5C6.09583 16.3833 5.90833 16.2583 5.725 16.125L2.75 17.375L0 12.625L2.575 10.675C2.55833 10.5583 2.55 10.4458 2.55 10.3375C2.55 10.2292 2.55 10.1167 2.55 10C2.55 9.88333 2.55 9.77083 2.55 9.6625C2.55 9.55417 2.55833 9.44167 2.575 9.325L0 7.375L2.75 2.625L5.725 3.875C5.90833 3.74167 6.1 3.61667 6.3 3.5C6.5 3.38333 6.7 3.28333 6.9 3.2L7.3 0H12.8L13.2 3.2C13.4167 3.28333 13.6208 3.38333 13.8125 3.5C14.0042 3.61667 14.1917 3.74167 14.375 3.875L17.35 2.625L20.1 7.375L17.525 9.325C17.5417 9.44167 17.55 9.55417 17.55 9.6625C17.55 9.77083 17.55 9.88333 17.55 10C17.55 10.1167 17.55 10.2292 17.55 10.3375C17.55 10.4458 17.5333 10.5583 17.5 10.675L20.075 12.625L17.325 17.375L14.375 16.125C14.1917 16.2583 14 16.3833 13.8 16.5C13.6 16.6167 13.4 16.7167 13.2 16.8L12.8 20H7.3Z" fill="currentColor"/>
                    </svg>
                    <span>General</span>
                </a>
                <a href="{{ route('settings.payments.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.payments.*')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M2 5C2 3.9 2.9 3 4 3H16C17.1 3 18 3.9 18 5V15C18 16.1 17.1 17 16 17H4C2.9 17 2 16.1 2 15V5ZM4 7H16V5H4V7ZM4 10V15H16V10H4ZM6 13.5H10V12H6V13.5Z" fill="currentColor"/>
                    </svg>
                    <span>Payments</span>
                </a>
                <a href="{{ route('settings.locations.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.locations.*')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 18C8.4 16.65 7.1 15.4 6.1 14.25C5.1 13.1 4.35 12.05 3.85 11.1C3.35 10.15 3.1 9.25 3.1 8.4C3.1 6.35 3.76667 4.75 5.1 3.6C6.43333 2.45 8.06667 1.875 10 1.875C11.9333 1.875 13.5667 2.45 14.9 3.6C16.2333 4.75 16.9 6.35 16.9 8.4C16.9 9.25 16.65 10.15 16.15 11.1C15.65 12.05 14.9 13.1 13.9 14.25C12.9 15.4 11.6 16.65 10 18ZM10 10.5C10.5833 10.5 11.0792 10.2958 11.4875 9.8875C11.8958 9.47917 12.1 8.98333 12.1 8.4C12.1 7.81667 11.8958 7.32083 11.4875 6.9125C11.0792 6.50417 10.5833 6.3 10 6.3C9.41667 6.3 8.92083 6.50417 8.5125 6.9125C8.10417 7.32083 7.9 7.81667 7.9 8.4C7.9 8.98333 8.10417 9.47917 8.5125 9.8875C8.92083 10.2958 9.41667 10.5 10 10.5Z" fill="currentColor"/>
                    </svg>
                    <span>Locations</span>
                </a>
                <a href="{{ route('settings.taxes.index') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('settings.taxes.*')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M3 15V5C3 3.9 3.9 3 5 3H15C16.1 3 17 3.9 17 5V15C17 16.1 16.1 17 15 17H5C3.9 17 3 16.1 3 15ZM5 7H15V5H5V7ZM5 10H11V8H5V10ZM5 13H9V11H5V13Z" fill="currentColor"/>
                    </svg>
                    <span>Taxes</span>
                </a>
                <a href="{{ route('shippingAutomation') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('shippingAutomation', 'settings.shipping.*')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M6 17C5.45 17 4.97917 16.8042 4.5875 16.4125C4.19583 16.0208 4 15.55 4 15C4 14.45 4.19583 13.9792 4.5875 13.5875C4.97917 13.1958 5.45 13 6 13C6.55 13 7.02083 13.1958 7.4125 13.5875C7.80417 13.9792 8 14.45 8 15C8 15.55 7.80417 16.0208 7.4125 16.4125C7.02083 16.8042 6.55 17 6 17ZM14 17C13.45 17 12.9792 16.8042 12.5875 16.4125C12.1958 16.0208 12 15.55 12 15C12 14.45 12.1958 13.9792 12.5875 13.5875C12.9792 13.1958 13.45 13 14 13C14.55 13 15.0208 13.1958 15.4125 13.5875C15.8042 13.9792 16 14.45 16 15C16 15.55 15.8042 16.0208 15.4125 16.4125C15.0208 16.8042 14.55 17 14 17ZM3 3H7L9 7H17C17.2833 7 17.5208 7.09583 17.7125 7.2875C17.9042 7.47917 18 7.71667 18 8C18 8.08333 17.9917 8.17083 17.975 8.2625C17.9583 8.35417 17.925 8.44167 17.875 8.525L16.1 11.75C15.9167 12.0833 15.6708 12.3333 15.3625 12.5C15.0542 12.6667 14.7167 12.75 14.35 12.75H8.25C7.88333 12.75 7.56667 12.675 7.3 12.525C7.03333 12.375 6.83333 12.1667 6.7 11.9L3 4H1V2H3V3Z" fill="currentColor"/>
                    </svg>
                    <span>Shipping</span>
                </a>

                <a href="{{ route('developer-storefront.settings') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('developer-storefront.*')])>
                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 17L4.75 12.625C4.9 12.2417 5.12917 11.9375 5.4375 11.7125C5.74583 11.4875 6.1 11.375 6.5 11.375H8.5L10 9.875V8.375C9.71667 8.375 9.47917 8.27917 9.2875 8.0875C9.09583 7.89583 9 7.65833 9 7.375V4.5L7.5 3H12.5L11 4.5V7.375C11 7.65833 10.9042 7.89583 10.7125 8.0875C10.5208 8.27917 10.2833 8.375 10 8.375V10.25L11.75 12H13.75C14.15 12 14.5042 12.1125 14.8125 12.3375C15.1208 12.5625 15.35 12.8667 15.5 13.25L17.25 17.625L15.75 18.75L14 14.375H12L10 12.375L8 14.375H6L4.25 18.75L2.75 17.625L3 17Z" fill="currentColor"/>
                    </svg>
                    <span>Test storefront</span>
                </a>
                <a href="{{ route('security') }}" @class(['sidebar-nav-link', 'sidebar-nav-link-active' => request()->routeIs('security')])>
                    <svg class="shrink-0" width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M8 20C5.68333 19.4167 3.77083 18.0875 2.2625 16.0125C0.754167 13.9375 0 11.6333 0 9.1V3L8 0L16 3V9.1C16 11.6333 15.2458 13.9375 13.7375 16.0125C12.2292 18.0875 10.3167 19.4167 8 20ZM8 17.9C9.61667 17.4 10.9667 16.4125 12.05 14.9375C13.1333 13.4625 13.7667 11.8167 13.95 10H8V2.125L2 4.375V9.1C2 9.28333 2 9.43333 2 9.55C2 9.66667 2.01667 9.81667 2.05 10H8V17.9Z" fill="currentColor"/>
                    </svg>
                    <span>Security</span>
                </a>
            </div>
        </details>

  <!--      <a href="{{ route('profileSettings') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-colors duration-150 {{ request()->routeIs('profileSettings') ? 'bg-white/10 text-white shadow-sm shadow-black/20' : 'text-zinc-400 hover:bg-white/[0.06] hover:text-zinc-100' }}">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 10C8.9 10 7.95833 9.60833 7.175 8.825C6.39167 8.04167 6 7.1 6 6C6 4.9 6.39167 3.95833 7.175 3.175C7.95833 2.39167 8.9 2 10 2C11.1 2 12.0417 2.39167 12.825 3.175C13.6083 3.95833 14 4.9 14 6C14 7.1 13.6083 8.04167 12.825 8.825C12.0417 9.60833 11.1 10 10 10ZM2 18V15.2C2 14.6333 2.14583 14.1125 2.4375 13.6375C2.72917 13.1625 3.11667 12.8 3.6 12.55C4.63333 12.0333 5.68333 11.6458 6.75 11.3875C7.81667 11.1292 8.9 11 10 11C11.1 11 12.1833 11.1292 13.25 11.3875C14.3167 11.6458 15.3667 12.0333 16.4 12.55C16.8833 12.8 17.2708 13.1625 17.5625 13.6375C17.8542 14.1125 18 14.6333 18 15.2V18H2Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('profileSettings') ? 'font-semibold' : 'font-medium' }}">Profile</span></a> -->
    </nav>

    @php
        $sidebarUser = auth()->user();
        $sidebarInitial = $sidebarUser ? \Illuminate\Support\Str::of($sidebarUser->name)->trim()->substr(0, 1)->upper() : '?';
        $sidebarRoleLabel = $sidebarUser?->role?->name === 'admin' ? 'Platform admin' : 'Merchant account';
    @endphp
    <div class="sidebar-footer shrink-0 space-y-2 px-3 py-3">
        <a href="{{ route('profileSettings') }}" @class(['sidebar-footer-profile', 'sidebar-footer-profile-active' => request()->routeIs('profileSettings')])>
            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-700 text-xs font-bold text-zinc-100 ring-1 ring-zinc-600/50">
                @if ($sidebarUser?->avatar)
                    <img src="{{ asset('storage/'.$sidebarUser->avatar) }}" alt="{{ $sidebarUser->name }}" class="h-full w-full object-cover">
                @else
                    {{ $sidebarInitial }}
                @endif
            </div>
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-white">{{ $sidebarUser?->name ?? 'Account' }}</div>
                <div class="truncate text-xs font-medium {{ request()->routeIs('profileSettings') ? 'text-indigo-300' : 'text-zinc-500' }}">{{ $sidebarRoleLabel }}</div>
            </div>
        </a>
        <a href="{{ route('logout') }}" class="sidebar-footer-logout">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="opacity-90" aria-hidden="true">
                <path d="M6 14C4.33333 14 2.91667 13.4167 1.75 12.25C0.583333 11.0833 0 9.66667 0 8C0 6.33333 0.583333 4.91667 1.75 3.75C2.91667 2.58333 4.33333 2 6 2H9V4H6C4.9 4 3.95833 4.39167 3.175 5.175C2.39167 5.95833 2 6.9 2 8C2 9.1 2.39167 10.0417 3.175 10.825C3.95833 11.6083 4.9 12 6 12H9V14H6ZM11 11L9.625 9.55L11.175 8H5V6H11.175L9.625 4.45L11 3L15 7L11 11Z" fill="currentColor"/>
            </svg>
            <span>Sign out</span>
        </a>
    </div>
</aside>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden">
    @hasSection('topbar')
        @yield('topbar')
    @endif

    <div class="merchant-app flex-1 space-y-6 overflow-y-auto p-4 lg:p-8">
        @yield('content')
    </div>
</main>

<script>
    function closeAllProfileMenus() {
      document.querySelectorAll('.profileMenu').forEach(function (menu) {
        menu.classList.add('hidden');
      });
    }
    

    function initTopbarProfileMenu(profileSettingsUrl, logoutUrl) {
      var headers = document.querySelectorAll('header');
      headers.forEach(function (header) {
        var avatar = header.querySelector('div.rounded-full.overflow-hidden');
        if (avatar && avatar.closest('aside')) return;
        if (avatar && avatar.closest('.profile-menu-wrapper')) return;

        function createProfileWrapper() {
          var wrapper = document.createElement('div');
          wrapper.className = 'relative profile-menu-wrapper shrink-0';

          var trigger = document.createElement('button');
          trigger.type = 'button';
          trigger.className = 'w-9 h-9 rounded-full bg-stone-200 border border-stone-300 overflow-hidden shrink-0 profileMenuToggle';
          trigger.setAttribute('aria-haspopup', 'true');
          trigger.setAttribute('aria-expanded', 'false');
          trigger.setAttribute('aria-label', 'Open profile menu');
          trigger.innerHTML =
            '<svg width=\"36\" height=\"36\" viewBox=\"0 0 36 36\" fill=\"none\">' +
            '<circle cx=\"18\" cy=\"13\" r=\"6\" fill=\"#94A3B8\"/>' +
            '<path d=\"M28 28C28 24 24 22 18 22C12 22 8 24 8 28\" fill=\"#94A3B8\"/>' +
            '</svg>';

          var menu = document.createElement('div');
          menu.className = 'profileMenu hidden absolute right-0 mt-2 w-44 rounded-xl border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/15 z-50';
          menu.innerHTML =
            '<a href=\"' + profileSettingsUrl + '\" class=\"block px-4 py-2 text-sm text-stone-800 hover:bg-stone-50\">Profile Settings</a>' +
            '<a href=\"' + logoutUrl + '\" class=\"block px-4 py-2 text-sm text-rose-700 hover:bg-rose-50\">Logout</a>';

          wrapper.appendChild(trigger);
          wrapper.appendChild(menu);
          return { wrapper: wrapper, trigger: trigger, menu: menu };
        }

        var parts = createProfileWrapper();
        var wrapper = parts.wrapper;
        var trigger = parts.trigger;
        var menu = parts.menu;

        if (avatar) {
          trigger.className = avatar.className + ' profileMenuToggle';
          trigger.innerHTML = avatar.innerHTML;
          avatar.replaceWith(wrapper);
        } else {
          var rightActions = header.querySelector('.flex.items-center.gap-3.shrink-0, .flex.items-center.gap-4.shrink-0, .flex.items-center.gap-2.shrink-0');
          if (rightActions) {
            rightActions.appendChild(wrapper);
          } else {
            header.appendChild(wrapper);
          }
        }

        trigger.addEventListener('click', function (e) {
          e.stopPropagation();
          var isOpen = !menu.classList.contains('hidden');
          closeAllProfileMenus();
          if (!isOpen) {
            menu.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
          } else {
            trigger.setAttribute('aria-expanded', 'false');
          }
        });
      });

      document.addEventListener('click', function () {
        closeAllProfileMenus();
      });
    }

    function openSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebarOverlay');
      if (!sidebar || !overlay) return;
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }

    function closeSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebarOverlay');
      if (!sidebar || !overlay) return;
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 768) {
        var overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }
    });

    document.addEventListener('DOMContentLoaded', function () {
      initTopbarProfileMenu("{{ route('profileSettings') }}", "{{ route('logout') }}");

      var merchantNav = document.getElementById('merchantNav');
      if (merchantNav) {
        merchantNav.addEventListener('click', function (e) {
          var link = e.target.closest('a[href]');
          if (!link) return;
          if (window.matchMedia('(max-width: 767px)').matches) {
            closeSidebar();
          }
        });
      }
    });
</script>
@stack('scripts')
</body>
</html>
