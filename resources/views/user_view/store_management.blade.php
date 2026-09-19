@extends('layouts.user.user-sidebar')

@section('title', 'Store Management Hub — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Your stores" lead="Each store is its own workspace. Use the sidebar switcher to change the active store.">
        <x-slot:search>
            <div
                class="relative"
                x-data="{
                    init() {
                        if (Alpine.store('storesHub') === undefined) {
                            Alpine.store('storesHub', { search: '' });
                        }
                    }
                }"
            >
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/>
                        <path d="m16 16 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </span>
                <label class="sr-only" for="stores-directory-search">Search stores</label>
                <input
                    id="stores-directory-search"
                    type="search"
                    x-model.debounce.200ms="$store.storesHub.search"
                    placeholder="Search stores..."
                    autocomplete="off"
                    class="h-9 w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                >
            </div>
        </x-slot:search>
        <x-slot:actions>
            @if ($canCreateStores ?? false)
                <button type="button" class="js-open-create-store-modal inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/></svg>
                    <span>Create store</span>
                </button>
            @endif
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
    $recentActivity = ($recentActivity ?? collect())->take(4);
    $draftStoreForNextStep = $draftStoreForNextStep ?? $stores->firstWhere('onboarding_completed', false);
    $storeMetrics = $storeMetrics ?? [];
    $closedStores = $closedStores ?? collect();
    $needsSetupCount = $stores->filter(function ($store) use ($storeMetrics) {
        $metrics = $storeMetrics[$store->id] ?? [];

        return ! (bool) ($metrics['setup_complete'] ?? false) || ! (bool) $store->onboarding_completed;
    })->count();
    $setupReviewStores = $stores->map(function ($store) use ($storeMetrics) {
        $metrics = $storeMetrics[$store->id] ?? [];
        $remaining = collect($metrics['setup_steps'] ?? [])
            ->reject(fn ($step) => (bool) ($step['ready'] ?? false))
            ->values();
        $needsOnboarding = ! (bool) $store->onboarding_completed;
        if ($remaining->isEmpty() && ! $needsOnboarding) {
            return null;
        }

        return [
            'store' => $store,
            'remaining' => $remaining,
            'needs_onboarding' => $needsOnboarding,
            'ready_count' => (int) ($metrics['setup_ready_count'] ?? 0),
            'setup_total' => (int) ($metrics['setup_total'] ?? 4),
        ];
    })->filter()->values();
    $avatarPalettes = [
        ['#e6f4ef', '#08765c'],
        ['#f2ecff', '#6941c6'],
        ['#eaf5ff', '#0871b8'],
        ['#fff4e4', '#b55d00'],
        ['#e7f6f2', '#08755d'],
        ['#f3edff', '#6a3fc4'],
        ['#fff0f0', '#b42318'],
        ['#edf1f5', '#344054'],
    ];
    $storeInitials = static function ($name): string {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode(''));
    };
    $categoryLabel = static function ($store): string {
        $category = strtolower((string) ($store->category ?? 'general'));
        if (in_array($category, ['physical', 'digital'], true)) {
            return ucfirst($category);
        }

        return $category === '' ? 'General' : ucfirst($category);
    };
    $typeFilterValue = static function ($store): string {
        $category = strtolower((string) ($store->category ?? ''));

        return in_array($category, ['physical', 'digital'], true) ? $category : 'other';
    };
@endphp

<div
    class="stores-dir w-full space-y-1"
    data-current-store-id="{{ $activeStoreId }}"
    x-data="{
        view: localStorage.getItem('storesDirectoryView') || 'grid',
        tab: window.location.hash === '#closed-stores' ? 'closed' : 'all',
        sortBy: 'name',
        typeFilter: 'all',
        page: 1,
        pageSize: 8,
        openMenu: null,
        reviewOpen: false,
        setView(next) {
            this.view = next;
            this.page = 1;
            localStorage.setItem('storesDirectoryView', next);
        },
        setTab(next) {
            this.tab = next;
            this.page = 1;
            this.openMenu = null;
            if (next === 'closed') {
                history.replaceState(null, '', '#closed-stores');
            } else if (window.location.hash === '#closed-stores') {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
        },
        openReview() {
            this.reviewOpen = true;
            this.setTab('needs');
            this.$nextTick(() => this.$refs.setupReview?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        cardRoot() {
            return this.view === 'list' ? this.$refs.storeList : this.$refs.storeGrid;
        },
        matches(el) {
            if (this.tab === 'closed') return false;
            const status = el.dataset.storeStatus || '';
            const needs = el.dataset.needsSetup === '1';
            const type = el.dataset.storeType || '';
            const name = (el.dataset.storeName || '').toLowerCase();
            const q = (this.$store.storesHub?.search || '').trim().toLowerCase();
            const tabOk = this.tab === 'all' || (this.tab === 'needs' && needs);
            const typeOk = this.typeFilter === 'all' || this.typeFilter === type;
            const searchOk = !q || name.includes(q);
            return tabOk && typeOk && searchOk && (status === 'live' || status === 'draft');
        },
        filteredCards() {
            const root = this.cardRoot();
            if (!root) return [];
            return [...root.querySelectorAll('.js-store-card')].filter((el) => this.matches(el));
        },
        sortedCards() {
            const cards = this.filteredCards();
            cards.sort((a, b) => {
                if (this.sortBy === 'revenue') return Number(b.dataset.revenue || 0) - Number(a.dataset.revenue || 0);
                if (this.sortBy === 'orders') return Number(b.dataset.orders || 0) - Number(a.dataset.orders || 0);
                if (this.sortBy === 'products') return Number(b.dataset.products || 0) - Number(a.dataset.products || 0);
                return (a.dataset.storeName || '').localeCompare(b.dataset.storeName || '');
            });
            return cards;
        },
        needsPagination() {
            return this.view === 'list' && this.tab !== 'closed';
        },
        pageCount() {
            if (!this.needsPagination()) return 1;
            return Math.max(1, Math.ceil(this.sortedCards().length / this.pageSize));
        },
        showingText() {
            const total = this.sortedCards().length;
            if (this.tab === 'closed') {
                const count = {{ $closedStores->count() }};
                return count ? `Showing ${count} closed ${count === 1 ? 'store' : 'stores'}` : 'Showing 0 stores';
            }
            if (!total) return 'Showing 0 stores';
            if (this.view === 'grid') {
                return `Showing ${total} ${total === 1 ? 'store' : 'stores'}`;
            }
            const start = (this.page - 1) * this.pageSize + 1;
            const end = Math.min(this.page * this.pageSize, total);
            return `Showing ${start}–${end} of ${total}`;
        },
        onPage(el) {
            const cards = this.sortedCards();
            const index = cards.indexOf(el);
            if (index < 0) return false;
            if (this.view === 'grid' || !this.needsPagination()) return true;
            const start = (this.page - 1) * this.pageSize;
            return index >= start && index < start + this.pageSize;
        },
        applySort() {
            const root = this.cardRoot();
            if (!root) return;
            this.sortedCards().forEach((card) => root.appendChild(card));
            if (this.page > this.pageCount()) this.page = this.pageCount();
        }
    }"
    x-init="if (Alpine.store('storesHub') === undefined) { Alpine.store('storesHub', { search: '' }); } $nextTick(() => applySort()); $watch('sortBy', () => { page = 1; applySort(); }); $watch('view', () => { page = 1; $nextTick(() => applySort()); }); $watch('typeFilter', () => page = 1); $watch('$store.storesHub.search', () => page = 1); $watch('pageSize', () => page = 1)"
    @click.outside="openMenu = null"
>
    <section aria-labelledby="overviewHeading">
        <div class="sd-intro">
            <h2 id="overviewHeading">Store overview</h2>
            <p>Manage every workspace, review performance, and keep setup on track.</p>
        </div>
        <div class="sd-overview">
            <div class="sd-overview-head">
                <div class="sd-eyebrow">Portfolio overview</div>
                <div class="sd-scope">Across all active stores</div>
            </div>
            <div class="sd-metrics">
                <div class="sd-metric">
                    <div class="sd-metric-icon" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5M9 20v-6h6v6"/></svg>
                    </div>
                    <div>
                        <div class="sd-metric-label">Stores</div>
                        <div class="sd-metric-value">{{ $stores->count() }}</div>
                        <div class="sd-metric-note">{{ $stores->count() }} active workspaces</div>
                    </div>
                </div>
                <div class="sd-metric">
                    <div class="sd-metric-icon blue" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="m4 7 8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4M12 11v10"/></svg>
                    </div>
                    <div>
                        <div class="sd-metric-label">Products</div>
                        <div class="sd-metric-value">{{ number_format($totalProducts) }}</div>
                        <div class="sd-metric-note">Across all catalogs</div>
                    </div>
                </div>
                <div class="sd-metric">
                    <div class="sd-metric-icon violet" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M20 13 13 20 4 11V4h7z"/><circle cx="8" cy="8" r="1.3" fill="currentColor" stroke="none"/></svg>
                    </div>
                    <div>
                        <div class="sd-metric-label">Brands</div>
                        <div class="sd-metric-value">{{ number_format($totalBrands) }}</div>
                        <div class="sd-metric-note">Unique brands</div>
                    </div>
                </div>
                <div class="sd-metric">
                    <div class="sd-metric-icon amber" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v6M12 17h.01"/></svg>
                    </div>
                    <div>
                        <div class="sd-metric-label">Need setup</div>
                        <div class="sd-metric-value">{{ $needsSetupCount }}</div>
                        <div class="sd-metric-note">
                            @if ($draftStoresCount > 0)
                                Finish store setup
                            @elseif ($needsSetupCount > 0)
                                {{ $needsSetupCount === 1 ? '1 store still needs setup' : $needsSetupCount.' stores still need setup' }}
                            @else
                                Your stores are ready
                            @endif
                            @if ($needsSetupCount > 0)
                                · <button type="button" class="sd-review" @click="openReview()">Review</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($setupReviewStores->isNotEmpty())
        <section
            class="sd-review-panel"
            x-ref="setupReview"
            x-show="reviewOpen"
            x-cloak
            aria-labelledby="setupReviewHeading"
        >
            <div class="sd-review-panel-head">
                <div>
                    <h2 id="setupReviewHeading">Remaining setup</h2>
                    <p class="sd-copy">These stores still have setup steps to finish before they are ready to sell.</p>
                </div>
                <button type="button" class="sd-review-close" @click="reviewOpen = false">Hide</button>
            </div>
            <div class="sd-review-stores">
                @foreach ($setupReviewStores as $review)
                    @php $reviewStore = $review['store']; @endphp
                    <article class="sd-review-store">
                        <div class="sd-review-store-head">
                            <div>
                                <p class="sd-review-store-name">{{ $reviewStore->name }}</p>
                                <p class="sd-copy">{{ $review['ready_count'] }} of {{ $review['setup_total'] }} operational steps are ready.</p>
                            </div>
                            @include('user_view.partials.store_switch_action', [
                                'store' => $reviewStore,
                                'label' => 'Continue setup →',
                                'class' => 'sd-action',
                                'isActive' => $activeStoreId === (int) $reviewStore->id,
                                'redirectTo' => 'dashboard',
                                'href' => route('dashboard'),
                            ])
                        </div>
                        <ul class="sd-review-steps">
                            @if ($review['needs_onboarding'])
                                <li class="is-pending">Finish store setup</li>
                            @endif
                            @forelse ($review['remaining'] as $step)
                                <li class="is-pending">
                                    <span>{{ $step['title'] }}</span>
                                    <span class="sd-review-step-detail">{{ $step['detail'] }}</span>
                                </li>
                            @empty
                                @unless ($review['needs_onboarding'])
                                    <li class="is-ready">Operational setup is complete</li>
                                @endunless
                            @endforelse
                        </ul>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-4" aria-labelledby="storesHeading">
        <div class="sd-stores-head">
            <div>
                <h2 id="storesHeading">Stores</h2>
                <p class="sd-copy">Choose a workspace or switch how stores are displayed.</p>
            </div>
            <div class="sd-controls">
                <label class="sr-only" for="stores-directory-type">Store type</label>
                <select id="stores-directory-type" class="sd-select" x-model="typeFilter">
                    <option value="all">All types</option>
                    <option value="physical">Physical</option>
                    <option value="digital">Digital</option>
                </select>
                <label class="sr-only" for="stores-directory-sort">Sort stores</label>
                <select id="stores-directory-sort" class="sd-select" x-model="sortBy">
                    <option value="name">Name</option>
                    <option value="revenue">Revenue</option>
                    <option value="orders">Orders</option>
                    <option value="products">Products</option>
                </select>
                <div class="sd-view" role="group" aria-label="Store view">
                    <button type="button" class="sd-view-btn" :class="view === 'grid' && 'is-active'" :aria-pressed="view === 'grid'" @click="setView('grid')">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                        Grid
                    </button>
                    <button type="button" class="sd-view-btn" :class="view === 'list' && 'is-active'" :aria-pressed="view === 'list'" @click="setView('list')">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg>
                        List
                    </button>
                </div>
            </div>
        </div>

        <div class="sd-tabs" role="tablist" aria-label="Store status">
            <button type="button" class="sd-tab" role="tab" :class="tab === 'all' && 'is-active'" :aria-selected="tab === 'all'" @click="setTab('all')">
                All stores <span class="sd-count">{{ $stores->count() }}</span>
            </button>
            <button type="button" class="sd-tab" role="tab" :class="tab === 'needs' && 'is-active'" :aria-selected="tab === 'needs'" @click="setTab('needs')">
                Needs setup <span class="sd-count">{{ $needsSetupCount }}</span>
            </button>
            <button type="button" class="sd-tab" role="tab" :class="tab === 'closed' && 'is-active'" :aria-selected="tab === 'closed'" @click="setTab('closed')">
                Closed <span class="sd-count">{{ $closedStores->count() }}</span>
            </button>
        </div>

        <div x-show="tab !== 'closed'" x-cloak>
            <div class="sd-grid" x-ref="storeGrid" x-show="view === 'grid'" x-cloak>
                @forelse ($stores as $store)
                    @include('user_view.partials.store_directory_card', ['layout' => 'grid'])
                @empty
                    <div class="sd-empty col-span-full">
                        <div class="sd-empty-icon" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5"/></svg>
                        </div>
                        <h3>No stores yet</h3>
                        @if ($canCreateStores ?? false)
                            <p>Create your first store to get started</p>
                            <button type="button" class="js-open-create-store-modal mt-4 inline-flex items-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-hover">Create store</button>
                        @else
                            <p>You can work in stores an owner has invited you to. You cannot create a new store unless they allow it.</p>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="sd-list" x-ref="storeList" x-show="view === 'list'" x-cloak>
                <div class="sd-list-row sd-list-head" aria-hidden="true">
                    <div>Store</div>
                    <div>Setup</div>
                    <div class="sd-numeric">Products</div>
                    <div class="sd-numeric">Orders · 7 days</div>
                    <div class="sd-numeric">Revenue · 7 days</div>
                    <div></div>
                </div>
                @foreach ($stores as $store)
                    @include('user_view.partials.store_directory_card', ['layout' => 'list'])
                @endforeach
            </div>

            <div
                class="sd-empty"
                x-show="tab !== 'closed' && {{ $stores->count() }} > 0 && sortedCards().length === 0"
                x-cloak
            >
                <div class="sd-empty-icon" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                </div>
                <h3>No stores found</h3>
                <p>Try a different search or clear the selected filters.</p>
                <button type="button" class="mt-4 rounded-lg border border-border bg-white px-3 py-1.5 text-sm font-semibold text-ink-secondary" @click="$store.storesHub.search = ''; typeFilter = 'all'; tab = 'all'">Clear filters</button>
            </div>
        </div>

        @if ($closedStores->isNotEmpty())
            <div id="closed-stores" x-show="tab === 'closed'" x-cloak>
                <div class="sd-intro mb-2">
                    <h2>Closed stores</h2>
                    <p>Stores you've closed are kept here until you restore or permanently delete them.</p>
                </div>
                <div class="sd-grid" x-show="view === 'grid'">
                    @foreach ($closedStores as $closedStore)
                        @php
                            [$avatarBg, $avatarColor] = $avatarPalettes[$closedStore->id % count($avatarPalettes)];
                            $closedOn = optional($closedStore->deleted_at)->format('M j, Y');
                            $closedStorePayload = [
                                'id' => $closedStore->id,
                                'name' => $closedStore->name,
                                'delete_url' => route('store.permanent-destroy', ['storeId' => $closedStore->id]),
                            ];
                        @endphp
                        <article class="sd-card" style="--sd-avatar-bg: {{ $avatarBg }}; --sd-avatar-color: {{ $avatarColor }}">
                            <div class="sd-card-top">
                                <div class="sd-avatar">{{ $storeInitials($closedStore->name) }}</div>
                                <div class="sd-title">
                                    <div class="sd-name-line"><span class="sd-name">{{ $closedStore->name }}</span></div>
                                    <div class="sd-meta">Closed on {{ $closedOn ?: 'Unknown date' }}</div>
                                </div>
                            </div>
                            <div class="sd-status-line">
                                <span class="sd-status is-closed"><span class="sd-dot"></span>Closed</span>
                            </div>
                            <div class="sd-card-actions">
                                <form method="POST" action="{{ route('store.restore', ['storeId' => $closedStore->id]) }}">
                                    @csrf
                                    <button type="submit" class="sd-action">Restore Store</button>
                                </form>
                                <button
                                    type="button"
                                    class="js-open-permanent-delete-modal sd-secondary"
                                    data-store='@json($closedStorePayload)'
                                >
                                    Delete Permanently
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="sd-list" x-show="view === 'list'" x-cloak>
                    <div class="sd-list-row sd-list-head" aria-hidden="true">
                        <div>Store</div>
                        <div>Setup</div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    @foreach ($closedStores as $closedStore)
                        @php
                            [$avatarBg, $avatarColor] = $avatarPalettes[$closedStore->id % count($avatarPalettes)];
                            $closedOn = optional($closedStore->deleted_at)->format('M j, Y');
                            $closedStorePayload = [
                                'id' => $closedStore->id,
                                'name' => $closedStore->name,
                                'delete_url' => route('store.permanent-destroy', ['storeId' => $closedStore->id]),
                            ];
                        @endphp
                        <article class="sd-list-row" style="--sd-avatar-bg: {{ $avatarBg }}; --sd-avatar-color: {{ $avatarColor }}">
                            <div class="sd-list-store">
                                <div class="sd-avatar">{{ $storeInitials($closedStore->name) }}</div>
                                <div class="sd-title">
                                    <div class="sd-name-line"><span class="sd-name">{{ $closedStore->name }}</span></div>
                                    <div class="sd-meta">Closed on {{ $closedOn ?: 'Unknown date' }}</div>
                                </div>
                            </div>
                            <div><span class="sd-status is-closed"><span class="sd-dot"></span>Closed</span></div>
                            <div></div>
                            <div></div>
                            <div></div>
                            <div class="sd-list-action">
                                <form method="POST" action="{{ route('store.restore', ['storeId' => $closedStore->id]) }}">
                                    @csrf
                                    <button type="submit" class="sd-action">Restore Store</button>
                                </form>
                                <button type="button" class="js-open-permanent-delete-modal sd-secondary" data-store='@json($closedStorePayload)'>Delete Permanently</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <div class="sd-empty" x-show="tab === 'closed'" x-cloak>
                <div class="sd-empty-icon" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5"/></svg>
                </div>
                <h3>No closed stores</h3>
                <p>Stores you close will remain recoverable from this view.</p>
            </div>
        @endif

        <div class="sd-footer">
            <div>Revenue is shown in each store's currency. Shown in current store currency.</div>
            <div class="sd-pages">
                <span x-text="showingText()"></span>
                <span class="inline-flex items-center gap-2" x-show="needsPagination()" x-cloak>
                    <span>Rows</span>
                    <label class="sr-only" for="stores-directory-page-size">Rows per page</label>
                    <select id="stores-directory-page-size" class="sd-select" style="min-width: 4.5rem; height: 2rem;" x-model.number="pageSize">
                        <option value="4">4</option>
                        <option value="8">8</option>
                        <option value="12">12</option>
                    </select>
                    <button type="button" class="sd-page-btn" :disabled="page <= 1" @click="page--" aria-label="Previous page">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <span class="sd-page-btn" x-text="page" aria-label="Current page"></span>
                    <button type="button" class="sd-page-btn" :disabled="page >= pageCount()" @click="page++" aria-label="Next page">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    </button>
                </span>
            </div>
        </div>
    </section>

    <section class="sd-activity" aria-labelledby="activityHeading">
        <div class="sd-activity-head">
            <div>
                <h2 id="activityHeading">Recent activity</h2>
                <p class="sd-copy">Latest updates across your stores.</p>
            </div>
            @if ($activeStoreId > 0 && $stores->contains(fn ($s) => (int) $s->id === $activeStoreId))
                <a href="{{ route('orders') }}" class="inline-flex h-8 items-center rounded-lg border border-border bg-white px-3 text-sm font-semibold text-ink-secondary hover:bg-surface-muted">View orders</a>
            @elseif ($stores->isNotEmpty())
                <button
                    type="button"
                    class="inline-flex h-8 items-center rounded-lg border border-border bg-white px-3 text-sm font-semibold text-ink-secondary hover:bg-surface-muted"
                    data-store-switch-request="1"
                    data-store-id="{{ $stores->first()->id }}"
                    data-store-name="{{ $stores->first()->name }}"
                    data-redirect-to="orders"
                >View orders</button>
            @endif
        </div>
        @if ($recentActivity->isNotEmpty())
            <div class="sd-activity-grid">
                @foreach ($recentActivity as $event)
                    @php
                        $title = filled($event->title)
                            ? $event->title
                            : \App\Support\OrderLifecycle::eventTypeLabel($event->event_type);
                        $storeName = $event->store?->name ?? 'Store';
                        $description = filled($event->description) ? $event->description : null;
                        $activityStore = $event->store;
                        $activityOrderId = $event->order?->id;
                        $isActivityCurrent = $activityStore && $activeStoreId === (int) $activityStore->id;
                        $activityHref = $activityOrderId
                            ? route('orderViewDetails', $activityOrderId)
                            : route('orders');
                        $activityRedirect = $activityOrderId ? 'order' : 'orders';
                    @endphp
                    @if ($activityStore && $isActivityCurrent)
                        <a href="{{ $activityHref }}" class="sd-activity-item">
                            @include('user_view.partials.store_activity_icon', ['event' => $event])
                            <span>
                                <span class="sd-activity-title">{{ $title }}</span>
                                <span class="sd-activity-detail">
                                    {{ $storeName }}
                                    @if ($description)
                                        · {{ \Illuminate\Support\Str::limit($description, 72) }}
                                    @endif
                                </span>
                                <span class="sd-activity-time">{{ optional($event->created_at)->diffForHumans() }}</span>
                            </span>
                        </a>
                    @elseif ($activityStore)
                        <button
                            type="button"
                            class="sd-activity-item"
                            data-store-switch-request="1"
                            data-store-id="{{ $activityStore->id }}"
                            data-store-name="{{ $activityStore->name }}"
                            data-redirect-to="{{ $activityRedirect }}"
                            @if ($activityOrderId)
                                data-order-id="{{ $activityOrderId }}"
                            @endif
                        >
                            @include('user_view.partials.store_activity_icon', ['event' => $event])
                            <span>
                                <span class="sd-activity-title">{{ $title }}</span>
                                <span class="sd-activity-detail">
                                    {{ $storeName }}
                                    @if ($description)
                                        · {{ \Illuminate\Support\Str::limit($description, 72) }}
                                    @endif
                                </span>
                                <span class="sd-activity-time">{{ optional($event->created_at)->diffForHumans() }}</span>
                            </span>
                        </button>
                    @else
                        <div class="sd-activity-item">
                            @include('user_view.partials.store_activity_icon', ['event' => $event])
                            <span>
                                <span class="sd-activity-title">{{ $title }}</span>
                                <span class="sd-activity-detail">
                                    {{ $storeName }}
                                    @if ($description)
                                        · {{ \Illuminate\Support\Str::limit($description, 72) }}
                                    @endif
                                </span>
                                <span class="sd-activity-time">{{ optional($event->created_at)->diffForHumans() }}</span>
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <div class="rounded-lg border border-dashed border-border bg-surface-muted px-3 py-8 text-center">
                <p class="text-sm font-medium text-ink">No recent activity yet</p>
                <p class="mt-1 text-xs text-ink-muted">Order updates across your stores will appear here.</p>
            </div>
        @endif
    </section>
</div>

<script type="application/json" id="merchant-store-switch-urls">{!! json_encode([
    'dashboard' => route('dashboard'),
    'orders' => route('orders'),
    'products' => route('products'),
    'locations' => route('settings.locations.index'),
    'taxes' => route('settings.taxes.index'),
    'delivery' => route('shippingAutomation'),
    'orderBase' => url('/orders').'/',
]) !!}</script>
<form id="hub-store-switch-form" method="POST" action="{{ route('current-store.update') }}" class="hidden" data-turbo="false">
    @csrf
    <input type="hidden" name="store_id" value="">
    <input type="hidden" name="redirect_to" value="">
    <input type="hidden" name="order_id" value="">
</form>

@if ($canCreateStores ?? false)
    @include('user_view.partials.store_create_modal')
@endif
@include('user_view.partials.store_edit_modal')
@include('user_view.partials.store_permanent_delete_modal')
@endsection
