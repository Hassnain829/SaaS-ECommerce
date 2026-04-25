@extends('layouts.user.user-sidebar')

@section('title', 'Products Admin - BaaS Core')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', 'E-commerce Portal')

@php
    use App\Support\ProductCustomFieldHelper;
    use App\Support\ProductDetailPresenter;
    use App\Support\ProductEditPayload;
    $baseFilters = [
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'product_type' => $filters['product_type'] ?? '',
        'status' => $filters['status'] ?? '',
        'stock' => $filters['stock'] ?? '',
        'sort' => $filters['sort'] ?? 'latest',
        'brand' => $filters['brand'] ?? '',
        'tag' => $filters['tag'] ?? '',
        'cf_key' => $filters['cf_key'] ?? '',
        'cf_value' => $filters['cf_value'] ?? '',
    ];

    $openProductModal = request()->boolean('openAddProduct') || old('_open_add_product_modal');
    $brandCount = $brandCount ?? 0;
    $activeBrandFilter = $activeBrandFilter ?? null;
    $activeTagFilter = $activeTagFilter ?? null;
    $activeTaxonomyCategoryFilter = $activeTaxonomyCategoryFilter ?? null;
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

    $filtersRefineOpen = ($filters['brand'] ?? '') !== '' || ($filters['tag'] ?? '') !== '' || ($filters['stock'] ?? '') === 'low' || ($filters['status'] ?? '') === 'published' || ($filters['status'] ?? '') === 'draft'
        || (($filters['cf_key'] ?? '') !== '' && ($filters['cf_value'] ?? '') !== '');
    $productListDetailKeys = $productListDetailKeys ?? [];
    $catalogCustomFieldKeyOptions = $catalogCustomFieldKeyOptions ?? [];
    $highlightKeyOptions = collect($catalogCustomFieldKeyOptions);
    foreach ($productListDetailKeys as $savedKey) {
        $savedKey = trim((string) $savedKey);
        if ($savedKey !== '' && ! $highlightKeyOptions->contains(fn ($o) => (string) ($o['value'] ?? '') === $savedKey)) {
            $highlightKeyOptions->push([
                'value' => $savedKey,
                'label' => ProductDetailPresenter::humanizeKey($savedKey).' (saved)',
            ]);
        }
    }
    $cfKeyFilter = trim((string) ($filters['cf_key'] ?? ''));
    $cfKeyFilterOptions = collect($catalogCustomFieldKeyOptions);
    if ($cfKeyFilter !== '' && ! $cfKeyFilterOptions->contains(fn ($o) => (string) ($o['value'] ?? '') === $cfKeyFilter)) {
        $cfKeyFilterOptions->push([
            'value' => $cfKeyFilter,
            'label' => ProductDetailPresenter::humanizeKey($cfKeyFilter).' (current filter)',
        ]);
    }
    $cfKeyChipLabel = $cfKeyFilter !== '' ? ProductDetailPresenter::humanizeKey($cfKeyFilter) : '';
@endphp

@section('topbar')
    <header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
                <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor" />
            </svg>
        </button>

        <form method="GET" action="{{ route('products') }}" class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
            <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
            <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
            <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
            <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
            <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
            <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor" />
                </svg>
            </span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search products, SKUs, categories, and extra details…" class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </form>

        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <button type="button" data-open-product-modal class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
                </svg>
                <span>Add Product</span>
            </button>
            @if ($canManageBrands || $canManageTags || $canManageCategories)
                <details id="products-catalog-more-menu" class="group relative hidden sm:block" data-products-more-actions>
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm font-semibold text-[#475569] shadow-sm hover:border-[#CBD5E1] hover:bg-[#F8FAFC] [&::-webkit-details-marker]:hidden" aria-label="More catalog actions">
                        <span>More</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="text-[#94A3B8] transition group-open:rotate-180" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>
                    <div class="absolute right-0 z-40 mt-1 w-52 overflow-hidden rounded-xl border border-[#E2E8F0] bg-white py-1 shadow-lg ring-1 ring-black/5">
                        @if ($canManageBrands)
                            <a href="{{ route('products.import.create') }}" class="block px-4 py-2.5 text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">Import products</a>
                            <a href="{{ route('products.import.history') }}" class="block px-4 py-2.5 text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">Import history</a>
                        @endif
                        @if ($canManageBrands || $canManageTags || $canManageCategories)
                            <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">
                                Catalog tools
                            </button>
                        @endif
                    </div>
                </details>
            @endif

            <div class="w-px h-6 bg-[#E2E8F0] hidden sm:block"></div>

            <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
                <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                    <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B" />
                </svg>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
            </button>

            <button class="p-2 rounded-full hover:bg-gray-100 transition-colors hidden sm:flex">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95C11.2333 17.95 13.125 17.175 14.675 15.625C16.225 14.075 17 12.1833 17 9.95C17 7.71667 16.225 5.825 14.675 4.275C13.125 2.725 11.2333 1.95 9 1.95C6.76667 1.95 4.875 2.725 3.325 4.275C1.775 5.825 1 7.71667 1 9.95C1 12.1833 1.775 14.075 3.325 15.625C4.875 17.175 6.76667 17.95 9 17.95ZM9 15C9.28333 15 9.52083 14.9042 9.7125 14.7125C9.90417 14.5208 10 14.2833 10 14C10 13.7167 9.90417 13.4792 9.7125 13.2875C9.52083 13.0958 9.28333 13 9 13C8.71667 13 8.47917 13.0958 8.2875 13.2875C8.09583 13.4792 8 13.7167 8 14C8 14.2833 8.09583 14.5208 8.2875 14.7125C8.47917 14.9042 8.71667 15 9 15ZM9 11H10V5H8V6H9V11Z" fill="#64748B" />
                </svg>
            </button>

            <div class="w-9 h-9 rounded-full bg-[#E2E8F0] border border-[#CBD5E1] overflow-hidden shrink-0">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="13" r="6" fill="#94A3B8" />
                    <path d="M28 28C28 24 24 22 18 22C12 22 8 24 8 28" fill="#94A3B8" />
                </svg>
            </div>
        </div>
    </header>
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

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Products</h1>
            <p class="text-sm text-[#64748B] mt-0.5 max-w-2xl">Products and inventory for the store selected in the sidebar.</p>
            @if ($selectedStore)
                <p class="mt-1 text-sm font-medium text-[#0052CC]">Active store: {{ $selectedStore->name }}</p>
            @endif
            @if ($activeBrandFilter)
                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#BFDBFE] bg-[#F0F9FF] px-3 py-2 text-sm text-[#0C4A6E]">
                    <span>Showing products with brand <span class="font-semibold">{{ $activeBrandFilter->name }}</span>.</span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['brand' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear brand filter</a>
                </div>
            @endif
            @if ($activeTagFilter)
                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#E9D5FF] bg-[#FAF5FF] px-3 py-2 text-sm text-[#581C87]">
                    <span>Filtered by tag <span class="font-semibold">{{ $activeTagFilter->name }}</span>.</span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['tag' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear tag filter</a>
                </div>
            @endif
            @if ($activeTaxonomyCategoryFilter)
                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#CCFBF1] bg-[#F0FDFA] px-3 py-2 text-sm text-[#115E59]">
                    <span>Filtered by category <span class="font-semibold">{{ $activeTaxonomyCategoryFilter->name }}</span>.</span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['category' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear category filter</a>
                </div>
            @endif
            @if (($filters['cf_key'] ?? '') !== '' && ($filters['cf_value'] ?? '') !== '')
                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                    <span>Saved product detail <span class="font-semibold text-[#0F172A]">{{ $cfKeyChipLabel }}</span> contains <span class="font-semibold">{{ $filters['cf_value'] }}</span>.</span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['cf_key' => null, 'cf_value' => null]))) }}" class="font-semibold text-[#0052CC] hover:underline">Clear</a>
                </div>
            @endif
        </div>
        <div class="flex flex-col sm:items-end gap-3">
            @if ($canManageBrands || $canManageTags || $canManageCategories)
                <details class="group relative sm:hidden w-full" data-products-more-actions-mobile>
                    <summary class="flex w-full cursor-pointer list-none items-center justify-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-semibold text-[#334155] [&::-webkit-details-marker]:hidden">
                        More catalog actions
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" class="text-[#94A3B8] group-open:rotate-180 transition" aria-hidden="true"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>
                    <div class="absolute left-0 right-0 z-40 mt-1 overflow-hidden rounded-xl border border-[#E2E8F0] bg-white py-1 shadow-lg">
                        @if ($canManageBrands)
                            <a href="{{ route('products.import.create') }}" class="block px-4 py-2.5 text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">Import products</a>
                            <a href="{{ route('products.import.history') }}" class="block px-4 py-2.5 text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">Import history</a>
                        @endif
                        @if ($canManageBrands || $canManageTags || $canManageCategories)
                            <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]">Catalog tools</button>
                        @endif
                    </div>
                </details>
            @endif
            <button type="button" data-open-product-modal class="sm:hidden flex items-center justify-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2.5 rounded-lg">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
                </svg>
                Add Product
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="text-sm text-[#64748B]">Products in view</div>
            <div class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ number_format($stats['total_products']) }}</div>
            <div class="mt-1 text-xs text-[#64748B]">{{ $activeBrandFilter || $activeTagFilter || $activeTaxonomyCategoryFilter || ($filters['product_type'] ?? '') !== '' ? 'Matches current filters' : 'In this store' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="text-sm text-[#64748B]">Out of Stock</div>
            <div class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ number_format($stats['out_of_stock']) }}</div>
            <div class="mt-1 text-xs text-red-500 font-bold">Needs attention</div>
        </div>
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="text-sm text-[#64748B]">Low Stock</div>
            <div class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ number_format($stats['low_stock']) }}</div>
            <div class="mt-1 text-xs text-orange-500 font-bold">Ordering recommended</div>
        </div>
        <div class="rounded-xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="text-sm text-[#64748B]">Brands</div>
            <div class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ number_format($brandCount) }}</div>
            @if ($canManageBrands || $canManageTags || $canManageCategories)
                <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="mt-3 w-full rounded-lg border border-[#E2E8F0] py-2 text-xs font-semibold text-[#0052CC] transition hover:bg-[#F8FAFC] sm:hidden">Catalog tools</button>
            @else
                <p class="mt-2 text-xs text-[#94A3B8]">Owner or manager can edit.</p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="border-b border-[#E2E8F0] px-4 py-3 lg:px-5 lg:py-3.5">
            <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
                <form method="GET" action="{{ route('products') }}" class="flex flex-wrap items-end gap-x-4 gap-y-2">
                    <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                    <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                    <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                    <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                    <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                    <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#0F766E]"> Category</span>
                        <div class="relative">
                            <select name="category" onchange="this.form.submit()" aria-label="Filter by catalog category" class="appearance-none bg-[#0052CC] text-white text-sm font-semibold px-4 py-2.5 pr-9 rounded-xl shadow-sm min-w-[11rem]">
                                <option value="">All categories</option>
                                @foreach ($catalogTaxonomyCategories ?? [] as $taxCat)
                                    <option value="{{ $taxCat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $taxCat->id)>{{ $taxCat->name }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" width="12" height="12" viewBox="0 0 14 14" fill="none">
                                <path d="M7 9L3 5H11L7 9Z" fill="white" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]" title="How the product is sold or fulfilled—not the same as category above.">Type <span class="font-normal normal-case text-[#94A3B8]">(behavior)</span></span>
                        <div class="relative">
                            <select name="product_type" onchange="this.form.submit()" aria-label="Filter by product behavior type" class="appearance-none border text-sm font-semibold px-4 py-2.5 pr-9 rounded-xl transition-colors {{ ($filters['product_type'] ?? '') !== '' ? 'border-[#0D9488] bg-[#CCFBF1] text-[#115E59]' : 'border-[#E2E8F0] bg-white text-[#475569]' }}">
                                <option value="">All types</option>
                                @foreach ($productTypeFilterOptions ?? [] as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}" @selected(($filters['product_type'] ?? '') === $typeValue)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#64748B]" width="12" height="12" viewBox="0 0 14 14" fill="none">
                                <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                            </svg>
                        </div>
                    </div>
                </form>
                <div class="flex flex-wrap items-center gap-1.5 sm:ml-auto">
                    <form method="GET" action="{{ route('products') }}" class="relative">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                        <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                        <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                        <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                        <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                        <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                        <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                        <select name="sort" onchange="this.form.submit()" class="absolute inset-0 opacity-0 cursor-pointer" aria-label="Sort products">
                            <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest</option>
                            <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name</option>
                            <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Price High</option>
                            <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Price Low</option>
                            <option value="stock_high" @selected(($filters['sort'] ?? '') === 'stock_high')>Stock High</option>
                            <option value="stock_low" @selected(($filters['sort'] ?? '') === 'stock_low')>Stock Low</option>
                        </select>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] hover:bg-[#F8FAFC]" title="Sort">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M7 11H9V9H7V11ZM1 3V5H15V3H1ZM4 8H12V6H4V8Z" fill="currentColor" />
                            </svg>
                        </span>
                    </form>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['export' => 'csv']))) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] hover:bg-[#F8FAFC]" title="Export CSV">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M2 14H14V12H2V14ZM14 6H11V2H5V6H2L8 12L14 6Z" fill="currentColor" />
                        </svg>
                    </a>
                    <a href="{{ route('products') }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E8F0] bg-white px-3 text-xs font-semibold text-[#64748B] hover:bg-[#F8FAFC]" title="Clear all filters and search">Reset</a>
                </div>
            </div>

            <details id="products-advanced-filters-panel" class="group mt-2 border-t border-[#F1F5F9] pt-2 sm:mt-3 sm:pt-3" @if ($filtersRefineOpen) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-2 text-xs font-semibold text-[#64748B] hover:text-[#334155] [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex h-5 w-5 items-center justify-center rounded border border-[#E2E8F0] bg-[#F8FAFC] text-[10px] text-[#94A3B8] group-open:rotate-90 transition-transform" aria-hidden="true">›</span>
                    <span>Advanced filters &amp; table settings</span>
                    <span class="font-normal text-[#94A3B8]">brand, tag, inventory, list columns, saved detail search</span>
                </summary>
                <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-2 rounded-lg bg-[#F8FAFC]/80 px-3 py-2.5">
                    <form method="GET" action="{{ route('products') }}" class="contents">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                        <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                        <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                        <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                        <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                        <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                        <div class="relative">
                            <select name="brand" onchange="this.form.submit()" aria-label="Filter by brand" class="appearance-none border border-[#E2E8F0] bg-white text-xs font-medium text-[#64748B] px-3 py-1.5 pr-8 rounded-lg transition-colors {{ ($filters['brand'] ?? '') !== '' ? 'ring-1 ring-[#CBD5E1] text-[#334155]' : '' }}">
                                <option value="">All brands</option>
                                @foreach ($catalogBrands as $b)
                                    <option value="{{ $b->id }}" @selected((string) ($filters['brand'] ?? '') === (string) $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="10" height="10" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                            </svg>
                        </div>
                    </form>
                    <form method="GET" action="{{ route('products') }}" class="contents">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                        <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                        <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                        <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                        <input type="hidden" name="cf_key" value="{{ $filters['cf_key'] ?? '' }}">
                        <input type="hidden" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}">
                        <div class="relative">
                            <select name="tag" onchange="this.form.submit()" aria-label="Filter by tag" class="appearance-none border border-[#E2E8F0] bg-white text-xs font-medium text-[#64748B] px-3 py-1.5 pr-8 rounded-lg transition-colors {{ ($filters['tag'] ?? '') !== '' ? 'ring-1 ring-[#CBD5E1] text-[#334155]' : '' }}">
                                <option value="">All tags</option>
                                @foreach ($catalogTags ?? [] as $tg)
                                    <option value="{{ $tg->id }}" @selected((string) ($filters['tag'] ?? '') === (string) $tg->id)>{{ $tg->name }}</option>
                                @endforeach
                            </select>
                            <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="10" height="10" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M7 9L3 5H11L7 9Z" fill="currentColor" />
                            </svg>
                        </div>
                    </form>
                    <form method="GET" action="{{ route('products') }}" class="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                        <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                        <input type="hidden" name="product_type" value="{{ $filters['product_type'] ?? '' }}">
                        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                        <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                        <input type="hidden" name="brand" value="{{ $filters['brand'] ?? '' }}">
                        <input type="hidden" name="tag" value="{{ $filters['tag'] ?? '' }}">
                        <datalist id="catalog-cf-key-suggestions">
                            @foreach ($cfKeyFilterOptions as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </datalist>
                        <div class="flex min-w-0 max-w-full flex-col gap-0.5 sm:max-w-[14rem]">
                            <label for="cf_key_input" class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Saved product detail</label>
                            <input id="cf_key_input" name="cf_key" list="catalog-cf-key-suggestions" value="{{ $cfKeyFilter }}" placeholder="Material, supplier, …" class="w-full min-w-0 rounded-lg border border-[#E2E8F0] bg-white px-2.5 py-1.5 text-xs text-[#334155]" maxlength="128" autocomplete="off" aria-describedby="cf-key-filter-help">
                            <p id="cf-key-filter-help" class="text-[10px] leading-snug text-[#94A3B8]">Choose a saved detail or type a field name; suggestions come from this store’s catalog.</p>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label for="cf_value_input" class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Contains</label>
                            <input id="cf_value_input" name="cf_value" value="{{ $filters['cf_value'] ?? '' }}" placeholder="Text to match" class="w-40 rounded-lg border border-[#E2E8F0] bg-white px-2.5 py-1.5 text-xs text-[#334155]" maxlength="200">
                        </div>
                        <button type="submit" class="rounded-lg bg-[#0F172A] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#1E293B]">Apply</button>
                        @if (($filters['cf_key'] ?? '') !== '' || ($filters['cf_value'] ?? '') !== '')
                            <a href="{{ route('products', array_filter(array_merge($baseFilters, ['cf_key' => null, 'cf_value' => null]))) }}" class="self-end text-xs font-semibold text-[#0052CC] hover:underline">Clear filter</a>
                        @endif
                    </form>
                    <span class="hidden h-4 w-px bg-[#E2E8F0] sm:inline" aria-hidden="true"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">||</span>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['stock' => ($filters['stock'] ?? '') === 'low' ? null : 'low']))) }}" class="flex items-center gap-1 border border-[#E2E8F0] bg-white text-xs font-medium text-[#64748B] px-2.5 py-1.5 rounded-lg transition-colors hover:bg-white {{ ($filters['stock'] ?? '') === 'low' ? 'border-[#F97316] bg-orange-50 text-orange-700' : '' }}">
                        Low stock
                        <span class="rounded-full bg-[#F97316] px-1.5 py-0 text-[10px] font-bold text-white tabular-nums">{{ $stats['low_stock'] }}</span>
                    </a>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['status' => ($filters['status'] ?? '') === 'published' ? null : 'published']))) }}" class="border border-[#E2E8F0] bg-white text-xs font-medium text-[#64748B] px-2.5 py-1.5 rounded-lg transition-colors hover:bg-white {{ ($filters['status'] ?? '') === 'published' ? 'border-[#0052CC] bg-[#EEF4FF] text-[#0052CC]' : '' }}">Published</a>
                    <a href="{{ route('products', array_filter(array_merge($baseFilters, ['status' => ($filters['status'] ?? '') === 'draft' ? null : 'draft']))) }}" class="border border-[#E2E8F0] bg-white text-xs font-medium text-[#64748B] px-2.5 py-1.5 rounded-lg transition-colors hover:bg-white {{ ($filters['status'] ?? '') === 'draft' ? 'border-slate-400 bg-slate-50 text-[#334155]' : '' }}">Drafts</a>
                </div>
                @if ($canManageBrands)
                    <div class="mt-4 border-t border-[#E2E8F0] pt-4">
                        <p class="text-xs font-semibold text-[#334155]">Product list columns</p>
                        <form method="POST" action="{{ route('products.catalog-list-highlights') }}" class="mt-2 space-y-2 rounded-lg bg-[#F8FAFC]/80 px-3 py-2.5" aria-describedby="product-list-highlights-help">
                            @csrf
                            <p id="product-list-highlights-help" class="text-[10px] leading-snug text-[#64748B]">Choose up to two saved product details to show under each product name in this list.</p>
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="flex min-w-0 flex-col gap-0.5 sm:min-w-[10rem]">
                                    <label for="detail_key_1" class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Detail 1</label>
                                    <select id="detail_key_1" name="detail_key_1" class="w-full max-w-[12rem] rounded-lg border border-[#E2E8F0] bg-white px-2 py-1.5 text-xs text-[#334155]">
                                        <option value="">None</option>
                                        @foreach ($highlightKeyOptions as $opt)
                                            <option value="{{ $opt['value'] }}" @selected(($productListDetailKeys[0] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex min-w-0 flex-col gap-0.5 sm:min-w-[10rem]">
                                    <label for="detail_key_2" class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Detail 2</label>
                                    <select id="detail_key_2" name="detail_key_2" class="w-full max-w-[12rem] rounded-lg border border-[#E2E8F0] bg-white px-2 py-1.5 text-xs text-[#334155]">
                                        <option value="">None</option>
                                        @foreach ($highlightKeyOptions as $opt)
                                            <option value="{{ $opt['value'] }}" @selected(($productListDetailKeys[1] ?? '') === $opt['value'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="rounded-lg bg-[#0052CC] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#0047B3]">Save</button>
                            </div>
                        </form>
                    </div>
                @endif
            </details>
        </div>

        @if ($canManageBrands)
            <div id="bulk-catalog-toolbar" class="hidden border-b border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 lg:px-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm text-[#334155]"><span id="bulk-selected-count" class="font-bold text-[#0F172A]">0</span> <span id="bulk-selected-label" class="text-[#64748B]">selected on this page</span></p>
                        <button type="button" id="bulk-select-all-matching" class="w-fit rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#334155] hover:bg-[#F1F5F9]">
                            Select all matching (up to {{ count($bulkSelectableProductIds ?? []) }})
                        </button>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex flex-col gap-1">
                            <label for="bulk-action-select" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Bulk action</label>
                            <select id="bulk-action-select" class="min-w-[11rem] rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                <option value="">Choose…</option>
                                <option value="delete">Delete</option>
                                <option value="stock">Stock (set or adjust)</option>
                                <option value="categories">Add categories</option>
                                <option value="brand">Set brand</option>
                                <option value="tags">Add tags</option>
                                <option value="status">Set status</option>
                            </select>
                        </div>
                        <div id="bulk-extra-stock" class="hidden flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-end">
                            <div class="flex flex-wrap items-end gap-2">
                                <select id="bulk-stock-mode" class="rounded-lg border border-[#CBD5E1] bg-white px-2 py-2 text-xs font-semibold text-[#334155]">
                                    <option value="set">Set to</option>
                                    <option value="delta">Adjust by</option>
                                </select>
                                <input id="bulk-stock-value" type="number" class="w-24 rounded-lg border border-[#CBD5E1] px-2 py-2 text-sm" placeholder="Qty">
                            </div>
                            <div class="flex min-w-[14rem] max-w-md flex-col gap-1">
                                <label for="bulk-stock-variant-scope" class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Products with multiple variants</label>
                                <select id="bulk-stock-variant-scope" class="rounded-lg border border-[#CBD5E1] bg-white px-2 py-2 text-xs font-medium text-[#334155]">
                                    <option value="default_variant_only">Update default row only</option>
                                    <option value="all_variants_same">Apply same quantity to every variant row</option>
                                    <option value="skip_multi_variant">Skip multi-variant products</option>
                                </select>
                            </div>
                        </div>
                        <div id="bulk-extra-categories" class="hidden max-w-full">
                            <select id="bulk-category-ids" multiple size="3" class="max-w-xs rounded-lg border border-[#CBD5E1] bg-white px-2 py-1 text-xs text-[#334155]">
                                @foreach ($catalogTaxonomyCategories ?? [] as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="bulk-extra-brand" class="hidden">
                            <select id="bulk-brand-id" class="min-w-[10rem] rounded-lg border border-[#CBD5E1] bg-white px-2 py-2 text-sm text-[#334155]">
                                <option value="">Choose brand</option>
                                @foreach ($catalogBrands ?? [] as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="bulk-extra-tags" class="hidden max-w-full">
                            <select id="bulk-tag-ids" multiple size="3" class="max-w-xs rounded-lg border border-[#CBD5E1] bg-white px-2 py-1 text-xs text-[#334155]">
                                @foreach ($catalogTags ?? [] as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="bulk-extra-status" class="hidden">
                            <select id="bulk-status-value" class="rounded-lg border border-[#CBD5E1] bg-white px-2 py-2 text-sm text-[#334155]">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <button type="button" id="bulk-apply-btn" class="inline-flex items-center gap-2 rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3] disabled:cursor-not-allowed disabled:opacity-60">
                            <span id="bulk-apply-spinner" class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                            <span>Apply</span>
                        </button>
                    </div>
                </div>
                @if ($errors->has('bulk'))
                    <p class="mt-2 text-sm font-medium text-[#B42318]">{{ $errors->first('bulk') }}</p>
                @endif
            </div>

            <form id="bulk-products-form" method="POST" action="{{ route('products.bulk') }}" class="hidden" aria-hidden="true">
                @csrf
                <input type="hidden" name="action" id="bulk-form-action" value="">
                <input type="hidden" name="stock_mode" id="bulk-form-stock-mode" value="">
                <input type="hidden" name="stock_value" id="bulk-form-stock-value" value="">
                <input type="hidden" name="bulk_variant_stock_scope" id="bulk-form-stock-variant-scope" value="default_variant_only">
                <input type="hidden" name="brand_id" id="bulk-form-brand-id" value="">
                <input type="hidden" name="product_status" id="bulk-form-product-status" value="">
                <div id="bulk-form-category-inputs"></div>
                <div id="bulk-form-tag-inputs"></div>
                <div id="bulk-form-product-id-inputs"></div>
            </form>

            <div id="bulk-confirm-shell" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/40 px-4 py-8" role="dialog" aria-modal="true" aria-labelledby="bulk-confirm-title">
                <div class="w-full max-w-md rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-xl">
                    <h3 id="bulk-confirm-title" class="text-lg font-semibold text-[#0F172A]">Confirm bulk action</h3>
                    <p id="bulk-confirm-body" class="mt-2 text-sm leading-relaxed text-[#475569]"></p>
                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <button type="button" id="bulk-confirm-cancel" class="rounded-lg border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                        <button type="button" id="bulk-confirm-ok" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">Confirm</button>
                    </div>
                </div>
            </div>
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
                            $inventory = (int) ($product->variants_sum_stock ?? 0);
                            $alertLevel = (int) ($product->variants_max_stock_alert ?? ($product->meta['stock_alert'] ?? 0));
                            $stockState = $inventory === 0 ? 'out' : ($inventory <= max($alertLevel, 0) ? 'low' : 'in');
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
                        @endphp
                        <tr class="hover:bg-[#F8FAFC] transition-colors">
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
                                    @php
                                        $visibleCats = $product->categories->take(3);
                                        $extraCatCount = $product->categories->count() - $visibleCats->count();
                                    @endphp
                                    <div class="flex max-w-[12rem] flex-wrap gap-1">
                                        @foreach ($visibleCats as $cat)
                                            <span class="inline-block max-w-[6.5rem] truncate rounded-md border border-[#99F6E4] bg-[#F0FDFA] px-2 py-0.5 text-[11px] font-semibold text-[#0F766E]" title="{{ $cat->name }}">{{ $cat->name }}</span>
                                        @endforeach
                                        @if ($extraCatCount > 0)
                                            <span class="text-[10px] font-medium text-[#94A3B8]">+{{ $extraCatCount }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-[#94A3B8]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs" title="Fulfillment / behavior type">{{ \Illuminate\Support\Str::title(str_replace(['-', '_'], ' ', $product->product_type)) }}</span></td>
                            <td class="px-4 py-4">
                                @if ($stockState === 'out')
                                    <span class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#EF4444" /></svg>Out of Stock</span>
                                @elseif ($stockState === 'low')
                                    <span class="inline-flex items-center gap-1.5 bg-orange-50 text-orange-500 text-xs font-bold px-3 py-1 rounded-full border border-orange-100"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#F97316" /></svg>Low Stock</span>
                                @elseif ($product->status)
                                    <span class="inline-flex items-center gap-1.5 bg-green-50 text-green-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#22C55E" /></svg>Published</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded-full"><svg width="8" height="8" viewBox="0 0 8 8" fill="none"><circle cx="4" cy="4" r="4" fill="#64748B" /></svg>Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 font-inter font-medium text-[#0F172A] text-sm">{{ $product->store?->currency ?? '$' }}{{ number_format((float) $product->base_price, 2) }}</td>
                            <td class="px-4 py-4 w-28">
                                <div class="bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden">
                                    <div class="h-full rounded-full {{ $stockState === 'out' ? 'bg-[#E2E8F0]' : ($stockState === 'low' ? 'bg-[#F97316]' : 'bg-[#3B82F6]') }}" style="width:{{ $stockWidth }}%"></div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-[#475569] font-semibold text-sm w-8">{{ $inventory }}</td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('products.show', $product) }}" class="inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569] hover:bg-[#F8FAFC]">View</a>
                                    <button type="button" class="js-open-edit-product-modal inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0052CC] hover:bg-[#EEF4FF]" data-product='@json($productActionPayload)' onclick="window.openProductEditModalFromElement && window.openProductEditModalFromElement(this)">Edit</button>
                                    <button type="button" class="js-open-delete-product-modal inline-flex items-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-3 py-2 text-xs font-semibold text-[#B42318] hover:bg-[#FEEBEC]" data-product='@json($productActionPayload)' onclick="window.openProductDeleteModalFromElement && window.openProductDeleteModalFromElement(this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-sm font-semibold text-[#0F172A]">No products found</p>
                                <p class="mt-1 text-sm text-[#64748B]">Try a different search term or clear your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 lg:px-5 py-4 border-t border-[#E2E8F0]">
            <span class="text-[#64748B] text-sm">Showing <span class="font-semibold text-[#0F172A]">{{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }}</span> of <span class="font-semibold text-[#0F172A]">{{ $products->total() }}</span> results</span>
            <div class="flex items-center gap-1.5">
                @if ($products->onFirstPage())
                    <span class="px-3 py-1.5 text-sm font-inter font-medium text-[#94A3B8] border border-[#E2E8F0] rounded-lg">Previous</span>
                @else
                    <a href="{{ $products->previousPageUrl() }}" class="px-3 py-1.5 text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50 transition-colors">Previous</a>
                @endif

                @foreach ($products->getUrlRange(max(1, $products->currentPage() - 1), min($products->lastPage(), $products->currentPage() + 1)) as $page => $url)
                    @if ($page === $products->currentPage())
                        <span class="w-8 h-8 flex items-center justify-center text-sm font-bold bg-[#0052CC] text-white rounded-lg">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" class="px-3 py-1.5 text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50 transition-colors">Next</a>
                @else
                    <span class="px-3 py-1.5 text-sm font-inter font-medium text-[#94A3B8] border border-[#E2E8F0] rounded-lg">Next</span>
                @endif
            </div>
        </div>
    </div>

    @include('user_view.partials.product_create_modal', [
        'productModalSelectedStore' => $selectedStore,
        'productModalIsOpen' => $openProductModal,
        'productModalOpenQuery' => 'openAddProduct',
        'catalogBrands' => $catalogBrands ?? collect(),
        'catalogTags' => $catalogTags ?? collect(),
        'catalogTaxonomyCategories' => $catalogTaxonomyCategories ?? collect(),
        'catalogImagesForVariantPicker' => [],
    ])
    @include('user_view.partials.product_edit_modal', [
        'catalogBrands' => $catalogBrands ?? collect(),
        'catalogTags' => $catalogTags ?? collect(),
        'catalogTaxonomyCategories' => $catalogTaxonomyCategories ?? collect(),
    ])

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
        (() => {
            const bulkForm = document.getElementById('bulk-products-form');
            if (!bulkForm) {
                return;
            }

            const allMatchingIds = Array.isArray(window.__bulkSelectableProductIds) ? window.__bulkSelectableProductIds.map(String) : [];
            let bulkAllMode = false;
            let bulkAllSelection = new Set();

            const selectAll = document.getElementById('selectAllProducts');
            const rowCheckboxes = [...document.querySelectorAll('.js-product-row-checkbox')];
            const toolbar = document.getElementById('bulk-catalog-toolbar');
            const countEl = document.getElementById('bulk-selected-count');
            const actionSelect = document.getElementById('bulk-action-select');
            const extraStock = document.getElementById('bulk-extra-stock');
            const extraCategories = document.getElementById('bulk-extra-categories');
            const extraBrand = document.getElementById('bulk-extra-brand');
            const extraTags = document.getElementById('bulk-extra-tags');
            const extraStatus = document.getElementById('bulk-extra-status');
            const applyBtn = document.getElementById('bulk-apply-btn');
            const applySpinner = document.getElementById('bulk-apply-spinner');
            const confirmShell = document.getElementById('bulk-confirm-shell');
            const confirmBody = document.getElementById('bulk-confirm-body');
            const confirmOk = document.getElementById('bulk-confirm-ok');
            const confirmCancel = document.getElementById('bulk-confirm-cancel');

            function pageSelectedIds() {
                return rowCheckboxes.filter((c) => c.checked && !c.disabled).map((c) => c.getAttribute('data-product-id')).filter(Boolean);
            }

            function effectiveSelectedIds() {
                if (bulkAllMode && bulkAllSelection.size > 0) {
                    return [...bulkAllSelection];
                }
                return pageSelectedIds();
            }

            function refreshBulkUi() {
                const ids = effectiveSelectedIds();
                if (countEl) {
                    countEl.textContent = String(ids.length);
                }
                const label = document.getElementById('bulk-selected-label');
                if (label) {
                    label.textContent = bulkAllMode ? 'selected (all matching, up to 500)' : 'selected on this page';
                }
                if (toolbar) {
                    toolbar.classList.toggle('hidden', ids.length === 0);
                }
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
                [extraStock, extraCategories, extraBrand, extraTags, extraStatus].forEach((el) => el && el.classList.add('hidden'));
                if (v === 'stock' && extraStock) extraStock.classList.remove('hidden');
                if (v === 'categories' && extraCategories) extraCategories.classList.remove('hidden');
                if (v === 'brand' && extraBrand) extraBrand.classList.remove('hidden');
                if (v === 'tags' && extraTags) extraTags.classList.remove('hidden');
                if (v === 'status' && extraStatus) extraStatus.classList.remove('hidden');
            }

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
            }

            confirmCancel?.addEventListener('click', closeConfirm);
            confirmOk?.addEventListener('click', () => {
                const fn = confirmAction;
                closeConfirm();
                if (typeof fn === 'function') {
                    fn();
                }
            });

            function prepareAndSubmit(action, ids) {
                document.getElementById('bulk-form-action').value = action;
                const pidWrap = document.getElementById('bulk-form-product-id-inputs');
                pidWrap.innerHTML = '';
                ids.forEach((id) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'product_ids[]';
                    inp.value = id;
                    pidWrap.appendChild(inp);
                });
                document.getElementById('bulk-form-stock-mode').value = '';
                document.getElementById('bulk-form-stock-value').value = '';
                const scopeEl = document.getElementById('bulk-form-stock-variant-scope');
                if (scopeEl) scopeEl.value = 'default_variant_only';
                document.getElementById('bulk-form-brand-id').value = '';
                document.getElementById('bulk-form-product-status').value = '';
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
                }
                if (action === 'categories') {
                    const sel = document.getElementById('bulk-category-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    setMultiHidden(document.getElementById('bulk-form-category-inputs'), 'category_ids[]', picked);
                }
                if (action === 'brand') {
                    const bid = document.getElementById('bulk-brand-id')?.value;
                    document.getElementById('bulk-form-brand-id').value = bid || '';
                }
                if (action === 'tags') {
                    const sel = document.getElementById('bulk-tag-ids');
                    const picked = sel ? [...sel.selectedOptions].map((o) => o.value) : [];
                    setMultiHidden(document.getElementById('bulk-form-tag-inputs'), 'tag_ids[]', picked);
                }
                if (action === 'status') {
                    document.getElementById('bulk-form-product-status').value = document.getElementById('bulk-status-value')?.value || 'published';
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
                    window.alert('Choose a bulk action.');
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

                const n = ids.length;
                let msg = `Apply this bulk action to ${n} product(s)?`;
                if (action === 'delete') {
                    msg = `Archive ${n} product(s) (soft delete)? They will be hidden from the catalog but can be restored from the database if needed.`;
                }
                if (action === 'stock') {
                    const mode = document.getElementById('bulk-stock-mode')?.value || 'set';
                    const val = document.getElementById('bulk-stock-value')?.value;
                    const scope = document.getElementById('bulk-stock-variant-scope')?.value || 'default_variant_only';
                    const scopeExplain = scope === 'all_variants_same'
                        ? 'Multi-variant products: every variant row will be set to this same quantity.'
                        : scope === 'skip_multi_variant'
                            ? 'Multi-variant products will be skipped entirely; only products with a single inventory row are updated.'
                            : 'Only the default inventory row per product is updated; extra variant rows on multi-variant products are left unchanged.';
                    const modeExplain = mode === 'delta'
                        ? `Adjust stock by ${val} units on each affected row (never below zero).`
                        : `Set stock to exactly ${val} units on each affected row.`;
                    msg = `${modeExplain} ${scopeExplain} This applies to ${n} selected product(s). Continue?`;
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
