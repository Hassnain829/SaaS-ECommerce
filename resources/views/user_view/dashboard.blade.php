@extends('layouts.user.user-sidebar')

@section('title', 'Dashboard — '.config('app.name'))

@php
    $d = $dashboard ?? ['has_store' => false];
    $hasStore = (bool) ($d['has_store'] ?? false);
    $range = $d['range'] ?? '30';
    $permissions = $d['permissions'] ?? [];
    $setup = $d['setup_progress'] ?? [];
    $setupComplete = (bool) ($d['setup_complete'] ?? false);
    $metrics = $d['metrics'] ?? [];
    $attention = $d['attention'] ?? [];
    $flow = $d['order_flow'] ?? ['rows' => [], 'max' => 1, 'ship_now' => 0];
    $systems = $d['systems'] ?? ['items' => []];
    $chart = $d['chart'] ?? ['labels' => [], 'current' => [], 'previous' => [], 'current_formatted' => [], 'empty' => true];
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Dashboard" lead="Your store at a glance.">
        @if ($hasStore)
            <x-slot:actions>
                @if (! empty($permissions['orders_draft']))
                    <a href="{{ route('orders.create') }}" class="hidden h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink sm:inline-flex">Create order</a>
                @endif
                @if (! empty($permissions['catalog_manage']))
                    <a href="{{ route('products.create') }}" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span class="hidden sm:inline">Add product</span>
                    </a>
                @endif
            </x-slot:actions>
        @endif
    </x-ui.merchant-topbar>
@endsection

@section('content')
@if (! $hasStore)
    <div class="merchant-card max-w-xl p-6">
        <h2 class="text-lg font-semibold text-ink">Welcome</h2>
        @if ($canCreateStores ?? false)
            <p class="mt-2 text-sm text-ink-secondary">Create a store to see your dashboard and start managing products and orders.</p>
            <a href="{{ route('store-management') }}" class="mt-5 inline-flex items-center rounded-md bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Go to store management
            </a>
        @else
            <p class="mt-2 text-sm text-ink-secondary">You don’t have a store to work in yet. Ask the owner to finish your invitation, or to allow you to create a store.</p>
            <a href="{{ route('store-management') }}" class="mt-5 inline-flex items-center rounded-md bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Go to store management
            </a>
        @endif
    </div>
@else
    <div
        class="merchant-dashboard settings-workspace-fluid w-full"
        data-merchant-dashboard
        data-user-id="{{ auth()->id() }}"
        data-store-id="{{ $d['store']->id }}"
        data-currency="{{ $d['currency'] }}"
        data-range="{{ $range }}"
    >
        <svg aria-hidden="true" width="0" height="0" class="mdash-sprite">
            <symbol id="mdash-box" viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 4 8-4M12 11v10" fill="none" stroke="currentColor" stroke-width="1.8"/></symbol>
            <symbol id="mdash-return" viewBox="0 0 24 24"><path d="M9 7 4 12l5 5M5 12h10a5 5 0 1 1 0 10h-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
            <symbol id="mdash-alert" viewBox="0 0 24 24"><path d="m12 3 10 18H2L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v5M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
            <symbol id="mdash-arrow" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
            <symbol id="mdash-truck" viewBox="0 0 24 24"><path d="M3 6h12v11H3zM15 10h4l2 3v4h-6z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="7" cy="18" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="18" cy="18" r="2" fill="none" stroke="currentColor" stroke-width="1.8"/></symbol>
            <symbol id="mdash-check" viewBox="0 0 24 24"><path d="m6 12 4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
            <symbol id="mdash-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m19 13.5 2-1.5-2-1.5-.5-1.3.7-2.4-2.4-.7-1.7 1-1.4-.5L12 4l-1.7 2.6-1.4.5-1.7-1-2.4.7.7 2.4-.5 1.3L3 12l2 1.5.5 1.3-.7 2.4 2.4.7 1.7-1 1.4.5L12 20l1.7-2.6 1.4.5 1.7 1 2.4-.7-.7-2.4.5-1.3Z" fill="none" stroke="currentColor" stroke-width="1.5"/></symbol>
        </svg>

        <section class="mdash-welcome" aria-labelledby="welcomeHeading">
            <div class="mdash-welcome-title">
                <h2 id="welcomeHeading">{{ $d['greeting'] ?? 'Hello' }}</h2>
                @if ($setupComplete)
                    <span class="mdash-status-pill">Store operational</span>
                @endif
            </div>
            <p class="mdash-welcome-lead">{{ $d['greeting_lead'] ?? 'Here’s what needs your attention.' }}</p>
            <div class="mdash-range-tools">
                <div class="mdash-segmented" aria-label="Dashboard date range">
                    <a href="{{ route('dashboard', ['range' => 'today']) }}" class="mdash-segment {{ $range === 'today' ? 'is-active' : '' }}" @if ($range === 'today') aria-current="page" @endif>Today</a>
                    <a href="{{ route('dashboard', ['range' => '7']) }}" class="mdash-segment {{ $range === '7' ? 'is-active' : '' }}" @if ($range === '7') aria-current="page" @endif>7 days</a>
                    <a href="{{ route('dashboard', ['range' => '30']) }}" class="mdash-segment {{ $range === '30' ? 'is-active' : '' }}" @if ($range === '30') aria-current="page" @endif>30 days</a>
                </div>
                <button class="mdash-btn mdash-btn-secondary" type="button" data-dashboard-customize>
                    <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-gear"/></svg>
                    Customize
                </button>
            </div>
        </section>

        @if (! $setupComplete && (! empty($permissions['settings_view']) || ! empty($permissions['payments_view']) || ! empty($permissions['developer_api_view'])))
            @php
                $setupSteps = $setup['steps'] ?? [];
                $setupNext = $setup['next'] ?? null;
                $setupReady = (int) ($setup['ready_count'] ?? 0);
                $setupTotal = (int) ($setup['total_count'] ?? count($setupSteps));
                $setupRemaining = (int) ($setup['remaining_count'] ?? max(0, $setupTotal - $setupReady));
                $setupPercent = (float) ($setup['progress_percent'] ?? ($setupTotal > 0 ? round(($setupReady / $setupTotal) * 100, 1) : 0));
                $setupHeadline = $setupRemaining === 1
                    ? 'One step left to start selling'
                    : $setupRemaining.' steps left to start selling';
                $canOpenSetup = ! empty($setupNext['href']);
            @endphp
            <section class="mdash-setup-card" data-setup-card aria-labelledby="setupNoticeTitle">
                <div class="mdash-setup-summary">
                    <div class="mdash-setup-progress" aria-label="Setup progress">
                        <div class="mdash-setup-ring" style="--progress: {{ $setupPercent }}%">
                            <div class="mdash-setup-ring-copy">
                                <strong>{{ $setupReady }} of {{ $setupTotal }}</strong>
                                <span>ready</span>
                            </div>
                        </div>
                    </div>

                    <div class="mdash-setup-copy">
                        <p class="mdash-setup-eyebrow">Store readiness</p>
                        <h2 id="setupNoticeTitle">{{ $setupHeadline }}</h2>
                        <p>{{ $setupNext['next_description'] ?? ($setupNext['description'] ?? 'Complete remaining setup so you can start selling.') }}</p>
                        @if ($setupReady > 0)
                            <div class="mdash-setup-strip" aria-label="Completed setup areas">
                                @foreach ($setupSteps as $step)
                                    @if (! empty($step['ready']))
                                        <span class="mdash-setup-chip is-complete">
                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <span>{{ $step['short_title'] ?? $step['title'] }} ready</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <aside class="mdash-setup-next" aria-label="Next setup step">
                        <span class="mdash-setup-next-label">Next step</span>
                        <strong class="mdash-setup-next-title">{{ $setupNext['title'] ?? 'Complete remaining setup' }}</strong>
                        @if ($canOpenSetup && ! empty($setupNext['href']))
                            <a class="mdash-setup-primary" href="{{ $setupNext['href'] }}">
                                <span>{{ $setupNext['cta'] ?? 'Continue setup' }}</span>
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        @endif
                        <button class="mdash-setup-link" type="button" data-setup-checklist-toggle aria-expanded="false" aria-controls="setupChecklist">
                            View full checklist
                        </button>
                    </aside>
                </div>

                <div class="mdash-setup-checklist" id="setupChecklist" data-setup-checklist hidden>
                    <div class="mdash-setup-checklist-head">
                        <div>
                            <h3>Setup checklist</h3>
                            <p>Complete these areas before accepting live orders.</p>
                        </div>
                        <button class="mdash-setup-icon-btn" type="button" data-setup-checklist-close aria-label="Close checklist">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <ol class="mdash-setup-list">
                        @foreach ($setupSteps as $step)
                            <li class="mdash-setup-item">
                                <span class="mdash-setup-step-icon {{ ! empty($step['ready']) ? 'is-complete' : '' }}" aria-hidden="true">
                                    @if (! empty($step['ready']))
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="2"/><path d="M12 8v5M12 16.5v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    @endif
                                </span>
                                <div class="mdash-setup-step-text">
                                    <strong>{{ $step['title'] }}</strong>
                                    <span>{{ ! empty($step['ready']) ? 'Ready' : ($step['description'] ?? '') }}</span>
                                </div>
                                @if ($canOpenSetup && ! empty($step['href']))
                                    <a class="mdash-setup-secondary" href="{{ $step['href'] }}">{{ $step['cta'] ?? 'Open' }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        <section class="mdash-surface mdash-attention" id="attentionSection" data-dashboard-panel="attention" aria-labelledby="attentionTitle">
            <p class="mdash-kicker" id="attentionTitle">Needs attention</p>
            <div class="mdash-attention-grid">
                @if (! empty($permissions['orders_view']))
                <article class="mdash-attention-item">
                    <span class="mdash-attention-icon" aria-hidden="true"><svg class="mdash-icon"><use href="#mdash-box"/></svg></span>
                    <span class="mdash-attention-copy">
                        <strong><b>{{ number_format((int) ($attention['fulfillment']['count'] ?? 0)) }}</b> {{ (int) ($attention['fulfillment']['count'] ?? 0) === 1 ? 'order' : 'orders' }}</strong>
                        <span>{{ (int) ($attention['fulfillment']['count'] ?? 0) === 0 ? 'None waiting to fulfill' : 'Ready to fulfill' }}</span>
                    </span>
                    @if (! empty($attention['fulfillment']['href']))
                        <a class="mdash-action" href="{{ $attention['fulfillment']['href'] }}">Open <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    @endif
                </article>
                @endif
                @if (! empty($permissions['catalog_view']))
                <article class="mdash-attention-item">
                    <span class="mdash-attention-icon" aria-hidden="true"><svg class="mdash-icon"><use href="#mdash-box"/></svg></span>
                    <span class="mdash-attention-copy">
                        <strong><b>{{ number_format((int) ($attention['low_stock']['count'] ?? 0)) }}</b> {{ (int) ($attention['low_stock']['count'] ?? 0) === 1 ? 'product' : 'products' }}</strong>
                        <span>{{ (int) ($attention['low_stock']['count'] ?? 0) === 0 ? 'No low-stock items' : 'Low stock' }}</span>
                    </span>
                    @if (! empty($attention['low_stock']['href']))
                        <a class="mdash-action" href="{{ $attention['low_stock']['href'] }}">Review <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    @endif
                </article>
                @endif
                @if (! empty($permissions['orders_view']))
                <article class="mdash-attention-item">
                    <span class="mdash-attention-icon" aria-hidden="true"><svg class="mdash-icon"><use href="#mdash-return"/></svg></span>
                    <span class="mdash-attention-copy">
                        <strong><b>{{ number_format((int) ($attention['returns']['count'] ?? 0)) }}</b> {{ (int) ($attention['returns']['count'] ?? 0) === 1 ? 'return' : 'returns' }}</strong>
                        <span>{{ (int) ($attention['returns']['count'] ?? 0) === 0 ? 'No returns waiting' : 'Awaiting review' }}</span>
                    </span>
                    @if (! empty($attention['returns']['href']))
                        <a class="mdash-action" href="{{ $attention['returns']['href'] }}">Resolve <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    @endif
                </article>
                @endif
                @if (! empty($permissions['settings_view']) || ! empty($permissions['orders_view']))
                <article class="mdash-attention-item">
                    <span class="mdash-attention-icon {{ (int) ($attention['delivery']['count'] ?? 0) > 0 ? 'is-danger' : '' }}" aria-hidden="true"><svg class="mdash-icon"><use href="#mdash-alert"/></svg></span>
                    <span class="mdash-attention-copy">
                        <strong><b class="{{ (int) ($attention['delivery']['count'] ?? 0) > 0 ? 'is-danger' : '' }}">{{ number_format((int) ($attention['delivery']['count'] ?? 0)) }}</b> {{ (int) ($attention['delivery']['count'] ?? 0) === 1 ? 'delivery issue' : 'delivery issues' }}</strong>
                        <span>{{ (int) ($attention['delivery']['count'] ?? 0) === 0 ? 'No delivery issues' : ($attention['delivery']['label'] ?? 'Needs attention') }}</span>
                    </span>
                    @if (! empty($attention['delivery']['href']))
                        <a class="mdash-action" href="{{ $attention['delivery']['href'] }}">Fix <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    @endif
                </article>
                @endif
            </div>
        </section>

        @if ($metrics !== [])
        <div class="mdash-grid" id="analyticsGrid">
            <section class="mdash-surface mdash-performance" id="performanceCard" aria-labelledby="performanceTitle">
                <div class="mdash-card-head">
                    <div>
                        <h3 id="performanceTitle">Performance</h3>
                        <p>{{ $d['range_description'] }}</p>
                    </div>
                </div>
                <div class="mdash-metrics">
                    @forelse ($metrics as $key => $metric)
                        <div class="mdash-metric" data-metric="{{ $key }}" data-metric-display="{{ $metric['display'] }}">
                            <span>{{ $metric['label'] }}</span>
                            <strong>{{ $metric['display'] }}</strong>
                            @if (($metric['change']['label'] ?? '') !== '')
                                <em class="is-{{ $metric['change']['direction'] ?? 'flat' }}">{{ $metric['change']['label'] }}</em>
                            @endif
                        </div>
                    @empty
                    @endforelse
                </div>
                @if (! empty($permissions['orders_view']))
                <div class="mdash-chart-title">
                    <h4>Revenue over time</h4>
                    <div class="mdash-legend">
                        <span><i></i>Current period</span>
                        <span><i class="is-previous"></i>Previous period</span>
                    </div>
                </div>
                <div class="mdash-chart-wrap" id="chartWrap">
                    @if (! empty($chart['empty']))
                        <div class="mdash-chart-empty">
                            <p>No revenue in this period</p>
                            <span>Totals appear here once orders are placed.</span>
                        </div>
                    @endif
                    <svg id="revenueChart" class="mdash-chart" role="img" aria-label="Revenue over time" preserveAspectRatio="none" @if (! empty($chart['empty'])) hidden @endif></svg>
                    <div class="mdash-chart-tooltip" id="chartTooltip" hidden>
                        <small></small>
                        <strong></strong>
                    </div>
                </div>
                @endif
            </section>

            @if (! empty($permissions['orders_view']))
            <section class="mdash-surface mdash-order-flow" id="orderFlowCard" data-dashboard-panel="orderFlow" aria-labelledby="orderFlowTitle">
                <div class="mdash-card-head">
                    <div>
                        <h3 id="orderFlowTitle">Order flow</h3>
                        <p>Live workload</p>
                    </div>
                </div>
                <div class="mdash-flow-list">
                    @forelse ($flow['rows'] ?? [] as $row)
                        <div class="mdash-flow-row">
                            @if (! empty($row['href']))
                                <a href="{{ $row['href'] }}">{{ $row['label'] }}</a>
                            @else
                                <span>{{ $row['label'] }}</span>
                            @endif
                            <span class="mdash-mini-track">
                                <span class="mdash-mini-bar" style="width: {{ max(8, (int) round((((int) $row['count']) / max(1, (int) ($flow['max'] ?? 1))) * 100)) }}%"></span>
                            </span>
                            <strong>{{ number_format((int) $row['count']) }}</strong>
                        </div>
                    @empty
                        <p class="mdash-empty-copy">No open order workload yet.</p>
                    @endforelse
                </div>
                @if (! empty($flow['ship_now_href']))
                    <a class="mdash-fulfill-callout" href="{{ $flow['ship_now_href'] }}">
                        <svg class="mdash-icon" aria-hidden="true"><use href="#mdash-truck"/></svg>
                        <strong>{{ number_format((int) ($flow['ship_now'] ?? 0)) }} {{ (int) ($flow['ship_now'] ?? 0) === 1 ? 'order can ship now' : 'orders can ship now' }}</strong>
                        <span>Open fulfillment →</span>
                    </a>
                @endif
            </section>
            @endif
        </div>
        @endif

        <div class="mdash-lower" id="lowerGrid">
            @if (! empty($permissions['orders_view']))
            <section class="mdash-surface mdash-table-card" id="recentOrdersCard" data-dashboard-panel="recentOrders" aria-labelledby="recentOrdersTitle">
                <div class="mdash-card-head">
                    <div><h3 id="recentOrdersTitle">Recent orders</h3></div>
                    @if (! empty($permissions['orders_view']))
                        <a class="mdash-view-all" href="{{ route('orders') }}">View all <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    @endif
                </div>
                <div class="mdash-table-wrap">
                    @if (($d['recent_orders'] ?? []) === [])
                        <p class="mdash-empty-copy">No orders yet. When sales come in, they will show up here.</p>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Source</th>
                                    <th>Payment</th>
                                    <th>Fulfillment</th>
                                    <th>Total</th>
                                    <th>Time</th>
                                    <th aria-label="Open"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['recent_orders'] as $order)
                                    <tr>
                                        <td>
                                            @if (! empty($order['href']))
                                                <a class="mdash-order-number" href="{{ $order['href'] }}">{{ $order['number'] }}</a>
                                            @else
                                                <span class="mdash-order-number">{{ $order['number'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $order['source'] }}</td>
                                        <td><span class="mdash-status is-{{ $order['payment_tone'] }}">{{ $order['payment'] }}</span></td>
                                        <td><span class="mdash-status is-{{ $order['fulfillment_tone'] }}">{{ $order['fulfillment'] }}</span></td>
                                        <td class="mdash-money">{{ $order['total'] }}</td>
                                        <td>{{ $order['time'] }}</td>
                                        <td>
                                            @if (! empty($order['href']))
                                                <a href="{{ $order['href'] }}" aria-label="Open {{ $order['number'] }}">
                                                    <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
            @endif

            @if (! empty($permissions['catalog_view']))
            <section class="mdash-surface mdash-inventory" id="inventoryCard" data-dashboard-panel="inventory" aria-labelledby="inventoryTitle">
                <div class="mdash-card-head">
                    <div>
                        <h3 id="inventoryTitle">Inventory watch</h3>
                        <p>Items needing attention</p>
                    </div>
                </div>
                <div class="mdash-inventory-list">
                    @forelse ($d['inventory_watch'] ?? [] as $item)
                        @php $rowTag = ! empty($item['href']) ? 'a' : 'div'; @endphp
                        <{{ $rowTag }} class="mdash-inventory-row" @if (! empty($item['href'])) href="{{ $item['href'] }}" @endif>
                            <span class="mdash-thumb" aria-hidden="true">
                                @if (! empty($item['image_url']))
                                    <img src="{{ $item['image_url'] }}" alt="">
                                @else
                                    <svg class="mdash-icon"><use href="#mdash-box"/></svg>
                                @endif
                            </span>
                            <span>
                                <span class="mdash-inventory-name">{{ $item['name'] }}</span>
                                <span class="mdash-sku">SKU {{ $item['sku'] }}</span>
                            </span>
                            <span class="mdash-stock-cell">
                                <span class="mdash-stock-copy {{ ! empty($item['critical']) ? 'is-critical' : '' }}">{{ number_format((int) $item['stock']) }} left</span>
                                <span class="mdash-stock-track" aria-hidden="true">
                                    <span class="mdash-stock-bar {{ ! empty($item['critical']) ? 'is-critical' : '' }}" style="width: {{ max(8, min(100, (int) ($item['percent'] ?? 0))) }}%"></span>
                                </span>
                            </span>
                            <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg>
                        </{{ $rowTag }}>
                    @empty
                        <p class="mdash-empty-copy">No items are at their low-stock alert.</p>
                    @endforelse
                </div>
                @if (! empty($attention['low_stock']['href']))
                    <div class="mdash-inventory-footer">
                        <a class="mdash-view-all" href="{{ $attention['low_stock']['href'] }}">Review inventory <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
                    </div>
                @endif
            </section>
            @endif
        </div>

        @if (($systems['items'] ?? []) !== [])
        <section class="mdash-surface mdash-systems" id="systemsStrip" data-dashboard-panel="systems" aria-labelledby="systemsTitle">
            <h3 id="systemsTitle">Store systems</h3>
            <div class="mdash-system-items">
                @foreach ($systems['items'] ?? [] as $system)
                    @php $systemTag = ! empty($system['href']) ? 'a' : 'span'; @endphp
                    <{{ $systemTag }} class="mdash-system-item {{ ! empty($system['ready']) ? 'is-ready' : 'is-muted' }}" @if (! empty($system['href'])) href="{{ $system['href'] }}" @endif>
                        <i class="mdash-system-check {{ ! empty($system['ready']) ? 'is-ready' : '' }}" aria-hidden="true">
                            @if (! empty($system['ready']))
                                <svg class="mdash-icon-sm"><use href="#mdash-check"/></svg>
                            @endif
                        </i>
                        {{ $system['label'] }}
                    </{{ $systemTag }}>
                @endforeach
            </div>
            @if (! empty($systems['manage_href']))
                <a class="mdash-view-all" href="{{ $systems['manage_href'] }}">Manage settings <svg class="mdash-icon-sm" aria-hidden="true"><use href="#mdash-arrow"/></svg></a>
            @endif
        </section>
        @endif
        <script type="application/json" id="merchant-dashboard-chart-data">@json($chart)</script>
    </div>
@endif
@endsection

@if ($hasStore)
    @push('overlays')
        <dialog class="ui-native-dialog mdash-dialog" id="dashboardCustomizeDialog" aria-labelledby="dashboardCustomizeTitle">
            <form method="dialog" id="dashboardCustomizeForm">
                <div class="mdash-dialog-head">
                    <div>
                        <h2 id="dashboardCustomizeTitle">Customize dashboard</h2>
                        <p>Choose which operational panels are visible.</p>
                    </div>
                    <button class="mdash-close" type="button" data-dashboard-customize-close aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="mdash-dialog-body">
                    <label class="mdash-check-row"><input type="checkbox" name="attention" checked> Needs-attention queue</label>
                    <label class="mdash-check-row"><input type="checkbox" name="orderFlow" checked> Order flow</label>
                    <label class="mdash-check-row"><input type="checkbox" name="recentOrders" checked> Recent orders</label>
                    <label class="mdash-check-row"><input type="checkbox" name="inventory" checked> Inventory watch</label>
                    <label class="mdash-check-row"><input type="checkbox" name="systems" checked> Store systems</label>
                </div>
                <div class="mdash-dialog-actions">
                    <button class="mdash-btn mdash-btn-secondary" type="button" data-dashboard-customize-reset>Reset</button>
                    <button class="mdash-btn mdash-btn-primary" type="submit">Save layout</button>
                </div>
            </form>
        </dialog>
    @endpush
@endif
