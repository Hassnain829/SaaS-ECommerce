@extends('layouts.user.user-sidebar')

@section('title', 'Dashboard — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Dashboard" lead="Store performance and setup checklist.">
        <x-slot:actions>
            <a href="{{ route('products') }}" class="hidden items-center gap-1.5 rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover sm:inline-flex">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="currentColor"/>
                </svg>
                <span>Products</span>
            </a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $d = $dashboard ?? ['has_store' => false];
    $hasStore = $d['has_store'] ?? false;
    $currency = $d['currency'] ?? 'USD';
    $setup = $d['setup_progress'] ?? [];
    $setupSteps = [
        [
            'title' => 'Set store location',
            'description' => (($setup['location']['count'] ?? 0) > 0)
                ? (($setup['location']['count'] ?? 0).' active ship-from location(s)')
                : 'Add at least one active ship-from location',
            'ready' => (bool) ($setup['location']['ready'] ?? false),
            'href' => route('settings.locations.index'),
            'cta' => ((bool) ($setup['location']['ready'] ?? false)) ? 'Manage locations' : 'Set location',
        ],
        [
            'title' => 'Configure checkout tax',
            'description' => ((bool) ($setup['tax']['ready'] ?? false))
                ? (($setup['tax']['count'] ?? 0).' active tax rate(s)')
                : 'Enable tax and add at least one active tax rate',
            'ready' => (bool) ($setup['tax']['ready'] ?? false),
            'href' => route('settings.taxes.index'),
            'cta' => ((bool) ($setup['tax']['ready'] ?? false)) ? 'Review tax' : 'Set tax',
        ],
        [
            'title' => 'Prepare delivery setup',
            'description' => ((bool) ($setup['delivery']['ready'] ?? false))
                ? (($setup['delivery']['areas_count'] ?? 0).' area(s), '.($setup['delivery']['options_count'] ?? 0).' option(s)')
                : 'Add delivery areas and checkout delivery options',
            'ready' => (bool) ($setup['delivery']['ready'] ?? false),
            'href' => route('shippingAutomation'),
            'cta' => ((bool) ($setup['delivery']['ready'] ?? false)) ? 'Review delivery' : 'Set delivery',
        ],
    ];
    $setupReadyCount = collect($setupSteps)->where('ready', true)->count();
    $setupComplete = $setupReadyCount === count($setupSteps);
    $setupPercent = count($setupSteps) > 0 ? (int) round(($setupReadyCount / count($setupSteps)) * 100) : 0;
    $chartDays = $d['chart_days'] ?? [];
    $chartMax = 0.0;
    foreach ($chartDays as $day) {
        $chartMax = max($chartMax, (float) ($day['total'] ?? 0));
    }
    $chartEmpty = $chartMax <= 0.0;
@endphp

@if (! $hasStore)
    <div class="merchant-card max-w-xl p-6">
        <h2 class="text-lg font-semibold text-ink">Welcome</h2>
        <p class="mt-2 text-sm text-ink-secondary">Create a store to see your dashboard and start managing products and orders.</p>
        <a href="{{ route('store-management') }}" class="mt-5 inline-flex items-center rounded-md bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
            Go to store management
        </a>
    </div>
@else
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-ink">{{ $setupComplete ? 'Performance overview' : 'Store setup' }}</h2>
            <p class="mt-0.5 text-sm text-ink-secondary">
                <span class="font-medium text-ink">{{ $d['store']->name }}</span>
                @if ($setupComplete)
                    <span class="text-ink-muted"> · </span>
                    Revenue and activity use the last 30 days.
                @else
                    <span class="text-ink-muted"> · </span>
                    Complete the steps below to get your store ready for sales.
                @endif
            </p>
        </div>
        @if ($setupComplete)
            <div class="inline-flex items-center gap-2 self-start rounded-md border border-border bg-surface px-2.5 py-1.5 text-xs font-medium text-ink-secondary">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                Store setup complete
            </div>
        @endif
    </div>

    <section class="dashboard-setup-hero">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Store setup</p>
                <h2 class="mt-1 text-lg font-semibold text-ink">
                    {{ $setupComplete ? 'Your store is ready to operate' : 'Finish setup to start selling' }}
                </h2>
                <p class="mt-1 text-sm text-ink-secondary">{{ $setupReadyCount }} of {{ count($setupSteps) }} areas are ready.</p>
            </div>
            <a href="{{ route('generalSettings') }}" class="inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">
                Store settings
            </a>
        </div>
        <div class="dashboard-setup-progress" aria-hidden="true">
            <div class="dashboard-setup-progress-bar" style="width: {{ $setupPercent }}%"></div>
        </div>
        <div class="mt-4 settings-checklist">
            @foreach ($setupSteps as $index => $step)
                <article class="settings-checklist-row">
                    <span @class([
                        'settings-checklist-icon',
                        'settings-checklist-icon-ready' => $step['ready'],
                        'settings-checklist-icon-pending' => ! $step['ready'],
                    ])>{{ $step['ready'] ? '✓' : ($index + 1) }}</span>
                    <div>
                        <p class="settings-checklist-label">Step {{ $index + 1 }}</p>
                        <h3 class="settings-checklist-title">{{ $step['title'] }}</h3>
                        <p class="settings-checklist-detail">{{ $step['description'] }}</p>
                    </div>
                    <a href="{{ $step['href'] }}" class="settings-checklist-action {{ $step['ready'] ? 'settings-checklist-action-secondary' : 'settings-checklist-action-primary' }}">
                        {{ $step['cta'] }}
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    @if (! $setupComplete)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="merchant-card p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Open orders</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-ink">{{ number_format($d['active_orders_count']) }}</p>
                <p class="mt-1.5 text-xs text-ink-muted">Orders waiting for action.</p>
            </div>
            <div class="merchant-card p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Products</p>
                <p class="mt-1.5 text-xl font-semibold tabular-nums text-ink">{{ number_format($d['products_count']) }}</p>
                <p class="mt-1.5 text-xs text-ink-muted">Items in your catalog.</p>
            </div>
        </div>
    @else
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="merchant-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Revenue · 30 days</p>
            <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-ink">{{ \App\Support\MoneyDisplay::format($d['revenue_30d'], $currency) }}</p>
            <p class="mt-1.5 text-xs leading-snug text-ink-muted">Excludes cancelled and refunded orders.</p>
        </div>

        <div class="merchant-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Open orders</p>
            <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-ink">{{ number_format($d['active_orders_count']) }}</p>
            <p class="mt-1.5 text-xs leading-snug text-ink-muted">Pending, confirmed, or processing.</p>
        </div>

        <div class="merchant-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Customers</p>
            <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-ink">{{ number_format($d['customers_count']) }}</p>
            <p class="mt-1.5 text-xs leading-snug text-ink-muted">+{{ number_format($d['customers_new_30d']) }} new in the last 30 days</p>
        </div>

        <div class="merchant-card p-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Products</p>
            <p class="mt-1.5 text-xl font-semibold tabular-nums tracking-tight text-ink">{{ number_format($d['products_count']) }}</p>
            <p class="mt-1.5 text-xs leading-snug text-ink-muted">{{ number_format($d['orders_30d_count']) }} orders in the last 30 days</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
        <div class="merchant-card p-4 lg:col-span-2">
            <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Revenue trend</h2>
                    <p class="text-xs text-ink-muted">Last 7 days, paid orders only</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-ink-muted">
                    <span class="h-2 w-2 shrink-0 rounded-sm bg-brand" aria-hidden="true"></span>
                    Paid total per day
                </div>
            </div>
            <div class="relative rounded-md bg-surface-muted/60 ring-1 ring-inset ring-border">
                @if ($chartEmpty)
                    <div class="flex h-48 flex-col items-center justify-center gap-1 px-4 text-center">
                        <p class="text-sm font-medium text-ink">No paid revenue in this window</p>
                        <p class="max-w-sm text-xs text-ink-muted">When orders are paid in the last 7 days, daily totals appear here.</p>
                    </div>
                @else
                    <div class="flex h-48 items-end gap-1 px-2 pb-0 pt-3 sm:gap-2">
                        @foreach ($chartDays as $day)
                            @php
                                $dayTotal = (float) ($day['total'] ?? 0);
                                $pct = $dayTotal > 0 && $chartMax > 0
                                    ? max(14, min(100, round(($dayTotal / $chartMax) * 100)))
                                    : 0;
                            @endphp
                            <div class="flex h-full min-w-0 flex-1 flex-col items-stretch justify-end">
                                <div
                                    class="mx-auto w-full max-w-[2.25rem] rounded-t-sm bg-brand transition hover:bg-brand-hover"
                                    style="height: {{ $pct }}%"
                                    title="{{ \App\Support\MoneyDisplay::format($day['total'], $currency) }}"
                                ></div>
                                <p class="mt-2 pb-2 text-center text-[10px] font-medium uppercase tracking-wide text-ink-muted sm:text-[11px]">{{ $day['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="merchant-card flex flex-col p-4">
            <div class="mb-2">
                <h2 class="text-sm font-semibold text-ink">Recent orders</h2>
                <p class="text-xs text-ink-muted">Latest in this store</p>
            </div>
            <div class="relative max-h-60 flex-1 space-y-1 overflow-y-auto">
                @forelse ($d['recent_orders'] as $order)
                    @php
                        $orderFull = $order->order_number ? trim((string) $order->order_number) : ('Order #'.$order->id);
                        $orderDisplay = strlen($orderFull) > 24
                            ? substr($orderFull, 0, 10).'…'.substr($orderFull, -8)
                            : $orderFull;
                    @endphp
                    <a href="{{ route('orderViewDetails', $order) }}" class="flex items-center gap-3 rounded-md border border-transparent px-2.5 py-2 transition hover:border-border hover:bg-surface-muted" title="{{ $orderFull }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-mono text-xs font-semibold text-ink">{{ $orderDisplay }}</span>
                            <span class="mt-0.5 block truncate text-xs text-ink-muted">{{ \Illuminate\Support\Str::headline($order->status) }} · {{ \App\Support\MoneyDisplay::formatWithCode($order->grand_total, $order->currency_code ?: ($currency ?? 'USD')) }}</span>
                        </span>
                    </a>
                @empty
                    <div class="rounded-md border border-dashed border-border bg-surface-muted/50 px-4 py-8 text-center text-sm text-ink-secondary">
                        No orders yet. When sales come in, they will show up here.
                    </div>
                @endforelse
            </div>
            <div class="mt-2 border-t border-border pt-2.5 text-center">
                <a href="{{ route('orders') }}" class="text-sm font-semibold text-brand transition hover:text-brand-hover">View all orders</a>
            </div>
        </div>
    </div>

    <div class="merchant-card overflow-hidden">
        <div class="flex flex-col gap-1 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-ink">Top products</h2>
                <p class="text-xs text-ink-muted">By line revenue · last 30 days</p>
            </div>
            <a href="{{ route('products') }}" class="text-sm font-semibold text-brand transition hover:text-brand-hover">View catalog</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-surface-muted/70 text-left text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-2.5">Product</th>
                        <th class="hidden px-4 py-2.5 sm:table-cell">Units sold</th>
                        <th class="px-4 py-2.5 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($d['top_products'] as $row)
                        <tr class="transition hover:bg-surface-muted/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('products.show', $row->product_id) }}" class="font-medium text-ink hover:text-brand">{{ $row->display_name }}</a>
                            </td>
                            <td class="hidden px-4 py-3 text-ink-secondary sm:table-cell tabular-nums">{{ number_format((int) $row->units_sold) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-ink">{{ \App\Support\MoneyDisplay::format($row->revenue, $currency) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-sm text-ink-secondary">
                                No product sales in this window yet. Top sellers will appear here once orders include line items.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endif
@endsection
