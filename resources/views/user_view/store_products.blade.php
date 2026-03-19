@extends('layouts.user.user-sidebar')

@section('title', 'Manage Products - ' . $store->name . ' | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Search bar -->
    <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" placeholder="Search products..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
    </div>

    <!-- Right icons -->
    <div class="flex items-center gap-3 shrink-0">
        <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
            </svg>
            <span>Add Product</span>
        </a>

        <div class="w-px h-6 bg-[#E2E8F0] hidden sm:block"></div>

        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
        </button>

        <button class="p-2 rounded-full hover:bg-gray-100 transition-colors hidden sm:flex">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95C11.2333 17.95 13.125 17.175 14.675 15.625C16.225 14.075 17 12.1833 17 9.95C17 7.71667 16.225 5.825 14.675 4.275C13.125 2.725 11.2333 1.95 9 1.95C6.76667 1.95 4.875 2.725 3.325 4.275C1.775 5.825 1 7.71667 1 9.95C1 12.1833 1.775 14.075 3.325 15.625C4.875 17.175 6.76667 17.95 9 17.95ZM9 15C9.28333 15 9.52083 14.9042 9.7125 14.7125C9.90417 14.5208 10 14.2833 10 14C10 13.7167 9.90417 13.4792 9.7125 13.2875C9.52083 13.0958 9.28333 13 9 13C8.71667 13 8.47917 13.0958 8.2875 13.2875C8.09583 13.4792 8 13.7167 8 14C8 14.2833 8.09583 14.5208 8.2875 14.7125C8.47917 14.9042 8.71667 15 9 15ZM9 11H10V5H8V6H9V11Z" fill="#64748B"/>
            </svg>
        </button>

        <!-- Profile avatar placeholder -->
        <div class="w-9 h-9 rounded-full bg-[#E2E8F0] border border-[#CBD5E1] overflow-hidden shrink-0">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <circle cx="18" cy="13" r="6" fill="#94A3B8"/>
                <path d="M28 28C28 24 24 22 18 22C12 22 8 24 8 28" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-6">
    <!-- Header with Back button -->
    <div class="flex items-center gap-4">
        <a href="{{ route('store-management') }}" class="flex items-center gap-2 text-[#64748B] hover:text-[#0052CC] transition-colors">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M13 10L6 3M13 10L6 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="font-medium">Back to Store Management</span>
        </a>
    </div>

    <!-- Store Header -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-[#DCE9FF] rounded-lg flex items-center justify-center">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                        <path d="M21.25 23.75V20H17.5V17.5H21.25V13.75H23.75V17.5H27.5V20H23.75V23.75H21.25ZM1.25 20V12.5H0V10L1.25 3.75H20L21.25 10V12.5H20V16.25H17.5V12.5H12.5V20H1.25ZM3.75 17.5H10V12.5H3.75V17.5ZM2.5625 10H18.6875L17.9375 6.25H3.3125L2.5625 10ZM1.25 2.5V0H20V2.5H1.25Z" fill="#003D9B"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-black text-[#0B1C30]">{{ $store->name }}</h1>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="px-3 py-1 bg-[#DCE9FF] text-[#434654] text-xs font-bold uppercase rounded-full">{{ ucfirst($store->category ?? 'General') }}</span>
                        @if ($store->onboarding_completed)
                            <span class="flex items-center gap-1 px-3 py-1 bg-[#4EDEA3]/20 text-[#005236] text-xs font-bold uppercase rounded-full">
                                <span class="w-1.5 h-1.5 bg-[#4EDEA3] rounded-full"></span>
                                Live Store
                            </span>
                        @else
                            <span class="flex items-center gap-1 px-3 py-1 bg-[#C3C6D6]/30 text-[#434654] text-xs font-bold uppercase rounded-full">
                                <span class="w-1.5 h-1.5 bg-[#737685] rounded-full"></span>
                                Draft
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="flex sm:hidden items-center gap-2 bg-[#0052CC] text-white font-bold px-4 py-2 rounded-lg hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                </svg>
                <span>Add</span>
            </a>
        </div>
    </div>

    <!-- Store Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg border border-[#E2E8F0] p-4">
            <div class="text-sm text-[#64748B] font-medium">Total Products</div>
            <div class="text-2xl font-bold text-[#0B1C30] mt-1">{{ count($products) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#E2E8F0] p-4">
            <div class="text-sm text-[#64748B] font-medium">Store Created</div>
            <div class="text-2xl font-bold text-[#0B1C30] mt-1">{{ $store->created_at->format('M d, Y') }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#E2E8F0] p-4">
            <div class="text-sm text-[#64748B] font-medium">Slug</div>
            <div class="text-lg font-bold text-[#0B1C30] mt-1">{{ $store->slug }}</div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session('success'))
        <div class="rounded-lg border border-[#CBE8D1] bg-[#ECFDF3] px-4 py-3 text-sm text-[#05603A]">
            {{ session('success') }}
        </div>
    @endif

    <!-- Products Section -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-[#0B1C30]">Products</h2>
            <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="hidden sm:inline-flex items-center gap-2 bg-[#0052CC] text-white font-bold px-4 py-2 rounded-lg hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                </svg>
                Add Product
            </a>
        </div>

        @if ($products->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#E2E8F0]">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-[#434654]">Product Name</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-[#434654]">Type</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-[#434654]">Base Price</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-[#434654]">Stock</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-[#434654]">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] transition-colors">
                                <td class="py-3 px-4">
                                    <div class="text-sm font-medium text-[#0B1C30]">{{ $product->name }}</div>
                                    @if ($product->sku)
                                        <div class="text-xs text-[#64748B]">SKU: {{ $product->sku }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm text-[#434654]">{{ ucfirst($product->product_type) }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm font-medium text-[#0B1C30]">${{ number_format($product->base_price, 2) }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm text-[#434654]">{{ $product->default_stock }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm text-[#64748B]">{{ $product->created_at->format('M d, Y') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <div class="w-16 h-16 bg-[#DCE9FF] rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                        <path d="M21.25 23.75V20H17.5V17.5H21.25V13.75H23.75V17.5H27.5V20H23.75V23.75H21.25ZM1.25 20V12.5H0V10L1.25 3.75H20L21.25 10V12.5H20V16.25H17.5V12.5H12.5V20H1.25ZM3.75 17.5H10V12.5H3.75V17.5ZM2.5625 10H18.6875L17.9375 6.25H3.3125L2.5625 10ZM1.25 2.5V0H20V2.5H1.25Z" fill="#003D9B"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-[#0B1C30] mb-2">No Products Yet</h3>
                <p class="text-[#434654] mb-6">Add your first product to expand your store catalog</p>
                <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="inline-flex items-center gap-2 bg-[#0052CC] text-white font-bold px-6 py-3 rounded-lg hover:bg-[#0047B3] transition-colors">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                    </svg>
                    Add First Product
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
