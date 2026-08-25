@extends('layouts.user.user-sidebar')

@section('title', 'Products — '.config('app.name'))
@section('sidebar_brand_title', config('app.name'))
@section('sidebar_brand_subtitle', optional($currentStore)->name ?? 'Catalog')

@php
    use App\Support\ProductCustomFieldHelper;
    use App\Support\ProductDetailPresenter;
    use App\Support\ProductEditPayload;
    use App\Support\ProductInventoryState;
    $baseFilters = [
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'product_type' => $filters['product_type'] ?? '',
        'status' => $filters['status'] ?? '',
        'stock' => $filters['stock'] ?? '',
        'sort' => $filters['sort'] ?? 'latest',
        'brand' => $filters['brand'] ?? '',
        'tag' => $filters['tag'] ?? '',
        'attribute_term' => $filters['attribute_term'] ?? '',
        'cf_key' => $filters['cf_key'] ?? '',
        'cf_value' => $filters['cf_value'] ?? '',
        'shipping_weight' => $filters['shipping_weight'] ?? '',
        'view' => ($filters['view'] ?? 'active') === 'archived' ? 'deleted' : ($filters['view'] ?? 'active'),
        'per_page' => $filters['per_page'] ?? 25,
    ];

    $brandCount = $brandCount ?? 0;
    $activeBrandFilter = $activeBrandFilter ?? null;
    $activeTagFilter = $activeTagFilter ?? null;
    $activeTaxonomyCategoryFilter = $activeTaxonomyCategoryFilter ?? null;
    $activeAttributeTermFilter = $activeAttributeTermFilter ?? null;
    $catalogView = $catalogView ?? (($filters['view'] ?? 'active') === 'deleted' || ($filters['view'] ?? '') === 'archived' ? 'deleted' : 'active');
    $isDeletedView = $catalogView === 'deleted';
    $deletedCount = (int) ($deletedCount ?? $archivedCount ?? 0);
    $canManageBrands = in_array($currentUserStoreRole ?? '', ['owner', 'manager'], true);
    $canManageTags = $canManageBrands;
    $canManageCategories = $canManageBrands;

    $catalogToolsReopen = $errors->any() && (
        old('_open_brand_add_modal') == '1' || old('_open_brand_add_modal') === true ||
        old('_editing_brand_id') ||
        old('_open_tag_add_modal') == '1' || old('_open_tag_add_modal') === true ||
        old('_editing_tag_id') ||
        old('_open_category_add_modal') == '1' || old('_open_category_add_modal') === true ||
        old('_editing_category_id')
    );
    $catalogToolsDefaultTab = 'categories';
    if ($errors->any()) {
        if (old('_editing_brand_id')) {
            $catalogToolsDefaultTab = 'brands';
        } elseif (old('_editing_tag_id')) {
            $catalogToolsDefaultTab = 'tags';
        } elseif (old('_editing_category_id')) {
            $catalogToolsDefaultTab = 'categories';
        } elseif (old('_open_brand_add_modal') == '1' || old('_open_brand_add_modal') === true) {
            $catalogToolsDefaultTab = 'brands';
        } elseif (old('_open_tag_add_modal') == '1' || old('_open_tag_add_modal') === true) {
            $catalogToolsDefaultTab = 'tags';
        } elseif (old('_open_category_add_modal') == '1' || old('_open_category_add_modal') === true) {
            $catalogToolsDefaultTab = 'categories';
        }
    }
    $openCatalogToolsShell = $catalogToolsReopen || request()->boolean('openCatalogTools');

    $productListDetailKeys = $productListDetailKeys ?? [];
    $cfKeyFilter = trim((string) ($filters['cf_key'] ?? ''));
    $cfKeyChipLabel = $cfKeyFilter !== '' ? ProductDetailPresenter::humanizeKey($cfKeyFilter) : '';

    $activeSort = ($filters['sort'] ?? 'latest') !== '' ? ($filters['sort'] ?? 'latest') : 'latest';
    $panelFilterCount = collect([
        $filters['q'] ?? '',
        $filters['category'] ?? '',
        $filters['product_type'] ?? '',
        $filters['brand'] ?? '',
        $filters['tag'] ?? '',
        $filters['attribute_term'] ?? '',
        $filters['status'] ?? '',
        $filters['stock'] ?? '',
        $filters['shipping_weight'] ?? '',
        $activeSort !== 'latest' ? $activeSort : '',
    ])->filter(fn ($v) => trim((string) $v) !== '')->count();
    $filtersPanelOpen = $panelFilterCount > 0;
    $isGenuinelyEmptyCatalog = ! $isDeletedView && $panelFilterCount === 0 && (int) $products->total() === 0;
    $isFilteredEmptyCatalog = ! $isDeletedView && $panelFilterCount > 0 && (int) $products->total() === 0;
    $catalogAttributeTermCount = ($catalogAttributes ?? collect())->sum(fn ($attribute) => $attribute->terms->count());
    $sortOptions = [
        'latest' => 'Latest',
        'name' => 'Name',
        'price_high' => 'Price high → low',
        'price_low' => 'Price low → high',
        'stock_high' => 'Stock high → low',
        'stock_low' => 'Stock low → high',
    ];

    $paginationCurrent = method_exists($products, 'currentPage') ? $products->currentPage() : 1;
    $paginationLast = method_exists($products, 'lastPage') ? $products->lastPage() : 1;
    $paginationWindow = 2;
    $paginationStart = max(1, $paginationCurrent - $paginationWindow);
    $paginationEnd = min($paginationLast, $paginationCurrent + $paginationWindow);
@endphp

@section('topbar')
    <x-ui.merchant-topbar
        title="Products"
        :lead="$isDeletedView
            ? 'Review deleted products for '.($selectedStore->name ?? 'this store')
            : 'Manage catalog and inventory for '.($selectedStore->name ?? 'this store')"
    >
        <x-slot:actions>
            @if ($canManageBrands)
                <a href="{{ route('products.import.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">
                    <span>Import products</span>
                </a>
            @endif
            <a href="{{ route('products.create') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-md bg-brand px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
                </svg>
                <span>Add product</span>
            </a>
            @if ($canManageBrands || $canManageTags || $canManageCategories)
                <details id="products-catalog-more-menu" class="group relative hidden sm:block" data-products-more-actions>
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-2 text-sm font-semibold text-ink-secondary hover:bg-surface-muted [&::-webkit-details-marker]:hidden" aria-label="More catalog actions">
                        <span>More</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="text-ink-muted transition group-open:rotate-180" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>
                    <div class="absolute right-0 z-40 mt-1 w-52 overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg">
                        @if ($canManageBrands)
                            <a href="{{ route('products.import.history') }}" class="block px-4 py-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted hover:text-ink">Import history</a>
                            <a href="{{ route('catalog.attributes.index') }}" class="block px-4 py-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted hover:text-ink">Manage specifications</a>
                        @endif
                        @if ($canManageBrands || $canManageTags || $canManageCategories)
                            <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-ink-secondary hover:bg-surface-muted hover:text-ink">
                                Catalog tools
                            </button>
                        @endif
                    </div>
                </details>
            @endif
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @include('user_view.partials.flash_success')

    @if ($errors->has('brand'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
            <p class="font-semibold text-amber-900">Cannot remove this brand</p>
            <p class="mt-1 text-amber-900/90">{{ $errors->first('brand') }}</p>
        </div>
    @endif

    @if ($errors->has('category'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm" role="alert">
            <p class="font-semibold text-amber-900">Cannot remove this category</p>
            <p class="mt-1 text-amber-900/90">{{ $errors->first('category') }}</p>
        </div>
    @endif

    {{-- Unified page intro: heading + view toggle + stats --}}
    <section class="merchant-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-border px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-xl font-semibold tracking-tight text-ink">
                        {{ $isDeletedView ? 'Deleted products' : 'Product catalog' }}
                    </h2>
                    @if ($selectedStore)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#CDE5DB] bg-[#F4FBF8] px-2.5 py-1 text-xs font-semibold text-[#0A4335]">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand" aria-hidden="true"></span>
                            {{ $selectedStore->name }}
                        </span>
                    @endif
                </div>
                <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-ink-secondary">
                    @if ($isDeletedView)
                        Deleted products stay here until you undo delete or permanently remove them.
                    @else
                        Browse, filter, and update inventory for this store. Select products to make changes in bulk.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:shrink-0">
                <div class="inline-flex rounded-lg border border-border bg-surface-muted/60 p-1" role="tablist" aria-label="Catalog view">
                    <a
                        href="{{ route('products', array_filter(array_merge($baseFilters, ['view' => null]))) }}"
                        class="inline-flex items-center rounded-md px-3.5 py-2 text-sm font-semibold transition {{ ! $isDeletedView ? 'bg-brand text-white shadow-sm' : 'text-ink-secondary hover:bg-surface hover:text-ink' }}"
                        @if (! $isDeletedView) aria-current="page" @endif
                    >
                        Products
                    </a>
                    <a
                        href="{{ route('products', array_filter(array_merge($baseFilters, ['view' => 'deleted']))) }}"
                        class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-semibold transition {{ $isDeletedView ? 'bg-brand text-white shadow-sm' : 'text-ink-secondary hover:bg-surface hover:text-ink' }}"
                        @if ($isDeletedView) aria-current="page" @endif
                    >
                        Deleted
                        @if ($deletedCount > 0)
                            <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-[10px] font-bold tabular-nums {{ $isDeletedView ? 'bg-white/20 text-white' : 'bg-surface text-ink-secondary' }}">{{ $deletedCount }}</span>
                        @endif
                    </a>
                </div>

                @if ($canManageBrands || $canManageTags || $canManageCategories)
                    <details class="group relative sm:hidden" data-products-more-actions-mobile>
                        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold text-ink-secondary [&::-webkit-details-marker]:hidden">
                            More
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="text-ink-muted transition group-open:rotate-180" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </summary>
                        <div class="absolute right-0 z-40 mt-1 w-52 overflow-hidden rounded-xl border border-border bg-surface py-1 shadow-lg">
                            @if ($canManageBrands)
                                <a href="{{ route('products.import.create') }}" class="block px-4 py-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted">Import products</a>
                                <a href="{{ route('products.import.history') }}" class="block px-4 py-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted">Import history</a>
                                <a href="{{ route('catalog.attributes.index') }}" class="block px-4 py-2.5 text-sm font-medium text-ink-secondary hover:bg-surface-muted">Manage specifications</a>
                            @endif
                            <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-ink-secondary hover:bg-surface-muted">Catalog tools</button>
                        </div>
                    </details>
                @endif

                @if ($canManageBrands)
                    <a href="{{ route('products.import.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-ink-secondary sm:hidden">
                        Import
                    </a>
                @endif

                <a href="{{ route('products.create') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand px-3.5 py-2 text-sm font-bold text-white sm:hidden">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" /></svg>
                    Add
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 divide-y divide-border sm:grid-cols-4 sm:divide-x sm:divide-y-0" data-products-stats>
            <div class="px-4 py-4 sm:px-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-ink-muted">In view</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums" data-stat-in-view>{{ number_format($stats['total_products']) }}</p>
                <p class="mt-0.5 text-xs text-ink-muted">{{ $activeBrandFilter || $activeTagFilter || $activeTaxonomyCategoryFilter || $activeAttributeTermFilter || ($filters['product_type'] ?? '') !== '' ? 'Matching filters' : 'Products in this store' }}</p>
            </div>
            <div class="px-4 py-4 sm:px-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-ink-muted">Out of stock</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums {{ (int) $stats['out_of_stock'] > 0 ? 'text-[#B91C1C]' : 'text-ink' }}" data-stat-out-of-stock data-stat-value="{{ (int) $stats['out_of_stock'] }}">{{ number_format($stats['out_of_stock']) }}</p>
                <p class="mt-0.5 text-xs font-medium {{ (int) $stats['out_of_stock'] > 0 ? 'text-[#B91C1C]' : 'text-ink-muted' }}" data-stat-out-of-stock-label>{{ (int) $stats['out_of_stock'] > 0 ? 'Needs attention' : 'All stocked' }}</p>
            </div>
            <div class="px-4 py-4 sm:px-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-ink-muted">Low stock</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums {{ (int) $stats['low_stock'] > 0 ? 'text-[#C2410C]' : 'text-ink' }}" data-stat-low-stock data-stat-value="{{ (int) $stats['low_stock'] }}">{{ number_format($stats['low_stock']) }}</p>
                <p class="mt-0.5 text-xs font-medium {{ (int) $stats['low_stock'] > 0 ? 'text-[#C2410C]' : 'text-ink-muted' }}" data-stat-low-stock-label>{{ (int) $stats['low_stock'] > 0 ? 'Reorder soon' : 'Looking good' }}</p>
            </div>
            <div class="px-4 py-4 sm:px-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-ink-muted">Brands</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-ink tabular-nums">{{ number_format($brandCount) }}</p>
                @if ($canManageBrands || $canManageTags || $canManageCategories)
                    <button type="button" data-open-catalog-tools data-catalog-tools-tab="brands" class="mt-1 text-xs font-semibold text-brand hover:text-brand-hover">Manage brands</button>
                @else
                    <p class="mt-0.5 text-xs text-ink-muted">Catalog labels</p>
                @endif
            </div>
        </div>
    </section>

    @if ($activeBrandFilter || $activeTagFilter || $activeTaxonomyCategoryFilter || $activeAttributeTermFilter || (($filters['cf_key'] ?? '') !== '' && ($filters['cf_value'] ?? '') !== ''))
        <div class="flex flex-wrap gap-2">
            @if ($activeBrandFilter)
                <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#BFDBFE] bg-[#F0F9FF] px-3 py-2 text-sm text-[#0C4A6E]">
                    <span>Brand <span class="font-semibold">{{ $activeBrandFilter->name }}</span></span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['brand' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
            @if ($activeTagFilter)
                <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#E9D5FF] bg-[#FAF5FF] px-3 py-2 text-sm text-[#581C87]">
                    <span>Tag <span class="font-semibold">{{ $activeTagFilter->name }}</span></span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['tag' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
            @if ($activeTaxonomyCategoryFilter)
                <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#CCFBF1] bg-[#F0FDFA] px-3 py-2 text-sm text-[#115E59]">
                    <span>Category <span class="font-semibold">{{ $activeTaxonomyCategoryFilter->name }}</span></span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['category' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
            @if ($activeAttributeTermFilter)
                <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-sm text-[#1E3A8A]">
                    <span>Spec <span class="font-semibold">{{ $activeAttributeTermFilter->name }}</span></span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['attribute_term' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
            @if (($filters['cf_key'] ?? '') !== '' && ($filters['cf_value'] ?? '') !== '')
                <div class="inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                    <span><span class="font-semibold text-[#0F172A]">{{ $cfKeyChipLabel }}</span> contains <span class="font-semibold">{{ $filters['cf_value'] }}</span></span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['cf_key' => null, 'cf_value' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
        </div>
    @endif

    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="border-b border-[#E2E8F0] px-4 py-3 lg:px-5 lg:py-3.5">
            <details id="products-filters-panel" class="group" {{ $filtersPanelOpen ? 'open' : '' }}>
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#CBD5E1] hover:bg-[#F8FAFC]">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="text-[#64748B]" aria-hidden="true"><path d="M2 3H14M4 8H12M6 13H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <span>Search &amp; filters</span>
                        @if ($panelFilterCount > 0)
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1.5 text-[10px] font-bold text-white">{{ $panelFilterCount }}</span>
                        @endif
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="text-[#94A3B8] transition group-open:rotate-180" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="ml-auto flex flex-wrap items-center gap-1.5" onclick="event.preventDefault()">
                        <a href="{{ route('products', array_filter(array_merge($baseFilters, ['export' => 'csv']))) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] transition hover:bg-[#F8FAFC]" title="Export CSV" onclick="event.stopPropagation()">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M2 14H14V12H2V14ZM14 6H11V2H5V6H2L8 12L14 6Z" fill="currentColor" />
                            </svg>
                        </a>
                        <a href="{{ route('products', $isDeletedView ? ['view' => 'deleted'] : []) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E8F0] bg-white px-3 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAFC]" title="Clear all filters and search" onclick="event.stopPropagation()">Reset</a>
                    </span>
                </summary>

                <form id="products-filters-form" method="GET" action="{{ route('products') }}" class="mt-3 space-y-3">
                    <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                    <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                    <input type="hidden" name="view" value="{{ $catalogView }}">
                    <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 25 }}">

                    @php
                        $catalogCategoryList = collect($catalogTaxonomyCategories ?? []);
                        $catalogBrandList = collect($catalogBrands ?? []);
                        $catalogTagList = collect($catalogTags ?? []);
                        $activeCategoryLabel = $activeTaxonomyCategoryFilter
                            ? $activeTaxonomyCategoryFilter->name.' ('.(int) ($activeTaxonomyCategoryFilter->products_count ?? 0).')'
                            : 'Any category';
                        $activeBrandLabel = $activeBrandFilter?->name ?? 'Any brand';
                        $activeTagLabel = $activeTagFilter?->name ?? 'Any tag';
                        $activeTypeLabel = ($filters['product_type'] ?? '') !== ''
                            ? (($productTypeFilterOptions ?? collect())[$filters['product_type']] ?? $filters['product_type'])
                            : 'Any type';
                        $activeSpecLabel = 'Any specification';
                        if (! empty($filters['attribute_term'])) {
                            foreach (($catalogAttributes ?? collect()) as $attribute) {
                                $matchedTerm = $attribute->terms->firstWhere('id', (int) $filters['attribute_term']);
                                if ($matchedTerm) {
                                    $activeSpecLabel = $attribute->name.' · '.$matchedTerm->name;
                                    break;
                                }
                            }
                        }
                        $pickerTriggerBase = 'inline-flex w-full items-center justify-between gap-2 rounded-lg border bg-white px-3 py-2 text-left text-sm font-semibold transition hover:border-[#CBD5E1]';
                        $pickerTriggerIdle = $pickerTriggerBase.' border-[#E2E8F0] text-[#334155]';
                        $pickerTriggerActive = $pickerTriggerBase.' border-brand bg-[#E6F4EF] text-[#0A4335]';
                    @endphp

                    {{-- Search --}}
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[#94A3B8]" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M11.5 11.5L15 15M7 12.5C3.96243 12.5 1.5 10.0376 1.5 7C1.5 3.96243 3.96243 1.5 7 1.5C10.0376 1.5 12.5 3.96243 12.5 7C12.5 10.0376 10.0376 12.5 7 12.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </span>
                            <input id="products-filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name, SKU, or details…" class="h-10 w-full rounded-lg border border-[#E2E8F0] bg-white py-2 pl-9 pr-3 text-sm text-[#0F172A] placeholder:text-[#94A3B8] focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                        </div>
                        <button type="submit" class="h-10 shrink-0 rounded-lg bg-brand px-4 text-sm font-bold text-white hover:bg-brand-hover">Search</button>
                    </div>

                    {{-- Quick chips: sort + status (few options, stay as chips) --}}
                    <div class="flex flex-col gap-2.5 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Sort</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($sortOptions as $sortValue => $sortLabel)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="sort" value="{{ $sortValue }}" class="peer sr-only js-filter-auto-submit" @checked($activeSort === $sortValue)>
                                        <span class="inline-flex rounded-full border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-semibold text-[#64748B] transition hover:border-[#CBD5E1] peer-checked:border-[#0052CC] peer-checked:bg-[#EEF4FF] peer-checked:text-[#0052CC]">{{ $sortLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @if (! $isDeletedView)
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-[#E2E8F0] pt-2.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Show</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status" value="" class="peer sr-only js-filter-auto-submit" @checked(($filters['status'] ?? '') === '')>
                                        <span class="inline-flex rounded-full border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-semibold text-[#64748B] transition hover:border-[#CBD5E1] peer-checked:border-[#0052CC] peer-checked:bg-[#EEF4FF] peer-checked:text-[#0052CC]">All</span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status" value="published" class="peer sr-only js-filter-auto-submit" @checked(($filters['status'] ?? '') === 'published')>
                                        <span class="inline-flex rounded-full border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-semibold text-[#64748B] transition hover:border-[#CBD5E1] peer-checked:border-[#0052CC] peer-checked:bg-[#EEF4FF] peer-checked:text-[#0052CC]">Published</span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="status" value="draft" class="peer sr-only js-filter-auto-submit" @checked(($filters['status'] ?? '') === 'draft')>
                                        <span class="inline-flex rounded-full border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-semibold text-[#64748B] transition hover:border-[#CBD5E1] peer-checked:border-[#0052CC] peer-checked:bg-[#EEF4FF] peer-checked:text-[#0052CC]">Drafts</span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="stock" value="low" class="peer sr-only js-filter-auto-submit" @checked(($filters['stock'] ?? '') === 'low')>
                                        <span class="inline-flex items-center gap-1 rounded-full border border-[#E2E8F0] bg-white px-2.5 py-1 text-xs font-semibold text-[#64748B] transition hover:border-[#CBD5E1] peer-checked:border-[#F97316] peer-checked:bg-orange-50 peer-checked:text-orange-700">
                                            Low stock
                                        <span class="rounded-full bg-[#F97316] px-1.5 text-[10px] font-bold text-white tabular-nums" data-filter-low-stock-count>{{ $stats['low_stock'] }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="status" value="">
                            <input type="hidden" name="stock" value="">
                        @endif
                    </div>

                    {{-- Compact searchable pickers (no chip walls) --}}
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        {{-- Category --}}
                        <div class="relative" data-filter-picker id="filter-category">
                            <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}" data-picker-value>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#0F766E]">Category</p>
                            <button type="button" data-picker-trigger class="{{ ($filters['category'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                <span class="min-w-0 truncate" data-picker-label>{{ $activeCategoryLabel }}</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                <div class="border-b border-[#F1F5F9] p-2">
                                    <input type="search" data-picker-search placeholder="Type to find…" class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" autocomplete="off">
                                </div>
                                <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                    <li>
                                        <button type="button" data-picker-option data-value="" data-label="Any category" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['category'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any category</button>
                                    </li>
                                    @foreach ($catalogCategoryList as $taxCat)
                                        @php
                                            $categoryProductCount = (int) ($taxCat->products_count ?? 0);
                                            $categoryOptionLabel = $taxCat->name.' ('.$categoryProductCount.')';
                                        @endphp
                                        <li>
                                            <button type="button" data-picker-option data-value="{{ $taxCat->id }}" data-label="{{ $categoryOptionLabel }}" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ (string) ($filters['category'] ?? '') === (string) $taxCat->id ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">
                                                <span class="min-w-0 truncate">{{ $taxCat->name }}</span>
                                                <span class="shrink-0 tabular-nums text-xs font-semibold text-[#94A3B8]">{{ $categoryProductCount }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        {{-- Brand --}}
                        <div class="relative" data-filter-picker id="filter-brand">
                            <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}" data-picker-value>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Brand</p>
                            <button type="button" data-picker-trigger class="{{ ($filters['brand'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                <span class="min-w-0 truncate" data-picker-label>{{ $activeBrandLabel }}</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                <div class="border-b border-[#F1F5F9] p-2">
                                    <input type="search" data-picker-search placeholder="Type to find…" class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" autocomplete="off">
                                </div>
                                <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                    <li>
                                        <button type="button" data-picker-option data-value="" data-label="Any brand" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['brand'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any brand</button>
                                    </li>
                                    @foreach ($catalogBrandList as $b)
                                        <li>
                                            <button type="button" data-picker-option data-value="{{ $b->id }}" data-label="{{ $b->name }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ (string) ($filters['brand'] ?? '') === (string) $b->id ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $b->name }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        {{-- Tag --}}
                        @if ($catalogTagList->isNotEmpty())
                            <div class="relative" data-filter-picker id="filter-tag">
                                <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}" data-picker-value>
                                <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Tag</p>
                                <button type="button" data-picker-trigger class="{{ ($filters['tag'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="min-w-0 truncate" data-picker-label>{{ $activeTagLabel }}</span>
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                    <div class="border-b border-[#F1F5F9] p-2">
                                        <input type="search" data-picker-search placeholder="Type to find…" class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" autocomplete="off">
                                    </div>
                                    <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                        <li>
                                            <button type="button" data-picker-option data-value="" data-label="Any tag" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['tag'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any tag</button>
                                        </li>
                                        @foreach ($catalogTagList as $tagOption)
                                            <li>
                                                <button type="button" data-picker-option data-value="{{ $tagOption->id }}" data-label="{{ $tagOption->name }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ (string) ($filters['tag'] ?? '') === (string) $tagOption->id ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $tagOption->name }}</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="tag" value="">
                        @endif

                        {{-- Product type --}}
                        <div class="relative" data-filter-picker id="filter-product-type">
                            <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}" data-picker-value>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Product type</p>
                            <button type="button" data-picker-trigger class="{{ ($filters['product_type'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                <span class="min-w-0 truncate" data-picker-label>{{ $activeTypeLabel }}</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                <div class="border-b border-[#F1F5F9] p-2">
                                    <input type="search" data-picker-search placeholder="Type to find…" class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" autocomplete="off">
                                </div>
                                <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                    <li>
                                        <button type="button" data-picker-option data-value="" data-label="Any type" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['product_type'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any type</button>
                                    </li>
                                    @foreach ($productTypeFilterOptions ?? [] as $typeValue => $typeLabel)
                                        <li>
                                            <button type="button" data-picker-option data-value="{{ $typeValue }}" data-label="{{ $typeLabel }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['product_type'] ?? '') === $typeValue ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $typeLabel }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        @if ($catalogAttributeTermCount > 0)
                            <div class="relative sm:col-span-2 xl:col-span-2" data-filter-picker id="filter-attribute-term">
                                <input type="hidden" name="attribute_term" value="{{ $filters['attribute_term'] ?? '' }}" data-picker-value>
                                <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Specification</p>
                                <button type="button" data-picker-trigger class="{{ ($filters['attribute_term'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="min-w-0 truncate" data-picker-label>{{ $activeSpecLabel !== '' ? $activeSpecLabel : 'Any specification' }}</span>
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                    <div class="border-b border-[#F1F5F9] p-2">
                                        <input type="search" data-picker-search placeholder="Type to find…" class="h-9 w-full rounded-lg border border-[#E2E8F0] px-3 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" autocomplete="off">
                                    </div>
                                    <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                        <li>
                                            <button type="button" data-picker-option data-value="" data-label="Any specification" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['attribute_term'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any specification</button>
                                        </li>
                                        @foreach (($catalogAttributes ?? collect()) as $attribute)
                                            @foreach ($attribute->terms as $term)
                                                @php $specLabel = $attribute->name.' · '.$term->name; @endphp
                                                <li>
                                                    <button type="button" data-picker-option data-value="{{ $term->id }}" data-label="{{ $specLabel }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ (string) ($filters['attribute_term'] ?? '') === (string) $term->id ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $specLabel }}</button>
                                                </li>
                                            @endforeach
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="attribute_term" value="">
                        @endif

                        @php
                            $shippingWeightUnit = $shippingWeightUnit ?? 'LB';
                            $shippingWeightFallback = $shippingWeightFallback ?? null;
                            $activeShippingWeightLabel = match ($filters['shipping_weight'] ?? '') {
                                'has' => 'Has product weight',
                                'missing' => $shippingWeightFallback
                                    ? 'Missing product weight (uses store fallback)'
                                    : 'Missing product weight',
                                'uses_fallback' => $shippingWeightFallback
                                    ? 'Uses store fallback at checkout'
                                    : 'Needs shipping weight estimate',
                                default => 'Any shipping weight',
                            };
                        @endphp
                        <div class="relative" data-filter-picker id="filter-shipping-weight">
                            <input type="hidden" name="shipping_weight" value="{{ $filters['shipping_weight'] ?? '' }}" data-picker-value>
                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Shipping weight</p>
                            <button type="button" data-picker-trigger class="{{ ($filters['shipping_weight'] ?? '') !== '' ? $pickerTriggerActive : $pickerTriggerIdle }}" aria-haspopup="listbox" aria-expanded="false">
                                <span class="min-w-0 truncate" data-picker-label>{{ $activeShippingWeightLabel }}</span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="shrink-0 text-[#94A3B8]" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <div data-picker-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-200/60">
                                <ul data-picker-list class="max-h-52 overflow-y-auto py-1" role="listbox">
                                    <li>
                                        <button type="button" data-picker-option data-value="" data-label="Any shipping weight" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['shipping_weight'] ?? '') === '' ? 'bg-[#EEF4FF] text-[#0052CC]' : '' }}">Any</button>
                                    </li>
                                    <li>
                                        <button type="button" data-picker-option data-value="has" data-label="Has product weight" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['shipping_weight'] ?? '') === 'has' ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">Has product weight</button>
                                    </li>
                                    <li>
                                        <button type="button" data-picker-option data-value="missing" data-label="{{ $shippingWeightFallback ? 'Missing product weight (uses store fallback)' : 'Missing product weight' }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['shipping_weight'] ?? '') === 'missing' ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $shippingWeightFallback ? 'Missing product weight (uses store fallback)' : 'Missing product weight' }}</button>
                                    </li>
                                    <li>
                                        <button type="button" data-picker-option data-value="uses_fallback" data-label="{{ $shippingWeightFallback ? 'Uses store fallback at checkout' : 'Needs shipping weight estimate' }}" class="flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC] {{ ($filters['shipping_weight'] ?? '') === 'uses_fallback' ? 'bg-[#E6F4EF] text-[#0A4335]' : '' }}">{{ $shippingWeightFallback ? 'Uses store fallback at checkout' : 'Needs shipping weight estimate' }}</button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('products', $isDeletedView ? ['view' => 'deleted'] : []) }}" class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm font-semibold text-[#64748B] hover:bg-[#F8FAFC]">Clear filters</a>
                        <p class="text-xs text-[#94A3B8]">Type in a picker to find options fast — no long lists on the page.</p>
                    </div>
                </form>
            </details>
        </div>

        <script>
            (() => {
                const form = document.getElementById('products-filters-form');
                if (!form) return;

                const submitForm = () => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                };

                form.querySelectorAll('.js-filter-auto-submit').forEach((input) => {
                    input.addEventListener('change', submitForm);
                });

                const closeAllPickers = (except = null) => {
                    form.querySelectorAll('[data-filter-picker]').forEach((picker) => {
                        if (except && picker === except) return;
                        const panel = picker.querySelector('[data-picker-panel]');
                        const trigger = picker.querySelector('[data-picker-trigger]');
                        if (panel) panel.classList.add('hidden');
                        if (trigger) trigger.setAttribute('aria-expanded', 'false');
                    });
                };

                form.querySelectorAll('[data-filter-picker]').forEach((picker) => {
                    const trigger = picker.querySelector('[data-picker-trigger]');
                    const panel = picker.querySelector('[data-picker-panel]');
                    const search = picker.querySelector('[data-picker-search]');
                    const valueInput = picker.querySelector('[data-picker-value]');
                    const labelEl = picker.querySelector('[data-picker-label]');
                    if (!trigger || !panel || !valueInput) return;

                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        const willOpen = panel.classList.contains('hidden');
                        closeAllPickers();
                        if (willOpen) {
                            panel.classList.remove('hidden');
                            trigger.setAttribute('aria-expanded', 'true');
                            if (search) {
                                search.value = '';
                                panel.querySelectorAll('[data-picker-option]').forEach((opt) => {
                                    opt.closest('li')?.classList.remove('hidden');
                                });
                                setTimeout(() => search.focus(), 0);
                            }
                        }
                    });

                    search?.addEventListener('input', () => {
                        const needle = (search.value || '').trim().toLowerCase();
                        panel.querySelectorAll('[data-picker-option]').forEach((opt) => {
                            const label = (opt.getAttribute('data-label') || '').toLowerCase();
                            const show = !needle || label.includes(needle);
                            opt.closest('li')?.classList.toggle('hidden', !show);
                        });
                    });

                    search?.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            closeAllPickers();
                            trigger.focus();
                        }
                    });

                    panel.querySelectorAll('[data-picker-option]').forEach((opt) => {
                        opt.addEventListener('click', () => {
                            const value = opt.getAttribute('data-value') ?? '';
                            const label = opt.getAttribute('data-label') || 'Any';
                            valueInput.value = value;
                            if (labelEl) labelEl.textContent = label;
                            closeAllPickers();
                            submitForm();
                        });
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!form.contains(event.target)) {
                        closeAllPickers();
                        return;
                    }
                    const inPicker = event.target.closest('[data-filter-picker]');
                    if (!inPicker) {
                        closeAllPickers();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeAllPickers();
                    }
                });
            })();
        </script>

        <script>
            (() => {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                function parseErrorMessage(data, fallback) {
                    if (!data || typeof data !== 'object') return fallback;
                    if (typeof data.message === 'string' && data.message.trim() !== '') return data.message;
                    if (data.errors && typeof data.errors === 'object') {
                        const parts = Object.values(data.errors).flat().filter(Boolean);
                        if (parts.length) return parts.join(' ');
                    }
                    return fallback;
                }

                async function patchJson(url, body) {
                    const response = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(parseErrorMessage(data, 'Could not save. Please try again.'));
                    }
                    return data;
                }

                async function deleteJson(url) {
                    const response = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(parseErrorMessage(data, 'Could not remove category.'));
                    }
                    return data;
                }

                function updateStockVisuals(row, total, alertLevel, published, stockState) {
                    if (!row) return;
                    const inventory = Math.max(0, parseInt(String(total), 10) || 0);
                    const alert = Math.max(0, parseInt(String(alertLevel), 10) || 0);
                    const state = stockState || (inventory === 0 ? 'out' : (alert > 0 && inventory <= alert ? 'low' : 'in'));
                    const previousState = row.getAttribute('data-stock-state') || 'in';
                    row.setAttribute('data-stock-state', state);

                    const fill = row.querySelector('.js-stock-bar-fill');
                    if (fill) {
                        fill.style.width = Math.min(100, Math.max(4, inventory)) + '%';
                        fill.className = 'js-stock-bar-fill h-full rounded-full ' + (
                            state === 'out' ? 'bg-[#E2E8F0]' : (state === 'low' ? 'bg-[#F97316]' : 'bg-[#3B82F6]')
                        );
                    }

                    const stockRoot = row.querySelector('.js-inline-stock');
                    if (stockRoot) {
                        stockRoot.setAttribute('data-alert', String(alert));
                    }

                    const badge = row.querySelector('.js-row-status-badge');
                    if (badge) {
                        if (state === 'out') {
                            badge.className = 'js-row-status-badge inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1 rounded-full';
                            badge.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#EF4444" /></svg>Out of Stock';
                        } else if (state === 'low') {
                            badge.className = 'js-row-status-badge inline-flex items-center gap-1.5 bg-orange-50 text-orange-500 text-xs font-bold px-3 py-1 rounded-full border border-orange-100';
                            badge.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#F97316" /></svg>Low Stock';
                        } else if (published) {
                            badge.className = 'js-row-status-badge inline-flex items-center gap-1.5 bg-green-50 text-green-600 text-xs font-bold px-3 py-1 rounded-full';
                            badge.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#22C55E" /></svg>Published';
                        } else {
                            badge.className = 'js-row-status-badge inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded-full';
                            badge.innerHTML = '<svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#64748B" /></svg>Draft';
                        }
                    }

                    syncHeaderStockStats(previousState, state);
                }

                function readStat(el) {
                    if (!el) return 0;
                    const raw = el.getAttribute('data-stat-value');
                    if (raw != null && raw !== '') return Math.max(0, parseInt(raw, 10) || 0);
                    return Math.max(0, parseInt(String(el.textContent || '').replace(/,/g, ''), 10) || 0);
                }

                function writeStat(el, value, labelEl, activeLabel, idleLabel, emphasizeClass) {
                    if (!el) return;
                    const next = Math.max(0, value);
                    el.setAttribute('data-stat-value', String(next));
                    el.textContent = next.toLocaleString();
                    if (next > 0) {
                        el.classList.remove('text-ink');
                        el.classList.add(emphasizeClass);
                    } else {
                        el.classList.remove(emphasizeClass);
                        el.classList.add('text-ink');
                    }
                    if (labelEl) {
                        labelEl.textContent = next > 0 ? activeLabel : idleLabel;
                        if (next > 0) {
                            labelEl.classList.remove('text-ink-muted');
                            labelEl.classList.add(emphasizeClass);
                        } else {
                            labelEl.classList.remove(emphasizeClass);
                            labelEl.classList.add('text-ink-muted');
                        }
                    }
                }

                function syncHeaderStockStats(previousState, nextState) {
                    if (previousState === nextState) return;
                    const outEl = document.querySelector('[data-stat-out-of-stock]');
                    const lowEl = document.querySelector('[data-stat-low-stock]');
                    const outLabel = document.querySelector('[data-stat-out-of-stock-label]');
                    const lowLabel = document.querySelector('[data-stat-low-stock-label]');
                    const filterLow = document.querySelector('[data-filter-low-stock-count]');

                    let outCount = readStat(outEl);
                    let lowCount = readStat(lowEl);

                    if (previousState === 'out') outCount -= 1;
                    if (previousState === 'low') lowCount -= 1;
                    if (nextState === 'out') outCount += 1;
                    if (nextState === 'low') lowCount += 1;

                    writeStat(outEl, outCount, outLabel, 'Needs attention', 'All stocked', 'text-[#B91C1C]');
                    writeStat(lowEl, lowCount, lowLabel, 'Reorder soon', 'Looking good', 'text-[#C2410C]');
                    if (filterLow) {
                        filterLow.textContent = String(Math.max(0, lowCount));
                    }
                }

                function parseButtonPayload(btn) {
                    if (!btn) return null;
                    const raw = btn.getAttribute('data-product') || btn.dataset.product || '';
                    if (!raw) return null;
                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        return null;
                    }
                }

                window.__productEditPayloadById = window.__productEditPayloadById || {};
                window.__liveProductValuesById = window.__liveProductValuesById || {};

                function rememberEditPayload(productId, payload) {
                    if (productId == null || !payload) return;
                    window.__productEditPayloadById[String(productId)] = payload;
                }

                function readRememberedPayload(productId) {
                    if (productId == null) return null;
                    return window.__productEditPayloadById[String(productId)] || null;
                }

                function rememberLiveValues(productId, values) {
                    if (productId == null || !values) return;
                    const key = String(productId);
                    const prev = window.__liveProductValuesById[key] || {};
                    const next = { ...prev, updatedAt: Date.now() };
                    if (values.price !== undefined) next.price = values.price == null ? null : String(values.price);
                    if (values.stock !== undefined) next.stock = values.stock == null ? null : String(values.stock);
                    if (values.inventory !== undefined) next.inventory = values.inventory == null ? null : String(values.inventory);
                    if (values.variants !== undefined) {
                        next.variants = Array.isArray(values.variants)
                            ? values.variants.map((row) => ({
                                id: Number(row.id),
                                stock: Math.max(0, parseInt(String(row.stock ?? 0), 10) || 0),
                                stock_alert: row.stock_alert != null
                                    ? Math.max(0, parseInt(String(row.stock_alert), 10) || 0)
                                    : undefined,
                            }))
                            : null;
                    }
                    window.__liveProductValuesById[key] = next;
                }

                function readLiveValues(productId) {
                    if (productId == null) return null;
                    return window.__liveProductValuesById[String(productId)] || null;
                }

                function seedEditPayloadMemory() {
                    document.querySelectorAll('.js-product-edit-payload').forEach((btn) => {
                        const payload = parseButtonPayload(btn);
                        const productId = payload?.id || btn.getAttribute('data-product-id');
                        if (!productId) return;
                        // Never clobber newer inline edits with stale page-load JSON.
                        if (readLiveValues(productId)?.updatedAt) {
                            return;
                        }
                        if (payload) {
                            rememberEditPayload(productId, payload);
                        }
                        const row = btn.closest('tr');
                        if (row) {
                            rememberLiveValues(productId, {
                                price: row.getAttribute('data-live-price'),
                                stock: row.getAttribute('data-live-stock'),
                                inventory: row.getAttribute('data-live-inventory'),
                            });
                        }
                    });
                }

                function writeRowEditPayload(row, payload) {
                    if (!row || !payload) return;
                    try {
                        const json = JSON.stringify(payload);
                        row.querySelectorAll('.js-product-edit-payload, .js-open-delete-product-modal').forEach((btn) => {
                            btn.setAttribute('data-product', json);
                        });
                    } catch (e) {
                        // Attribute write can fail for oversized payloads; memory maps still win.
                    }
                    if (payload.id != null) {
                        rememberEditPayload(payload.id, payload);
                    }
                }

                function findPayloadVariant(payload, variantId) {
                    if (!payload || !Array.isArray(payload.variants) || !payload.variants.length) {
                        return null;
                    }
                    if (variantId != null && variantId !== '') {
                        const match = payload.variants.find((row) => String(row.id) === String(variantId));
                        if (match) return match;
                    }
                    const simple = payload.variants.find((row) => !row.option_map || Object.keys(row.option_map || {}).length === 0);
                    return simple || payload.variants[0];
                }

                function applyVariantStockSnapshot(payload, variants) {
                    if (!payload || !Array.isArray(payload.variants) || !Array.isArray(variants)) {
                        return payload;
                    }
                    const byId = new Map(variants.map((row) => [String(row.id), row]));
                    payload.variants = payload.variants.map((variant) => {
                        const next = byId.get(String(variant.id));
                        if (!next) return variant;
                        return {
                            ...variant,
                            stock: String(next.stock ?? variant.stock ?? 0),
                            stock_alert: next.stock_alert != null ? Number(next.stock_alert) : variant.stock_alert,
                        };
                    });
                    return payload;
                }

                function rowProductId(row) {
                    if (!row) return null;
                    return row.querySelector('.js-product-row-checkbox')?.getAttribute('data-product-id')
                        || row.querySelector('.js-product-edit-payload')?.getAttribute('data-product-id')
                        || row.getAttribute('data-product-id')
                        || null;
                }

                function setLiveRowValues(row, values) {
                    if (!row || !values) return;
                    const productId = rowProductId(row);
                    if (values.price !== undefined && values.price !== null && values.price !== '') {
                        row.setAttribute('data-live-price', String(values.price));
                    }
                    if (values.stock !== undefined && values.stock !== null) {
                        row.setAttribute('data-live-stock', String(values.stock));
                    }
                    if (values.inventory !== undefined && values.inventory !== null) {
                        row.setAttribute('data-live-inventory', String(values.inventory));
                    }
                    if (productId != null) {
                        rememberLiveValues(productId, values);
                    }
                }

                function patchRowEditPayload(row, mutator) {
                    if (!row || typeof mutator !== 'function') return;
                    const btn = row.querySelector('.js-product-edit-payload, .js-open-delete-product-modal');
                    const fromBtn = parseButtonPayload(btn);
                    const productId = fromBtn?.id || rowProductId(row);
                    const current = (productId && readRememberedPayload(productId)) || fromBtn;
                    if (!current) {
                        // Still keep lightweight live values for popup hydrate.
                        return;
                    }
                    const next = mutator(JSON.parse(JSON.stringify(current)));
                    if (next) {
                        writeRowEditPayload(row, next);
                    }
                }

                function hydratePayloadFromListRow(row, payload) {
                    if (!row || !payload) return payload;
                    const next = JSON.parse(JSON.stringify(payload));
                    const productId = next.id || rowProductId(row);
                    const liveMap = readLiveValues(productId) || {};
                    const livePrice = liveMap.price != null ? String(liveMap.price) : row.getAttribute('data-live-price');
                    const liveStock = liveMap.stock != null ? String(liveMap.stock) : row.getAttribute('data-live-stock');
                    const liveInventory = liveMap.inventory != null ? String(liveMap.inventory) : row.getAttribute('data-live-inventory');
                    const priceInput = row.querySelector('.js-inline-price-input');
                    const priceRaw = (livePrice != null && livePrice !== '')
                        ? livePrice
                        : (priceInput ? String(priceInput.value ?? '').trim() : '');
                    if (priceRaw !== '') {
                        const price = Number(priceRaw);
                        if (Number.isFinite(price) && price >= 0) {
                            const priceStr = String(price);
                            next.base_price = priceStr;
                            const variant = findPayloadVariant(next, null);
                            if (variant && (!Array.isArray(next.variants) || next.variants.length <= 1)) {
                                variant.price = priceStr;
                            }
                        }
                    }

                    const stockRoot = row.querySelector('.js-inline-stock');
                    const variantCount = Math.max(1, parseInt(String(stockRoot?.getAttribute('data-variant-count') || '1'), 10) || 1);
                    const stockValueEl = row.querySelector('.js-inline-stock-value');
                    const stockInput = row.querySelector('.js-inline-stock-input');
                    const totalText = (liveInventory != null && liveInventory !== '')
                        ? String(liveInventory)
                        : (stockValueEl ? String(stockValueEl.textContent || '').replace(/,/g, '').trim() : '');
                    const total = parseInt(totalText, 10);
                    if (Number.isFinite(total) && total >= 0) {
                        next.default_stock = total;
                    }

                    if (variantCount <= 1) {
                        const stockCandidate = (liveStock != null && liveStock !== '')
                            ? liveStock
                            : (stockInput && String(stockInput.value ?? '').trim() !== ''
                                ? String(stockInput.value)
                                : (Number.isFinite(total) ? String(total) : null));
                        if (stockCandidate != null) {
                            const stockStr = String(Math.max(0, parseInt(String(stockCandidate), 10) || 0));
                            next.default_stock = Number(stockStr);
                            if (!Array.isArray(next.variants) || next.variants.length === 0) {
                                next.variants = [{
                                    id: '',
                                    option_map: {},
                                    sku: next.sku || '',
                                    price: next.base_price || '',
                                    compare_at_price: '',
                                    stock: stockStr,
                                    stock_alert: next.stock_alert || 0,
                                    product_image_id: '',
                                    custom_fields: [],
                                }];
                            } else {
                                next.variants = next.variants.map((variant, index) => (
                                    index === 0 || !variant.option_map || Object.keys(variant.option_map || {}).length === 0
                                        ? { ...variant, stock: stockStr }
                                        : variant
                                ));
                                if (next.variants.length === 1) {
                                    next.variants[0].stock = stockStr;
                                }
                            }
                        }
                    } else if (Array.isArray(liveMap.variants) && liveMap.variants.length && Array.isArray(next.variants)) {
                        // Multi-option products: always prefer the latest per-option live stocks.
                        applyVariantStockSnapshot(next, liveMap.variants);
                        const liveTotal = liveMap.variants.reduce(
                            (sum, row) => sum + Math.max(0, parseInt(String(row.stock ?? 0), 10) || 0),
                            0
                        );
                        next.default_stock = liveTotal;
                    }

                    return next;
                }

                function resolveEditPayloadFromButton(button) {
                    const row = button?.closest?.('tr') || null;
                    const fromBtn = parseButtonPayload(button);
                    const productId = fromBtn?.id
                        || button?.getAttribute?.('data-product-id')
                        || rowProductId(row);
                    const remembered = productId ? readRememberedPayload(productId) : null;
                    const base = remembered || fromBtn;
                    if (!base) return null;
                    return hydratePayloadFromListRow(row, base);
                }

                window.resolveProductEditPayloadFromButton = resolveEditPayloadFromButton;
                window.readLiveProductValues = readLiveValues;

                function syncEditPopupAfterInlinePrice(row, data, price) {
                    const nextPrice = Number(data.base_price ?? price);
                    const priceStr = Number.isFinite(nextPrice) ? String(nextPrice) : String(price);
                    setLiveRowValues(row, { price: priceStr });
                    patchRowEditPayload(row, (payload) => {
                        payload.base_price = priceStr;
                        const variant = findPayloadVariant(payload, data.variant_id);
                        if (variant) {
                            variant.price = priceStr;
                        }
                        return payload;
                    });
                }

                function syncEditPopupAfterInlineStock(row, data, stock) {
                    const nextStock = data.stock ?? stock;
                    const stockStr = String(Math.max(0, parseInt(String(nextStock), 10) || 0));
                    const variantCount = Math.max(1, parseInt(String(row?.querySelector('.js-inline-stock')?.getAttribute('data-variant-count') || '1'), 10) || 1);
                    const typedVariants = Array.isArray(data.variants) ? data.variants : null;
                    // Simple products: edited stock is the inventory total.
                    // Multi-option: inventory is the sum of typed/API variant rows.
                    let inventory = stockStr;
                    if (variantCount > 1) {
                        if (typedVariants && typedVariants.length) {
                            inventory = String(typedVariants.reduce(
                                (sum, row) => sum + Math.max(0, parseInt(String(row.stock ?? 0), 10) || 0),
                                0
                            ));
                        } else if (data.inventory_total != null) {
                            inventory = String(Math.max(0, parseInt(String(data.inventory_total), 10) || 0));
                        }
                    }
                    setLiveRowValues(row, {
                        stock: variantCount <= 1 ? stockStr : inventory,
                        inventory,
                        variants: typedVariants || undefined,
                    });
                    patchRowEditPayload(row, (payload) => {
                        if (typedVariants && typedVariants.length) {
                            applyVariantStockSnapshot(payload, typedVariants);
                        } else {
                            const variant = findPayloadVariant(payload, data.variant_id);
                            if (variant) {
                                variant.stock = stockStr;
                                if (data.stock_alert != null) {
                                    variant.stock_alert = Math.max(0, parseInt(String(data.stock_alert), 10) || 0);
                                }
                            }
                            if (Array.isArray(payload.variants) && payload.variants.length === 1) {
                                payload.variants[0].stock = stockStr;
                            }
                        }
                        if (data.stock_alert != null && (!Array.isArray(payload.variants) || payload.variants.length <= 1)) {
                            payload.stock_alert = Math.max(0, parseInt(String(data.stock_alert), 10) || 0);
                        }
                        payload.default_stock = Math.max(0, parseInt(inventory, 10) || 0);
                        return payload;
                    });
                }

                function variantOptionLabel(payload, variant) {
                    const types = Array.isArray(payload?.variation_types) ? payload.variation_types : [];
                    const parts = [];
                    Object.entries(variant?.option_map || {}).forEach(([variationIndex, optionIndex]) => {
                        const type = types[Number(variationIndex)];
                        const option = type && Array.isArray(type.options) ? type.options[Number(optionIndex)] : null;
                        if (type?.name && option) {
                            parts.push(String(type.name) + ': ' + String(option));
                        }
                    });
                    if (parts.length) return parts.join(' · ');
                    if (variant?.sku) return String(variant.sku);
                    return 'Main stock';
                }

                const variantStockEls = () => ({
                    popover: document.getElementById('inline-variant-stock-popover'),
                    rows: document.getElementById('inline-variant-stock-rows'),
                    title: document.getElementById('inline-variant-stock-title'),
                    save: document.getElementById('inline-variant-stock-save'),
                    cancel: document.getElementById('inline-variant-stock-cancel'),
                });
                let variantStockContext = null;

                function closeVariantStockPopover() {
                    variantStockContext = null;
                    const { popover } = variantStockEls();
                    if (popover) {
                        popover.classList.add('hidden');
                    }
                }

                function openVariantStockPopover(root) {
                    const { popover, rows, title } = variantStockEls();
                    if (!root || !popover || !rows) return;
                    const row = root.closest('tr');
                    const btn = row?.querySelector('.js-product-edit-payload');
                    const productId = root.getAttribute('data-product-id')
                        || btn?.getAttribute('data-product-id')
                        || rowProductId(row);
                    const payload = resolveEditPayloadFromButton(btn)
                        || parseButtonPayload(btn)
                        || readRememberedPayload(productId);
                    if (!payload || !Array.isArray(payload.variants) || !payload.variants.length) {
                        window.alert('Open Edit to manage stock for this product’s options.');
                        return;
                    }

                    // Prefer the latest typed option stocks over any stale payload snapshot.
                    const live = readLiveValues(productId);
                    let variantRows = payload.variants;
                    if (live && Array.isArray(live.variants) && live.variants.length) {
                        const byId = new Map(live.variants.map((item) => [String(item.id), item]));
                        variantRows = payload.variants.map((variant) => {
                            const match = byId.get(String(variant.id));
                            return match ? { ...variant, stock: String(match.stock) } : variant;
                        });
                    }

                    variantStockContext = {
                        root,
                        row,
                        url: root.getAttribute('data-url'),
                        productId: payload.id || productId,
                    };
                    if (title) {
                        title.textContent = 'Stock by option';
                    }
                    rows.innerHTML = variantRows.map((variant) => {
                        const label = variantOptionLabel(payload, variant);
                        const stock = Math.max(0, parseInt(String(variant.stock ?? 0), 10) || 0);
                        return '<label class="flex items-center justify-between gap-3 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">'
                            + '<span class="min-w-0 flex-1 text-xs font-semibold text-[#334155]">' + label.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;') + '</span>'
                            + '<input type="number" min="0" step="1" inputmode="numeric" data-variant-id="' + String(variant.id) + '" value="' + stock + '" class="js-inline-variant-stock-input h-9 w-20 rounded-md border border-[#CBD5E1] px-2 text-sm font-semibold text-[#0F172A]">'
                            + '</label>';
                    }).join('');

                    const rect = root.getBoundingClientRect();
                    popover.classList.remove('hidden');
                    const popW = popover.offsetWidth || 320;
                    const left = Math.min(window.innerWidth - popW - 12, Math.max(12, rect.left + window.scrollX - 40));
                    const top = rect.bottom + window.scrollY + 8;
                    popover.style.left = left + 'px';
                    popover.style.top = top + 'px';
                }

                async function saveVariantStockPopover() {
                    const { rows, save } = variantStockEls();
                    if (!variantStockContext || !rows) return;
                    const url = variantStockContext.url;
                    if (!url) return;
                    const variants = [...rows.querySelectorAll('.js-inline-variant-stock-input')].map((input) => ({
                        id: Number(input.getAttribute('data-variant-id')),
                        stock: Math.max(0, parseInt(String(input.value ?? '0'), 10) || 0),
                    })).filter((row) => Number.isFinite(row.id) && row.id > 0);

                    if (!variants.length) {
                        window.alert('No option stock rows to save.');
                        return;
                    }

                    if (save) {
                        save.disabled = true;
                    }
                    try {
                        const data = await patchJson(url, { variants });
                        // Always trust the values the merchant just typed for live UI/popup sync.
                        const typedTotal = variants.reduce((sum, row) => sum + row.stock, 0);
                        const apiVariants = Array.isArray(data.variants) && data.variants.length
                            ? data.variants
                            : variants;
                        const mergedVariants = variants.map((typed) => {
                            const fromApi = apiVariants.find((row) => String(row.id) === String(typed.id));
                            return {
                                id: typed.id,
                                stock: typed.stock,
                                stock_alert: fromApi?.stock_alert,
                            };
                        });
                        const total = typedTotal;
                        const alert = data.stock_alert ?? (variantStockContext.root.getAttribute('data-alert') || '0');
                        const published = data.is_published != null
                            ? !!data.is_published
                            : variantStockContext.root.getAttribute('data-published') === '1';
                        const valueEl = variantStockContext.root.querySelector('.js-inline-stock-value');
                        if (valueEl) {
                            valueEl.textContent = String(total);
                        }
                        updateStockVisuals(
                            variantStockContext.row,
                            total,
                            alert,
                            published,
                            data.stock_state || null
                        );
                        syncEditPopupAfterInlineStock(variantStockContext.row, {
                            ...data,
                            inventory_total: total,
                            variants: mergedVariants,
                        }, total);
                        closeVariantStockPopover();
                    } catch (err) {
                        window.alert(err.message || 'Could not save option stock.');
                    } finally {
                        if (save) {
                            save.disabled = false;
                        }
                    }
                }

                seedEditPayloadMemory();
                document.addEventListener('DOMContentLoaded', seedEditPayloadMemory);
                requestAnimationFrame(() => seedEditPayloadMemory());
                setTimeout(seedEditPayloadMemory, 0);

                document.addEventListener('click', (event) => {
                    const { popover, cancel, save } = variantStockEls();
                    if (cancel && (event.target === cancel || cancel.contains(event.target))) {
                        closeVariantStockPopover();
                        return;
                    }
                    if (save && (event.target === save || save.contains(event.target))) {
                        saveVariantStockPopover();
                        return;
                    }
                    const openBtn = event.target.closest('.js-inline-variant-stock-open');
                    if (openBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        openVariantStockPopover(openBtn.closest('.js-inline-variant-stock'));
                        return;
                    }
                    if (popover && !popover.classList.contains('hidden')) {
                        if (!popover.contains(event.target) && !event.target.closest('.js-inline-variant-stock-open')) {
                            closeVariantStockPopover();
                        }
                    }
                });

                function getEditorParts(root) {
                    const input = root.querySelector('.js-inline-edit-input');
                    const display = root.querySelector('.js-inline-edit-display');
                    return { input, display };
                }

                function closeEditor(root, restoreOriginal = false) {
                    if (!root) return;
                    const { input, display } = getEditorParts(root);
                    if (restoreOriginal && input && root.dataset.originalValue != null) {
                        input.value = root.dataset.originalValue;
                    }
                    root.dataset.editing = '0';
                    root.dataset.saving = '0';
                    root.dataset.ignoreBlur = '1';
                    if (input) {
                        input.classList.add('hidden');
                        input.style.display = 'none';
                        input.disabled = false;
                        try { input.blur(); } catch (e) {}
                    }
                    if (display) {
                        display.classList.remove('invisible');
                        display.style.visibility = '';
                    }
                    setTimeout(() => {
                        delete root.dataset.ignoreBlur;
                    }, 200);
                }

                function openEditor(root) {
                    if (!root || root.dataset.saving === '1') return;
                    // Only one cell editing at a time — save any other open editor first.
                    document.querySelectorAll('.js-inline-edit[data-editing="1"]').forEach((other) => {
                        if (other !== root) {
                            commitEditor(other);
                        }
                    });

                    const { input, display } = getEditorParts(root);
                    if (!input || !display) return;

                    root.dataset.originalValue = String(input.value ?? '');
                    root.dataset.editing = '1';
                    delete root.dataset.ignoreBlur;
                    display.classList.add('invisible');
                    display.style.visibility = 'hidden';
                    input.classList.remove('hidden');
                    input.style.display = 'block';
                    input.disabled = false;
                    // Focus after paint so the field is visible first.
                    requestAnimationFrame(() => {
                        input.focus();
                        input.select();
                    });
                }

                function isEditorOpen(root) {
                    return root && root.dataset.editing === '1';
                }

                async function commitEditor(root) {
                    if (!isEditorOpen(root) || root.dataset.saving === '1') return;
                    const { input, display } = getEditorParts(root);
                    if (!input || !display) return;

                    const kind = root.getAttribute('data-inline-kind');
                    const url = root.getAttribute('data-url');
                    const raw = String(input.value ?? '').trim();
                    const original = String(root.dataset.originalValue ?? '');

                    if (raw === original) {
                        closeEditor(root, false);
                        return;
                    }

                    root.dataset.saving = '1';
                    input.disabled = true;

                    try {
                        if (kind === 'price') {
                            const currency = root.getAttribute('data-currency') || '';
                            const value = parseFloat(raw);
                            if (!url || Number.isNaN(value) || value < 0) {
                                throw new Error('Enter a valid price.');
                            }
                            const data = await patchJson(url, { base_price: value });
                            const next = Number(data.base_price);
                            display.textContent = data.formatted || (currency + next.toFixed(2));
                            input.value = next.toFixed(2);
                            root.dataset.originalValue = input.value;
                            syncEditPopupAfterInlinePrice(root.closest('tr'), data, next);
                        } else if (kind === 'stock') {
                            const value = parseInt(raw, 10);
                            if (!url || Number.isNaN(value) || value < 0) {
                                throw new Error('Enter a valid stock quantity.');
                            }
                            const data = await patchJson(url, { stock: value });
                            // Prefer the value the merchant just typed; fall back to API fields.
                            const savedStock = Number.isFinite(Number(data.stock)) ? Number(data.stock) : value;
                            const total = Number.isFinite(Number(data.inventory_total))
                                ? Number(data.inventory_total)
                                : savedStock;
                            const alert = data.stock_alert ?? (root.getAttribute('data-alert') || '0');
                            const published = data.is_published != null
                                ? !!data.is_published
                                : root.getAttribute('data-published') === '1';
                            const valueEl = display.querySelector('.js-inline-stock-value');
                            if (valueEl) {
                                valueEl.textContent = String(total);
                            }
                            input.value = String(savedStock);
                            root.dataset.originalValue = input.value;
                            root.setAttribute('data-alert', String(alert));
                            updateStockVisuals(
                                root.closest('tr'),
                                total,
                                alert,
                                published,
                                data.stock_state || null
                            );
                            syncEditPopupAfterInlineStock(root.closest('tr'), {
                                ...data,
                                stock: savedStock,
                                inventory_total: total,
                            }, savedStock);
                        } else {
                            throw new Error('Unknown editor.');
                        }

                        // Always leave edit mode after a successful save (Enter / blur / outside click).
                        closeEditor(root, false);
                    } catch (err) {
                        root.dataset.saving = '0';
                        input.disabled = false;
                        window.alert(err.message || 'Could not save.');
                        requestAnimationFrame(() => {
                            input.focus();
                            input.select();
                        });
                    }
                }

                document.addEventListener('click', async (event) => {
                    const removeBtn = event.target.closest('.js-detach-category');
                    if (removeBtn) {
                        event.preventDefault();
                        const url = removeBtn.getAttribute('data-url');
                        const chip = removeBtn.closest('.js-category-chip');
                        const wrap = removeBtn.closest('.js-product-categories');
                        if (!url || !chip) return;
                        removeBtn.disabled = true;
                        try {
                            await deleteJson(url);
                            chip.remove();
                            if (wrap && !wrap.querySelector('.js-category-chip')) {
                                const empty = document.createElement('span');
                                empty.className = 'js-product-categories-empty text-xs text-[#94A3B8]';
                                empty.textContent = '—';
                                wrap.replaceWith(empty);
                            }
                        } catch (err) {
                            window.alert(err.message || 'Could not remove category.');
                            removeBtn.disabled = false;
                        }
                        return;
                    }

                    const displayBtn = event.target.closest('.js-inline-edit-display');
                    if (displayBtn) {
                        event.preventDefault();
                        openEditor(displayBtn.closest('.js-inline-edit'));
                        return;
                    }

                    // Click outside an open editor → save & close.
                    if (!event.target.closest('.js-inline-edit')) {
                        document.querySelectorAll('.js-inline-edit[data-editing="1"]').forEach((root) => {
                            commitEditor(root);
                        });
                    }
                });

                // Event delegation: inline editors are rendered after this script in the Blade file.
                document.addEventListener('keydown', (event) => {
                    const input = event.target;
                    if (!(input instanceof HTMLInputElement) || !input.classList.contains('js-inline-edit-input')) {
                        return;
                    }
                    const root = input.closest('.js-inline-edit');
                    if (!root || !isEditorOpen(root)) return;

                    if (event.key === 'Enter') {
                        event.preventDefault();
                        event.stopPropagation();
                        root.dataset.ignoreBlur = '1';
                        commitEditor(root);
                        return;
                    }
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        event.stopPropagation();
                        closeEditor(root, true);
                    }
                });

                document.addEventListener('focusout', (event) => {
                    const input = event.target;
                    if (!(input instanceof HTMLInputElement) || !input.classList.contains('js-inline-edit-input')) {
                        return;
                    }
                    const root = input.closest('.js-inline-edit');
                    if (!root) return;
                    if (root.dataset.ignoreBlur === '1') return;

                    // Defer so Enter can commit first without a blur race.
                    setTimeout(() => {
                        if (root.dataset.ignoreBlur === '1') return;
                        if (isEditorOpen(root) && root.dataset.saving !== '1' && document.activeElement !== input) {
                            commitEditor(root);
                        }
                    }, 150);
                });
            })();
        </script>


        @if ($canManageBrands)
            <div id="bulk-catalog-toolbar" class="hidden border-b border-[#D8E8E1] bg-gradient-to-r from-[#E6F4EF] via-[#F4FBF8] to-white px-4 py-4 lg:px-5" data-catalog-view="{{ $catalogView }}" role="region" aria-label="Selected products actions">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand text-sm font-bold text-white shadow-sm" aria-hidden="true">
                                <span id="bulk-selected-count">0</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#0A4335]">
                                    <span id="bulk-selected-label">products selected</span>
                                </p>
                                <p class="mt-0.5 text-xs text-[#64748B]">Choose what you want to do with the selected products.</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button type="button" id="bulk-select-all-matching" class="rounded-lg border border-[#B7D6C9] bg-white px-3 py-1.5 text-xs font-semibold text-[#0A4335] transition hover:bg-[#E6F4EF]">
                                        Select all matching ({{ number_format((int) ($bulkMatchingCount ?? count($bulkSelectableProductIds ?? []))) }})
                                    </button>
                                    <button type="button" id="bulk-clear-selection" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#64748B] transition hover:bg-white hover:text-[#0F172A]">
                                        Clear selection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">What do you want to do?</p>
                        <div id="bulk-action-chips" class="flex flex-wrap gap-2" role="group" aria-label="Product actions">
                            @if ($isDeletedView)
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#BBF7D0] bg-white px-3.5 py-2 text-sm font-semibold text-[#166534] transition hover:border-[#86EFAC] hover:bg-[#F0FDF4]" data-action="restore">
                                    Undo delete
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#FECACA] bg-white px-3.5 py-2 text-sm font-semibold text-[#B91C1C] transition hover:border-[#FCA5A5] hover:bg-[#FFF5F5]" data-action="force_delete">
                                    Permanently delete
                                </button>
                            @else
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="stock">
                                    Update stock
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="status">
                                    Publish or draft
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="categories">
                                    Add categories
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="brand">
                                    Assign brand
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="tags">
                                    Add tags
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]" data-action="shipping_weight">
                                    Set shipping weight
                                </button>
                                <button type="button" class="js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#FECACA] bg-white px-3.5 py-2 text-sm font-semibold text-[#B91C1C] transition hover:border-[#FCA5A5] hover:bg-[#FFF5F5]" data-action="delete">
                                    Delete
                                </button>
                            @endif
                        </div>
                        <select id="bulk-action-select" class="sr-only" aria-hidden="true" tabindex="-1">
                            <option value="">Choose…</option>
                            @if ($isDeletedView)
                                <option value="restore">Undo delete</option>
                                <option value="force_delete">Permanently delete</option>
                            @else
                                <option value="delete">Delete</option>
                                <option value="stock">Update stock</option>
                                <option value="categories">Add categories</option>
                                <option value="brand">Assign brand</option>
                                <option value="tags">Add tags</option>
                                <option value="status">Publish or draft</option>
                                <option value="shipping_weight">Set shipping weight</option>
                            @endif
                        </select>
                    </div>

                    <div id="bulk-options-panel" class="hidden rounded-xl border border-[#CDE5DB] bg-white p-4 shadow-sm">
                        <div id="bulk-extra-stock" class="hidden space-y-3">
                            <p class="text-sm font-semibold text-[#0F172A]">Update stock for selected products</p>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="flex flex-col gap-1">
                                    <label for="bulk-stock-mode" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Change type</label>
                                    <select id="bulk-stock-mode" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm font-medium text-[#334155]">
                                        <option value="set">Set stock to</option>
                                        <option value="delta">Increase or decrease by</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label for="bulk-stock-value" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Quantity</label>
                                    <input id="bulk-stock-value" type="number" class="w-28 rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="e.g. 25">
                                </div>
                                <div class="flex min-w-[16rem] max-w-md flex-1 flex-col gap-1">
                                    <label for="bulk-stock-variant-scope" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">If a product has multiple variants</label>
                                    <select id="bulk-stock-variant-scope" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm font-medium text-[#334155]">
                                        <option value="default_variant_only">Update the main variant only</option>
                                        <option value="all_variants_same">Use the same quantity on every variant</option>
                                        <option value="skip_multi_variant">Skip products with multiple variants</option>
                                    </select>
                                </div>
                            </div>
                            <fieldset id="bulk-stock-apply-mode-fieldset" class="space-y-2">
                                <legend class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Products that already have stock</legend>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="bulk_stock_apply_mode_ui" value="empty_only" class="mt-1" checked>
                                    <span>
                                        <span class="font-semibold text-[#0F172A]">Skip products that already have stock</span>
                                        <span class="mt-0.5 block text-xs text-[#64748B]">Only fill inventory rows that currently show 0. Existing quantities stay unchanged.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="bulk_stock_apply_mode_ui" value="replace_all" class="mt-1">
                                    <span>
                                        <span class="font-semibold text-[#0F172A]">Update every selected product</span>
                                        <span class="mt-0.5 block text-xs text-[#64748B]">Replaces existing stock values, including products that already have inventory.</span>
                                    </span>
                                </label>
                            </fieldset>
                            <p id="bulk-stock-delta-apply-hint" class="hidden text-xs text-[#64748B]">Increase/decrease always applies to every selected product’s current stock.</p>
                        </div>

                        <div id="bulk-extra-categories" class="hidden space-y-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Add categories</p>
                            <p class="text-xs text-[#64748B]">Hold Ctrl / Cmd to select more than one.</p>
                            <select id="bulk-category-ids" multiple size="4" class="w-full max-w-md rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#334155]">
                                @foreach ($catalogTaxonomyCategories ?? [] as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="bulk-extra-brand" class="hidden space-y-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Assign a brand</p>
                            <select id="bulk-brand-id" class="min-w-[14rem] rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#334155]">
                                <option value="">Choose a brand</option>
                                @foreach ($catalogBrands ?? [] as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="bulk-extra-tags" class="hidden space-y-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Add tags</p>
                            <p class="text-xs text-[#64748B]">Hold Ctrl / Cmd to select more than one.</p>
                            <select id="bulk-tag-ids" multiple size="4" class="w-full max-w-md rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#334155]">
                                @foreach ($catalogTags ?? [] as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="bulk-extra-status" class="hidden space-y-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Set product status</p>
                            <select id="bulk-status-value" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#334155]">
                                <option value="published">Published — visible to sell</option>
                                <option value="draft">Draft — hidden for now</option>
                            </select>
                        </div>

                        <div id="bulk-extra-shipping-weight" class="hidden space-y-3">
                            <p class="text-sm font-semibold text-[#0F172A]">Set shipping weight</p>
                            <p class="text-xs text-[#64748B]"><span id="bulk-shipping-weight-selected-count">0</span> products selected</p>

                            <fieldset class="space-y-2">
                                <legend class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Apply weight to</legend>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="bulk_shipping_weight_target_ui" value="products" class="mt-1" checked>
                                    <span>
                                        <span class="font-semibold text-[#0F172A]">Products</span>
                                        <span class="mt-0.5 block text-xs text-[#64748B]">One weight per selected product. Variant overrides stay unchanged.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="bulk_shipping_weight_target_ui" value="variants" class="mt-1">
                                    <span>
                                        <span class="font-semibold text-[#0F172A]">Variants</span>
                                        <span class="mt-0.5 block text-xs text-[#64748B]">Assign weights by option value (for example Size or Weight). Product weights are not changed.</span>
                                    </span>
                                </label>
                            </fieldset>

                            <div id="bulk-shipping-weight-products-panel" class="space-y-3">
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="flex flex-col gap-1">
                                        <label for="bulk-shipping-weight-value" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Shipping weight per item</label>
                                        <div class="flex items-center gap-2">
                            <input id="bulk-shipping-weight-value" type="number" min="0.01" max="{{ $shippingWeightMax ?? \App\Services\Delivery\StoreShippingPreferences::MAX_ITEM_WEIGHT }}" step="0.001" class="w-28 rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="e.g. 0.80">
                                            <span class="text-sm font-semibold text-[#64748B]">{{ $shippingWeightUnit ?? 'LB' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <fieldset class="space-y-2">
                                    <legend class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Apply to products</legend>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_shipping_weight_mode_ui" value="missing_only" class="mt-1" checked>
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Products that do not already have a weight</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Existing product weights will be preserved.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_shipping_weight_mode_ui" value="replace_all" class="mt-1">
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Every selected product</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Replaces the product-level shipping weight. Variant overrides stay unchanged.</span>
                                        </span>
                                    </label>
                                </fieldset>
                            </div>

                            <div id="bulk-shipping-weight-variants-panel" class="hidden space-y-3">
                                <fieldset class="space-y-2">
                                    <legend class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Variant bulk mode</legend>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_variant_bulk_mode_ui" value="map_by_option" class="mt-1" checked>
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Map by option value</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Enter one weight per option value (for example Small → 0.55).</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_variant_bulk_mode_ui" value="use_option_values" class="mt-1">
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Use option values as weights</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Parse labels like 5 lb or 16 oz. Preview and confirm before applying.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_variant_bulk_mode_ui" value="clear" class="mt-1">
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Clear variant weight overrides</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Variants inherit product weight, then store fallback.</span>
                                        </span>
                                    </label>
                                </fieldset>

                                <div id="bulk-variant-option-panel" class="space-y-2">
                                    <label for="bulk-variant-option-group" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Option group</label>
                                    <select id="bulk-variant-option-group" class="w-full max-w-md rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#334155]">
                                        <option value="">Choose an option group…</option>
                                    </select>
                                    <p id="bulk-variant-option-hint" class="text-xs text-[#64748B]">Load option groups from your selected products, then pick Size, Weight, or similar.</p>
                                </div>

                                <div id="bulk-variant-map-panel" class="space-y-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Weight by option value</p>
                                    <div id="bulk-variant-weight-map-rows" class="space-y-2 max-h-56 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3"></div>
                                </div>

                                <div id="bulk-variant-parse-panel" class="hidden space-y-2">
                                    <button type="button" id="bulk-variant-preview-parse" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm font-semibold text-[#334155] hover:bg-[#F8FAFC]">Preview parsed weights</button>
                                    <div id="bulk-variant-parse-preview" class="hidden rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-sm text-[#334155]"></div>
                                </div>

                                <div id="bulk-variant-clear-panel" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                    Clears explicit variant shipping weights on selected products. Variants without overrides are unchanged.
                                </div>

                                <div id="bulk-variant-preview-stats" class="hidden rounded-lg border border-[#DDE7F3] bg-[#F0F7FF] px-3 py-2 text-xs text-[#334155]"></div>

                                <fieldset id="bulk-variant-apply-mode-fieldset" class="space-y-2">
                                    <legend class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Apply to variants</legend>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_variant_shipping_weight_mode_ui" value="missing_only" class="mt-1" checked>
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Only variants without shipping weight</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Existing variant overrides will be preserved.</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                                        <input type="radio" name="bulk_variant_shipping_weight_mode_ui" value="replace_all" class="mt-1">
                                        <span>
                                            <span class="font-semibold text-[#0F172A]">Replace matching variant weights</span>
                                            <span class="mt-0.5 block text-xs text-[#64748B]">Updates every matching variant, including those with existing weights.</span>
                                        </span>
                                    </label>
                                </fieldset>
                            </div>
                        </div>

                        <div id="bulk-extra-simple" class="hidden">
                            <p id="bulk-extra-simple-copy" class="text-sm text-[#475569]"></p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-[#E2E8F0] pt-3">
                            <button type="button" id="bulk-apply-btn" class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-60">
                                <span id="bulk-apply-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                <span id="bulk-apply-label">Continue</span>
                            </button>
                            <button type="button" id="bulk-cancel-action" class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-semibold text-[#64748B] transition hover:bg-[#F8FAFC]">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                @if ($errors->has('bulk'))
                    <p class="mt-3 text-sm font-medium text-[#B42318]">{{ $errors->first('bulk') }}</p>
                @endif
            </div>

            <form id="bulk-products-form" method="POST" action="{{ route('products.bulk') }}" class="hidden" aria-hidden="true">
                @csrf
                <input type="hidden" name="action" id="bulk-form-action" value="">
                <input type="hidden" name="product_ids_json" id="bulk-form-product-ids-json" value="">
                <input type="hidden" name="stock_mode" id="bulk-form-stock-mode" value="">
                <input type="hidden" name="stock_value" id="bulk-form-stock-value" value="">
                <input type="hidden" name="bulk_variant_stock_scope" id="bulk-form-stock-variant-scope" value="default_variant_only">
                <input type="hidden" name="stock_apply_mode" id="bulk-form-stock-apply-mode" value="empty_only">
                <input type="hidden" name="brand_id" id="bulk-form-brand-id" value="">
                <input type="hidden" name="product_status" id="bulk-form-product-status" value="">
                <input type="hidden" name="shipping_weight_value" id="bulk-form-shipping-weight-value" value="">
                <input type="hidden" name="shipping_weight_mode" id="bulk-form-shipping-weight-mode" value="missing_only">
                <input type="hidden" name="shipping_weight_target" id="bulk-form-shipping-weight-target" value="products">
                <input type="hidden" name="variant_bulk_mode" id="bulk-form-variant-bulk-mode" value="map_by_option">
                <input type="hidden" name="variant_option_name" id="bulk-form-variant-option-name" value="">
                <input type="hidden" name="variant_weight_map_json" id="bulk-form-variant-weight-map-json" value="">
                <div id="bulk-form-category-inputs"></div>
                <div id="bulk-form-tag-inputs"></div>
                <div id="bulk-form-product-id-inputs"></div>
            </form>
        @endif

        <style>
            @keyframes product-thumb-shimmer {
                0% { background-position: -120% 0; }
                100% { background-position: 120% 0; }
            }
            .product-thumb-skeleton {
                background: linear-gradient(90deg, #E2E8F0 0%, #F1F5F9 45%, #E2E8F0 90%);
                background-size: 200% 100%;
                animation: product-thumb-shimmer 1.2s ease-in-out infinite;
            }
            .product-thumb-spinner {
                border: 2px solid #E2E8F0;
                border-top-color: #0052CC;
                animation: spin 0.7s linear infinite;
            }
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead>
                    <tr class="border-b border-[#E2E8F0] bg-[#F8FAFC]">
                        <th class="w-10 px-4 py-3"><input id="selectAllProducts" type="checkbox" class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Product</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Categories</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Behavior</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Status</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Price</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider" colspan="2">Inventory</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F1F5F9]">
                    @forelse ($products as $product)
                        @php
                            $inventoryState = ProductInventoryState::forProduct($product);
                            $inventory = $inventoryState['inventory'];
                            $alertLevel = $inventoryState['alert'];
                            $stockState = $inventoryState['state'];
                            $stockWidth = min(100, max(4, $inventory));
                            $primaryImage = $product->images->first(fn ($img) => $img->is_primary) ?? $product->images->first();
                            $primaryVisualState = 'none';
                            if ($primaryImage) {
                                if ($primaryImage->isReady()) {
                                    $primaryVisualState = 'ready';
                                } elseif ($primaryImage->isPendingVisual()) {
                                    $primaryVisualState = 'pending';
                                } elseif ($primaryImage->isFailed()) {
                                    $primaryVisualState = 'failed';
                                }
                            }
                            $galleryPaths = $product->images->filter(fn ($img) => $img->isReady())->pluck('image_path')->values()->all();
                            $productImageUrl = ($primaryVisualState === 'ready' && $primaryImage && $primaryImage->image_path)
                                ? asset('storage/'.$primaryImage->image_path)
                                : null;
                            $productActionPayload = ProductEditPayload::forProduct($product);
                            $detailChips = ProductCustomFieldHelper::listHighlightsForKeys(
                                is_array($product->meta) ? $product->meta : [],
                                $productListDetailKeys
                            );
                            $variantCount = $product->variants->count();
                            $defaultVariant = $product->variants->first(fn ($v) => $v->options->isEmpty())
                                ?? $product->variants->sortBy('id')->first();
                            $defaultVariantStock = (int) ($defaultVariant?->stock ?? $inventory);
                        @endphp
                        <tr class="hover:bg-[#F8FAFC] transition-colors" data-product-row data-product-id="{{ $product->id }}" data-stock-state="{{ $stockState }}" data-published="{{ $product->status ? '1' : '0' }}" data-live-price="{{ number_format((float) $product->base_price, 2, '.', '') }}" data-live-stock="{{ $defaultVariantStock }}" data-live-inventory="{{ $inventory }}">
                            <td class="px-4 py-4"><input type="checkbox" class="js-product-row-checkbox w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]" data-product-id="{{ $product->id }}" @if (! $canManageBrands) disabled @endif></td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex shrink-0 flex-col items-center gap-0.5 w-11">
                                    <div
                                        class="js-product-primary-thumb h-10 w-10 rounded-lg border border-[#E2E8F0] overflow-hidden flex items-center justify-center bg-[#F8FAFC]"
                                        data-product-id="{{ $product->id }}"
                                        data-state="{{ $primaryVisualState }}"
                                        data-url="{{ $productImageUrl ?? '' }}"
                                    >
                                        @if ($primaryVisualState === 'ready' && $productImageUrl)
                                            <img src="{{ $productImageUrl }}" alt="{{ $product->name }}" class="h-10 w-10 object-cover">
                                        @elseif ($primaryVisualState === 'pending')
                                            <div class="relative flex h-full w-full items-center justify-center product-thumb-skeleton">
                                                <span class="product-thumb-spinner absolute h-5 w-5 rounded-full" aria-hidden="true"></span>
                                            </div>
                                        @elseif ($primaryVisualState === 'failed')
                                            <div class="relative flex h-full w-full items-center justify-center bg-[#FEF2F2]" title="Image could not be loaded">
                                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" class="text-[#94A3B8]">
                                                    <path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="currentColor" />
                                                </svg>
                                                <span class="absolute bottom-0.5 right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[8px] font-bold text-white" title="Image error">!</span>
                                            </div>
                                        @else
                                            <div class="flex h-full w-full items-center justify-center bg-[#DCE9FF]">
                                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                    <path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#0052CC" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <span class="js-product-thumb-hint block min-h-[14px] w-full text-center text-[10px] leading-tight text-[#64748B]" data-product-id="{{ $product->id }}">@if ($primaryVisualState === 'pending')Image loading…@endif</span>
                                    </div>
                                    <div>
                                        <div class="font-inter font-medium text-[#0F172A] text-sm">{{ $product->name }}</div>
                                        <div class="text-[#94A3B8] text-xs">SKU: {{ $product->sku ?: 'Auto-generated' }}</div>
                                        @if ($product->brand)
                                            <div class="mt-0.5 text-[11px] text-[#94A3B8]">Brand <span class="text-[#64748B]">{{ $product->brand->name }}</span></div>
                                        @endif
                                        @if ($product->tags->isNotEmpty())
                                            @php
                                                $tagNames = $product->tags->pluck('name');
                                                $tagPreview = $tagNames->take(4)->implode(', ');
                                                $extraTagCount = max(0, $tagNames->count() - 4);
                                            @endphp
                                            <p class="mt-0.5 max-w-[16rem] truncate text-[11px] text-[#94A3B8]" title="{{ $tagNames->implode(', ') }}">Tags: {{ $tagPreview }}@if ($extraTagCount > 0) +{{ $extraTagCount }}@endif</p>
                                        @endif
                                        @if (! empty($detailChips))
                                            <div class="mt-1 flex max-w-[18rem] flex-wrap gap-1">
                                                @foreach ($detailChips as $chip)
                                                    <span class="inline-flex max-w-full items-center truncate rounded-md border border-[#BFDBFE] bg-[#EFF6FF] px-2 py-0.5 text-[10px] font-semibold text-[#1E40AF]" title="{{ $chip['label'] }}: {{ $chip['text'] }}">
                                                        <span class="shrink-0 text-[#64748B]">{{ $chip['label'] }}:</span>
                                                        <span class="ml-0.5 truncate">{{ $chip['text'] }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if ($product->categories->isNotEmpty())
                                    <div class="js-product-categories flex max-w-[14rem] flex-wrap gap-1" data-product-id="{{ $product->id }}">
                                        @foreach ($product->categories as $cat)
                                            <span class="js-category-chip group inline-flex max-w-full items-center gap-0.5 rounded-md border border-[#99F6E4] bg-[#F0FDFA] py-0.5 pl-2 pr-1 text-[11px] font-semibold text-[#0F766E]" data-category-id="{{ $cat->id }}" title="{{ $cat->name }}">
                                                <span class="max-w-[6rem] truncate">{{ $cat->name }}</span>
                                                @if ($canManageBrands && ! $isDeletedView)
                                                    <button
                                                        type="button"
                                                        class="js-detach-category inline-flex h-4 w-4 shrink-0 items-center justify-center rounded text-[#0F766E]/70 transition hover:bg-[#CCFBF1] hover:text-[#115E59]"
                                                        data-url="{{ route('products.inline.detach-category', ['product' => $product->id, 'category' => $cat->id]) }}"
                                                        aria-label="Remove {{ $cat->name }}"
                                                        title="Remove category"
                                                    >
                                                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M2 2L8 8M8 2L2 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                    </button>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="js-product-categories-empty text-xs text-[#94A3B8]" data-product-id="{{ $product->id }}">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs" title="Fulfillment / behavior type">{{ \App\Support\ProductTypeBehavior::productTypeLabel($product->product_type, trim((string) (($product->meta['custom_product_type_label'] ?? '')))) }}</span></td>
                            <td class="px-4 py-4">
                                @if ($stockState === 'out')
                                    <span class="js-row-status-badge inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#EF4444" /></svg>Out of Stock</span>
                                @elseif ($stockState === 'low')
                                    <span class="js-row-status-badge inline-flex items-center gap-1.5 bg-orange-50 text-orange-500 text-xs font-bold px-3 py-1 rounded-full border border-orange-100"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#F97316" /></svg>Low Stock</span>
                                @elseif ($product->status)
                                    <span class="js-row-status-badge inline-flex items-center gap-1.5 bg-green-50 text-green-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#22C55E" /></svg>Published</span>
                                @else
                                    <span class="js-row-status-badge inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#64748B" /></svg>Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $rowCurrency = $product->store?->currency ?? ($selectedStore->currency ?? 'USD');
                                    $rowPrice = (float) $product->base_price;
                                @endphp
                                @if ($canManageBrands && ! $isDeletedView)
                                    <div
                                        class="js-inline-edit js-inline-price group relative min-w-[5.5rem]"
                                        data-inline-kind="price"
                                        data-editing="0"
                                        data-url="{{ route('products.inline.price', $product) }}"
                                        data-currency="{{ $rowCurrency }}"
                                    >
                                        <button type="button" class="js-inline-edit-display js-inline-price-display rounded-md px-1.5 py-1 text-left font-inter text-sm font-medium text-[#0F172A] transition hover:bg-[#EEF4FF] hover:text-[#0052CC]" title="Click to edit price">
                                            {{ $rowCurrency }}{{ number_format($rowPrice, 2) }}
                                        </button>
                                        <input type="number" step="0.01" min="0" inputmode="decimal" class="js-inline-edit-input js-inline-price-input absolute inset-0 hidden h-9 w-28 rounded-md border border-brand bg-white px-2 text-sm font-medium text-[#0F172A] shadow-sm focus:outline-none focus:ring-2 focus:ring-brand/20" value="{{ number_format($rowPrice, 2, '.', '') }}" aria-label="Edit price" style="display:none">
                                    </div>
                                @else
                                    <span class="font-inter text-sm font-medium text-[#0F172A]">{{ $rowCurrency }}{{ number_format($rowPrice, 2) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 w-28">
                                <div class="js-stock-bar bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden" data-alert="{{ $alertLevel }}">
                                    <div class="js-stock-bar-fill h-full rounded-full {{ $stockState === 'out' ? 'bg-[#E2E8F0]' : ($stockState === 'low' ? 'bg-[#F97316]' : 'bg-[#3B82F6]') }}" style="width:{{ $stockWidth }}%"></div>
                                </div>
                            </td>
                            <td class="px-4 py-4 w-20">
                                @if ($canManageBrands && ! $isDeletedView)
                                    @if ($variantCount > 1)
                                        <div
                                            class="js-inline-stock js-inline-variant-stock relative"
                                            data-variant-count="{{ $variantCount }}"
                                            data-alert="{{ $alertLevel }}"
                                            data-published="{{ $product->status ? '1' : '0' }}"
                                            data-url="{{ route('products.inline.variant-stocks', $product) }}"
                                            data-product-id="{{ $product->id }}"
                                        >
                                            <button type="button" class="js-inline-variant-stock-open rounded-md px-1.5 py-1 text-left text-sm font-semibold text-[#475569] transition hover:bg-[#EEF4FF] hover:text-[#0052CC]" title="Edit stock for each option">
                                                <span class="js-inline-stock-value tabular-nums">{{ $inventory }}</span>
                                                <span class="mt-0.5 block text-[10px] font-medium text-[#0052CC]">{{ $variantCount }} options · edit</span>
                                            </button>
                                        </div>
                                    @else
                                        <div
                                            class="js-inline-edit js-inline-stock relative"
                                            data-inline-kind="stock"
                                            data-editing="0"
                                            data-url="{{ route('products.inline.stock', $product) }}"
                                            data-variant-count="1"
                                            data-alert="{{ $alertLevel }}"
                                            data-published="{{ $product->status ? '1' : '0' }}"
                                        >
                                            <button type="button" class="js-inline-edit-display js-inline-stock-display rounded-md px-1.5 py-1 text-left text-sm font-semibold text-[#475569] transition hover:bg-[#EEF4FF] hover:text-[#0052CC]" title="Click to edit stock">
                                                <span class="js-inline-stock-value tabular-nums">{{ $inventory }}</span>
                                            </button>
                                            <input type="number" step="1" min="0" inputmode="numeric" class="js-inline-edit-input js-inline-stock-input absolute inset-0 hidden h-9 w-20 rounded-md border border-brand bg-white px-2 text-sm font-semibold text-[#0F172A] shadow-sm focus:outline-none focus:ring-2 focus:ring-brand/20" value="{{ $defaultVariantStock }}" aria-label="Edit stock" style="display:none">
                                        </div>
                                    @endif
                                @else
                                    <span class="text-sm font-semibold text-[#475569] tabular-nums">{{ $inventory }}</span>
                                    @if ($variantCount > 1)
                                        <span class="mt-0.5 block text-[10px] font-medium text-[#94A3B8]">{{ $variantCount }} options</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($isDeletedView)
                                        @if ($canManageBrands)
                                            <form method="POST" action="{{ route('product.restore', ['productId' => $product->id]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-[#BBF7D0] bg-[#F0FDF4] px-3 py-2 text-xs font-semibold text-[#166534] hover:bg-[#DCFCE7]">Undo delete</button>
                                            </form>
                                            <form method="POST" action="{{ route('product.force-destroy', ['productId' => $product->id]) }}" class="inline" onsubmit="return confirm('Permanently delete this product? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-3 py-2 text-xs font-semibold text-[#B42318] hover:bg-[#FEEBEC]">Permanently delete</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-[#94A3B8]">Deleted</span>
                                        @endif
                                    @else
                                        <a href="{{ route('products.show', $product) }}" class="inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569] hover:bg-[#F8FAFC]">View</a>
                                        <a href="{{ route('products.edit', $product) }}" class="js-product-edit-payload inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0052CC] hover:bg-[#EEF4FF]" data-product-id="{{ $product->id }}" data-product='@json($productActionPayload)'>Edit</a>
                                        <button type="button" class="js-open-delete-product-modal inline-flex items-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-3 py-2 text-xs font-semibold text-[#B42318] hover:bg-[#FEEBEC]" data-product-id="{{ $product->id }}" data-product='@json($productActionPayload)'>Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                @if ($isDeletedView)
                                    <p class="text-sm font-semibold text-[#0F172A]">No deleted products</p>
                                    <p class="mt-1 text-sm text-[#64748B]">Deleted products appear here so you can undo delete or permanently remove them.</p>
                                @elseif ($isGenuinelyEmptyCatalog)
                                    <p class="text-sm font-semibold text-[#0F172A]">Add your first product</p>
                                    <p class="mt-1 text-sm text-[#64748B]">Create a product in the product workspace or import a catalog file to get started.</p>
                                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                                        <a href="{{ route('products.create') }}" class="inline-flex items-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-hover">Add product</a>
                                        @if ($canManageBrands)
                                            <a href="{{ route('products.import.create') }}" class="inline-flex items-center rounded-lg border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-semibold text-[#334155] hover:bg-[#F8FAFC]">Import products</a>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-sm font-semibold text-[#0F172A]">No products match these filters</p>
                                    <p class="mt-1 text-sm text-[#64748B]">Try a different search term or clear your filters.</p>
                                    <a href="{{ route('products') }}" class="mt-4 inline-flex items-center text-sm font-semibold text-[#0052CC] hover:underline">Clear filters</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-[#E2E8F0] px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                <span class="text-sm text-[#64748B]">Showing <span class="font-semibold text-[#0F172A]">{{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }}</span> of <span class="font-semibold text-[#0F172A]">{{ number_format($products->total()) }}</span></span>
                <form method="GET" action="{{ route('products') }}" class="inline-flex items-center gap-2">
                    <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                    <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                    <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                    <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                    <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                    <input type="hidden" name="attribute_term" value="{{ $filters['attribute_term'] ?? '' }}">
                    <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                    <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                    <input type="hidden" name="view" value="{{ $catalogView }}">
                    <label for="products-per-page" class="text-xs font-semibold text-[#64748B]">Per page</label>
                    <select id="products-per-page" name="per_page" onchange="this.form.submit()" class="rounded-lg border border-[#E2E8F0] bg-white px-2 py-1.5 text-xs font-semibold text-[#334155]">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <nav class="flex flex-wrap items-center gap-1.5" aria-label="Product pagination">
                @if ($paginationCurrent > 1)
                    <a href="{{ $products->url(1) }}" class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm font-medium text-[#475569] transition hover:bg-gray-50">First</a>
                    <a href="{{ $products->previousPageUrl() }}" class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-medium text-[#475569] transition hover:bg-gray-50">Previous</a>
                @else
                    <span class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm font-medium text-[#94A3B8]">First</span>
                    <span class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-medium text-[#94A3B8]">Previous</span>
                @endif

                @if ($paginationStart > 1)
                    <a href="{{ $products->url(1) }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-sm font-medium text-[#475569] hover:bg-gray-50">1</a>
                    @if ($paginationStart > 2)
                        <span class="px-1 text-sm text-[#94A3B8]">…</span>
                    @endif
                @endif

                @for ($page = $paginationStart; $page <= $paginationEnd; $page++)
                    @if ($page === $paginationCurrent)
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand text-sm font-bold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $products->url($page) }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-sm font-medium text-[#475569] hover:bg-gray-50">{{ $page }}</a>
                    @endif
                @endfor

                @if ($paginationEnd < $paginationLast)
                    @if ($paginationEnd < $paginationLast - 1)
                        <span class="px-1 text-sm text-[#94A3B8]">…</span>
                    @endif
                    <a href="{{ $products->url($paginationLast) }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-sm font-medium text-[#475569] hover:bg-gray-50">{{ $paginationLast }}</a>
                @endif

                @if ($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-medium text-[#475569] transition hover:bg-gray-50">Next</a>
                    <a href="{{ $products->url($paginationLast) }}" class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm font-medium text-[#475569] transition hover:bg-gray-50">Last</a>
                @else
                    <span class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-medium text-[#94A3B8]">Next</span>
                    <span class="rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm font-medium text-[#94A3B8]">Last</span>
                @endif
            </nav>
        </div>
    </div>

    <div id="inline-variant-stock-popover" class="fixed z-[80] hidden w-[22rem] max-w-[calc(100vw-1.5rem)] rounded-2xl border border-[#D8E8E1] bg-white p-4 shadow-xl shadow-slate-300/40" role="dialog" aria-label="Edit stock by option">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <p id="inline-variant-stock-title" class="text-sm font-semibold text-[#0A4335]">Stock by option</p>
                <p class="mt-0.5 text-xs text-[#64748B]">Set how many units you have for each option, then save.</p>
            </div>
            <button type="button" id="inline-variant-stock-cancel" class="rounded-lg px-2 py-1 text-xs font-semibold text-[#64748B] hover:bg-[#F8FAFC]">Close</button>
        </div>
        <div id="inline-variant-stock-rows" class="max-h-72 space-y-2 overflow-y-auto"></div>
        <div class="mt-4 flex justify-end gap-2">
            <button type="button" id="inline-variant-stock-save" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white hover:bg-brand-hover">Save stock</button>
        </div>
    </div>

    @include('user_view.partials.product_edit_modal', [
        'catalogBrands' => $catalogBrands ?? collect(),
        'catalogTags' => $catalogTags ?? collect(),
        'catalogTaxonomyCategories' => $catalogTaxonomyCategories ?? collect(),
        'catalogAttributes' => $catalogAttributes ?? collect(),
        'shippingPreferences' => [
            'fallback_item_weight' => $shippingWeightFallback ?? null,
            'weight_unit' => $shippingWeightUnit ?? 'LB',
        ],
    ])

    @if ($canManageBrands)
        <div id="bulk-confirm-shell" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="bulk-confirm-title">
            <div class="ui-modal-panel ui-modal-panel--md p-6">
                <h3 id="bulk-confirm-title" class="text-lg font-semibold text-[#0F172A]">Confirm change</h3>
                <p id="bulk-confirm-body" class="mt-2 text-sm leading-relaxed text-[#475569]"></p>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" id="bulk-confirm-cancel" class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                    <button type="button" id="bulk-confirm-ok" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-hover">Confirm</button>
                </div>
            </div>
        </div>
    @endif

    @if ($canManageBrands || $canManageTags || $canManageCategories)
        @include('user_view.partials.catalog_tools_modal', [
            'managementBrands' => $managementBrands ?? collect(),
            'managementTags' => $managementTags ?? collect(),
            'managementCategories' => $managementCategories ?? collect(),
            'canManageBrands' => $canManageBrands,
            'canManageTags' => $canManageTags,
            'canManageCategories' => $canManageCategories,
            'openCatalogToolsShell' => $openCatalogToolsShell,
            'catalogToolsDefaultTab' => $catalogToolsDefaultTab,
        ])
    @endif

    <script>
        window.__bulkSelectableProductIds = @json($bulkSelectableProductIds ?? []);
        window.__bulkMatchingCount = @json((int) ($bulkMatchingCount ?? count($bulkSelectableProductIds ?? [])));
        (() => {
            const bulkForm = document.getElementById('bulk-products-form');
            if (!bulkForm) {
                return;
            }

            const allMatchingIds = Array.isArray(window.__bulkSelectableProductIds) ? window.__bulkSelectableProductIds.map(String) : [];
            const allMatchingCount = Number(window.__bulkMatchingCount) || allMatchingIds.length;
            let bulkAllMode = false;
            let bulkAllSelection = new Set();

            const selectAll = document.getElementById('selectAllProducts');
            const rowCheckboxes = [...document.querySelectorAll('.js-product-row-checkbox')];
            const toolbar = document.getElementById('bulk-catalog-toolbar');
            const countEl = document.getElementById('bulk-selected-count');
            const actionSelect = document.getElementById('bulk-action-select');
            const actionChips = [...document.querySelectorAll('.js-bulk-action-chip')];
            const optionsPanel = document.getElementById('bulk-options-panel');
            const extraStock = document.getElementById('bulk-extra-stock');
            const extraCategories = document.getElementById('bulk-extra-categories');
            const extraBrand = document.getElementById('bulk-extra-brand');
            const extraTags = document.getElementById('bulk-extra-tags');
            const extraStatus = document.getElementById('bulk-extra-status');
            const extraShippingWeight = document.getElementById('bulk-extra-shipping-weight');
            const extraSimple = document.getElementById('bulk-extra-simple');
            const extraSimpleCopy = document.getElementById('bulk-extra-simple-copy');
            const applyBtn = document.getElementById('bulk-apply-btn');
            const applyLabel = document.getElementById('bulk-apply-label');
            const applySpinner = document.getElementById('bulk-apply-spinner');
            const cancelActionBtn = document.getElementById('bulk-cancel-action');
            const clearSelectionBtn = document.getElementById('bulk-clear-selection');
            const confirmShell = document.getElementById('bulk-confirm-shell');
            const confirmBody = document.getElementById('bulk-confirm-body');
            const confirmOk = document.getElementById('bulk-confirm-ok');
            const confirmCancel = document.getElementById('bulk-confirm-cancel');

            const chipIdle = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#CBD5E1] bg-white px-3.5 py-2 text-sm font-semibold text-[#334155] transition hover:border-[#94A3B8] hover:bg-[#F8FAFC]';
            const chipActive = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-brand bg-brand px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition';
            const chipDangerIdle = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#FECACA] bg-white px-3.5 py-2 text-sm font-semibold text-[#B91C1C] transition hover:border-[#FCA5A5] hover:bg-[#FFF5F5]';
            const chipDangerActive = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#DC2626] bg-[#DC2626] px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition';
            const chipSuccessIdle = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#BBF7D0] bg-white px-3.5 py-2 text-sm font-semibold text-[#166534] transition hover:border-[#86EFAC] hover:bg-[#F0FDF4]';
            const chipSuccessActive = 'js-bulk-action-chip inline-flex items-center gap-2 rounded-full border border-[#166534] bg-[#166534] px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition';

            function pageSelectedIds() {
                return rowCheckboxes.filter((c) => c.checked && !c.disabled).map((c) => c.getAttribute('data-product-id')).filter(Boolean);
            }

            function effectiveSelectedIds() {
                if (bulkAllMode && bulkAllSelection.size > 0) {
                    return [...bulkAllSelection];
                }
                return pageSelectedIds();
            }

            function resetActionUi() {
                if (actionSelect) actionSelect.value = '';
                actionChips.forEach((chip) => {
                    const action = chip.getAttribute('data-action') || '';
                    if (action === 'delete' || action === 'force_delete') {
                        chip.className = chipDangerIdle;
                    } else if (action === 'restore') {
                        chip.className = chipSuccessIdle;
                    } else {
                        chip.className = chipIdle;
                    }
                    chip.setAttribute('aria-pressed', 'false');
                });
                if (optionsPanel) optionsPanel.classList.add('hidden');
                [extraStock, extraCategories, extraBrand, extraTags, extraStatus, extraShippingWeight, extraSimple].forEach((el) => el && el.classList.add('hidden'));
            }

            function refreshBulkUi() {
                const ids = effectiveSelectedIds();
                const selectionKey = ids.join(',');
                if (selectionKey !== lastVariantPreviewSelectionKey) {
                    lastVariantPreviewSelectionKey = selectionKey;
                    resetVariantParsePreview();
                }
                if (countEl) {
                    countEl.textContent = String(ids.length);
                }
                const shippingCount = document.getElementById('bulk-shipping-weight-selected-count');
                if (shippingCount) {
                    shippingCount.textContent = String(ids.length);
                }
                const label = document.getElementById('bulk-selected-label');
                if (label) {
                    const noun = ids.length === 1 ? 'product selected' : 'products selected';
                    label.textContent = bulkAllMode
                        ? `${noun} (all ${allMatchingCount.toLocaleString()} matching)`
                        : noun;
                }
                if (toolbar) {
                    const show = ids.length > 0;
                    toolbar.classList.toggle('hidden', !show);
                    if (!show) {
                        resetActionUi();
                    }
                }
            }

            function clearSelection() {
                bulkAllMode = false;
                bulkAllSelection = new Set();
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
                if (selectAll) {
                    selectAll.checked = false;
                }
                refreshBulkUi();
            }

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    bulkAllMode = false;
                    bulkAllSelection = new Set();
                    rowCheckboxes.forEach((checkbox) => {
                        if (!checkbox.disabled) {
                            checkbox.checked = selectAll.checked;
                        }
                    });
                    refreshBulkUi();
                });
            }

            rowCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    bulkAllMode = false;
                    bulkAllSelection = new Set();
                    if (selectAll) {
                        const enabled = rowCheckboxes.filter((c) => !c.disabled);
                        selectAll.checked = enabled.length > 0 && enabled.every((rowCheckbox) => rowCheckbox.checked);
                    }
                    refreshBulkUi();
                });
            });

            clearSelectionBtn?.addEventListener('click', clearSelection);

            document.getElementById('bulk-select-all-matching')?.addEventListener('click', () => {
                if (!allMatchingIds.length) {
                    window.alert('No products in the current filtered list to select.');
                    return;
                }
                bulkAllMode = true;
                bulkAllSelection = new Set(allMatchingIds);
                rowCheckboxes.forEach((c) => {
                    const id = c.getAttribute('data-product-id');
                    c.checked = !!(id && bulkAllSelection.has(String(id)));
                });
                if (selectAll) {
                    const enabled = rowCheckboxes.filter((c) => !c.disabled);
                    selectAll.checked = enabled.length > 0 && enabled.every((rowCheckbox) => rowCheckbox.checked);
                }
                refreshBulkUi();
            });

            function toggleExtras() {
                const v = actionSelect ? actionSelect.value : '';
                [extraStock, extraCategories, extraBrand, extraTags, extraStatus, extraShippingWeight, extraSimple].forEach((el) => el && el.classList.add('hidden'));
                if (!v) {
                    if (optionsPanel) optionsPanel.classList.add('hidden');
                    return;
                }
                if (optionsPanel) optionsPanel.classList.remove('hidden');
                if (applyLabel) {
                    applyLabel.textContent = (v === 'delete' || v === 'force_delete') ? 'Confirm' : (v === 'shipping_weight' ? 'Apply weight' : 'Continue');
                }
                if (v === 'stock' && extraStock) {
                    extraStock.classList.remove('hidden');
                    refreshBulkStockApplyModeUi();
                }
                if (v === 'categories' && extraCategories) extraCategories.classList.remove('hidden');
                if (v === 'brand' && extraBrand) extraBrand.classList.remove('hidden');
                if (v === 'tags' && extraTags) extraTags.classList.remove('hidden');
                if (v === 'status' && extraStatus) extraStatus.classList.remove('hidden');
                if (v === 'shipping_weight' && extraShippingWeight) {
                    extraShippingWeight.classList.remove('hidden');
                    refreshShippingWeightPanels();
                    loadVariantOptionGroups();
                }
                if ((v === 'delete' || v === 'force_delete' || v === 'restore') && extraSimple) {
                    extraSimple.classList.remove('hidden');
                    if (extraSimpleCopy) {
                        if (v === 'delete') {
                            extraSimpleCopy.textContent = 'Selected products will move to Deleted products. You can undo delete later.';
                        } else if (v === 'force_delete') {
                            extraSimpleCopy.textContent = 'This permanently removes the selected products. This cannot be undone.';
                        } else {
                            extraSimpleCopy.textContent = 'Selected products will return to your catalog.';
                        }
                    }
                }
            }

            const bulkShippingPreviewUrl = @json(route('products.bulk.shipping-weight.preview'));
            const bulkShippingWeightUnit = @json($shippingWeightUnit ?? 'LB');
            const bulkShippingWeightMax = @json((float) ($shippingWeightMax ?? \App\Services\Delivery\StoreShippingPreferences::MAX_ITEM_WEIGHT));
            let cachedVariantOptionGroups = [];
            let variantParsePreviewConfirmed = false;
            let lastVariantPreviewSelectionKey = '';

            function refreshBulkStockApplyModeUi() {
                const mode = document.getElementById('bulk-stock-mode')?.value || 'set';
                const isSet = mode === 'set';
                document.getElementById('bulk-stock-apply-mode-fieldset')?.classList.toggle('hidden', !isSet);
                document.getElementById('bulk-stock-delta-apply-hint')?.classList.toggle('hidden', isSet);
            }

            document.getElementById('bulk-stock-mode')?.addEventListener('change', refreshBulkStockApplyModeUi);

            function shippingWeightTarget() {
                return document.querySelector('input[name="bulk_shipping_weight_target_ui"]:checked')?.value || 'products';
            }

            function variantBulkMode() {
                return document.querySelector('input[name="bulk_variant_bulk_mode_ui"]:checked')?.value || 'map_by_option';
            }

            function resetVariantParsePreview() {
                variantParsePreviewConfirmed = false;
                const preview = document.getElementById('bulk-variant-parse-preview');
                if (preview) {
                    preview.classList.add('hidden');
                    preview.replaceChildren();
                }
            }

            function refreshShippingWeightPanels() {
                const target = shippingWeightTarget();
                const productsPanel = document.getElementById('bulk-shipping-weight-products-panel');
                const variantsPanel = document.getElementById('bulk-shipping-weight-variants-panel');
                productsPanel?.classList.toggle('hidden', target !== 'products');
                variantsPanel?.classList.toggle('hidden', target !== 'variants');
                resetVariantParsePreview();
                if (target === 'variants') {
                    refreshVariantBulkModePanels();
                }
            }

            function refreshVariantBulkModePanels() {
                const mode = variantBulkMode();
                const optionPanel = document.getElementById('bulk-variant-option-panel');
                const mapPanel = document.getElementById('bulk-variant-map-panel');
                const parsePanel = document.getElementById('bulk-variant-parse-panel');
                const clearPanel = document.getElementById('bulk-variant-clear-panel');
                const applyModeFieldset = document.getElementById('bulk-variant-apply-mode-fieldset');
                const isClear = mode === 'clear';
                optionPanel?.classList.toggle('hidden', isClear);
                mapPanel?.classList.toggle('hidden', mode !== 'map_by_option');
                parsePanel?.classList.toggle('hidden', mode !== 'use_option_values');
                clearPanel?.classList.toggle('hidden', !isClear);
                applyModeFieldset?.classList.toggle('hidden', isClear);
                resetVariantParsePreview();
            }

            function collectVariantWeightMapFromUi() {
                const map = {};
                document.querySelectorAll('[data-bulk-variant-weight-row]').forEach((row) => {
                    const value = row.getAttribute('data-option-value') || '';
                    const input = row.querySelector('input[type="number"]');
                    const weight = input?.value;
                    if (value && weight !== '' && Number(weight) > 0) {
                        map[value] = Number(weight);
                    }
                });
                return map;
            }

            function renderVariantWeightMapRows(optionValues) {
                const container = document.getElementById('bulk-variant-weight-map-rows');
                if (!container) return;
                container.replaceChildren();
                if (!optionValues.length) {
                    const empty = document.createElement('p');
                    empty.className = 'text-xs text-[#64748B]';
                    empty.textContent = 'Choose an option group to load values from selected products.';
                    container.appendChild(empty);
                    return;
                }
                optionValues.forEach((value) => {
                    const row = document.createElement('div');
                    row.setAttribute('data-bulk-variant-weight-row', '1');
                    row.setAttribute('data-option-value', String(value));
                    row.className = 'flex items-center gap-3';

                    const label = document.createElement('span');
                    label.className = 'min-w-[6rem] text-sm font-medium text-[#0F172A]';
                    label.textContent = String(value);

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.min = '0.01';
                    input.max = String(bulkShippingWeightMax);
                    input.step = '0.001';
                    input.className = 'w-28 rounded-lg border border-[#CBD5E1] px-3 py-1.5 text-sm';
                    input.placeholder = 'Weight';

                    const unit = document.createElement('span');
                    unit.className = 'text-xs font-semibold text-[#64748B]';
                    unit.textContent = String(bulkShippingWeightUnit);

                    row.appendChild(label);
                    row.appendChild(input);
                    row.appendChild(unit);
                    container.appendChild(row);
                });
            }

            function populateVariantOptionGroupSelect(groups) {
                const select = document.getElementById('bulk-variant-option-group');
                if (!select) return;
                const previous = select.value;
                select.replaceChildren();
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Choose an option group…';
                select.appendChild(placeholder);
                groups.forEach((group) => {
                    const opt = document.createElement('option');
                    opt.value = group.name;
                    opt.textContent = `${group.name} (${group.product_count} products, ${group.variant_count} variants)`;
                    if (group.weight_related) {
                        opt.dataset.weightRelated = '1';
                    }
                    select.appendChild(opt);
                });
                if (previous && [...select.options].some((o) => o.value === previous)) {
                    select.value = previous;
                }
            }

            async function fetchVariantShippingPreview(extra = {}) {
                const ids = effectiveSelectedIds();
                const payload = {
                    product_ids_json: JSON.stringify(ids),
                    shipping_weight_target: 'variants',
                    variant_bulk_mode: variantBulkMode(),
                    variant_option_name: document.getElementById('bulk-variant-option-group')?.value || '',
                    shipping_weight_mode: document.querySelector('input[name="bulk_variant_shipping_weight_mode_ui"]:checked')?.value || 'missing_only',
                    variant_weight_map_json: JSON.stringify(collectVariantWeightMapFromUi()),
                    ...extra,
                };
                const token = document.querySelector('#bulk-products-form input[name="_token"]')?.value || '';
                const res = await fetch(bulkShippingPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Could not load preview.');
                }
                return res.json();
            }

            function renderVariantPreviewStats(data) {
                const el = document.getElementById('bulk-variant-preview-stats');
                if (!el || !data) return;
                el.classList.remove('hidden');
                el.replaceChildren();
                const lines = [
                    ['Selected products:', data.selected_products_count ?? 0],
                    ['Compatible with option:', data.compatible_products_count ?? 0],
                    ['No matching option:', data.incompatible_products_count ?? 0],
                    ['Matching variants:', data.matching_variants_count ?? 0],
                    ['Would update:', data.would_update_count ?? 0],
                ];
                lines.forEach(([label, value]) => {
                    const row = document.createElement('div');
                    const strong = document.createElement('span');
                    strong.className = 'font-semibold text-[#0F172A]';
                    strong.textContent = label + ' ';
                    row.appendChild(strong);
                    row.appendChild(document.createTextNode(String(value)));
                    el.appendChild(row);
                });
                if (data.would_skip_existing_count) {
                    const skip = document.createElement('div');
                    skip.textContent = `${data.would_skip_existing_count} variant(s) already have weights and would be skipped.`;
                    el.appendChild(skip);
                }
            }

            async function loadVariantOptionGroups() {
                if (shippingWeightTarget() !== 'variants') return;
                try {
                    const data = await fetchVariantShippingPreview({ variant_bulk_mode: 'map_by_option', variant_option_name: '' });
                    cachedVariantOptionGroups = Array.isArray(data.option_groups) ? data.option_groups : [];
                    populateVariantOptionGroupSelect(cachedVariantOptionGroups);
                } catch (error) {
                    cachedVariantOptionGroups = [];
                }
            }

            async function refreshVariantOptionValues() {
                resetVariantParsePreview();
                const groupName = document.getElementById('bulk-variant-option-group')?.value || '';
                const group = cachedVariantOptionGroups.find((g) => g.name === groupName);
                const values = group?.option_values || [];
                renderVariantWeightMapRows(values);
                if (groupName) {
                    try {
                        const data = await fetchVariantShippingPreview();
                        renderVariantPreviewStats(data);
                    } catch (error) {
                        document.getElementById('bulk-variant-preview-stats')?.classList.add('hidden');
                    }
                }
            }

            document.querySelectorAll('input[name="bulk_shipping_weight_target_ui"]').forEach((input) => {
                input.addEventListener('change', () => {
                    resetVariantParsePreview();
                    refreshShippingWeightPanels();
                    if (shippingWeightTarget() === 'variants') {
                        loadVariantOptionGroups();
                    }
                });
            });
            document.querySelectorAll('input[name="bulk_variant_bulk_mode_ui"]').forEach((input) => {
                input.addEventListener('change', () => {
                    refreshVariantBulkModePanels();
                    refreshVariantOptionValues();
                });
            });
            document.querySelectorAll('input[name="bulk_variant_shipping_weight_mode_ui"]').forEach((input) => {
                input.addEventListener('change', () => {
                    resetVariantParsePreview();
                    refreshVariantOptionValues();
                });
            });
            document.getElementById('bulk-variant-option-group')?.addEventListener('change', refreshVariantOptionValues);
            document.getElementById('bulk-variant-preview-parse')?.addEventListener('click', async () => {
                const groupName = document.getElementById('bulk-variant-option-group')?.value || '';
                if (!groupName) {
                    window.alert('Choose an option group first.');
                    return;
                }
                try {
                    const data = await fetchVariantShippingPreview({ variant_bulk_mode: 'use_option_values' });
                    renderVariantPreviewStats(data);
                    const preview = document.getElementById('bulk-variant-parse-preview');
                    if (!preview) return;
                    preview.replaceChildren();
                    const heading = document.createElement('p');
                    heading.className = 'mb-2 font-semibold text-[#0F172A]';
                    heading.textContent = 'Detected option: ' + groupName;
                    preview.appendChild(heading);
                    const parsed = Array.isArray(data.parsed_weights) ? data.parsed_weights : [];
                    if (!parsed.length) {
                        const empty = document.createElement('p');
                        empty.textContent = 'No parseable values found.';
                        preview.appendChild(empty);
                    } else {
                        parsed.forEach((row) => {
                            const line = document.createElement('div');
                            line.className = 'flex justify-between gap-3 py-1';
                            const left = document.createElement('span');
                            left.textContent = String(row.option_value ?? '');
                            const right = document.createElement('span');
                            right.className = 'font-semibold';
                            right.textContent = row.parse_ok
                                ? `${Number(row.parsed_weight).toFixed(2)} ${data.weight_unit || bulkShippingWeightUnit}`
                                : 'Could not parse';
                            line.appendChild(left);
                            line.appendChild(right);
                            preview.appendChild(line);
                        });
                    }
                    preview.classList.remove('hidden');
                    variantParsePreviewConfirmed = parsed.some((row) => row.parse_ok);
                } catch (error) {
                    window.alert(error.message || 'Preview failed.');
                }
            });

            function selectAction(action) {
                if (!actionSelect) return;
                actionSelect.value = action;
                actionChips.forEach((chip) => {
                    const chipAction = chip.getAttribute('data-action') || '';
                    const selected = chipAction === action;
                    chip.setAttribute('aria-pressed', selected ? 'true' : 'false');
                    if (chipAction === 'delete' || chipAction === 'force_delete') {
                        chip.className = selected ? chipDangerActive : chipDangerIdle;
                    } else if (chipAction === 'restore') {
                        chip.className = selected ? chipSuccessActive : chipSuccessIdle;
                    } else {
                        chip.className = selected ? chipActive : chipIdle;
                    }
                });
                toggleExtras();
            }

            actionChips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const action = chip.getAttribute('data-action') || '';
                    if (!action) return;
                    if (actionSelect && actionSelect.value === action) {
                        resetActionUi();
                        return;
                    }
                    selectAction(action);
                });
            });

            cancelActionBtn?.addEventListener('click', resetActionUi);
            actionSelect?.addEventListener('change', toggleExtras);
            toggleExtras();

            function setMultiHidden(container, name, selectedValues) {
                if (!container) return;
                container.innerHTML = '';
                selectedValues.forEach((val) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = name;
                    inp.value = String(val);
                    container.appendChild(inp);
                });
            }

            let confirmAction = null;

            function closeConfirm() {
                if (!confirmShell) return;
                confirmShell.classList.add('hidden');
                confirmShell.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                confirmAction = null;
            }

            function openConfirm(bodyHtml, onConfirm) {
                if (!confirmShell || !confirmBody) {
                    onConfirm();
                    return;
                }
                confirmBody.textContent = bodyHtml;
                confirmAction = onConfirm;
                confirmShell.classList.remove('hidden');
                confirmShell.classList.add('flex');
                document.body.classList.add('overflow-hidden');
            }

            confirmCancel?.addEventListener('click', closeConfirm);
            confirmOk?.addEventListener('click', () => {
                const fn = confirmAction;
                closeConfirm();
                if (typeof fn === 'function') {
                    fn();
                }
            });
            confirmShell?.addEventListener('click', (event) => {
                if (event.target === confirmShell) {
                    closeConfirm();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && confirmShell && !confirmShell.classList.contains('hidden')) {
                    closeConfirm();
                }
            });

            function prepareAndSubmit(action, ids) {
                document.getElementById('bulk-form-action').value = action;
                const pidWrap = document.getElementById('bulk-form-product-id-inputs');
                pidWrap.innerHTML = '';
                // Single JSON field avoids PHP max_input_vars truncation on large selections.
                const idsJson = document.getElementById('bulk-form-product-ids-json');
                if (idsJson) {
                    idsJson.value = JSON.stringify(ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0));
                }
                document.getElementById('bulk-form-stock-mode').value = '';
                document.getElementById('bulk-form-stock-value').value = '';
                const scopeEl = document.getElementById('bulk-form-stock-variant-scope');
                if (scopeEl) scopeEl.value = 'default_variant_only';
                const applyModeEl = document.getElementById('bulk-form-stock-apply-mode');
                if (applyModeEl) applyModeEl.value = 'empty_only';
                document.getElementById('bulk-form-brand-id').value = '';
                document.getElementById('bulk-form-product-status').value = '';
                const swValue = document.getElementById('bulk-form-shipping-weight-value');
                const swMode = document.getElementById('bulk-form-shipping-weight-mode');
                const swTarget = document.getElementById('bulk-form-shipping-weight-target');
                const swVariantMode = document.getElementById('bulk-form-variant-bulk-mode');
                const swVariantOption = document.getElementById('bulk-form-variant-option-name');
                const swVariantMap = document.getElementById('bulk-form-variant-weight-map-json');
                if (swValue) swValue.value = '';
                if (swMode) swMode.value = 'missing_only';
                if (swTarget) swTarget.value = 'products';
                if (swVariantMode) swVariantMode.value = 'map_by_option';
                if (swVariantOption) swVariantOption.value = '';
                if (swVariantMap) swVariantMap.value = '';
                setMultiHidden(document.getElementById('bulk-form-category-inputs'), 'category_ids[]', []);
                setMultiHidden(document.getElementById('bulk-form-tag-inputs'), 'tag_ids[]', []);

                if (action === 'stock') {
                    const mode = document.getElementById('bulk-stock-mode')?.value || 'set';
                    const val = document.getElementById('bulk-stock-value')?.value;
                    document.getElementById('bulk-form-stock-mode').value = mode;
                    document.getElementById('bulk-form-stock-value').value = String(val);
                    const sc = document.getElementById('bulk-stock-variant-scope')?.value || 'default_variant_only';
                    const scopeHidden = document.getElementById('bulk-form-stock-variant-scope');
                    if (scopeHidden) scopeHidden.value = sc;
                    const applyHidden = document.getElementById('bulk-form-stock-apply-mode');
                    if (applyHidden) {
                        applyHidden.value = mode === 'set'
                            ? (document.querySelector('input[name="bulk_stock_apply_mode_ui"]:checked')?.value || 'empty_only')
                            : 'replace_all';
                    }
                }
                if (action === 'categories') {
                    const sel = document.getElementById('bulk-category-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    setMultiHidden(document.getElementById('bulk-form-category-inputs'), 'category_ids[]', picked);
                }
                if (action === 'brand') {
                    document.getElementById('bulk-form-brand-id').value = document.getElementById('bulk-brand-id')?.value || '';
                }
                if (action === 'tags') {
                    const sel = document.getElementById('bulk-tag-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    setMultiHidden(document.getElementById('bulk-form-tag-inputs'), 'tag_ids[]', picked);
                }
                if (action === 'status') {
                    document.getElementById('bulk-form-product-status').value = document.getElementById('bulk-status-value')?.value || 'published';
                }
                if (action === 'shipping_weight') {
                    const target = shippingWeightTarget();
                    if (swTarget) swTarget.value = target;
                    if (target === 'products') {
                        const val = document.getElementById('bulk-shipping-weight-value')?.value;
                        const mode = document.querySelector('input[name="bulk_shipping_weight_mode_ui"]:checked')?.value || 'missing_only';
                        if (swValue) swValue.value = String(val ?? '');
                        if (swMode) swMode.value = mode;
                    } else {
                        const vMode = variantBulkMode();
                        const vApplyMode = document.querySelector('input[name="bulk_variant_shipping_weight_mode_ui"]:checked')?.value || 'missing_only';
                        if (swVariantMode) swVariantMode.value = vMode;
                        if (swMode) swMode.value = vApplyMode;
                        if (swVariantOption) swVariantOption.value = document.getElementById('bulk-variant-option-group')?.value || '';
                        if (swVariantMap) swVariantMap.value = JSON.stringify(collectVariantWeightMapFromUi());
                    }
                }
                if (applyBtn) applyBtn.disabled = true;
                if (applySpinner) applySpinner.classList.remove('hidden');
                bulkForm.submit();
            }

            applyBtn?.addEventListener('click', () => {
                const ids = effectiveSelectedIds();
                if (ids.length === 0) {
                    window.alert('Select at least one product.');
                    return;
                }
                const action = actionSelect?.value || '';
                if (!action) {
                    window.alert('Choose what you want to do with the selected products.');
                    return;
                }
                if (action === 'stock') {
                    const val = document.getElementById('bulk-stock-value')?.value;
                    if (val === '' || val === undefined) {
                        window.alert('Enter a stock quantity.');
                        return;
                    }
                }
                if (action === 'categories') {
                    const sel = document.getElementById('bulk-category-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    if (picked.length === 0) {
                        window.alert('Select one or more categories.');
                        return;
                    }
                }
                if (action === 'brand') {
                    const bid = document.getElementById('bulk-brand-id')?.value;
                    if (!bid) {
                        window.alert('Choose a brand.');
                        return;
                    }
                }
                if (action === 'tags') {
                    const sel = document.getElementById('bulk-tag-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    if (picked.length === 0) {
                        window.alert('Select one or more tags.');
                        return;
                    }
                }
                if (action === 'shipping_weight') {
                    const target = shippingWeightTarget();
                    if (target === 'products') {
                        const val = document.getElementById('bulk-shipping-weight-value')?.value;
                        if (val === '' || val === undefined || Number(val) <= 0) {
                            window.alert('Enter a shipping weight greater than zero.');
                            return;
                        }
                    } else {
                        const vMode = variantBulkMode();
                        if (vMode !== 'clear') {
                            const group = document.getElementById('bulk-variant-option-group')?.value || '';
                            if (!group) {
                                window.alert('Choose an option group for variant weights.');
                                return;
                            }
                        }
                        if (vMode === 'map_by_option') {
                            const map = collectVariantWeightMapFromUi();
                            if (Object.keys(map).length === 0) {
                                window.alert('Enter at least one option value weight.');
                                return;
                            }
                        }
                        if (vMode === 'use_option_values' && !variantParsePreviewConfirmed) {
                            window.alert('Preview parsed weights and confirm they look correct before applying.');
                            return;
                        }
                    }
                }

                const n = ids.length;
                let msg = `Apply this change to ${n} product(s)?`;
                if (action === 'delete') {
                    msg = `Delete ${n} product(s)? You can undo this later from Deleted products.`;
                }
                if (action === 'restore') {
                    msg = `Undo delete for ${n} product(s) and put them back in your catalog?`;
                }
                if (action === 'force_delete') {
                    msg = `Permanently delete ${n} product(s)? This cannot be undone.`;
                }
                if (action === 'stock') {
                    const mode = document.getElementById('bulk-stock-mode')?.value || 'set';
                    const val = document.getElementById('bulk-stock-value')?.value;
                    const scope = document.getElementById('bulk-stock-variant-scope')?.value || 'default_variant_only';
                    const applyMode = document.querySelector('input[name="bulk_stock_apply_mode_ui"]:checked')?.value || 'empty_only';
                    const scopeExplain = scope === 'all_variants_same'
                        ? 'Products with multiple variants will all get this same quantity.'
                        : scope === 'skip_multi_variant'
                            ? 'Products with multiple variants will be skipped.'
                            : 'Only the main variant is updated on multi-variant products.';
                    let modeExplain;
                    if (mode === 'delta') {
                        modeExplain = `Stock will change by ${val} units on each affected product.`;
                    } else if (applyMode === 'empty_only') {
                        modeExplain = `Stock will be set to ${val} only on inventory rows that currently have 0. Products that already have stock will be skipped.`;
                    } else {
                        modeExplain = `Stock will be set to ${val} on every selected product, including those that already have stock.`;
                    }
                    msg = `${modeExplain} ${scopeExplain} Continue for ${n} selected product(s)?`;
                }
                if (action === 'status') {
                    const status = document.getElementById('bulk-status-value')?.value || 'published';
                    msg = status === 'draft'
                        ? `Mark ${n} product(s) as draft (hidden for now)?`
                        : `Publish ${n} product(s) so they are ready to sell?`;
                }
                if (action === 'shipping_weight') {
                    const target = shippingWeightTarget();
                    if (target === 'variants') {
                        const vMode = variantBulkMode();
                        if (vMode === 'clear') {
                            msg = `Clear variant shipping weight overrides on selected products? Product weights will not change. Continue for ${n} product(s)?`;
                        } else if (vMode === 'use_option_values') {
                            msg = `Apply parsed option values as variant shipping weights? Product weights will not change. Continue for ${n} product(s)?`;
                        } else {
                            msg = `Apply variant shipping weights by option value? Product weights will not change. Continue for ${n} product(s)?`;
                        }
                    } else {
                        msg = `Set product shipping weight on ${n} selected product(s)? Variant overrides will stay unchanged.`;
                    }
                }

                openConfirm(msg, () => prepareAndSubmit(action, ids));
            });

            refreshBulkUi();
        })();
    </script>
    <script>
        (function () {
            const pollUrl = @json(route('products.primary-images'));
            const thumbs = Array.from(document.querySelectorAll('.js-product-primary-thumb'));
            if (!thumbs.some(function (el) { return el.dataset.state === 'pending'; })) {
                return;
            }
            const ids = Array.from(new Set(thumbs.map(function (el) { return el.dataset.productId; }).filter(Boolean)));
            if (!ids.length) {
                return;
            }
            function setHint(productId, text) {
                var hint = document.querySelector('.js-product-thumb-hint[data-product-id="' + productId + '"]');
                if (hint) {
                    hint.textContent = text || '';
                }
            }
            function setThumbFailed(el) {
                el.dataset.state = 'failed';
                el.innerHTML = '<div class="relative flex h-full w-full items-center justify-center bg-[#FEF2F2]" title="Image could not be loaded">' +
                    '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" class="text-[#94A3B8]"><path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="currentColor" /></svg>' +
                    '<span class="absolute bottom-0.5 right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-amber-500 text-[8px] font-bold text-white">!</span></div>';
            }
            function setThumbPending(el) {
                el.dataset.state = 'pending';
                el.innerHTML = '<div class="relative flex h-full w-full items-center justify-center product-thumb-skeleton">' +
                    '<span class="product-thumb-spinner absolute h-5 w-5 rounded-full" aria-hidden="true"></span></div>';
            }
            function tick() {
                return fetch(pollUrl + '?ids=' + encodeURIComponent(ids.join(',')), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (data) {
                        if (!data || !data.products) {
                            return;
                        }
                        var map = data.products;
                        thumbs.forEach(function (el) {
                            var id = el.dataset.productId;
                            var row = map[id];
                            if (!row) {
                                return;
                            }
                            if (row.state === 'ready' && row.url) {
                                el.dataset.state = 'ready';
                                el.dataset.url = row.url;
                                el.textContent = '';
                                var img = document.createElement('img');
                                img.src = row.url;
                                img.alt = '';
                                img.className = 'h-10 w-10 object-cover';
                                el.appendChild(img);
                                setHint(id, '');
                                return;
                            }
                            if (row.state === 'pending') {
                                setThumbPending(el);
                                setHint(id, 'Image loading…');
                                return;
                            }
                            if (row.state === 'failed') {
                                setThumbFailed(el);
                                setHint(id, '');
                            }
                        });
                    })
                    .catch(function () {});
            }
            function schedule() {
                if (!document.querySelector('.js-product-primary-thumb[data-state="pending"]')) {
                    return;
                }
                tick().finally(function () {
                    setTimeout(schedule, 4000);
                });
            }
            setTimeout(schedule, 4000);
        })();
    </script>
@endsection
