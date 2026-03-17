@extends('layouts.admin.admin-Sidebar')

@section('title', 'Global Billing & Financials')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Search bar (centered) -->
    <div class="flex-1 flex justify-center">
        <div class="relative w-full max-w-md">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M12.45 13.5L7.725 8.775C7.35 9.075 6.91875 9.3125 6.43125 9.4875C5.94375 9.6625 5.425 9.75 4.875 9.75C3.5125 9.75 2.35938 9.27813 1.41562 8.33438C0.471875 7.39063 0 6.2375 0 4.875C0 3.5125 0.471875 2.35938 1.41562 1.41562C2.35938 0.471875 3.5125 0 4.875 0C6.2375 0 7.39063 0.471875 8.33438 1.41562C9.27813 2.35938 9.75 3.5125 9.75 4.875C9.75 5.425 9.6625 5.94375 9.4875 6.43125C9.3125 6.91875 9.075 7.35 8.775 7.725L13.5 12.45L12.45 13.5ZM4.875 8.25C5.8125 8.25 6.60938 7.92188 7.26562 7.26562C7.92188 6.60938 8.25 5.8125 8.25 4.875C8.25 3.9375 7.92188 3.14062 7.26562 2.48438C6.60938 1.82812 5.8125 1.5 4.875 1.5C3.9375 1.5 3.14062 1.82812 2.48438 2.48438C1.82812 3.14062 1.5 3.9375 1.5 4.875C1.5 5.8125 1.82812 6.60938 2.48438 7.26562C3.14062 7.92188 3.9375 8.25 4.875 8.25Z" fill="#737685"/>
                </svg>
            </div>
            <input type="text" placeholder="Search billing cycles, merchants, or invoices..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>
    </div>

    <!-- Right icons and profile -->
    <div class="flex items-center gap-4 shrink-0">
        <!-- Notification bell with red dot -->
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#434654"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#BA1A1A] border-2 border-white rounded-full"></span>
        </button>

        <!-- Vertical divider -->
        <div class="w-px h-8 bg-[#C3C6D6]/30 hidden sm:block"></div>

        <!-- User profile -->
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-[#0B1C30]">Admin Profile</div>
                <div class="text-[10px] text-[#434654]">Master Administrator</div>
            </div>
            <div class="w-9 h-9 rounded-full bg-[#EFF4FF] border border-[#003D9B]/10 overflow-hidden">
                <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="13" r="5" fill="#94A3B8"/>
                    <path d="M28 26C28 22 24 20 18 20C12 20 8 22 8 26" fill="#94A3B8"/>
                </svg>
            </div>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter max-w-9xl mx-auto space-y-8">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-medium text-[#0B1C30] font-poppins">Global Billing & Financials</h1>
            <p class="text-sm md:text-base text-[#434654] mt-1">Cross-platform revenue health and merchant settlement orchestration.</p>
        </div>
        <!-- Action buttons -->
        <div class="flex items-center gap-3">
            <button class="flex items-center gap-2 px-4 py-2 bg-[#DCE9FF] text-[#003D9B] text-sm font-semibold rounded-lg hover:bg-[#c8d9ff] transition">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M6 9L2.25 5.25L3.3 4.1625L5.25 6.1125V0H6.75V6.1125L8.7 4.1625L9.75 5.25L6 9ZM1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5V8.25H1.5V10.5H10.5V8.25H12V10.5C12 10.9125 11.8531 11.2656 11.5594 11.5594C11.2656 11.8531 10.9125 12 10.5 12H1.5Z" fill="#003D9B"/>
                </svg>
                <span>Export Report</span>
            </button>
            <button class="flex items-center gap-2 px-4 py-2 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white text-sm font-semibold rounded-lg shadow-[0_4px_6px_-4px_rgba(0,61,155,0.1),0_10px_15px_-3px_rgba(0,61,155,0.1)] hover:brightness-105 transition">
                <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                    <path d="M4.5 6H0V4.5H4.5V0H6V4.5H10.5V6H6V10.5H4.5V6Z" fill="white"/>
                </svg>
                <span>New Invoice</span>
            </button>
        </div>
    </div>

    <!-- KPI Cards Grid (4 cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Platform Revenue -->
        <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border-l-4 border-l-[#003D9B] p-5">
            <div class="flex justify-between items-start">
                <div class="w-9 h-9 bg-[#DAE2FF] rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M8 16H12V14H8V16ZM8 12H12V10H8V12ZM6 18C5.45 18 4.97917 17.8042 4.5875 17.4125C4.19583 17.0208 4 16.55 4 16V4C4 3.45 4.19583 2.97917 4.5875 2.5875C4.97917 2.19583 5.45 2 6 2H12L16 6V16C16 16.55 15.8042 17.0208 15.4125 17.4125C15.0208 17.8042 14.55 18 14 18H6ZM11 7V3H6V16H14V7H11Z" fill="#003D9B"/>
                    </svg>
                </div>
                <span class="px-2 py-1 bg-[#4EDEA3]/20 text-[#004E33] text-xs font-bold rounded-full">+12%</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-bold uppercase text-[#434654] tracking-wider">Total Platform<br>Revenue</div>
                <div class="text-2xl font-bold text-[#0B1C30] mt-1">$1.4M</div>
            </div>
        </div>

        <!-- Active Subscriptions -->
        <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border-l-4 border-l-[#CBDBF5] p-5">
            <div class="flex justify-between items-start">
                <div class="w-9 h-9 bg-[#DCE9FF] rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M6 16C5.45 16 4.97917 15.8042 4.5875 15.4125C4.19583 15.0208 4 14.55 4 14V8C4 7.45 4.19583 6.97917 4.5875 6.5875C4.97917 6.19583 5.45 6 6 6H16C16.55 6 17.0208 6.19583 17.4125 6.5875C17.8042 6.97917 18 7.45 18 8V14C18 14.55 17.8042 15.0208 17.4125 15.4125C17.0208 15.8042 16.55 16 16 16H6ZM6 14H16V8H6V14ZM10 13L14 11L10 9V13ZM6 5V3H16V5H6ZM8 2V0H14V2H8ZM6 14V8V14Z" fill="#515F74"/>
                    </svg>
                </div>
                <span class="px-2 py-1 bg-[#E5EEFF] text-[#434654] text-xs font-bold rounded-full">Stable</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-bold uppercase text-[#434654] tracking-wider">Active<br>Subscriptions</div>
                <div class="text-2xl font-bold text-[#0B1C30] mt-1">8,420</div>
            </div>
        </div>

        <!-- Pending Payouts -->
        <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border-l-4 border-l-[#BA1A1A]/40 p-5">
            <div class="flex justify-between items-start">
                <div class="w-9 h-9 bg-[#FFDAD6] rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M14 17C13.0833 17 12.2917 16.675 11.625 16.025C10.9583 15.375 10.625 14.5833 10.625 13.65C10.625 12.7167 10.9583 11.925 11.625 11.275C12.2917 10.625 13.0833 10.3 14 10.3C14.9167 10.3 15.7083 10.625 16.375 11.275C17.0417 11.925 17.375 12.7167 17.375 13.65C17.375 14.5833 17.0417 15.375 16.375 16.025C15.7083 16.675 14.9167 17 14 17ZM14.9 15.15L16.25 13.8L15.4 12.95L14 14.35L13.1 13.45L12.25 14.3L13.65 15.7L12.25 17.1L13.1 17.95L14.5 16.55L15.9 17.95L16.75 17.1L15.35 15.7L14.9 15.15ZM6 16C5.45 16 4.97917 15.8042 4.5875 15.4125C4.19583 15.0208 4 14.55 4 14V6C4 5.45 4.19583 4.97917 4.5875 4.5875C4.97917 4.19583 5.45 4 6 4H8.175C8.35833 3.41667 8.71667 2.9375 9.25 2.5625C9.78333 2.1875 10.3667 2 11 2C11.6333 2 12.2167 2.1875 12.75 2.5625C13.2833 2.9375 13.6417 3.41667 13.825 4H16C16.55 4 17.0208 4.19583 17.4125 4.5875C17.8042 4.97917 18 5.45 18 6V10.25C17.6833 10.0167 17.3458 9.81667 16.9875 9.65C16.6292 9.48333 16.25 9.35 15.85 9.25V6H14V8H10V6H8V8H6V14H9.3C9.41667 14.3667 9.55 14.7167 9.7 15.05C9.85 15.3833 10.0333 15.7 10.25 16H6ZM11 6C11.2833 6 11.5208 5.90417 11.7125 5.7125C11.9042 5.52083 12 5.28333 12 5C12 4.71667 11.9042 4.47917 11.7125 4.2875C11.5208 4.09583 11.2833 4 11 4C10.7167 4 10.4792 4.09583 10.2875 4.2875C10.0958 4.47917 10 4.71667 10 5C10 5.28333 10.0958 5.52083 10.2875 5.7125C10.4792 5.90417 10.7167 6 11 6Z" fill="#BA1A1A"/>
                    </svg>
                </div>
                <span class="px-2 py-1 bg-[#FFDAD6]/50 text-[#93000A] text-xs font-bold rounded-full">18 Merchants</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-bold uppercase text-[#434654] tracking-wider">Pending Payouts</div>
                <div class="text-2xl font-bold text-[#0B1C30] mt-1">$142,500</div>
            </div>
        </div>

        <!-- Avg Revenue Per Tenant -->
        <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border-l-4 border-l-[#004E33]/40 p-5">
            <div class="flex justify-between items-start">
                <div class="w-9 h-9 bg-[#6FFBBE] rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M6.4 12L5 10.6L9.4 6.15L12.4 9.15L16 5.5H14V3.5H19V8.5H17V6.5L12.4 11.1L9.4 8.1L6.4 12Z" fill="#004E33"/>
                    </svg>
                </div>
                <span class="px-2 py-1 bg-[#4EDEA3]/20 text-[#004E33] text-xs font-bold rounded-full">+5%</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-bold uppercase text-[#434654] tracking-wider">Avg Revenue Per<br>Tenant</div>
                <div class="text-2xl font-bold text-[#0B1C30] mt-1">$166</div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart & Plan Distribution (two columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Revenue Chart (left 2 columns) -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-base font-medium text-[#0B1C30] font-poppins">Revenue Growth</h2>
                    <p class="text-sm text-[#434654]">Last 12 months performance trajectory</p>
                </div>
                <div class="flex bg-[#EFF4FF] rounded-lg p-1">
                    <button class="px-4 py-1.5 bg-white shadow-sm rounded-md text-xs font-bold text-[#0B1C30]">Monthly</button>
                    <button class="px-4 py-1.5 text-xs font-bold text-[#434654]">Quarterly</button>
                </div>
            </div>
            <!-- Bar chart (simplified representation) -->
            <div class="mt-8 h-48 flex items-end justify-between gap-1">
                @foreach([40, 60, 75, 55, 80, 95, 110, 130, 145, 160, 175, 190] as $height)
                    <div class="w-full bg-[#003D9B]/10 rounded-t" style="height: {{ $height }}px"></div>
                @endforeach
            </div>
            <!-- Month labels -->
            <div class="flex justify-between mt-2 text-[10px] font-bold uppercase text-[#434654] tracking-wider">
                <span>Jan</span>
                <span>Mar</span>
                <span>May</span>
                <span>Jul</span>
                <span>Sep</span>
                <span>Dec</span>
            </div>
        </div>

        <!-- Plan Distribution (right 1 column) -->
        <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6">
            <h3 class="text-base font-medium text-[#0B1C30] mb-4 font-poppins">Plan Distribution</h3>
            <!-- Donut chart (simplified with conic-gradient) -->
            <div class="relative w-32 h-32 mx-auto">
                <div class="w-full h-full rounded-full bg-conic from-[#515F74] via-[#004E33] via-[#003D9B] to-[#515F74]" style="background: conic-gradient(#515F74 0deg 162deg, #004E33 162deg 288deg, #003D9B 288deg 360deg);"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-xl font-bold text-[#0B1C30]">8.4k</span>
                    <span class="text-[10px] font-bold uppercase text-[#434654]">Tenants</span>
                </div>
            </div>
            <!-- Legend -->
            <div class="mt-6 space-y-3">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#515F74]"></span>
                        <span class="text-sm font-medium text-[#0B1C30]">Starter</span>
                    </div>
                    <span class="text-sm font-bold text-[#0B1C30]">45%</span>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#004E33]"></span>
                        <span class="text-sm font-medium text-[#0B1C30]">Growth</span>
                    </div>
                    <span class="text-sm font-bold text-[#0B1C30]">35%</span>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-[#003D9B]"></span>
                        <span class="text-sm font-medium text-[#0B1C30]">Enterprise</span>
                    </div>
                    <span class="text-sm font-bold text-[#0B1C30]">20%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Merchant Payout Queue Table -->
    <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#C3C6D6]/10 flex justify-between items-center">
            <div>
                <h3 class="text-base font-medium text-[#0B1C30] font-poppins">Merchant Payout Queue</h3>
                <p class="text-sm text-[#434654]">High-value pending settlements awaiting approval.</p>
            </div>
            <a href="#" class="text-sm font-bold text-[#003D9B] hover:underline">View All Requests</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#EFF4FF]/50 text-[#434654] text-xs font-bold uppercase tracking-wider">
                    <tr>
                        <th class="text-left py-3 px-6">Merchant/Store Name</th>
                        <th class="text-left py-3 px-6">Category</th>
                        <th class="text-left py-3 px-6">Request Date</th>
                        <th class="text-left py-3 px-6">Amount</th>
                        <th class="text-left py-3 px-6">Status</th>
                        <th class="text-right py-3 px-6">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#C3C6D6]/10">
                    <!-- Nova Labs -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[#003D9B]/10 rounded-lg flex items-center justify-center text-xs font-bold text-[#003D9B]">NL</div>
                                <span class="font-semibold text-[#0B1C30]">Nova Labs</span>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">E-commerce</td>
                        <td class="py-4 px-6 text-[#434654]">Oct 24, 2023</td>
                        <td class="py-4 px-6 font-bold text-[#0B1C30]">$12,450.00</td>
                        <td class="py-4 px-6">
                            <span class="inline-block px-3 py-1 bg-[#DCE9FF] text-[#3A485B] text-[10px] font-bold uppercase rounded-full">Pending Approval</span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button class="px-4 py-1.5 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white text-xs font-bold rounded-lg shadow-sm">Approve</button>
                        </td>
                    </tr>
                    <!-- Quantum Media -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[#CBDBF5] rounded-lg flex items-center justify-center text-xs font-bold text-[#57657A]">QM</div>
                                <span class="font-semibold text-[#0B1C30]">Quantum Media</span>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">Digital Goods</td>
                        <td class="py-4 px-6 text-[#434654]">Oct 23, 2023</td>
                        <td class="py-4 px-6 font-bold text-[#0B1C30]">$8,200.00</td>
                        <td class="py-4 px-6">
                            <span class="inline-block px-3 py-1 bg-[#DCE9FF] text-[#3A485B] text-[10px] font-bold uppercase rounded-full">Pending Approval</span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button class="px-4 py-1.5 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white text-xs font-bold rounded-lg shadow-sm">Approve</button>
                        </td>
                    </tr>
                    <!-- Apex Solutions -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[#004E33]/10 rounded-lg flex items-center justify-center text-xs font-bold text-[#004E33]">AS</div>
                                <span class="font-semibold text-[#0B1C30]">Apex Solutions</span>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">SaaS Platform</td>
                        <td class="py-4 px-6 text-[#434654]">Oct 22, 2023</td>
                        <td class="py-4 px-6 font-bold text-[#0B1C30]">$42,100.00</td>
                        <td class="py-4 px-6">
                            <span class="inline-block px-3 py-1 bg-[#DAE2FF] text-[#003D9B] text-[10px] font-bold uppercase rounded-full">Processing</span>
                        </td>
                        <td class="py-4 px-6 text-right">
                            <button class="px-4 py-1.5 border border-[#C3C6D6]/30 text-[#434654] text-xs font-bold rounded-lg">Details</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bottom Grid: Recent Invoices & Billing API Cluster -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        <!-- Recent Invoices -->
        <div class="lg:col-span-3 bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-base font-medium text-[#0B1C30] font-poppins">Recent Platform Invoices</h3>
                <svg width="18" height="12" viewBox="0 0 18 12" fill="none">
                    <path d="M7 12V10H11V12H7ZM3 7V5H15V7H3ZM0 2V0H18V2H0Z" fill="#737685"/>
                </svg>
            </div>
            <div class="space-y-3">
                <!-- Invoice 1 -->
                <div class="bg-[#EFF4FF] rounded-xl p-4 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                                <path d="M4 16H12V14H4V16ZM4 12H12V10H4V12ZM2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H10L16 6V18C16 18.55 15.8042 19.0208 15.4125 19.4125C15.0208 19.8042 14.55 20 14 20H2ZM9 7V2H2V18H14V7H9Z" fill="#003D9B"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-[#0B1C30]">INV-2023-9402</div>
                            <div class="text-xs text-[#434654]">Tenant: BlueSky Logistics</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-[#0B1C30]">$4,250.00</div>
                        <div class="text-[10px] font-bold uppercase text-[#004E33]">Paid</div>
                    </div>
                </div>
                <!-- Invoice 2 -->
                <div class="bg-[#EFF4FF] rounded-xl p-4 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                                <path d="M4 16H12V14H4V16ZM4 12H12V10H4V12ZM2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H10L16 6V18C16 18.55 15.8042 19.0208 15.4125 19.4125C15.0208 19.8042 14.55 20 14 20H2ZM9 7V2H2V18H14V7H9Z" fill="#003D9B"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-[#0B1C30]">INV-2023-9401</div>
                            <div class="text-xs text-[#434654]">Tenant: SwiftDelivery Co.</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-[#0B1C30]">$1,890.00</div>
                        <div class="text-[10px] font-bold uppercase text-[#004E33]">Paid</div>
                    </div>
                </div>
                <!-- Invoice 3 -->
                <div class="bg-[#EFF4FF] rounded-xl p-4 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M10 15C10.2833 15 10.5208 14.9042 10.7125 14.7125C10.9042 14.5208 11 14.2833 11 14C11 13.7167 10.9042 13.4792 10.7125 13.2875C10.5208 13.0958 10.2833 13 10 13C9.71667 13 9.47917 13.0958 9.2875 13.2875C9.09583 13.4792 9 13.7167 9 14C9 14.2833 9.09583 14.5208 9.2875 14.7125C9.47917 14.9042 9.71667 15 10 15ZM9 11H11V5H9V11ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#BA1A1A" fill-opacity="0.8"/>
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-[#0B1C30]">INV-2023-9398</div>
                            <div class="text-xs text-[#434654]">Tenant: Global Retail Partners</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-[#0B1C30]">$12,400.00</div>
                        <div class="text-[10px] font-bold uppercase text-[#BA1A1A]">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing API Cluster -->
        <div class="lg:col-span-1 bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6 relative overflow-hidden">
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-[#003D9B] to-[#0052CC]"></div>
            <h3 class="text-base font-medium text-[#0B1C30] mb-1 font-poppins">Billing API Cluster</h3>
            <p class="text-xs font-bold uppercase text-[#434654] tracking-wider mb-4">System Health</p>
            <div class="space-y-4">
                <!-- us-east -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-bold text-[#0B1C30]">us-east-core-01</span>
                        <span class="text-[10px] font-bold text-[#004E33]">Operational</span>
                    </div>
                    <div class="h-1 bg-[#EFF4FF] rounded-full">
                        <div class="w-3/4 h-1 bg-[#004E33] rounded-full"></div>
                    </div>
                </div>
                <!-- eu-west -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-bold text-[#0B1C30]">eu-west-settle-04</span>
                        <span class="text-[10px] font-bold text-[#004E33]">Operational</span>
                    </div>
                    <div class="h-1 bg-[#EFF4FF] rounded-full">
                        <div class="w-2/3 h-1 bg-[#004E33] rounded-full"></div>
                    </div>
                </div>
                <!-- ap-south -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-bold text-[#0B1C30]">ap-south-ledger-02</span>
                        <span class="text-[10px] font-bold text-[#434654]">Maintenance</span>
                    </div>
                    <div class="h-1 bg-[#EFF4FF] rounded-full">
                        <div class="w-1/4 h-1 bg-[#737685] rounded-full"></div>
                    </div>
                </div>
            </div>
            <!-- Success rate card -->
            <div class="mt-6 bg-[#DAE2FF] rounded-lg p-4">
                <div class="text-[10px] font-bold uppercase text-[#003D9B]">Total Webhook Success Rate</div>
                <div class="text-2xl font-bold text-[#001848]">99.98%</div>
            </div>
        </div>
    </div>
</div>
@endsection
