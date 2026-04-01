@extends('layouts.user.user-sidebar')

@section('title', 'Products Admin - BaaS Core')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', 'E-commerce Portal')
@section('sidebar_logo')
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11 13L9 11L11 9L13 11L11 13ZM8.875 7.125L6.375 4.625L11 0L15.625 4.625L13.125 7.125L11 5L8.875 7.125ZM4.625 15.625L0 11L4.625 6.375L7.125 8.875L5 11L7.125 13.125L4.625 15.625ZM17.375 15.625L14.875 13.125L17 11L14.875 8.875L17.375 6.375L22 11L17.375 15.625ZM11 22L6.375 17.375L8.875 14.875L11 17L13.125 14.875L15.625 17.375L11 22Z" fill="white" />
    </svg>
@endsection

@php
    $baseFilters = [
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'status' => $filters['status'] ?? '',
        'stock' => $filters['stock'] ?? '',
        'sort' => $filters['sort'] ?? 'latest',
    ];

    $openProductModal = request()->boolean('openAddProduct') || old('_open_add_product_modal');
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
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
            <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor" />
                </svg>
            </span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search products, SKUs, categories..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </form>

        <div class="flex items-center gap-3 shrink-0">
            <button type="button" data-open-product-modal class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
                </svg>
                <span>Add Product</span>
            </button>

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
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Products</h1>
            <p class="text-sm text-[#64748B] mt-0.5">Manage your inventory and product listings for the active store.</p>
            @if ($selectedStore)
                <p class="mt-1 text-sm font-medium text-[#0052CC]">Viewing store: {{ $selectedStore->name }}</p>
            @endif
        </div>
        <div class="flex flex-col sm:items-end gap-3">
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
            <div class="text-sm text-[#64748B]">Total Products</div>
            <div class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ number_format($stats['total_products']) }}</div>
            <div class="mt-1 text-xs text-green-600 font-semibold">{{ $selectedStore ? $selectedStore->name : 'Across all stores' }}</div>
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
        <div class="bg-[#0052CC]/5 rounded-xl border border-[#0052CC]/20 p-5 shadow-sm">
            <div class="text-sm text-[#0052CC]/70">Active Categories</div>
            <div class="mt-2 text-2xl font-medium text-[#0052CC] font-poppins">{{ number_format($stats['active_categories']) }}</div>
            <div class="mt-1 text-xs text-[#0052CC]/60 font-bold">{{ $selectedStore ? 'Within selected store' : 'Across all stores' }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 px-4 lg:px-5 py-4 border-b border-[#E2E8F0]">
            <form method="GET" action="{{ route('products') }}" class="contents">
                <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'latest' }}">
                <div class="relative">
                    <select name="category" onchange="this.form.submit()" class="appearance-none bg-[#0052CC] text-white text-sm font-semibold px-4 py-2 pr-9 rounded-full">
                        <option value="">All Categories</option>
                        @foreach ($categories as $categoryValue => $categoryLabel)
                            <option value="{{ $categoryValue }}" @selected(($filters['category'] ?? '') === $categoryValue)>{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" width="12" height="12" viewBox="0 0 14 14" fill="none">
                        <path d="M7 9L3 5H11L7 9Z" fill="white" />
                    </svg>
                </div>
            </form>

            <a href="{{ route('products', array_filter(array_merge($baseFilters, ['stock' => ($filters['stock'] ?? '') === 'low' ? null : 'low']))) }}" class="flex items-center gap-1.5 border text-sm font-inter font-medium px-4 py-2 rounded-full transition-colors {{ ($filters['stock'] ?? '') === 'low' ? 'border-[#F97316] bg-orange-50 text-orange-500' : 'border-[#E2E8F0] text-[#475569] hover:bg-gray-50' }}">
                Low Stock
                <span class="bg-[#F97316] text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">{{ $stats['low_stock'] }}</span>
            </a>
            <a href="{{ route('products', array_filter(array_merge($baseFilters, ['status' => ($filters['status'] ?? '') === 'published' ? null : 'published']))) }}" class="border text-sm font-inter font-medium px-4 py-2 rounded-full transition-colors {{ ($filters['status'] ?? '') === 'published' ? 'border-[#0052CC] bg-[#0052CC]/10 text-[#0052CC]' : 'border-[#E2E8F0] text-[#475569] hover:bg-gray-50' }}">Published</a>
            <a href="{{ route('products', array_filter(array_merge($baseFilters, ['status' => ($filters['status'] ?? '') === 'draft' ? null : 'draft']))) }}" class="border text-sm font-inter font-medium px-4 py-2 rounded-full transition-colors {{ ($filters['status'] ?? '') === 'draft' ? 'border-[#0052CC] bg-[#0052CC]/10 text-[#0052CC]' : 'border-[#E2E8F0] text-[#475569] hover:bg-gray-50' }}">Drafts</a>

            <div class="ml-auto flex gap-2">
                <form method="GET" action="{{ route('products') }}" class="relative">
                    <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    <input type="hidden" name="category" value="{{ $filters['category'] ?? '' }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                    <input type="hidden" name="stock" value="{{ $filters['stock'] ?? '' }}">
                    <select name="sort" onchange="this.form.submit()" class="absolute inset-0 opacity-0 cursor-pointer">
                        <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Latest</option>
                        <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name</option>
                        <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Price High</option>
                        <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Price Low</option>
                        <option value="stock_high" @selected(($filters['sort'] ?? '') === 'stock_high')>Stock High</option>
                        <option value="stock_low" @selected(($filters['sort'] ?? '') === 'stock_low')>Stock Low</option>
                    </select>
                    <span class="w-9 h-9 flex items-center justify-center border border-[#E2E8F0] rounded-lg hover:bg-gray-50" title="Sort products">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M7 11H9V9H7V11ZM1 3V5H15V3H1ZM4 8H12V6H4V8Z" fill="#475569" />
                        </svg>
                    </span>
                </form>
                <a href="{{ route('products', array_filter(array_merge($baseFilters, ['export' => 'csv']))) }}" class="w-9 h-9 flex items-center justify-center border border-[#E2E8F0] rounded-lg hover:bg-gray-50" title="Export CSV">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M2 14H14V12H2V14ZM14 6H11V2H5V6H2L8 12L14 6Z" fill="#475569" />
                    </svg>
                </a>
                <a href="{{ route('products') }}" class="inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569] hover:bg-gray-50">Reset</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[780px]">
                <thead>
                    <tr class="border-b border-[#E2E8F0] bg-[#F8FAFC]">
                        <th class="w-10 px-4 py-3"><input id="selectAllProducts" type="checkbox" class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Product</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Category</th>
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
                            $productImagePath = $product->meta['image_path'] ?? ($product->meta['image_paths'][0] ?? null);
                            $productImageUrl = $productImagePath ? asset('storage/' . $productImagePath) : null;
                            $productActionPayload = [
                                'id' => $product->id,
                                'name' => $product->name,
                                'description' => $product->description,
                                'sku' => $product->sku,
                                'base_price' => (string) $product->base_price,
                                'product_type' => $product->product_type,
                                'stock_alert' => (int) ($product->variants_max_stock_alert ?? ($product->meta['stock_alert'] ?? 0)),
                                'image_url' => $productImageUrl,
                                'image_paths' => collect($product->meta['image_paths'] ?? array_filter([$productImagePath]))->values()->all(),
                                'image_urls' => collect($product->meta['image_paths'] ?? array_filter([$productImagePath]))
                                    ->map(fn($path) => asset('storage/' . $path))
                                    ->values()
                                    ->all(),
                                'variation_types' => $product->variationTypes->map(fn($variationType) => [
                                    'name' => $variationType->name,
                                    'type' => $variationType->type,
                                    'options' => $variationType->options->sortBy('sort_order')->pluck('value')->values()->all(),
                                ])->values()->all(),
                                'variants' => $product->variants->map(function ($variant) use ($product) {
                                    $optionMap = [];

                                    foreach ($product->variationTypes as $variationIndex => $variationType) {
                                        $selectedOption = $variant->options->first(
                                            fn($option) => (int) $option->variation_type_id === (int) $variationType->id
                                        );

                                        if ($selectedOption) {
                                            $optionMap[$variationIndex] = $variationType->options
                                                ->sortBy('sort_order')
                                                ->pluck('id')
                                                ->search($selectedOption->id);
                                        }
                                    }

                                    return [
                                        'option_map' => $optionMap,
                                        'sku' => $variant->sku,
                                        'price' => (string) $variant->price,
                                        'stock' => (string) $variant->stock,
                                        'stock_alert' => (int) $variant->stock_alert,
                                    ];
                                })->values()->all(),
                                'update_url' => route('product.update', ['productId' => $product->id]),
                                'delete_url' => route('product.destroy', ['productId' => $product->id]),
                            ];
                        @endphp
                        <tr class="hover:bg-[#F8FAFC] transition-colors">
                            <td class="px-4 py-4"><input type="checkbox" class="js-product-row-checkbox w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($productImageUrl)
                                        <img src="{{ $productImageUrl }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg object-cover shrink-0 border border-[#DCE9FF]">
                                    @else
                                        <div class="w-10 h-10 rounded-lg bg-[#DCE9FF] shrink-0 flex items-center justify-center">
                                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                <path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#0052CC" />
                                            </svg>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-inter font-medium text-[#0F172A] text-sm">{{ $product->name }}</div>
                                        <div class="text-[#94A3B8] text-xs">SKU: {{ $product->sku ?: 'Auto-generated' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">{{ \Illuminate\Support\Str::title(str_replace(['-', '_'], ' ', $product->product_type)) }}</span></td>
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
                                <div class="flex items-center gap-2">
                                    <button type="button" class="js-open-edit-product-modal inline-flex items-center rounded-lg border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#0052CC] hover:bg-[#EEF4FF]" data-product='@json($productActionPayload)' onclick="window.openProductEditModalFromElement && window.openProductEditModalFromElement(this)">Edit</button>
                                    <button type="button" class="js-open-delete-product-modal inline-flex items-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-3 py-2 text-xs font-semibold text-[#B42318] hover:bg-[#FEEBEC]" data-product='@json($productActionPayload)' onclick="window.openProductDeleteModalFromElement && window.openProductDeleteModalFromElement(this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
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
    ])
    @include('user_view.partials.product_edit_modal')

    <script>
        (() => {
            const selectAll = document.getElementById('selectAllProducts');
            const rowCheckboxes = [...document.querySelectorAll('.js-product-row-checkbox')];

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    rowCheckboxes.forEach((checkbox) => {
                        checkbox.checked = selectAll.checked;
                    });
                });
            }

            rowCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    if (!selectAll) return;
                    selectAll.checked = rowCheckboxes.length > 0 && rowCheckboxes.every((rowCheckbox) => rowCheckbox.checked);
                });
            });

        })();
    </script>
@endsection
