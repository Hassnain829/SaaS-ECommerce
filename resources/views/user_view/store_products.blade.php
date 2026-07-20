@extends('layouts.user.user-sidebar')

@section('title', 'Manage Products - ' . $store->name . ' | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Store products" :lead="$store->name">
        <x-slot:actions>
            <a href="{{ route('store-management') }}" class="hidden h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700 lg:inline-flex">Back to stores</a>
            <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="hidden sm:inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-brand-hover">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/></svg>
                <span>Add Product</span>
            </a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
<div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-6">
    <!-- Header with Back button -->
    <div class="flex items-center gap-4">
        <a href="{{ route('store-management') }}" class="flex items-center gap-2 text-[#64748B] hover:text-[#0052CC] transition-colors">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M13 10L6 3M13 10L6 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="font-inter font-medium">Back to Store Management</span>
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <div class="w-16 h-16 bg-[#DCE9FF] rounded-lg flex items-center justify-center">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                        <path d="M21.25 23.75V20H17.5V17.5H21.25V13.75H23.75V17.5H27.5V20H23.75V23.75H21.25ZM1.25 20V12.5H0V10L1.25 3.75H20L21.25 10V12.5H20V16.25H17.5V12.5H12.5V20H1.25ZM3.75 17.5H10V12.5H3.75V17.5ZM2.5625 10H18.6875L17.9375 6.25H3.3125L2.5625 10ZM1.25 2.5V0H20V2.5H1.25Z" fill="#003D9B"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-3xl font-black text-[#0B1C30]">{{ $store->name }}</h2>
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
            <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="flex sm:hidden items-center gap-2 bg-brand text-white font-bold px-4 py-2 rounded-lg hover:bg-brand-hover transition-colors">
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
            <div class="text-sm text-[#64748B] font-inter font-medium">Total Products</div>
            <div class="text-2xl font-bold text-[#0B1C30] mt-1">{{ count($products) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#E2E8F0] p-4">
            <div class="text-sm text-[#64748B] font-inter font-medium">Store Created</div>
            <div class="text-2xl font-bold text-[#0B1C30] mt-1">{{ $store->created_at->format('M d, Y') }}</div>
        </div>
        <div class="bg-white rounded-lg border border-[#E2E8F0] p-4">
            <div class="text-sm text-[#64748B] font-inter font-medium">Slug</div>
            <div class="text-lg font-bold text-[#0B1C30] mt-1">{{ $store->slug }}</div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-[#0B1C30]">Products</h2>
            <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="hidden sm:inline-flex items-center gap-2 bg-brand text-white font-bold px-4 py-2 rounded-lg hover:bg-brand-hover transition-colors">
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
                                    <div class="text-sm font-inter font-medium text-[#0B1C30]">{{ $product->name }}</div>
                                    @if ($product->sku)
                                        <div class="text-xs text-[#64748B]">SKU: {{ $product->sku }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm text-[#434654]">{{ ucfirst($product->product_type) }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm font-inter font-medium text-[#0B1C30]">${{ number_format($product->base_price, 2) }}</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-sm text-[#434654]">{{ $product->variants->sum('stock') }}</span>
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
                <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" class="inline-flex items-center gap-2 bg-brand text-white font-bold px-6 py-3 rounded-lg hover:bg-brand-hover transition-colors">
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
