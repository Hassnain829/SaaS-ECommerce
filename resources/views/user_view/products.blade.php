@extends('layouts.user.user-sidebar')

@section('title', 'Products Admin - BaaS Core')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', 'E-commerce Portal')
@section('sidebar_logo')
    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M11 13L9 11L11 9L13 11L11 13ZM8.875 7.125L6.375 4.625L11 0L15.625 4.625L13.125 7.125L11 5L8.875 7.125ZM4.625 15.625L0 11L4.625 6.375L7.125 8.875L5 11L7.125 13.125L4.625 15.625ZM17.375 15.625L14.875 13.125L17 11L14.875 8.875L17.375 6.375L22 11L17.375 15.625ZM11 22L6.375 17.375L8.875 14.875L11 17L13.125 14.875L15.625 17.375L11 22Z"
            fill="white" />
    </svg>
@endsection

@section('topbar')
    <header
        class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
        <button id="sidebarToggle" onclick="openSidebar()"
            class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0"
            aria-label="Open sidebar">
            <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
                <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor" />
            </svg>
        </button>

        <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path
                        d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z"
                        fill="currentColor" />
                </svg>
            </span>
            <input type="text" placeholder="Search products, SKUs, categories..."
                class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('onboarding-Step2-AddProductVariations', ['fresh' => 1]) }}"
                class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
                </svg>
                <span>Add Product</span>
            </a>

            <div class="w-px h-6 bg-[#E2E8F0] hidden sm:block"></div>

            <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
                <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                    <path
                        d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z"
                        fill="#64748B" />
                </svg>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
            </button>

            <button class="p-2 rounded-full hover:bg-gray-100 transition-colors hidden sm:flex">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path
                        d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95C11.2333 17.95 13.125 17.175 14.675 15.625C16.225 14.075 17 12.1833 17 9.95C17 7.71667 16.225 5.825 14.675 4.275C13.125 2.725 11.2333 1.95 9 1.95C6.76667 1.95 4.875 2.725 3.325 4.275C1.775 5.825 1 7.71667 1 9.95C1 12.1833 1.775 14.075 3.325 15.625C4.875 17.175 6.76667 17.95 9 17.95ZM9 15C9.28333 15 9.52083 14.9042 9.7125 14.7125C9.90417 14.5208 10 14.2833 10 14C10 13.7167 9.90417 13.4792 9.7125 13.2875C9.52083 13.0958 9.28333 13 9 13C8.71667 13 8.47917 13.0958 8.2875 13.2875C8.09583 13.4792 8 13.7167 8 14C8 14.2833 8.09583 14.5208 8.2875 14.7125C8.47917 14.9042 8.71667 15 9 15ZM9 11H10V5H8V6H9V11Z"
                        fill="#64748B" />
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
    <!-- Page heading -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Products</h1>
            <p class="text-sm text-[#64748B] mt-0.5">Manage your inventory and product listings across all channels.</p>
        </div>
        <!-- Mobile add btn -->
        <a href="{{ route('onboarding-Step2-AddProductVariations', ['fresh' => 1]) }}"
            class="sm:hidden flex items-center justify-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2.5 rounded-lg">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white" />
            </svg>
            Add Product
        </a>
    </div>

    <!-- STATS CARDS (4-col Ã¢â€ â€™ 2-col on md Ã¢â€ â€™ 1-col on sm) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Total Products -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 bg-[#0052CC]/10 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path
                            d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z"
                            fill="#0052CC" />
                    </svg>
                </div>
                <span
                    class="bg-green-50 text-green-600 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                    <svg width="10" height="7" viewBox="0 0 12 8" fill="none">
                        <path
                            d="M0.933333 8L0 7.06667L4.93333 2.1L7.6 4.76667L11.0667 1.33333H9.33333V0H13.3333V4H12V2.26667L7.6 6.66667L4.93333 4L0.933333 8Z"
                            fill="#059669" />
                    </svg>
                    12.5%
                </span>
            </div>
            <div class="mt-3">
                <div class="text-sm text-[#64748B]">Total Products</div>
                <div class="text-2xl font-medium text-[#0F172A] font-poppins">1,248</div>
                <div class="text-xs text-green-600 font-semibold mt-1">+12% this month</div>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path
                            d="M9 13H11V15H9V13ZM9 5H11V11H9V5ZM10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM10 18C5.59 18 2 14.41 2 10C2 5.59 5.59 2 10 2C14.41 2 18 5.59 18 10C18 14.41 14.41 18 10 18Z"
                            fill="#EF4444" />
                    </svg>
                </div>
                <span class="bg-red-50 text-red-500 text-xs font-semibold px-2 py-1 rounded-full">Alert</span>
            </div>
            <div class="mt-3">
                <div class="text-sm text-[#64748B]">Out of Stock</div>
                <div class="text-2xl font-medium text-[#0F172A] font-poppins">24</div>
                <div class="text-xs text-red-500 font-bold mt-1">Needs attention</div>
            </div>
        </div>

        <!-- Low Stock -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center">
                    <svg width="4" height="20" viewBox="0 0 4 20" fill="none">
                        <rect width="4" height="20" rx="2" fill="#F97316" />
                    </svg>
                </div>
                <span class="bg-orange-50 text-orange-500 text-xs font-semibold px-2 py-1 rounded-full">Low</span>
            </div>
            <div class="mt-3">
                <div class="text-sm text-[#64748B]">Low Stock</div>
                <div class="text-2xl font-medium text-[#0F172A] font-poppins">12</div>
                <div class="text-xs text-orange-500 font-bold mt-1">Ordering recommended</div>
            </div>
        </div>

        <!-- Active Categories -->
        <div class="bg-[#0052CC]/5 rounded-xl border border-[#0052CC]/20 p-5 shadow-sm">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 bg-[#0052CC]/10 rounded-lg flex items-center justify-center">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path
                            d="M4 14H6V9H4V14ZM12 14H14V4H12V14ZM8 14H10V11H8V14ZM8 9H10V7H8V9ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z"
                            fill="#0052CC" />
                    </svg>
                </div>
                <span class="bg-[#0052CC]/10 text-[#0052CC] text-xs font-semibold px-2 py-1 rounded-full">4 markets</span>
            </div>
            <div class="mt-3">
                <div class="text-sm text-[#0052CC]/70">Active Categories</div>
                <div class="text-2xl font-medium text-[#0052CC] font-poppins">18</div>
                <div class="text-xs text-[#0052CC]/60 font-bold mt-1">Across 4 marketplaces</div>
            </div>
        </div>

    </div>

    <!-- PRODUCT TABLE CARD -->
    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-2 px-4 lg:px-5 py-4 border-b border-[#E2E8F0]">
            <button class="flex items-center gap-1.5 bg-[#0052CC] text-white text-sm font-semibold px-4 py-2 rounded-full">
                All Categories
                <svg width="12" height="12" viewBox="0 0 14 14" fill="none">
                    <path d="M7 9L3 5H11L7 9Z" fill="white" />
                </svg>
            </button>
            <button
                class="flex items-center gap-1.5 border border-[#E2E8F0] text-[#475569] text-sm font-inter font-medium px-4 py-2 rounded-full hover:bg-gray-50 transition-colors">
                Low Stock
                <span
                    class="bg-[#F97316] text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">12</span>
            </button>
            <button
                class="border border-[#E2E8F0] text-[#475569] text-sm font-inter font-medium px-4 py-2 rounded-full hover:bg-gray-50 transition-colors">Published</button>
            <button
                class="border border-[#E2E8F0] text-[#475569] text-sm font-inter font-medium px-4 py-2 rounded-full hover:bg-gray-50 transition-colors">Drafts</button>
            <div class="ml-auto flex gap-2">
                <button
                    class="w-9 h-9 flex items-center justify-center border border-[#E2E8F0] rounded-lg hover:bg-gray-50">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M7 11H9V9H7V11ZM1 3V5H15V3H1ZM4 8H12V6H4V8Z" fill="#475569" />
                    </svg>
                </button>
                <button
                    class="w-9 h-9 flex items-center justify-center border border-[#E2E8F0] rounded-lg hover:bg-gray-50">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M2 14H14V12H2V14ZM14 6H11V2H5V6H2L8 12L14 6Z" fill="#475569" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Table (horizontally scrollable on small screens) -->
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="border-b border-[#E2E8F0] bg-[#F8FAFC]">
                        <th class="w-10 px-4 py-3"><input type="checkbox"
                                class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Product
                        </th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Category
                        </th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Status
                        </th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Price</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider"
                            colspan="2">Inventory</th>
                        <th class="text-left px-4 py-3 text-[#64748B] text-xs font-bold uppercase tracking-wider">Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F1F5F9]">

                    <!-- Row 1 Ã¢â‚¬â€œ Wireless Noise (In Stock) -->
                    <tr class="hover:bg-[#F8FAFC] transition-colors">
                        <td class="px-4 py-4"><input type="checkbox"
                                class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#FDDCB5] shrink-0 flex items-center justify-center">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path
                                            d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z"
                                            fill="#F4A261" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-inter font-medium text-[#0F172A] text-sm">Wireless Noise ...</div>
                                    <div class="text-[#94A3B8] text-xs">SKU: WH-1000XM4</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4"><span
                                class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Electronics</span></td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 bg-green-50 text-green-600 text-xs font-bold px-3 py-1 rounded-full">
                                <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                    <circle cx="4" cy="4" r="4" fill="#22C55E" />
                                </svg>
                                In Stock
                            </span>
                        </td>
                        <td class="px-4 py-4 font-inter font-medium text-[#0F172A] text-sm">$349.00</td>
                        <td class="px-4 py-4 w-28">
                            <div class="bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden">
                                <div class="h-full rounded-full bg-[#3B82F6]" style="width:85%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-[#475569] font-semibold text-sm w-8">85</td>
                        <td class="px-4 py-4">
                            <button class="text-[#0052CC] text-sm font-semibold hover:underline">Edit</button>
                        </td>
                    </tr>

                    <!-- Row 2 Ã¢â‚¬â€œ Premium Leather (Low Stock) -->
                    <tr class="hover:bg-[#F8FAFC] transition-colors">
                        <td class="px-4 py-4"><input type="checkbox"
                                class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#F5D49A] shrink-0 flex items-center justify-center">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path
                                            d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z"
                                            fill="#E8B86D" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-inter font-medium text-[#0F172A] text-sm">Premium Leather ...</div>
                                    <div class="text-[#94A3B8] text-xs">SKU: ACC-LWL-01</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4"><span
                                class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Accessories</span></td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 bg-orange-50 text-orange-500 text-xs font-bold px-3 py-1 rounded-full border border-orange-100">
                                <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                    <circle cx="4" cy="4" r="4" fill="#F97316" />
                                </svg>
                                Low Stock
                            </span>
                        </td>
                        <td class="px-4 py-4 font-inter font-medium text-[#0F172A] text-sm">$59.00</td>
                        <td class="px-4 py-4 w-28">
                            <div class="bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden">
                                <div class="h-full rounded-full bg-[#F97316]" style="width:12%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-[#475569] font-semibold text-sm w-8">12</td>
                        <td class="px-4 py-4">
                            <button class="text-[#0052CC] text-sm font-semibold hover:underline">Edit</button>
                        </td>
                    </tr>

                    <!-- Row 3 Ã¢â‚¬â€œ Organic Cotton (Out of Stock) -->
                    <tr class="hover:bg-[#F8FAFC] transition-colors">
                        <td class="px-4 py-4"><input type="checkbox"
                                class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#E8E0CC] shrink-0 flex items-center justify-center">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path
                                            d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z"
                                            fill="#C8B89A" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-inter font-medium text-[#0F172A] text-sm">Organic Cotton ...</div>
                                    <div class="text-[#94A3B8] text-xs">SKU: TS-ORG-WHT</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4"><span
                                class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Apparel</span></td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-xs font-bold px-3 py-1 rounded-full">
                                <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                    <circle cx="4" cy="4" r="4" fill="#EF4444" />
                                </svg>
                                Out of Stock
                            </span>
                        </td>
                        <td class="px-4 py-4 font-inter font-medium text-[#0F172A] text-sm">$25.00</td>
                        <td class="px-4 py-4 w-28">
                            <div class="bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden">
                                <div class="h-full rounded-full bg-[#E2E8F0]" style="width:2%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-[#475569] font-semibold text-sm w-8">0</td>
                        <td class="px-4 py-4">
                            <button class="text-[#0052CC] text-sm font-semibold hover:underline">Edit</button>
                        </td>
                    </tr>

                    <!-- Row 4 Ã¢â‚¬â€œ Smart Fitness (In Stock) -->
                    <tr class="hover:bg-[#F8FAFC] transition-colors">
                        <td class="px-4 py-4"><input type="checkbox"
                                class="w-4 h-4 rounded border-[#CBD5E1] accent-[#0052CC]"></td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-[#FDBA74] shrink-0 flex items-center justify-center">
                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                                        <path
                                            d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z"
                                            fill="#F97316" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-inter font-medium text-[#0F172A] text-sm">Smart Fitness ...</div>
                                    <div class="text-[#94A3B8] text-xs">SKU: EL-FIT-V2</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4"><span
                                class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Electronics</span></td>
                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center gap-1.5 bg-green-50 text-green-600 text-xs font-bold px-3 py-1 rounded-full">
                                <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                    <circle cx="4" cy="4" r="4" fill="#22C55E" />
                                </svg>
                                In Stock
                            </span>
                        </td>
                        <td class="px-4 py-4 font-inter font-medium text-[#0F172A] text-sm">$129.00</td>
                        <td class="px-4 py-4 w-28">
                            <div class="bg-[#F1F5F9] rounded-full h-1.5 min-w-20 overflow-hidden">
                                <div class="h-full rounded-full bg-[#3B82F6]" style="width:42%"></div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-[#475569] font-semibold text-sm w-8">42</td>
                        <td class="px-4 py-4">
                            <button class="text-[#0052CC] text-sm font-semibold hover:underline">Edit</button>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        <!-- Pagination (matches Dashboard.txt style) -->
        <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 lg:px-5 py-4 border-t border-[#E2E8F0]">
            <span class="text-[#64748B] text-sm">Showing <span class="font-semibold text-[#0F172A]">1 to 4</span> of <span
                    class="font-semibold text-[#0F172A]">120</span> results</span>
            <div class="flex items-center gap-1.5">
                <button
                    class="px-3 py-1.5 text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50 transition-colors">Previous</button>
                <button
                    class="w-8 h-8 flex items-center justify-center text-sm font-bold bg-[#0052CC] text-white rounded-lg">1</button>
                <button
                    class="w-8 h-8 flex items-center justify-center text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50">2</button>
                <button
                    class="w-8 h-8 flex items-center justify-center text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50">3</button>
                <span class="text-[#94A3B8] text-sm px-0.5">...</span>
                <button
                    class="w-8 h-8 flex items-center justify-center text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50">30</button>
                <button
                    class="px-3 py-1.5 text-sm font-inter font-medium text-[#475569] border border-[#E2E8F0] rounded-lg hover:bg-gray-50 transition-colors">Next</button>
            </div>
        </div>

    </div>
@endsection