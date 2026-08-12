@extends('layouts.user.user-sidebar')

@section('title', 'Store Management Hub — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Your stores" lead="Each store is its own workspace. Use the sidebar switcher to change the active store.">
        <x-slot:actions>
            <button type="button" class="js-open-create-store-modal hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/></svg>
                <span>Create store</span>
            </button>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $liveStoresCount = (int) ($liveStoresCount ?? $stores->where('onboarding_completed', true)->count());
    $draftStoresCount = (int) ($draftStoresCount ?? $stores->where('onboarding_completed', false)->count());
    $totalProducts = (int) ($totalProducts ?? $stores->sum(fn ($s) => (int) ($s->products_count ?? 0)));
    $totalBrands = (int) ($totalBrands ?? $stores->sum(fn ($s) => (int) ($s->brands_count ?? 0)));
    $activeStoreId = (int) ($activeStoreId ?? session('current_store_id'));
    $recentActivity = $recentActivity ?? collect();
    $draftStoreForNextStep = $draftStoreForNextStep ?? $stores->firstWhere('onboarding_completed', false);
    $storeMetrics = $storeMetrics ?? [];

    $sparklinePoints = static function (array $values): string {
        $count = count($values);
        if ($count === 0) {
            return '0,15 100,15';
        }
        $max = max(0.0001, ...array_map('floatval', $values));
        $points = [];
        foreach ($values as $i => $value) {
            $x = $count === 1 ? 0.0 : ($i / ($count - 1)) * 100;
            $y = 26 - (((float) $value / $max) * 20);
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    };
@endphp

<div
    class="mx-auto max-w-9xl space-y-8 px-4 lg:px-0"
    x-data="{
        storeFilter: 'all',
        sortBy: 'name',
        search: '',
        openMenu: null,
        matches(el) {
            const status = el.dataset.storeStatus || '';
            const name = (el.dataset.storeName || '').toLowerCase();
            const q = this.search.trim().toLowerCase();
            const statusOk = this.storeFilter === 'all' || this.storeFilter === status;
            const searchOk = !q || name.includes(q);
            return statusOk && searchOk;
        },
        applySort() {
            const grid = this.$refs.storeGrid;
            if (!grid) return;
            const cards = [...grid.querySelectorAll('.js-store-card')];
            cards.sort((a, b) => {
                if (this.sortBy === 'revenue') {
                    return Number(b.dataset.revenue || 0) - Number(a.dataset.revenue || 0);
                }
                if (this.sortBy === 'products') {
                    return Number(b.dataset.products || 0) - Number(a.dataset.products || 0);
                }
                return (a.dataset.storeName || '').localeCompare(b.dataset.storeName || '');
            });
            cards.forEach((card) => grid.appendChild(card));
            const addCard = this.$refs.addStoreCard;
            if (addCard) grid.appendChild(addCard);
        }
    }"
    x-init="$watch('sortBy', () => applySort()); applySort()"
    @click.outside="openMenu = null"
>
    {{-- Tabs + search/sort --}}
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 border-b border-slate-200 sm:flex-row sm:items-end sm:justify-between">
            <nav class="flex min-w-max gap-6 overflow-x-auto" role="tablist" aria-label="Filter stores">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="storeFilter === 'all'"
                    @click="storeFilter = 'all'"
                    :class="storeFilter === 'all' ? 'border-brand text-brand font-bold' : 'border-transparent text-slate-500 font-medium hover:text-slate-800'"
                    class="border-b-2 pb-3 text-sm transition"
                >
                    All Stores ({{ count($stores) }})
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="storeFilter === 'live'"
                    @click="storeFilter = 'live'"
                    :class="storeFilter === 'live' ? 'border-brand text-brand font-bold' : 'border-transparent text-slate-500 font-medium hover:text-slate-800'"
                    class="border-b-2 pb-3 text-sm transition"
                >
                    Live ({{ $liveStoresCount }})
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="storeFilter === 'draft'"
                    @click="storeFilter = 'draft'"
                    :class="storeFilter === 'draft' ? 'border-brand text-brand font-bold' : 'border-transparent text-slate-500 font-medium hover:text-slate-800'"
                    class="border-b-2 pb-3 text-sm transition"
                >
                    Drafts ({{ $draftStoresCount }})
                </button>
            </nav>

            <div class="flex flex-wrap items-center gap-2 pb-2">
                <label class="relative">
                    <span class="sr-only">Search stores</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/>
                    </svg>
                    <input
                        type="search"
                        x-model.debounce.200ms="search"
                        placeholder="Search stores…"
                        class="h-9 w-44 rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 sm:w-52"
                    >
                </label>
                <label class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700">
                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3 3.75A.75.75 0 013.75 3h12.5a.75.75 0 01.53 1.28L12 9.06v5.69a.75.75 0 01-1.28.53l-2-2A.75.75 0 018.5 12.5V9.06L3.22 4.28A.75.75 0 013 3.75z"/>
                    </svg>
                    <span class="hidden sm:inline">Sort</span>
                    <select x-model="sortBy" class="border-0 bg-transparent p-0 text-xs font-semibold text-slate-700 focus:ring-0">
                        <option value="name">Name</option>
                        <option value="revenue">7D revenue</option>
                        <option value="products">Products</option>
                    </select>
                </label>
            </div>
        </div>
    </div>

    {{-- Store grid --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3" x-ref="storeGrid">
        @forelse ($stores as $store)
            @php
                $isLive = (bool) $store->onboarding_completed;
                $isActive = $activeStoreId === (int) $store->id;
                $logoUrl = $store->logoPublicUrl();
                $metrics = $storeMetrics[$store->id] ?? [
                    'revenue_7d' => 0.0,
                    'orders_7d' => 0,
                    'orders_change_pct' => null,
                    'sparkline' => array_fill(0, 7, 0.0),
                    'health' => 'setup',
                    'health_label' => 'Setup needed',
                    'setup_ready_count' => 0,
                    'setup_total' => 5,
                    'setup_complete' => false,
                ];
                $sparkValues = array_map('floatval', $metrics['sparkline'] ?? []);
                $sparkHasData = collect($sparkValues)->sum() > 0;
                $changePct = $metrics['orders_change_pct'];
                $storeActionPayload = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'contact_email' => $store->settings['contact_email'] ?? '',
                    'primary_market' => $store->settings['primary_market'] ?? 'Global Market',
                    'currency' => $store->currency,
                    'timezone' => $store->timezone,
                    'address' => $store->address,
                    'category' => $store->category,
                    'custom_category' => $store->settings['custom_category'] ?? '',
                    'business_models' => $store->settings['business_models'] ?? [],
                    'logo_url' => $logoUrl,
                    'update_url' => route('store.update', ['storeId' => $store->id]),
                    'delete_url' => route('store.destroy', ['storeId' => $store->id]),
                    'redirect_to' => 'store-management',
                ];
            @endphp
            <article
                class="js-store-card group relative overflow-hidden rounded-xl border-2 bg-white shadow-sm transition hover:border-brand/40 {{ $isActive ? 'border-brand ring-4 ring-brand/5' : 'border-slate-200' }}"
                data-store-status="{{ $isLive ? 'live' : 'draft' }}"
                data-store-name="{{ $store->name }}"
                data-revenue="{{ (float) ($metrics['revenue_7d'] ?? 0) }}"
                data-products="{{ (int) ($store->products_count ?? 0) }}"
                x-show="matches($el)"
            >
                <div class="p-5 sm:p-6">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex min-w-0 gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $store->name }} logo" class="h-full w-full object-contain p-1">
                                @else
                                    <span class="text-sm font-bold uppercase text-brand">{{ \Illuminate\Support\Str::substr($store->name, 0, 1) }}</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-base font-semibold text-slate-900">{{ $store->name }}</h3>
                                    @if ($isActive)
                                        <span class="rounded bg-brand px-1.5 py-0.5 text-[10px] font-bold uppercase text-white" title="This is the store currently selected in your sidebar">Working here</span>
                                    @endif
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{ ucfirst($store->category ?? 'General') }}</span>
                                    @if ($isLive)
                                        <span class="inline-flex items-center gap-1 rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Draft
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <form method="POST" action="{{ route('current-store.update') }}">
                                @csrf
                                <input type="hidden" name="store_id" value="{{ $store->id }}">
                                <input type="hidden" name="redirect_to" value="dashboard">
                                <button
                                    type="submit"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-50 hover:text-brand"
                                    title="{{ $isActive ? 'Open dashboard' : 'Switch to this store and open dashboard' }}"
                                    aria-label="Open {{ $store->name }} dashboard"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                    </svg>
                                </button>
                            </form>
                            @php
                                $health = $metrics['health'] ?? 'setup';
                                $healthClass = match ($health) {
                                    'healthy' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                    'ready' => 'border-sky-200 bg-sky-50 text-sky-800',
                                    default => 'border-amber-200 bg-amber-50 text-amber-900',
                                };
                                $setupReady = (int) ($metrics['setup_ready_count'] ?? 0);
                                $setupTotal = (int) ($metrics['setup_total'] ?? 5);
                                $healthTitle = $health === 'setup'
                                    ? "Finish store setup ({$setupReady}/{$setupTotal}): catalog, location, tax, and delivery"
                                    : ($health === 'ready'
                                        ? 'Major setup is complete. Waiting for recent orders.'
                                        : 'Major setup is complete and this store has recent orders.');
                            @endphp
                            <span
                                class="rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $healthClass }}"
                                title="{{ $healthTitle }}"
                            >{{ $metrics['health_label'] ?? 'Setup needed' }}</span>
                        </div>
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-4 border-y border-slate-100 py-4">
                        <div>
                            <p class="text-xs font-medium text-slate-500">7D Revenue</p>
                            <p class="mt-0.5 text-lg font-bold text-slate-900">{{ \App\Support\MoneyDisplay::format($metrics['revenue_7d'] ?? 0, $store->currency ?: 'USD') }}</p>
                            <div class="mt-2">
                                <svg
                                    class="js-store-sparkline h-8 w-[100px] {{ $sparkHasData ? 'stroke-brand' : 'stroke-slate-300' }}"
                                    viewBox="0 0 100 30"
                                    fill="none"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    data-sparkline='@json($sparkValues)'
                                    aria-hidden="true"
                                >
                                    @if ($sparkHasData)
                                        <polyline points="{{ $sparklinePoints($sparkValues) }}"></polyline>
                                    @else
                                        <line x1="0" y1="15" x2="100" y2="15"></line>
                                    @endif
                                </svg>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium text-slate-500">Orders (7D)</p>
                            <p class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format((int) ($metrics['orders_7d'] ?? 0)) }}</p>
                            @if ($changePct === null)
                                <p class="mt-1 text-xs font-bold text-slate-400">—</p>
                            @elseif ($changePct > 0)
                                <p class="mt-1 text-xs font-bold text-emerald-700">+{{ number_format($changePct, 1) }}%</p>
                            @elseif ($changePct < 0)
                                <p class="mt-1 text-xs font-bold text-red-600">{{ number_format($changePct, 1) }}%</p>
                            @else
                                <p class="mt-1 text-xs font-bold text-slate-500">0%</p>
                            @endif
                        </div>
                    </div>

                    <div class="mb-5 flex items-end justify-between gap-3">
                        <div class="flex gap-5">
                            <div>
                                <p class="text-xs text-slate-500">Products</p>
                                <p class="text-sm font-bold text-slate-900">{{ number_format((int) ($store->products_count ?? 0)) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Brands</p>
                                <p class="text-sm font-bold text-slate-900">{{ number_format((int) ($store->brands_count ?? 0)) }}</p>
                            </div>
                        </div>
                        <p class="text-xs italic text-slate-400">Created {{ $store->created_at->format('M d, Y') }}</p>
                    </div>

                    <div class="flex gap-2">
                        <a
                            href="{{ route('store.products', ['storeId' => $store->id]) }}"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-brand py-2.5 text-sm font-bold text-white transition hover:bg-brand-hover"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9.315 7.584C12.195 3.883 16.695 1.5 21.75 1.5a.75.75 0 01.75.75c0 5.056-2.383 9.555-6.084 12.436A6.75 6.75 0 019.75 22.5a.75.75 0 01-.75-.75v-4.131A15.838 15.838 0 016.382 15H2.25a.75.75 0 01-.75-.75 6.75 6.75 0 017.815-6.666zM15 6.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" clip-rule="evenodd"/>
                                <path d="M5.26 17.242a.75.75 0 10-.897-1.203 5.243 5.243 0 00-2.05 5.022.75.75 0 00.625.627 5.243 5.243 0 005.022-2.051.75.75 0 10-1.202-.897 3.744 3.744 0 01-3.008 1.51c0-1.23.592-2.323 1.51-3.008z"/>
                            </svg>
                            Open catalog
                        </a>
                        <div class="relative">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50"
                                @click.stop="openMenu = openMenu === {{ $store->id }} ? null : {{ $store->id }}"
                                :aria-expanded="openMenu === {{ $store->id }}"
                                aria-haspopup="menu"
                                title="More actions"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                </svg>
                            </button>
                            <div
                                x-show="openMenu === {{ $store->id }}"
                                x-cloak
                                class="absolute bottom-12 right-0 z-20 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                                role="menu"
                            >
                                <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Add product</a>
                                <button
                                    type="button"
                                    class="js-open-edit-store-modal block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                                    data-store='@json($storeActionPayload)'
                                    role="menuitem"
                                    @click="openMenu = null"
                                >
                                    Edit store
                                </button>
                                @if ($isLive)
                                    <form method="POST" action="{{ route('store.lifecycle', ['storeId' => $store->id]) }}" role="none">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                            Move to draft
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('store.lifecycle', ['storeId' => $store->id]) }}" role="none">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="live">
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm font-semibold text-brand hover:bg-brand/5" role="menuitem">
                                            Mark as live
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand/10 text-brand">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 4h16v2H4V4zm1 4h14l1 12H4L5 8zm3 2v2h8v-2H8z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">No stores yet</h3>
                <p class="mb-6 mt-2 text-sm text-slate-600">Create your first store to get started</p>
                <button type="button" class="js-open-create-store-modal inline-flex items-center gap-2 rounded-lg bg-brand px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-hover">
                    Create First Store
                </button>
            </div>
        @endforelse

        @if (count($stores) > 0)
            <div
                class="col-span-full rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500"
                x-show="![...$refs.storeGrid.querySelectorAll('.js-store-card')].some((el) => matches(el))"
                x-cloak
            >
                <p>No stores match this filter or search.</p>
            </div>

            <button
                type="button"
                x-ref="addStoreCard"
                class="js-open-create-store-modal flex min-h-[280px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/60 p-8 text-center transition hover:border-brand hover:bg-white"
            >
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2h6z"/></svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Add Another Store</h3>
                <p class="mt-1 max-w-[200px] text-xs text-slate-500">Scale your business ecosystem</p>
            </button>
        @endif
    </div>

    {{-- Workspace summary + activity --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Workspace summary</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900">{{ $stores->count() }} {{ Str::plural('store', $stores->count()) }}</h3>
                </div>

                <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="mb-1 text-xs text-slate-500">Total Products</p>
                        <p class="text-base font-bold text-slate-900">{{ number_format($totalProducts) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="mb-1 text-xs text-slate-500">Total Brands</p>
                        <p class="text-base font-bold text-slate-900">{{ number_format($totalBrands) }}</p>
                    </div>
                    <div class="rounded-lg border border-brand/10 bg-brand/5 p-4">
                        <p class="mb-1 text-xs text-brand/80">Live Stores</p>
                        <p class="text-base font-bold text-brand">{{ number_format($liveStoresCount) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="mb-1 text-xs text-slate-500">Draft Stores</p>
                        <p class="text-base font-bold text-slate-900">{{ number_format($draftStoresCount) }}</p>
                    </div>
                </div>

                <div class="flex flex-col items-start justify-between gap-4 rounded-xl bg-slate-100 p-4 sm:flex-row sm:items-center">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-white">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            @if ($stores->isEmpty())
                                <p class="text-sm font-bold text-slate-900">Create your first store</p>
                                <p class="text-xs text-slate-600">Set up a workspace for products, orders, and customers.</p>
                            @elseif ($draftStoresCount > 0)
                                <p class="text-sm font-bold text-slate-900">Finish store setup</p>
                                <p class="text-xs text-slate-600">
                                    {{ $draftStoresCount }} {{ Str::plural('draft store', $draftStoresCount) }} still need attention.
                                    @if ($draftStoreForNextStep)
                                        Continue with <span class="font-bold text-slate-900">{{ $draftStoreForNextStep->name }}</span>.
                                    @endif
                                </p>
                            @else
                                <p class="text-sm font-bold text-slate-900">Your stores are ready</p>
                                <p class="text-xs text-slate-600">Open a catalog to add products, or review recent orders.</p>
                            @endif
                        </div>
                    </div>
                    @if ($stores->isEmpty())
                        <button type="button" class="js-open-create-store-modal rounded-lg bg-brand px-5 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">Create store</button>
                    @elseif ($draftStoreForNextStep)
                        <a href="{{ route('store.products', ['storeId' => $draftStoreForNextStep->id]) }}" class="rounded-lg bg-brand px-5 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">Continue setup</a>
                    @elseif ($activeStoreId > 0)
                        <a href="{{ route('orders') }}" class="rounded-lg bg-brand px-5 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">View orders</a>
                    @else
                        <button type="button" class="js-open-create-store-modal rounded-lg bg-brand px-5 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">Add another store</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex items-center gap-2">
                <svg class="h-5 w-5 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .192.168.1.5.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
                </svg>
                <h3 class="text-base font-bold text-slate-900">Recent Activity</h3>
            </div>

            <div class="flex-1 space-y-5">
                @forelse ($recentActivity as $index => $event)
                    @php
                        $title = filled($event->title) ? $event->title : str_replace('_', ' ', ucfirst((string) $event->event_type));
                        $storeName = $event->store?->name ?? 'Store';
                        $description = filled($event->description) ? $event->description : null;
                        $tone = match (true) {
                            str_contains(strtolower((string) $event->event_type), 'fail') || str_contains(strtolower($title), 'fail') => 'error',
                            str_contains(strtolower($title), 'order') || str_contains(strtolower((string) $event->event_type), 'order') => 'order',
                            str_contains(strtolower($title), 'return') || str_contains(strtolower($title), 'refund') => 'neutral',
                            default => 'info',
                        };
                        $isLast = $loop->last;
                    @endphp
                    <div class="flex gap-3">
                        <div class="relative flex flex-col items-center">
                            <div @class([
                                'relative z-10 flex h-8 w-8 items-center justify-center rounded-full',
                                'bg-red-100 text-red-700' => $tone === 'error',
                                'bg-sky-100 text-sky-700' => $tone === 'order',
                                'bg-brand/10 text-brand' => $tone === 'info',
                                'bg-slate-100 text-slate-600' => $tone === 'neutral',
                            ])>
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    @if ($tone === 'error')
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    @elseif ($tone === 'order')
                                        <path d="M1 1.75A.75.75 0 011.75 1h1.628a1.75 1.75 0 011.734 1.51L5.18 3.5H17.25a.75.75 0 01.73.93l-1.4 5.6a1.75 1.75 0 01-1.7 1.32H7.02l.12.49A1.75 1.75 0 018.86 13h7.39a.75.75 0 010 1.5H8.86a3.25 3.25 0 01-3.2-2.64L4.12 3.91a.25.25 0 00-.247-.216H1.75A.75.75 0 011 1.75zM6.5 17.5a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm8 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/>
                                    @else
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd"/>
                                    @endif
                                </svg>
                            </div>
                            @unless ($isLast)
                                <div class="mt-1 w-px flex-1 bg-slate-200" aria-hidden="true"></div>
                            @endunless
                        </div>
                        <div class="min-w-0 pb-1">
                            <p class="text-sm font-bold text-slate-900">{{ $title }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $storeName }}
                                @if ($description)
                                    · {{ \Illuminate\Support\Str::limit($description, 72) }}
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-slate-400">{{ optional($event->created_at)->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-8 text-center">
                        <p class="text-sm font-medium text-slate-900">No recent activity yet</p>
                        <p class="mt-1 text-xs text-slate-500">Order updates across your stores will appear here.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                @if ($activeStoreId > 0 && $stores->contains(fn ($s) => (int) $s->id === $activeStoreId))
                    <a href="{{ route('orders') }}" class="block w-full rounded-lg border border-slate-200 py-2.5 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-50">View All Activity</a>
                @elseif ($stores->isNotEmpty())
                    <form method="POST" action="{{ route('current-store.update') }}">
                        @csrf
                        <input type="hidden" name="store_id" value="{{ $stores->first()->id }}">
                        <input type="hidden" name="redirect_to" value="orders">
                        <button type="submit" class="w-full rounded-lg border border-slate-200 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">View All Activity</button>
                    </form>
                @else
                    <p class="text-center text-xs text-slate-500">Create a store to track order activity.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@include('user_view.partials.store_create_modal')
@include('user_view.partials.store_edit_modal')
@endsection

@push('scripts')
<script>
(() => {
    const strokeSparkline = (svg) => {
        const raw = svg.getAttribute('data-sparkline');
        if (!raw) return;
        let values;
        try {
            values = JSON.parse(raw);
        } catch (e) {
            return;
        }
        if (!Array.isArray(values) || values.length === 0) return;
        const nums = values.map((v) => Number(v) || 0);
        const max = Math.max(...nums, 0.0001);
        const hasData = nums.some((v) => v > 0);
        const points = nums.map((v, i) => {
            const x = nums.length === 1 ? 0 : (i / (nums.length - 1)) * 100;
            const y = hasData ? (26 - ((v / max) * 20)) : 15;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');

        svg.replaceChildren();
        const ns = 'http://www.w3.org/2000/svg';
        if (!hasData) {
            const line = document.createElementNS(ns, 'line');
            line.setAttribute('x1', '0');
            line.setAttribute('y1', '15');
            line.setAttribute('x2', '100');
            line.setAttribute('y2', '15');
            svg.appendChild(line);
            return;
        }
        const poly = document.createElementNS(ns, 'polyline');
        poly.setAttribute('points', points);
        svg.appendChild(poly);
    };

    document.querySelectorAll('.js-store-sparkline').forEach(strokeSparkline);
})();
</script>
@endpush
