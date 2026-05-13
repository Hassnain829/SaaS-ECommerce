@extends('layouts.user.user-sidebar')

@section('title', 'Dashboard — '.config('app.name'))

@section('topbar')
<header class="sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 border-b border-stone-200/80 bg-white/92 px-4 py-3 shadow-sm shadow-stone-900/[0.03] backdrop-blur-md lg:px-8">
    <button id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-white text-stone-600 shadow-sm transition hover:border-stone-300 hover:bg-stone-50 md:hidden" aria-label="Open menu">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="search" name="q" autocomplete="off" placeholder="Search products, orders, customers…" class="w-full rounded-xl border border-stone-200 bg-stone-50 py-2.5 pl-10 pr-4 text-sm text-stone-900 shadow-inner placeholder:text-stone-500 focus:border-indigo-400/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
    </div>

    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <a href="{{ route('products') }}" class="hidden items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover sm:inline-flex">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="currentColor"/>
            </svg>
            <span>Products</span>
        </a>

        <div class="hidden h-6 w-px bg-stone-200 sm:block"></div>

        <a href="{{ route('notifications') }}" class="relative flex rounded-full p-2 text-stone-500 transition hover:bg-stone-100 hover:text-stone-800" aria-label="Notifications">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" aria-hidden="true">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full border-2 border-white bg-rose-500"></span>
        </a>

        <a href="{{ route('generalSettings') }}" class="hidden rounded-full p-2 text-stone-500 transition hover:bg-stone-100 hover:text-stone-800 sm:flex" aria-label="Settings">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95C11.2333 17.95 13.125 17.175 14.675 15.625C16.225 14.075 17 12.1833 17 9.95C17 7.71667 16.225 5.825 14.675 4.275C13.125 2.725 11.2333 1.95 9 1.95C6.76667 1.95 4.875 2.725 3.325 4.275C1.775 5.825 1 7.71667 1 9.95C1 12.1833 1.775 14.075 3.325 15.625C4.875 17.175 6.76667 17.95 9 17.95ZM9 15C9.28333 15 9.52083 14.9042 9.7125 14.7125C9.90417 14.5208 10 14.2833 10 14C10 13.7167 9.90417 13.4792 9.7125 13.2875C9.52083 13.0958 9.28333 13 9 13C8.71667 13 8.47917 13.0958 8.2875 13.2875C8.09583 13.4792 8 13.7167 8 14C8 14.2833 8.09583 14.5208 8.2875 14.7125C8.47917 14.9042 8.71667 15 9 15ZM9 11H10V5H8V6H9V11Z" fill="currentColor"/>
            </svg>
        </a>

        <div class="h-9 w-9 shrink-0 overflow-hidden rounded-full border border-stone-200 bg-stone-200">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                <circle cx="18" cy="13" r="6" fill="#94A3B8"/>
                <path d="M28 28C28 24 24 22 18 22C12 22 8 24 8 28" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
@php
    $d = $dashboard ?? ['has_store' => false];
    $hasStore = $d['has_store'] ?? false;
    $currency = $d['currency'] ?? 'USD';
    $chartDays = $d['chart_days'] ?? [];
    $chartMax = 0.0;
    foreach ($chartDays as $day) {
        $chartMax = max($chartMax, (float) ($day['total'] ?? 0));
    }
    $chartEmpty = $chartMax <= 0.0;
@endphp

@if (! $hasStore)
    <div class="merchant-card max-w-xl p-8">
        <h1 class="text-xl font-semibold text-stone-900">Welcome</h1>
        <p class="mt-2 text-stone-600">Create a store to see your dashboard and start managing products and orders.</p>
        <a href="{{ route('store-management') }}" class="mt-6 inline-flex items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
            Go to store management
        </a>
    </div>
@else
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-stone-900">Dashboard</h1>
            <p class="mt-0.5 text-sm text-stone-600">
                <span class="font-medium text-stone-800">{{ $d['store']->name }}</span>
                <span class="text-stone-400"> · </span>
                Revenue and activity use the last 30 days; the chart uses the last 7 days.
            </p>
        </div>
        <div class="inline-flex items-center gap-2 self-start rounded-lg border border-stone-200/90 bg-stone-50/90 px-2.5 py-1.5 text-xs font-medium text-stone-600">
            <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500 ring-2 ring-emerald-500/25" aria-hidden="true"></span>
            Connected to your store
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="merchant-card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Revenue · 30 days</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-stone-900">{{ \App\Support\MoneyDisplay::format($d['revenue_30d'], $currency) }}</p>
                    <p class="mt-1.5 text-xs leading-snug text-stone-500">Excludes cancelled and refunded orders.</p>
                </div>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand/10">
                    <svg class="text-brand" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M12 8V2H20V8H12ZM2 12V2H10V12H2ZM12 20V10H20V20H12ZM2 20V14H10V20H2Z" fill="currentColor"/></svg>
                </div>
            </div>
        </div>

        <div class="merchant-card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Open orders</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-stone-900">{{ number_format($d['active_orders_count']) }}</p>
                    <p class="mt-1.5 text-xs leading-snug text-stone-500">Pending, confirmed, or processing.</p>
                </div>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100">
                    <svg class="text-sky-600" width="18" height="14" viewBox="0 0 20 16" fill="none" aria-hidden="true"><path d="M3 12C2.16667 12 1.45833 11.7083 0.875 11.125C0.291667 10.5417 0 9.83333 0 9H2V10C2 10.2833 2.09583 10.5208 2.2875 10.7125C2.47917 10.9042 2.71667 11 3 11H6V12H3ZM14 12L10 8L11.4 6.55L14 9.15V1H16V9.15L18.6 6.55L20 8L16 12H14ZM2 7V6H6V7H2ZM2 4V3H6V4H2ZM8 12V10H18V12H8ZM8 9V7H14V9H8ZM8 6V4H14V6H8Z" fill="currentColor"/></svg>
                </div>
            </div>
        </div>

        <div class="merchant-card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Customers</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-stone-900">{{ number_format($d['customers_count']) }}</p>
                    <p class="mt-1.5 text-xs leading-snug text-stone-500">+{{ number_format($d['customers_new_30d']) }} new in the last 30 days</p>
                </div>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                    <svg class="text-amber-700" width="18" height="14" viewBox="0 0 20 16" fill="none" aria-hidden="true"><path d="M14 8V5H12V3H14V0H16V3H19V5H16V8H14ZM8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8ZM0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0ZM2 14H14V13.2C14 13.0167 13.9542 12.85 13.8625 12.7C13.7708 12.55 13.65 12.4333 13.5 12.35C12.6 11.9 11.6917 11.5625 10.775 11.3375C9.85833 11.1125 8.93333 11 8 11C7.06667 11 6.14167 11.1125 5.225 11.3375C4.30833 11.5625 3.4 11.9 2.5 12.35C2.35 12.4333 2.22917 12.55 2.1375 12.7C2.04583 12.85 2 13.0167 2 13.2V14ZM8 6C8.55 6 9.02083 5.80417 9.4125 5.4125C9.80417 5.02083 10 4.55 10 4C10 3.45 9.80417 2.97917 9.4125 2.5875C9.02083 2.19583 8.55 2 8 2C7.45 2 6.97917 2.19583 6.5875 2.5875C6.19583 2.97917 6 3.45 6 4C6 4.55 6.19583 5.02083 6.5875 5.4125C6.97917 5.80417 7.45 6 8 6Z" fill="currentColor"/></svg>
                </div>
            </div>
        </div>

        <div class="merchant-card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Products</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-stone-900">{{ number_format($d['products_count']) }}</p>
                    <p class="mt-1.5 text-xs leading-snug text-stone-500">{{ number_format($d['orders_30d_count']) }} orders in the last 30 days (excludes cancelled and refunded)</p>
                </div>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100">
                    <svg class="text-violet-700" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z" fill="currentColor"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="merchant-card p-5 lg:col-span-2">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-stone-900">Revenue trend</h2>
                    <p class="text-sm text-stone-500">Last 7 days, paid orders only (same rules as above)</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-stone-500">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                    Paid total per day
                </div>
            </div>
            <div class="relative rounded-lg bg-gradient-to-b from-stone-50/80 to-white ring-1 ring-inset ring-stone-100">
                @if ($chartEmpty)
                    <div class="flex h-52 flex-col items-center justify-center gap-2 px-4 text-center">
                        <p class="text-sm font-medium text-stone-700">No paid revenue in this window</p>
                        <p class="max-w-sm text-xs text-stone-500">When orders are paid in the last 7 days, daily totals appear here as bars.</p>
                    </div>
                @else
                    <div class="flex h-52 items-end gap-1 px-2 pb-0 pt-4 sm:gap-2">
                        @foreach ($chartDays as $day)
                            @php
                                $dayTotal = (float) ($day['total'] ?? 0);
                                $pct = $dayTotal > 0 && $chartMax > 0
                                    ? max(14, min(100, round(($dayTotal / $chartMax) * 100)))
                                    : 0;
                            @endphp
                            <div class="flex h-full min-w-0 flex-1 flex-col items-stretch justify-end">
                                <div
                                    class="mx-auto w-full max-w-[2.5rem] rounded-t bg-brand/90 shadow-sm shadow-brand/15 transition hover:bg-brand"
                                    style="height: {{ $pct }}%"
                                    title="{{ \App\Support\MoneyDisplay::format($day['total'], $currency) }}"
                                ></div>
                                <p class="mt-2 pb-2 text-center text-[10px] font-medium uppercase tracking-wide text-stone-500 sm:text-[11px]">{{ $day['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="merchant-card flex flex-col p-5">
            <div class="mb-3">
                <h2 class="text-base font-semibold text-stone-900">Recent orders</h2>
                <p class="text-sm text-stone-500">Latest in this store</p>
            </div>
            <div class="relative max-h-64 flex-1 space-y-1.5 overflow-y-auto pr-0.5">
                @forelse ($d['recent_orders'] as $order)
                    @php
                        $orderFull = $order->order_number ? trim((string) $order->order_number) : ('Order #'.$order->id);
                        $orderDisplay = strlen($orderFull) > 24
                            ? substr($orderFull, 0, 10).'…'.substr($orderFull, -8)
                            : $orderFull;
                    @endphp
                    <a href="{{ route('orderViewDetails', $order) }}" class="flex items-center gap-3 rounded-lg border border-stone-100 bg-stone-50/90 px-3 py-2 transition hover:border-stone-200 hover:bg-white hover:shadow-sm" title="{{ $orderFull }}">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100/90 text-emerald-700" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6.67 11.67l5.37-5.38-1.17-1.16-4.2 4.2-1.87-1.88-1.17 1.17 3.04 3.05z" fill="currentColor"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-xs font-semibold text-stone-900">{{ $orderDisplay }}</span>
                            <span class="mt-0.5 block truncate text-xs text-stone-500">{{ \Illuminate\Support\Str::headline($order->status) }} · {{ \App\Support\MoneyDisplay::format($order->grand_total, $currency) }}</span>
                        </span>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-stone-200 bg-stone-50/80 px-4 py-8 text-center text-sm text-stone-600">
                        No orders yet. When sales come in, they will show up here.
                    </div>
                @endforelse
            </div>
            <div class="mt-3 border-t border-stone-100 pt-3 text-center">
                <a href="{{ route('orders') }}" class="text-sm font-semibold text-brand transition hover:text-brand-hover">View all orders</a>
            </div>
        </div>
    </div>

    <div class="merchant-card overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-stone-100 bg-stone-50/50 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-stone-900">Top products</h2>
                <p class="text-sm text-stone-500">By line revenue · last 30 days</p>
            </div>
            <a href="{{ route('products') }}" class="text-sm font-semibold text-brand transition hover:text-brand-hover">View catalog</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-stone-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                    <tr>
                        <th class="px-5 py-2.5">Product</th>
                        <th class="hidden px-5 py-2.5 sm:table-cell">Units sold</th>
                        <th class="px-5 py-2.5 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($d['top_products'] as $row)
                        <tr class="transition hover:bg-stone-50/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-500">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M2 18c-.55 0-1.02-.2-1.41-.59A1.9 1.9 0 010 16V2C0 1.45.2.98.59.59S1.45 0 2 0h14c.55 0 1.02.2 1.41.59.39.39.59.86.59 1.41v14c0 .55-.2 1.02-.59 1.41-.39.39-.86.59-1.41.59H2z" fill="currentColor"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $row->product_id) }}" class="font-medium text-stone-900 hover:text-brand">{{ $row->display_name }}</a>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-5 py-3 text-stone-600 sm:table-cell tabular-nums">{{ number_format((int) $row->units_sold) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-semibold tabular-nums text-stone-900">{{ \App\Support\MoneyDisplay::format($row->revenue, $currency) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-stone-600">
                                No product sales in this window yet. Top sellers will appear here once orders include line items.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
