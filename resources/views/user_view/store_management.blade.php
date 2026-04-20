@extends('layouts.user.user-sidebar')

@section('title', 'Store Management Hub - BaaS Core')

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
        <input type="text" placeholder="Search orders, products, or reports..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
    </div>

    <!-- Right icons -->
    <div class="flex items-center gap-3 shrink-0">
        <button type="button" class="js-open-create-store-modal hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
            </svg>
            <span>Create Store</span>
        </button>

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
<div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-8">
    <!-- Hero Header -->
    <div>
        <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Your stores</h1>
        <p class="mt-2 max-w-2xl text-sm text-[#64748B]">Each store is its own workspace. Open a store’s <span class="font-semibold text-[#475569]">catalog</span> to manage products.</p>
        <p class="mt-2 text-xs text-[#94A3B8]">Use the sidebar <span class="font-medium text-[#64748B]">Current Store</span> switcher anytime to change which store you are working in.</p>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-[#C3C6D6]/30 overflow-x-auto">
        <nav class="flex gap-8 min-w-max">
            <button class="pb-4 border-b-2 border-[#003D9B] text-[#003D9B] font-bold text-sm">All Stores ({{ count($stores) }})</button>
            <button class="pb-4 border-b-2 border-transparent text-[#434654] font-inter font-medium text-sm">Live ({{ $stores->where('onboarding_completed', true)->count() }})</button>
            <button class="pb-4 border-b-2 border-transparent text-[#434654] font-inter font-medium text-sm">Drafts ({{ $stores->where('onboarding_completed', false)->count() }})</button>
        </nav>
    </div>

    <!-- Stores Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($stores as $store)
            @php
                $storeActionPayload = [
                    'id' => $store->id,
                    'name' => $store->name,
                    'primary_market' => $store->settings['primary_market'] ?? 'Global Market',
                    'currency' => $store->currency,
                    'timezone' => $store->timezone,
                    'address' => $store->address,
                    'category' => $store->category,
                    'custom_category' => $store->settings['custom_category'] ?? '',
                    'business_models' => $store->settings['business_models'] ?? [],
                    'logo_url' => $store->logoPublicUrl(),
                    'update_url' => route('store.update', ['storeId' => $store->id]),
                    'delete_url' => route('store.destroy', ['storeId' => $store->id]),
                ];
            @endphp
            <!-- Dynamic Store Card -->
            <div class="rounded-xl border border-[#E2E8F0] bg-white p-6 shadow-sm">
                <div class="flex justify-between items-start">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                            @if ($store->logo)
                                <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-full w-full object-contain p-1">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-[#DCE9FF]">
                                    <svg width="21" height="18" viewBox="0 0 21 18" fill="none">
                                        <path d="M4.48333 7.95L3.48333 8.5C3.25 8.63333 3 8.66667 2.73333 8.6C2.46667 8.53333 2.26667 8.38333 2.13333 8.15L0.133333 4.65C0 4.41667 -0.0333333 4.16667 0.0333333 3.9C0.1 3.63333 0.25 3.43333 0.483333 3.3L6.23333 0H7.98333C8.13333 0 8.25417 0.0458333 8.34583 0.1375C8.4375 0.229167 8.48333 0.35 8.48333 0.5V1C8.48333 1.55 8.67917 2.02083 9.07083 2.4125C9.4625 2.80417 9.93333 3 10.4833 3C11.0333 3 11.5042 2.80417 11.8958 2.4125C12.2875 2.02083 12.4833 1.55 12.4833 1V0.5C12.4833 0.35 12.5292 0.229167 12.6208 0.1375C12.7125 0.0458333 12.8333 0 12.9833 0H14.7333L20.4833 3.3C20.7167 3.43333 20.8667 3.63333 20.9333 3.9C21 4.16667 20.9667 4.41667 20.8333 4.65L18.8333 8.15C18.7 8.38333 18.5042 8.52917 18.2458 8.5875C17.9875 8.64583 17.7333 8.60833 17.4833 8.475L16.4833 7.975V17C16.4833 17.2833 16.3875 17.5208 16.1958 17.7125C16.0042 17.9042 15.7667 18 15.4833 18H5.48333C5.2 18 4.9625 17.9042 4.77083 17.7125C4.57917 17.5208 4.48333 17.2833 4.48333 17V7.95M6.48333 4.6V16H14.4833V4.6L17.5833 6.3L18.6333 4.55L14.3333 2.05C14.0833 2.9 13.6125 3.60417 12.9208 4.1625C12.2292 4.72083 11.4167 5 10.4833 5C9.55 5 8.7375 4.72083 8.04583 4.1625C7.35417 3.60417 6.88333 2.9 6.63333 2.05L2.33333 4.55L3.38333 6.3L6.48333 4.6Z" fill="#003D9B"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h3 class="font-inter font-medium text-[#0F172A]">{{ $store->name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="px-2 py-0.5 bg-[#DCE9FF] text-[#434654] text-[10px] font-bold uppercase rounded-full">{{ ucfirst($store->category ?? 'General') }}</span>
                                @if ($store->onboarding_completed)
                                    <span class="flex items-center gap-1 px-2 py-0.5 bg-[#4EDEA3]/20 text-[#005236] text-[10px] font-bold uppercase rounded-full">
                                        <span class="w-1.5 h-1.5 bg-[#4EDEA3] rounded-full"></span>
                                        Live
                                    </span>
                                @else
                                    <span class="flex items-center gap-1 px-2 py-0.5 bg-[#C3C6D6]/30 text-[#434654] text-[10px] font-bold uppercase rounded-full">
                                        <span class="w-1.5 h-1.5 bg-[#737685] rounded-full"></span>
                                        Draft
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" class="text-[#434654] cursor-pointer">
                        <path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H9V2H2V2V2V16V16V16H16V16V16V9H18V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2ZM6.7 12.7L5.3 11.3L14.6 2H11V0H18V7H16V3.4L6.7 12.7Z" fill="currentColor"/>
                    </svg>
                </div>

                <!-- Store Details -->
                <div class="mt-4 border-y border-[#E2E8F0] py-4">
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748B]">Slug</span>
                            <span class="font-inter font-medium text-[#0F172A]">{{ $store->slug }}</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#64748B]">Created</span>
                            <span class="font-inter font-medium text-[#0F172A]">{{ $store->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-3 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#F8FAFC] px-2.5 py-1 font-medium text-[#0F172A] ring-1 ring-[#E2E8F0]">
                                <span class="text-[#64748B] font-normal">Products</span>
                                {{ (int) ($store->products_count ?? 0) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#F8FAFC] px-2.5 py-1 font-medium text-[#0F172A] ring-1 ring-[#E2E8F0]">
                                <span class="text-[#64748B] font-normal">Brands</span>
                                {{ (int) ($store->brands_count ?? 0) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-stretch">
                    <a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="flex-1 min-w-[8rem] rounded-lg bg-[#0052CC] py-2.5 text-center text-sm font-bold text-white transition hover:bg-[#0042a3]">Open catalog</a>
                    <a href="{{ route('store.add-product', ['storeId' => $store->id]) }}" title="Add product" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] p-2.5 hover:bg-gray-50 sm:shrink-0">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <path d="M8 16H10V10H16V8H10V2H8V8H2V10H8V16Z" fill="#434654"/>
                        </svg>
                    </a>
                    <button
                        type="button"
                        class="js-open-edit-store-modal inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] p-2.5 hover:bg-gray-100"
                        data-store='@json($storeActionPayload)'
                        title="Edit Store"
                    >
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                            <path d="M2 16H3.425L13.2 6.225L11.775 4.8L2 14.575V16ZM0 18V13.75L13.2 0.575C13.4 0.391667 13.6208 0.25 13.8625 0.15C14.1042 0.05 14.3583 0 14.625 0C14.8917 0 15.15 0.05 15.4 0.15C15.65 0.25 15.8667 0.4 16.05 0.6L17.425 2C17.625 2.18333 17.7708 2.4 17.8625 2.65C17.9542 2.9 18 3.15 18 3.4C18 3.66667 17.9542 3.92083 17.8625 4.1625C17.7708 4.40417 17.625 4.625 17.425 4.825L4.25 18H0Z" fill="#434654"/>
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="lg:col-span-3 text-center py-12">
                <div class="w-16 h-16 bg-[#DCE9FF] rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                        <path d="M21.25 23.75V20H17.5V17.5H21.25V13.75H23.75V17.5H27.5V20H23.75V23.75H21.25ZM1.25 20V12.5H0V10L1.25 3.75H20L21.25 10V12.5H20V16.25H17.5V12.5H12.5V20H1.25ZM3.75 17.5H10V12.5H3.75V17.5ZM2.5625 10H18.6875L17.9375 6.25H3.3125L2.5625 10ZM1.25 2.5V0H20V2.5H1.25Z" fill="#003D9B"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold font-poppins text-[#0B1C30] mb-2">No Stores Yet</h3>
                <p class="text-[#434654] mb-6">Create your first store to get started</p>
                <button type="button" class="js-open-create-store-modal inline-flex items-center gap-2 bg-[#0052CC] text-white font-bold px-6 py-3 rounded-lg hover:bg-[#0042a3] transition">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
                    </svg>
                    Create First Store
                </button>
            </div>
        @endforelse

        <!-- Add Another Store Card (visible when stores exist) -->
        @if (count($stores) > 0)
            <button type="button" class="js-open-create-store-modal border-2 border-dashed border-[#C3C6D6]/50 rounded-xl p-6 flex flex-col items-center justify-center text-center hover:border-[#0052CC] hover:bg-[#F8FAFC] transition">
                <div class="w-14 h-14 bg-[#DCE9FF] rounded-full flex items-center justify-center">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none">
                        <path d="M21.25 23.75V20H17.5V17.5H21.25V13.75H23.75V17.5H27.5V20H23.75V23.75H21.25ZM1.25 20V12.5H0V10L1.25 3.75H20L21.25 10V12.5H20V16.25H17.5V12.5H12.5V20H1.25ZM3.75 17.5H10V12.5H3.75V17.5ZM2.5625 10H18.6875L17.9375 6.25H3.3125L2.5625 10ZM1.25 2.5V0H20V2.5H1.25Z" fill="#434654"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold font-poppins text-[#0B1C30] mt-4">Add Another Store</h3>
                <p class="text-xs text-[#434654] mt-1">Scale your business ecosystem</p>
            </button>
        @endif
    </div>

    <!-- Platform Overview + Recent Activity (two column layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-6">
        <!-- Left: workspace summary -->
        <div class="lg:col-span-2 space-y-5">
            <div class="rounded-xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Workspace summary</p>
                <p class="mt-2 text-2xl font-medium text-[#0F172A] font-poppins">{{ $stores->count() }} {{ Str::plural('store', $stores->count()) }}</p>
                <div class="mt-4 flex flex-wrap gap-4 text-sm">
                    <div>
                        <span class="text-[#64748B]">Products (all stores)</span>
                        <p class="text-lg font-semibold text-[#0F172A]">{{ number_format($stores->sum(fn ($s) => (int) ($s->products_count ?? 0))) }}</p>
                    </div>
                    <div>
                        <span class="text-[#64748B]">Brands</span>
                        <p class="text-lg font-semibold text-[#0F172A]">{{ number_format($stores->sum(fn ($s) => (int) ($s->brands_count ?? 0))) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-[#64748B]">Counts reflect your memberships. Use a store card to open its catalog.</p>
            </div>

            <!-- Upgrade Banner -->
            <div class="bg-[#0052CC]/5 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border border-transparent">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-[#0052CC]/10 rounded-full flex items-center justify-center">
                        <svg width="21" height="21" viewBox="0 0 21 21" fill="none">
                            <path d="M3.6 7.99556L5.55 8.82056C5.78333 8.3539 6.025 7.9039 6.275 7.47056C6.525 7.03723 6.8 6.6039 7.1 6.17056L5.7 5.89556L3.6 7.99556ZM7.15 10.0706L10 12.8956C10.7 12.6289 11.45 12.2206 12.25 11.6706C13.05 11.1206 13.8 10.4956 14.5 9.79556C15.6667 8.6289 16.5792 7.33306 17.2375 5.90806C17.8958 4.48306 18.1833 3.17056 18.1 1.97056C16.9 1.88723 15.5833 2.17473 14.15 2.83306C12.7167 3.4914 11.4167 4.4039 10.25 5.57056C9.55 6.27056 8.925 7.02056 8.375 7.82056C7.825 8.62056 7.41667 9.37056 7.15 10.0706ZM11.6 8.44556C11.2167 8.06223 11.025 7.5914 11.025 7.03306C11.025 6.47473 11.2167 6.0039 11.6 5.62056C11.9833 5.23723 12.4583 5.04556 13.025 5.04556C13.5917 5.04556 14.0667 5.23723 14.45 5.62056C14.8333 6.0039 15.025 6.47473 15.025 7.03306C15.025 7.5914 14.8333 8.06223 14.45 8.44556C14.0667 8.8289 13.5917 9.02056 13.025 9.02056C12.4583 9.02056 11.9833 8.8289 11.6 8.44556ZM12.075 16.4706L14.175 14.3706L13.9 12.9706C13.4667 13.2706 13.0333 13.5414 12.6 13.7831C12.1667 14.0247 11.7167 14.2622 11.25 14.4956L12.075 16.4706ZM19.9 0.145565C20.2167 2.16223 20.0208 4.12473 19.3125 6.03306C18.6042 7.9414 17.3833 9.76223 15.65 11.4956L16.15 13.9706C16.2167 14.3039 16.2 14.6289 16.1 14.9456C16 15.2622 15.8333 15.5372 15.6 15.7706L11.4 19.9706L9.3 15.0456L5.025 10.7706L0.1 8.67056L4.275 4.47056C4.50833 4.23723 4.7875 4.07056 5.1125 3.97056C5.4375 3.87056 5.76667 3.8539 6.1 3.92056L8.575 4.42056C10.3083 2.68723 12.125 1.46223 14.025 0.745565C15.925 0.0288979 17.8833 -0.171102 19.9 0.145565ZM1.875 13.9456C2.45833 13.3622 3.17083 13.0664 4.0125 13.0581C4.85417 13.0497 5.56667 13.3372 6.15 13.9206C6.73333 14.5039 7.02083 15.2164 7.0125 16.0581C7.00417 16.8997 6.70833 17.6122 6.125 18.1956C5.70833 18.6122 5.0125 18.9706 4.0375 19.2706C3.0625 19.5706 1.71667 19.8372 0 20.0706C0.233333 18.3539 0.5 17.0081 0.8 16.0331C1.1 15.0581 1.45833 14.3622 1.875 13.9456ZM3.3 15.3456C3.13333 15.5122 2.96667 15.8164 2.8 16.2581C2.63333 16.6997 2.51667 17.1456 2.45 17.5956C2.9 17.5289 3.34583 17.4164 3.7875 17.2581C4.22917 17.0997 4.53333 16.9372 4.7 16.7706C4.9 16.5706 5.00833 16.3289 5.025 16.0456C5.04167 15.7622 4.95 15.5206 4.75 15.3206C4.55 15.1206 4.30833 15.0247 4.025 15.0331C3.74167 15.0414 3.5 15.1456 3.3 15.3456Z" fill="#003D9B"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-[#0B1C30]">Scale your platform</h4>
                        <p class="text-xs text-[#434654]">Unlock advanced automation and priority support for your stores.</p>
                    </div>
                </div>
                <button class="px-6 py-2 bg-[#0052CC] text-white text-xs font-bold rounded-lg whitespace-nowrap">View Upgrade Options</button>
            </div>
        </div>

        <!-- Right: Recent Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-transparent p-5">
            <div class="flex items-center gap-3 mb-4">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M9 18C6.7 18 4.69583 17.2375 2.9875 15.7125C1.27917 14.1875 0.3 12.2833 0.05 10H2.1C2.33333 11.7333 3.10417 13.1667 4.4125 14.3C5.72083 15.4333 7.25 16 9 16C10.95 16 12.6042 15.3208 13.9625 13.9625C15.3208 12.6042 16 10.95 16 9C16 7.05 15.3208 5.39583 13.9625 4.0375C12.6042 2.67917 10.95 2 9 2C7.85 2 6.775 2.26667 5.775 2.8C4.775 3.33333 3.93333 4.06667 3.25 5H6V7H0V1H2V3.35C2.85 2.28333 3.8875 1.45833 5.1125 0.875C6.3375 0.291667 7.63333 0 9 0C10.25 0 11.4208 0.2375 12.5125 0.7125C13.6042 1.1875 14.5542 1.82917 15.3625 2.6375C16.1708 3.44583 16.8125 4.39583 17.2875 5.4875C17.7625 6.57917 18 7.75 18 9C18 10.25 17.7625 11.4208 17.2875 12.5125C16.8125 13.6042 16.1708 14.5542 15.3625 15.3625C14.5542 16.1708 13.6042 16.8125 12.5125 17.2875C11.4208 17.7625 10.25 18 9 18ZM11.8 13.2L8 9.4V4H10V8.6L13.2 11.8L11.8 13.2Z" fill="#003D9B"/>
                </svg>
                <h4 class="text-base font-bold text-[#0B1C30]">Recent Activity</h4>
            </div>

            <div class="space-y-4">
                <!-- New Order -->
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-[#4EDEA3]/20 rounded-full flex items-center justify-center">
                        <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
                            <path d="M1.16667 11.6667C0.845833 11.6667 0.571181 11.5524 0.342708 11.324C0.114236 11.0955 0 10.8208 0 10.5V3.5C0 3.17917 0.114236 2.90451 0.342708 2.67604C0.571181 2.44757 0.845833 2.33333 1.16667 2.33333H2.33333C2.33333 1.69167 2.56181 1.14236 3.01875 0.685417C3.47569 0.228472 4.025 0 4.66667 0C5.30833 0 5.85764 0.228472 6.31458 0.685417C6.77153 1.14236 7 1.69167 7 2.33333H8.16667C8.4875 2.33333 8.76215 2.44757 8.99063 2.67604C9.2191 2.90451 9.33333 3.17917 9.33333 3.5V10.5C9.33333 10.8208 9.2191 11.0955 8.99063 11.324C8.76215 11.5524 8.4875 11.6667 8.16667 11.6667H1.16667ZM1.16667 10.5H8.16667V3.5H7V4.66667C7 4.83194 6.9441 4.97049 6.83229 5.08229C6.72049 5.1941 6.58194 5.25 6.41667 5.25C6.25139 5.25 6.11285 5.1941 6.00104 5.08229C5.88924 4.97049 5.83333 4.83194 5.83333 4.66667V3.5H3.5V4.66667C3.5 4.83194 3.4441 4.97049 3.33229 5.08229C3.22049 5.1941 3.08194 5.25 2.91667 5.25C2.75139 5.25 2.61285 5.1941 2.50104 5.08229C2.38924 4.97049 2.33333 4.83194 2.33333 4.66667V3.5H1.16667V10.5ZM3.5 2.33333H5.83333C5.83333 2.0125 5.7191 1.73785 5.49062 1.50937C5.26215 1.2809 4.9875 1.16667 4.66667 1.16667C4.34583 1.16667 4.07118 1.2809 3.84271 1.50937C3.61424 1.73785 3.5 2.0125 3.5 2.33333Z" fill="#005236"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-inter font-medium text-[#0B1C30]">New Order: #8942</div>
                        <div class="text-xs text-[#434654]">Modern Marketplace • 2m ago</div>
                    </div>
                </div>
                <!-- Theme Updated -->
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-[#D5E3FC]/50 rounded-full flex items-center justify-center">
                        <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                            <path d="M5.25 10.5C4.52083 10.5 3.83785 10.3615 3.20104 10.0844C2.56424 9.80729 2.01007 9.43299 1.53854 8.96146C1.06701 8.48993 0.692708 7.93576 0.415625 7.29896C0.138542 6.66215 0 5.97917 0 5.25C0 4.52083 0.138542 3.83785 0.415625 3.20104C0.692708 2.56424 1.06701 2.01007 1.53854 1.53854C2.01007 1.06701 2.56424 0.692708 3.20104 0.415625C3.83785 0.138542 4.52083 0 5.25 0C6.04722 0 6.80312 0.170139 7.51771 0.510417C8.23229 0.850694 8.8375 1.33194 9.33333 1.95417V0.583333H10.5V4.08333H7V2.91667H8.60417C8.20556 2.37222 7.71458 1.94444 7.13125 1.63333C6.54792 1.32222 5.92083 1.16667 5.25 1.16667C4.1125 1.16667 3.14757 1.56285 2.35521 2.35521C1.56285 3.14757 1.16667 4.1125 1.16667 5.25C1.16667 6.3875 1.56285 7.35243 2.35521 8.14479C3.14757 8.93715 4.1125 9.33333 5.25 9.33333C6.27083 9.33333 7.16285 9.00278 7.92604 8.34167C8.68924 7.68056 9.13889 6.84444 9.275 5.83333H10.4708C10.325 7.16528 9.75382 8.27604 8.75729 9.16562C7.76076 10.0552 6.59167 10.5 5.25 10.5ZM6.88333 7.7L4.66667 5.48333V2.33333H5.83333V5.01667L7.7 6.88333L6.88333 7.7Z" fill="#57657A"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-inter font-medium text-[#0B1C30]">Theme Updated: V2.4</div>
                        <div class="text-xs text-[#434654]">Electro Hub • 45m ago</div>
                    </div>
                </div>
                <!-- New Domain Linked -->
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-[#0052CC]/20 rounded-full flex items-center justify-center">
                        <svg width="11" height="12" viewBox="0 0 11 12" fill="none">
                            <path d="M2.625 1.75C2.38194 1.75 2.17535 1.83507 2.00521 2.00521C1.83507 2.17535 1.75 2.38194 1.75 2.625C1.75 2.86806 1.83507 3.07465 2.00521 3.24479C2.17535 3.41493 2.38194 3.5 2.625 3.5C2.86806 3.5 3.07465 3.41493 3.24479 3.24479C3.41493 3.07465 3.5 2.86806 3.5 2.625C3.5 2.38194 3.41493 2.17535 3.24479 2.00521C3.07465 1.83507 2.86806 1.75 2.625 1.75ZM2.625 7.58333C2.38194 7.58333 2.17535 7.6684 2.00521 7.83854C1.83507 8.00868 1.75 8.21528 1.75 8.45833C1.75 8.70139 1.83507 8.90799 2.00521 9.07812C2.17535 9.24826 2.38194 9.33333 2.625 9.33333C2.86806 9.33333 3.07465 9.24826 3.24479 9.07812C3.41493 8.90799 3.5 8.70139 3.5 8.45833C3.5 8.21528 3.41493 8.00868 3.24479 7.83854C3.07465 7.6684 2.86806 7.58333 2.625 7.58333ZM0.583333 0H9.91667C10.0819 0 10.2205 0.0559028 10.3323 0.167708C10.4441 0.279514 10.5 0.418056 10.5 0.583333V4.66667C10.5 4.83194 10.4441 4.97049 10.3323 5.08229C10.2205 5.1941 10.0819 5.25 9.91667 5.25H0.583333C0.418056 5.25 0.279514 5.1941 0.167708 5.08229C0.0559028 4.97049 0 4.83194 0 4.66667V0.583333C0 0.418056 0.0559028 0.279514 0.167708 0.167708C0.279514 0.0559028 0.418056 0 0.583333 0ZM1.16667 1.16667V4.08333H9.33333V1.16667H1.16667ZM0.583333 5.83333H9.91667C10.0819 5.83333 10.2205 5.88924 10.3323 6.00104C10.4441 6.11285 10.5 6.25139 10.5 6.41667V10.5C10.5 10.6653 10.4441 10.8038 10.3323 10.9156C10.2205 11.0274 10.0819 11.0833 9.91667 11.0833H0.583333C0.418056 11.0833 0.279514 11.0274 0.167708 10.9156C0.0559028 10.8038 0 10.6653 0 10.5V6.41667C0 6.25139 0.0559028 6.11285 0.167708 6.00104C0.279514 5.88924 0.418056 5.83333 0.583333 5.83333ZM1.16667 7V9.91667H9.33333V7H1.16667Z" fill="#003D9B"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-inter font-medium text-[#0B1C30]">New Domain Linked</div>
                        <div class="text-xs text-[#434654]">Organic Living • 2h ago</div>
                    </div>
                </div>
                <!-- Inventory Alert -->
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-[#FFDAD6]/50 rounded-full flex items-center justify-center">
                        <svg width="13" height="12" viewBox="0 0 13 12" fill="none">
                            <path d="M0 11.0833L6.41667 0L12.8333 11.0833H0ZM2.0125 9.91667H10.8208L6.41667 2.33333L2.0125 9.91667ZM6.41667 9.33333C6.58194 9.33333 6.72049 9.27743 6.83229 9.16562C6.9441 9.05382 7 8.91528 7 8.75C7 8.58472 6.9441 8.44618 6.83229 8.33438C6.72049 8.22257 6.58194 8.16667 6.41667 8.16667C6.25139 8.16667 6.11285 8.22257 6.00104 8.33438C5.88924 8.44618 5.83333 8.58472 5.83333 8.75C5.83333 8.91528 5.88924 9.05382 6.00104 9.16562C6.11285 9.27743 6.25139 9.33333 6.41667 9.33333ZM5.83333 7.58333H7V4.66667H5.83333V7.58333Z" fill="#93000A"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-inter font-medium text-[#0B1C30]">Inventory Alert: Low Stock</div>
                        <div class="text-xs text-[#434654]">Modern Marketplace • 5h ago</div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-2 border-t border-[#C3C6D6]/10">
                <button class="w-full py-2 border border-[#003D9B]/20 text-[#003D9B] text-sm font-bold rounded-lg">View All Activity</button>
            </div>
        </div>
    </div>
</div>

@include('user_view.partials.store_create_modal')
@include('user_view.partials.store_edit_modal')
@endsection
