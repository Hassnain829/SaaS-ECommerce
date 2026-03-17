@extends('layouts.admin.admin-sidebar')

@section('title', 'Global Product Inventory')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Left: Admin Console + Search -->
    <div class="flex items-center gap-4 flex-1">
        <!-- Search (hidden on very small, visible on sm and up) -->
        <div class="relative flex-1 max-w-md hidden sm:block">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.5 13.5L10.875 9.875C11.3333 9.20833 11.6667 8.45833 11.875 7.625C12.0833 6.79167 12.1875 5.91667 12.1875 5C12.1875 3.95833 11.9427 2.98958 11.4531 2.09375C10.9635 1.19792 10.3021 0.484375 9.46875 0.234375C9.21875 0.15625 8.94271 0.09375 8.64062 0.046875C8.33854 0 8.02083 0 7.6875 0C6.64583 0 5.67708 0.244792 4.78125 0.734375C3.88542 1.22396 3.17188 1.88542 2.64062 2.71875C2.10938 3.55208 1.84375 4.47917 1.84375 5.5C1.84375 6.52083 2.10938 7.44792 2.64062 8.28125C3.17188 9.11458 3.88542 9.77604 4.78125 10.2656C5.67708 10.7552 6.64583 11 7.6875 11C8.60417 11 9.47917 10.8958 10.3125 10.6875C11.1458 10.4792 11.8958 10.1458 12.5625 9.6875L16.1875 13.3125L14.5 13.5ZM7.5 9C6.58333 9 5.80208 8.67708 5.15625 8.03125C4.51042 7.38542 4.1875 6.60417 4.1875 5.6875C4.1875 4.77083 4.51042 3.98958 5.15625 3.34375C5.80208 2.69792 6.58333 2.375 7.5 2.375C8.41667 2.375 9.19792 2.69792 9.84375 3.34375C10.4896 3.98958 10.8125 4.77083 10.8125 5.6875C10.8125 6.60417 10.4896 7.38542 9.84375 8.03125C9.19792 8.67708 8.41667 9 7.5 9Z" fill="#434654"/>
                </svg>
            </div>
            <input type="text" placeholder="Search products, tenants, or SKUs..." class="w-full bg-[#EFF4FF] border border-transparent rounded-lg py-2 pl-10 pr-3 text-sm text-[#0B1C30] placeholder:text-[#737685] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>
    </div>

    <!-- Right icons -->
    <div class="flex items-center gap-4 shrink-0">
        <!-- Notification bell with red dot -->
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#434654"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#BA1A1A] border-2 border-white rounded-full"></span>
        </button>

        <!-- Vertical divider -->
        <div class="w-px h-8 bg-[#C3C6D6]/30 hidden sm:block"></div>

        <!-- Profile image (placeholder) -->
        <div class="w-8 h-8 rounded-full border border-[#C3C6D6]/30 overflow-hidden">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="16" cy="12" r="5" fill="#94A3B8"/>
                <path d="M26 26C26 22 22 20 16 20C10 20 6 22 6 26" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter flex-1 overflow-auto">
    <div class="p-4 md:p-6 lg:p-8 space-y-6 md:space-y-8">
        <!-- Page title & subtitle -->
        <div>
            <h1 class="text-2xl md:text-3xl font-medium text-[#0B1C30] font-['Inter'] font-poppins">Global Product Inventory</h1>
            <p class="text-sm md:text-base text-[#434654] mt-1">Aggregate view of SKU performance and stock health across all tenants</p>
        </div>

        <!-- KPI cards grid (4 cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 lg:gap-5">
        <!-- Total active SKUs -->
        <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6 border-l-4 border-l-[#003D9B]">
            <div class="text-[#434654] text-xs font-bold uppercase tracking-wider">Total Active SKUs</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl md:text-3xl font-bold text-[#0B1C30]">12,842</span>
                <span class="flex items-center gap-1 text-[#004E33] text-xs md:text-sm font-semibold">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="none"><path d="M3.5 8V1.9125L0.7 4.7125L0 4L4 0L8 4L7.3 4.7125L4.5 1.9125V8H3.5Z" fill="#004E33"/></svg>
                    4.2%
                </span>
            </div>
        </div>
        <!-- Global inventory value -->
        <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6 border-l-4 border-l-[#003D9B]">
            <div class="text-[#434654] text-xs font-bold uppercase tracking-wider">Global Inventory<br>Value</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl md:text-3xl font-bold text-[#0B1C30]">$2.4M</span>
                <span class="flex items-center gap-1 text-[#004E33] text-xs md:text-sm font-semibold">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="none"><path d="M3.5 8V1.9125L0.7 4.7125L0 4L4 0L8 4L7.3 4.7125L4.5 1.9125V8H3.5Z" fill="#004E33"/></svg>
                    12%
                </span>
            </div>
        </div>
        <!-- Low stock alerts -->
        <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6 border-l-4 border-l-[#BA1A1A]">
            <div class="text-[#434654] text-xs font-bold uppercase tracking-wider">Low Stock Alerts<br>(Global)</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl md:text-3xl font-bold text-[#0B1C30]">43</span>
                <span class="flex items-center gap-1 text-[#BA1A1A] text-xs md:text-sm font-semibold">
                    <svg width="11" height="10" viewBox="0 0 11 10" fill="none"><path d="M0 9.5L5.5 0L11 9.5H0ZM1.725 8.5H9.275L5.5 2L1.725 8.5ZM5.5 8C5.64167 8 5.76042 7.95208 5.85625 7.85625C5.95208 7.76042 6 7.64167 6 7.5C6 7.35833 5.95208 7.23958 5.85625 7.14375C5.76042 7.04792 5.64167 7 5.5 7C5.35833 7 5.23958 7.04792 5.14375 7.14375C5.04792 7.23958 5 7.35833 5 7.5C5 7.64167 5.04792 7.76042 5.14375 7.85625C5.23958 7.95208 5.35833 8 5.5 8ZM5 6.5H6V4H5V6.5Z" fill="#BA1A1A"/></svg>
                    Critical
                </span>
            </div>
        </div>
        <!-- Top performing category -->
        <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6 border-l-4 border-l-[#4EDEA3]">
            <div class="text-[#434654] text-xs font-bold uppercase tracking-wider">Top Performing<br>Category</div>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl md:text-3xl font-bold text-[#0B1C30]">Electronics</span>
                <span class="text-[#434654] text-xs font-medium text-right leading-tight">28% of<br>Sales</span>
            </div>
        </div>
    </div>

        <!-- Filters row -->
        <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.02)] p-3 md:p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-4">
            <div class="flex flex-wrap items-center gap-2">
            <span class="text-[#434654] text-xs font-bold uppercase mr-1">Category:</span>
            <button class="px-4 py-1.5 bg-[#003D9B] text-white text-xs font-semibold rounded-full">All</button>
            <button class="px-4 py-1.5 bg-[#DCE9FF] text-[#434654] text-xs font-semibold rounded-full">Food & Beverage</button>
            <button class="px-4 py-1.5 bg-[#DCE9FF] text-[#434654] text-xs font-semibold rounded-full">Fashion</button>
            <button class="px-4 py-1.5 bg-[#DCE9FF] text-[#434654] text-xs font-semibold rounded-full">Electronics</button>
            </div>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Filter by Tenant input -->
                <div class="relative flex-1 sm:flex-none">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M11 10.5L7.875 7.375C8.33333 6.70833 8.66667 5.95833 8.875 5.125C9.08333 4.29167 9.1875 3.41667 9.1875 2.5C9.1875 1.45833 8.94271 0.489583 8.45312 -0.40625C7.96354 -1.30208 7.30208 -2.01562 6.46875 -2.26562C6.21875 -2.34375 5.94271 -2.40625 5.64062 -2.45312C5.33854 -2.5 5.02083 -2.5 4.6875 -2.5C3.64583 -2.5 2.67708 -2.25521 1.78125 -1.76562C0.885417 -1.27604 0.171875 -0.614583 -0.359375 0.21875C-0.890625 1.05208 -1.15625 1.97917 -1.15625 3C-1.15625 4.02083 -0.890625 4.94792 -0.359375 5.78125C0.171875 6.61458 0.885417 7.27604 1.78125 7.76562C2.67708 8.25521 3.64583 8.5 4.6875 8.5C5.60417 8.5 6.47917 8.39583 7.3125 8.1875C8.14583 7.97917 8.89583 7.64583 9.5625 7.1875L12.6875 10.3125L11 10.5ZM4.5 6.5C3.58333 6.5 2.80208 6.17708 2.15625 5.53125C1.51042 4.88542 1.1875 4.10417 1.1875 3.1875C1.1875 2.27083 1.51042 1.48958 2.15625 0.84375C2.80208 0.197917 3.58333 -0.125 4.5 -0.125C5.41667 -0.125 6.19792 0.197917 6.84375 0.84375C7.48958 1.48958 7.8125 2.27083 7.8125 3.1875C7.8125 4.10417 7.48958 4.88542 6.84375 5.53125C6.19792 6.17708 5.41667 6.5 4.5 6.5Z" fill="#434654"/></svg>
                </div>
                    <input type="text" placeholder="Filter by Tenant..." class="w-full sm:w-48 bg-[#EFF4FF] border border-transparent rounded-lg py-1.5 pl-9 pr-3 text-xs text-[#0B1C30] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                </div>
                <!-- Stock Status toggle -->
                <div class="flex items-center gap-2 bg-[#EFF4FF] rounded-lg px-3 py-1.5">
                    <span class="text-[#434654] text-[10px] font-bold uppercase tracking-wider">Stock<br>Status</span>
                    <div class="relative w-7 h-4 bg-[#4EDEA3] rounded-full">
                        <div class="absolute right-1 top-1 w-2 h-2 bg-white rounded-full"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main area: table + right sidebar -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 md:gap-6">
        <!-- Table (left, takes 3 columns) -->
        <div class="lg:col-span-3 bg-white rounded-2xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#EFF4FF] text-[#434654] text-[10px] md:text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="text-left py-3 md:py-4 px-3 md:px-6">Product & Category</th>
                            <th class="text-left py-3 md:py-4 px-3 md:px-6">Tenant</th>
                            <th class="text-right py-3 md:py-4 px-3 md:px-6">Price Range</th>
                            <th class="text-center py-3 md:py-4 px-3 md:px-6">Global Stock</th>
                            <th class="text-left py-3 md:py-4 px-3 md:px-6">Status</th>
                            <th class="text-right py-3 md:py-4 px-3 md:px-6">Sales (MTD)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#C3C6D6]/10">
                        <!-- Row 1: Artisan Sourdough -->
                        <tr>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 md:w-10 h-8 md:h-10 bg-[#DCE9FF] rounded-lg flex items-center justify-center shrink-0">
                                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none"><path d="M12.325 12C11.7917 11.25 11.0875 10.7292 10.2125 10.4375C9.3375 10.1458 8.43333 10 7.5 10C6.56667 10 5.6625 10.1458 4.7875 10.4375C3.9125 10.7292 3.20833 11.25 2.675 12H12.325V12M0 14C0 12.1833 0.758333 10.7292 2.275 9.6375C3.79167 8.54583 5.53333 8 7.5 8C9.46667 8 11.2083 8.54583 12.725 9.6375C14.2417 10.7292 15 12.1833 15 14H0V14M0 18V16H15V18H0V18M17 22V20H18.4L19.8 6H10.25L10 4H15V0H17V4H22L20.35 20.55C20.3 20.9667 20.1167 21.3125 19.8 21.5875C19.4833 21.8625 19.1167 22 18.7 22H17V22M17 20H18.4V20H17V20M1 22C0.716667 22 0.479167 21.9042 0.2875 21.7125C0.0958333 21.5208 0 21.2833 0 21V20H15V21C15 21.2833 14.9042 21.5208 14.7125 21.7125C14.5208 21.9042 14.2833 22 14 22H1V22M7.5 12V12" fill="#003D9B" fill-opacity="0.6"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[#0B1C30] text-sm truncate">Artisan Sourdough</div>
                                        <div class="text-[10px] md:text-[11px] text-[#434654]">Bakery & Pastries</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-[#60A5FA] shrink-0"></span>
                                    <span class="text-[#434654] text-xs md:text-sm truncate">Blueberry Bistro</span>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$8.50 - $12.00</td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-[#0B1C30] text-xs md:text-sm">1,204</span>
                                    <div class="w-12 h-1 md:w-20 md:h-1.5 bg-[#DCE9FF] rounded-full mt-1 shrink-0">
                                        <div class="w-10 h-1 md:w-16 md:h-1.5 bg-[#006846] rounded-full"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <span class="px-2 md:px-3 py-1 bg-[#4EDEA3] text-[#005236] text-[9px] md:text-[10px] font-bold uppercase rounded-full inline-block">Success</span>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$14,502</td>
                        </tr>
                        <!-- Row 2: Nexus Smartwatch X1 -->
                        <tr>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 md:w-10 h-8 md:h-10 bg-[#DCE9FF] rounded-lg flex items-center justify-center shrink-0">
                                        <svg width="14" height="20" viewBox="0 0 14 20" fill="none"><path d="M5.5 2V2H8.5V2C8.36667 2 8.1625 2 7.8875 2C7.6125 2 7.31667 2 7 2C6.68333 2 6.3875 2 6.1125 2C5.8375 2 5.63333 2 5.5 2V2M5.5 18V18C5.63333 18 5.8375 18 6.1125 18C6.3875 18 6.68333 18 7 18C7.31667 18 7.6125 18 7.8875 18C8.1625 18 8.36667 18 8.5 18V18H5.5V18M4 20L2.65 15.45C1.85 14.8167 1.20833 14.025 0.725 13.075C0.241667 12.125 0 11.1 0 10C0 8.9 0.241667 7.875 0.725 6.925C1.20833 5.975 1.85 5.18333 2.65 4.55L4 0H10L11.35 4.55C12.15 5.18333 12.7917 5.975 13.275 6.925C13.7583 7.875 14 8.9 14 10C14 11.1 13.7583 12.125 13.275 13.075C12.7917 14.025 12.15 14.8167 11.35 15.45L10 20H4V20M7 15C8.38333 15 9.5625 14.5125 10.5375 13.5375C11.5125 12.5625 12 11.3833 12 10C12 8.61667 11.5125 7.4375 10.5375 6.4625C9.5625 5.4875 8.38333 5 7 5C5.61667 5 4.4375 5.4875 3.4625 6.4625C2.4875 7.4375 2 8.61667 2 10C2 11.3833 2.4875 12.5625 3.4625 13.5375C4.4375 14.5125 5.61667 15 7 15V15M5.1 3.25C5.43333 3.16667 5.75417 3.1 6.0625 3.05C6.37083 3 6.68333 2.975 7 2.975C7.31667 2.975 7.62917 3 7.9375 3.05C8.24583 3.1 8.56667 3.16667 8.9 3.25L8.5 2H5.5L5.1 3.25V3.25M5.5 18H8.5L8.9 16.75C8.56667 16.8333 8.24583 16.8958 7.9375 16.9375C7.62917 16.9792 7.31667 17 7 17C6.68333 17 6.37083 16.9792 6.0625 16.9375C5.75417 16.8958 5.43333 16.8333 5.1 16.75L5.5 18V18" fill="#003D9B" fill-opacity="0.6"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[#0B1C30] text-sm truncate">Nexus Smartwatch X1</div>
                                        <div class="text-[10px] md:text-[11px] text-[#434654]">Electronics</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-[#C084FC] shrink-0"></span>
                                    <span class="text-[#434654] text-xs md:text-sm truncate">TechNexus Store</span>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$199.99</td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-[#0B1C30] text-xs md:text-sm">12</span>
                                    <div class="w-12 h-1 md:w-20 md:h-1.5 bg-[#DCE9FF] rounded-full mt-1 shrink-0">
                                        <div class="w-3 h-1 md:w-3 md:h-1.5 bg-[#BA1A1A] rounded-full"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <span class="px-2 md:px-3 py-1 bg-[#FFDAD6] text-[#93000A] text-[9px] md:text-[10px] font-bold uppercase rounded-full inline-block">Error</span>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$284,900</td>
                        </tr>
                        <!-- Row 3: Organic Roast Coffee -->
                        <tr>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 md:w-10 h-8 md:h-10 bg-[#DCE9FF] rounded-lg flex items-center justify-center shrink-0">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M7 15C5.05 15 3.39583 14.3208 2.0375 12.9625C0.679167 11.6042 0 9.95 0 8V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H14.5C15.4667 0 16.2917 0.341667 16.975 1.025C17.6583 1.70833 18 2.53333 18 3.5C18 4.46667 17.6583 5.29167 16.975 5.975C16.2917 6.65833 15.4667 7 14.5 7H14V8C14 9.95 13.3208 11.6042 11.9625 12.9625C10.6042 14.3208 8.95 15 7 15V15M2 5H12V2H2V5V5M7 13C8.38333 13 9.5625 12.5125 10.5375 11.5375C11.5125 10.5625 12 9.38333 12 8V7H2V8C2 9.38333 2.4875 10.5625 3.4625 11.5375C4.4375 12.5125 5.61667 13 7 13V13M14 5H14.5C14.9167 5 15.2708 4.85417 15.5625 4.5625C15.8542 4.27083 16 3.91667 16 3.5C16 3.08333 15.8542 2.72917 15.5625 2.4375C15.2708 2.14583 14.9167 2 14.5 2H14V5V5M0 18V16H16V18H0V18M7 7V7" fill="#003D9B" fill-opacity="0.6"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[#0B1C30] text-sm truncate">Organic Roast Coffee</div>
                                        <div class="text-[10px] md:text-[11px] text-[#434654]">Food & Beverage</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-[#34D399] shrink-0"></span>
                                    <span class="text-[#434654] text-xs md:text-sm truncate">GreenLeaf Market</span>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$14.50</td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-[#0B1C30] text-xs md:text-sm">430</span>
                                    <div class="w-12 h-1 md:w-20 md:h-1.5 bg-[#DCE9FF] rounded-full mt-1 shrink-0">
                                        <div class="w-5 h-1 md:w-9 md:h-1.5 bg-[#003D9B] rounded-full"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <span class="px-2 md:px-3 py-1 bg-[#D3E4FE] text-[#434654] text-[9px] md:text-[10px] font-bold uppercase rounded-full inline-block">Warning</span>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$42,110</td>
                        </tr>
                        <!-- Row 4: Cotton Crew Neck Tee -->
                        <tr>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 md:w-10 h-8 md:h-10 bg-[#DCE9FF] rounded-lg flex items-center justify-center shrink-0">
                                        <svg width="21" height="18" viewBox="0 0 21 18" fill="none"><path d="M4.48333 7.95L3.48333 8.5C3.25 8.63333 3 8.66667 2.73333 8.6C2.46667 8.53333 2.26667 8.38333 2.13333 8.15L0.133333 4.65C0 4.41667 -0.0333333 4.16667 0.0333333 3.9C0.1 3.63333 0.25 3.43333 0.483333 3.3L6.23333 0H7.98333C8.13333 0 8.25417 0.0458333 8.34583 0.1375C8.4375 0.229167 8.48333 0.35 8.48333 0.5V1C8.48333 1.55 8.67917 2.02083 9.07083 2.4125C9.4625 2.80417 9.93333 3 10.4833 3C11.0333 3 11.5042 2.80417 11.8958 2.4125C12.2875 2.02083 12.4833 1.55 12.4833 1V0.5C12.4833 0.35 12.5292 0.229167 12.6208 0.1375C12.7125 0.0458333 12.8333 0 12.9833 0H14.7333L20.4833 3.3C20.7167 3.43333 20.8667 3.63333 20.9333 3.9C21 4.16667 20.9667 4.41667 20.8333 4.65L18.8333 8.15C18.7 8.38333 18.5042 8.52917 18.2458 8.5875C17.9875 8.64583 17.7333 8.60833 17.4833 8.475L16.4833 7.975V17C16.4833 17.2833 16.3875 17.5208 16.1958 17.7125C16.0042 17.9042 15.7667 18 15.4833 18H5.48333C5.2 18 4.9625 17.9042 4.77083 17.7125C4.57917 17.5208 4.48333 17.2833 4.48333 17V7.95V7.95M6.48333 4.6V16H14.4833V4.6L17.5833 6.3L18.6333 4.55L14.3333 2.05V2.05C14.0833 2.9 13.6125 3.60417 12.9208 4.1625C12.2292 4.72083 11.4167 5 10.4833 5C9.55 5 8.7375 4.72083 8.04583 4.1625C7.35417 3.60417 6.88333 2.9 6.63333 2.05V2.05L2.33333 4.55L3.38333 6.3L6.48333 4.6V4.6M10.4833 9.025" fill="#003D9B" fill-opacity="0.6"/></svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[#0B1C30] text-sm truncate">Cotton Crew Neck Tee</div>
                                        <div class="text-[10px] md:text-[11px] text-[#434654]">Fashion</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-[#FB923C] shrink-0"></span>
                                    <span class="text-[#434654] text-xs md:text-sm truncate">UrbanThreads</span>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$24.00</td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <div class="flex flex-col items-center">
                                    <span class="font-semibold text-[#0B1C30] text-xs md:text-sm">3,490</span>
                                    <div class="w-12 h-1 md:w-20 md:h-1.5 bg-[#DCE9FF] rounded-full mt-1 shrink-0">
                                        <div class="w-10 h-1 md:w-[76px] md:h-1.5 bg-[#006846] rounded-full"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6">
                                <span class="px-2 md:px-3 py-1 bg-[#4EDEA3] text-[#005236] text-[9px] md:text-[10px] font-bold uppercase rounded-full inline-block">Success</span>
                            </td>
                            <td class="py-3 md:py-4 px-3 md:px-6 text-right font-semibold text-[#0B1C30] text-xs md:text-sm">$78,200</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Table footer / pagination -->
            <div class="bg-[#EFF4FF] px-3 md:px-6 py-3 md:py-4 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-[#C3C6D6]/10">
                <span class="text-[#434654] text-xs md:text-sm">Showing 1-10 of 12,842 products</span>
                <div class="flex items-center gap-2">
                    <button class="w-7 md:w-8 h-7 md:h-8 flex items-center justify-center bg-white border border-[#C3C6D6]/20 rounded-lg text-[#0B1C30] disabled:opacity-50" disabled>
                        <svg width="5" height="6" viewBox="0 0 5 7" fill="none"><path d="M3.5 7L0 3.5L3.5 0L4.31667 0.816667L1.63333 3.5L4.31667 6.18333L3.5 7Z" fill="currentColor"/></svg>
                    </button>
                    <button class="w-7 md:w-8 h-7 md:h-8 flex items-center justify-center bg-[#003D9B] text-white font-bold rounded-lg text-sm">1</button>
                    <button class="w-7 md:w-8 h-7 md:h-8 flex items-center justify-center bg-white border border-[#C3C6D6]/20 rounded-lg text-[#0B1C30] font-bold text-sm">2</button>
                    <button class="w-7 md:w-8 h-7 md:h-8 flex items-center justify-center bg-white border border-[#C3C6D6]/20 rounded-lg text-[#0B1C30] font-bold text-sm">3</button>
                    <button class="w-7 md:w-8 h-7 md:h-8 flex items-center justify-center bg-white border border-[#C3C6D6]/20 rounded-lg text-[#0B1C30]">
                        <svg width="5" height="7" viewBox="0 0 5 7" fill="none"><path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="currentColor"/></svg>
                    </button>
                </div>
            </div>
        </div>

            <!-- Right sidebar (1 column) -->
            <div class="lg:col-span-1 space-y-4 md:space-y-6">
            <!-- Category Distribution card -->
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xs md:text-sm font-medium text-[#0B1C30] font-poppins">Category Distribution</h3>
                    <svg width="10" height="3" viewBox="0 0 10 3" fill="none"><path d="M1.16667 2.33333C0.845833 2.33333 0.571181 2.2191 0.342708 1.99063C0.114236 1.76215 0 1.4875 0 1.16667C0 0.845833 0.114236 0.571181 0.342708 0.342708C0.571181 0.114236 0.845833 0 1.16667 0C1.4875 0 1.76215 0.114236 1.99063 0.342708C2.2191 0.571181 2.33333 0.845833 2.33333 1.16667C2.33333 1.4875 2.2191 1.76215 1.99063 1.99063C1.76215 2.2191 1.4875 2.33333 1.16667 2.33333ZM4.66667 2.33333C4.34583 2.33333 4.07118 2.2191 3.84271 1.99063C3.61424 1.76215 3.5 1.4875 3.5 1.16667C3.5 0.845833 3.61424 0.571181 3.84271 0.342708C4.07118 0.114236 4.34583 0 4.66667 0C4.9875 0 5.26215 0.114236 5.49062 0.342708C5.7191 0.571181 5.83333 0.845833 5.83333 1.16667C5.83333 1.4875 5.7191 1.76215 5.49062 1.99063C5.26215 2.2191 4.9875 2.33333 4.66667 2.33333ZM8.16667 2.33333C7.84583 2.33333 7.57118 2.2191 7.34271 1.99063C7.11424 1.76215 7 1.4875 7 1.16667C7 0.845833 7.11424 0.571181 7.34271 0.342708C7.57118 0.114236 7.84583 0 8.16667 0C8.4875 0 8.76215 0.114236 8.99063 0.342708C9.2191 0.571181 9.33333 0.845833 9.33333 1.16667C9.33333 1.4875 9.2191 1.76215 8.99063 1.99063C8.76215 2.2191 8.4875 2.33333 8.16667 2.33333Z" fill="#434654"/></svg>
                </div>
                <div class="relative flex justify-center mb-4">
                    <!-- Donut chart approximation -->
                    <div class="relative h-28 md:h-36 w-28 md:w-36">
                        <svg viewBox="0 0 160 160" class="h-full w-full">
                            <circle cx="80" cy="80" r="71.11" stroke="#EFF4FF" stroke-width="17.78" fill="none" />
                            <circle cx="80" cy="80" r="71.11" stroke="#003D9B" stroke-width="17.78" fill="none" stroke-dasharray="446" stroke-dashoffset="0" transform="rotate(-90 80 80)" />
                            <circle cx="80" cy="80" r="71.11" stroke="#4EDEA3" stroke-width="17.78" fill="none" stroke-dasharray="446" stroke-dashoffset="160" transform="rotate(-90 80 80)" />
                            <circle cx="80" cy="80" r="71.11" stroke="#D3E4FE" stroke-width="17.78" fill="none" stroke-dasharray="446" stroke-dashoffset="280" transform="rotate(-90 80 80)" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-lg md:text-xl font-bold text-[#0B1C30]">85%</span>
                            <span class="text-[8px] md:text-[9px] font-bold uppercase tracking-wider text-[#434654]">Filled</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#003D9B] shrink-0"></span>
                            <span class="text-xs text-[#434654]">Electronics</span>
                        </div>
                        <span class="text-xs font-bold text-[#0B1C30]">42%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#4EDEA3] shrink-0"></span>
                            <span class="text-xs text-[#434654]">Food & Beverage</span>
                        </div>
                        <span class="text-xs font-bold text-[#0B1C30]">28%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#D3E4FE] shrink-0"></span>
                            <span class="text-xs text-[#434654]">Fashion</span>
                        </div>
                        <span class="text-xs font-bold text-[#0B1C30]">15%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#C3C6D6] shrink-0"></span>
                            <span class="text-xs text-[#434654]">Others</span>
                        </div>
                        <span class="text-xs font-bold text-[#0B1C30]">15%</span>
                    </div>
                </div>
            </div>

            <!-- Recently Added Products -->
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-4 md:p-6">
                <h3 class="text-xs md:text-sm font-medium text-[#0B1C30] mb-4 font-poppins">Recently Added<br>Products</h3>
                <div class="space-y-3 md:space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 md:w-10 h-8 md:h-10 bg-[#E5EEFF] rounded-lg shrink-0"></div>
                        <div class="min-w-0">
                            <div class="text-xs md:text-xs font-bold text-[#0B1C30] truncate">Wireless Earbudâ€¦</div>
                            <div class="text-[9px] md:text-[10px] text-[#434654] truncate">TechNexus â€¢ 2 mins ago</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 md:w-10 h-8 md:h-10 bg-[#E5EEFF] rounded-lg shrink-0"></div>
                        <div class="min-w-0">
                            <div class="text-xs md:text-xs font-bold text-[#0B1C30] truncate">Ethiopian Yirgacâ€¦</div>
                            <div class="text-[9px] md:text-[10px] text-[#434654] truncate">GreenLeaf â€¢ 45 mins ago</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 md:w-10 h-8 md:h-10 bg-[#E5EEFF] rounded-lg shrink-0"></div>
                        <div class="min-w-0">
                            <div class="text-xs md:text-xs font-bold text-[#0B1C30] truncate">Lunar Run Sneaâ€¦</div>
                            <div class="text-[9px] md:text-[10px] text-[#434654] truncate">UrbanThreads â€¢ 3 hours ago</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-8 md:w-10 h-8 md:h-10 bg-[#E5EEFF] rounded-lg shrink-0"></div>
                        <div class="min-w-0">
                            <div class="text-xs md:text-xs font-bold text-[#0B1C30] truncate">Bamboo Sunglaâ€¦</div>
                            <div class="text-[9px] md:text-[10px] text-[#434654] truncate">GreenLeaf â€¢ 6 hours ago</div>
                        </div>
                    </div>
                </div>
                <button class="w-full mt-4 py-2 bg-[#E5EEFF] text-[#003D9B] text-xs font-bold rounded-lg">View All Activity</button>
            </div>
            </div>
        </div>
    </div>
</div>
@endsection
