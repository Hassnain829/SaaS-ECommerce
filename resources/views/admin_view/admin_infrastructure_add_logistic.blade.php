@extends('layouts.admin.admin-Sidebar')

@section('title', 'Add Logistics Integration')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
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
        <input type="text" placeholder="Search systems, logs, or tenants..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
    </div>

    <!-- Right side: notifications, region, profile -->
    <div class="flex items-center gap-3 shrink-0">
        <!-- Notification bell with red dot -->
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
        </button>

        <!-- Region indicator -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-[#334155]">US-EAST-1</span>
            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
        </div>

        <!-- Vertical divider -->
        <div class="w-px h-6 bg-[#E2E8F0] hidden sm:block"></div>

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
<div class="font-inter max-w-9xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm">
        <a href="{{ route('admin-infrastructure') }}" class="text-[#434654] hover:text-[#0F172A]">Infrastructure</a>
        <svg width="5" height="7" viewBox="0 0 5 7" fill="none">
            <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#434654"/>
        </svg>
        <a href="{{ route('admin-infrastructure') }}" class="text-[#434654] hover:text-[#0F172A]">Courier Services</a>
        <svg width="5" height="7" viewBox="0 0 5 7" fill="none">
            <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#434654"/>
        </svg>
        <span class="text-[#0B1C30] font-medium">All Logistic</span>
    </nav>

    <!-- Header with title and Add New Integration button -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Connect New Integration</h1>
        <button class="inline-flex items-center gap-2 px-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-[#0047B3] transition">
            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                <path d="M4.5 6H0V4.5H4.5V0H6V4.5H10.5V6H6V10.5H4.5V6Z" fill="white"/>
            </svg>
            <span>Add New Integration</span>
        </button>
    </div>

    <!-- Search and filter pills -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-4">
        <div class="flex flex-col md:flex-row gap-4">
            <!-- Search input -->
            <div class="relative flex-1">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                    <svg width="18" height="24" viewBox="0 0 18 24" fill="none">
                        <path d="M16.6 18L10.3 11.7C9.8 12.1 9.225 12.4167 8.575 12.65C7.925 12.8833 7.23333 13 6.5 13C4.68333 13 3.14583 12.3708 1.8875 11.1125C0.629167 9.85417 0 8.31667 0 6.5C0 4.68333 0.629167 3.14583 1.8875 1.8875C3.14583 0.629167 4.68333 0 6.5 0C8.31667 0 9.85417 0.629167 11.1125 1.8875C12.3708 3.14583 13 4.68333 13 6.5C13 7.23333 12.8833 7.925 12.65 8.575C12.4167 9.225 12.1 9.8 11.7 10.3L18 16.6L16.6 18Z" fill="#94A3B8"/>
                    </svg>
                </span>
                <input type="text" placeholder="Search for integration partners..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>

            <!-- Filter pills -->
            <div class="flex flex-wrap gap-2">
                <button class="px-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">All</button>
                <button class="px-4 py-2 bg-white border border-[#E2E8F0] text-[#475569] text-sm font-medium rounded-lg flex items-center gap-2 hover:bg-gray-50">
                    <svg width="17" height="12" viewBox="0 0 17 12" fill="none">
                        <path d="M3.75 12C3.125 12 2.59375 11.7812 2.15625 11.3438C1.71875 10.9062 1.5 10.375 1.5 9.75H0V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H12V3H14.25L16.5 6V9.75H15C15 10.375 14.7812 10.9062 14.3438 11.3438C13.9062 11.7812 13.375 12 12.75 12C12.125 12 11.5938 11.7812 11.1562 11.3438C10.7188 10.9062 10.5 10.375 10.5 9.75H6C6 10.375 5.78125 10.9062 5.34375 11.3438C4.90625 11.7812 4.375 12 3.75 12ZM3.75 10.5C3.9625 10.5 4.14063 10.4281 4.28438 10.2844C4.42813 10.1406 4.5 9.9625 4.5 9.75C4.5 9.5375 4.42813 9.35937 4.28438 9.21562C4.14063 9.07187 3.9625 9 3.75 9C3.5375 9 3.35938 9.07187 3.21563 9.21562C3.07188 9.35937 3 9.5375 3 9.75C3 9.9625 3.07188 10.1406 3.21563 10.2844C3.35938 10.4281 3.5375 10.5 3.75 10.5ZM1.5 8.25H2.1C2.3125 8.025 2.55625 7.84375 2.83125 7.70625C3.10625 7.56875 3.4125 7.5 3.75 7.5C4.0875 7.5 4.39375 7.56875 4.66875 7.70625C4.94375 7.84375 5.1875 8.025 5.4 8.25H10.5V1.5H1.5V1.5V1.5V8.25V8.25ZM12.75 10.5C12.9625 10.5 13.1406 10.4281 13.2844 10.2844C13.4281 10.1406 13.5 9.9625 13.5 9.75C13.5 9.5375 13.4281 9.35937 13.2844 9.21562C13.1406 9.07187 12.9625 9 12.75 9C12.5375 9 12.3594 9.07187 12.2156 9.21562C12.0719 9.35937 12 9.5375 12 9.75C12 9.9625 12.0719 10.1406 12.2156 10.2844C12.3594 10.4281 12.5375 10.5 12.75 10.5ZM12 6.75H15.1875L13.5 4.5H12V6.75Z" fill="#475569"/>
                    </svg>
                    <span>Logistics</span>
                </button>
                <button class="px-4 py-2 bg-white border border-[#E2E8F0] text-[#475569] text-sm font-medium rounded-lg flex items-center gap-2 hover:bg-gray-50">
                    <svg width="17" height="12" viewBox="0 0 17 12" fill="none">
                        <path d="M9.75 6.75C9.125 6.75 8.59375 6.53125 8.15625 6.09375C7.71875 5.65625 7.5 5.125 7.5 4.5C7.5 3.875 7.71875 3.34375 8.15625 2.90625C8.59375 2.46875 9.125 2.25 9.75 2.25C10.375 2.25 10.9062 2.46875 11.3438 2.90625C11.7812 3.34375 12 3.875 12 4.5C12 5.125 11.7812 5.65625 11.3438 6.09375C10.9062 6.53125 10.375 6.75 9.75 6.75ZM4.5 9C4.0875 9 3.73437 8.85312 3.44062 8.55937C3.14687 8.26562 3 7.9125 3 7.5V1.5C3 1.0875 3.14687 0.734375 3.44062 0.440625C3.73437 0.146875 4.0875 0 4.5 0H15C15.4125 0 15.7656 0.146875 16.0594 0.440625C16.3531 0.734375 16.5 1.0875 16.5 1.5V7.5C16.5 7.9125 16.3531 8.26562 16.0594 8.55937C15.7656 8.85312 15.4125 9 15 9H4.5ZM6 7.5H13.5C13.5 7.0875 13.6469 6.73438 13.9406 6.44063C14.2344 6.14688 14.5875 6 15 6V3C14.5875 3 14.2344 2.85313 13.9406 2.55938C13.6469 2.26563 13.5 1.9125 13.5 1.5H6C6 1.9125 5.85312 2.26563 5.55937 2.55938C5.26562 2.85313 4.9125 3 4.5 3V6C4.9125 6 5.26562 6.14688 5.55937 6.44063C5.85312 6.73438 6 7.0875 6 7.5ZM14.25 12H1.5C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5V2.25H1.5V10.5H14.25V12Z" fill="#475569"/>
                    </svg>
                    <span>Payments</span>
                </button>
                <button class="px-4 py-2 bg-white border border-[#E2E8F0] text-[#475569] text-sm font-medium rounded-lg flex items-center gap-2 hover:bg-gray-50">
                    <svg width="18" height="9" viewBox="0 0 18 9" fill="none">
                        <path d="M0 9V7.81875C0 7.28125 0.275 6.84375 0.825 6.50625C1.375 6.16875 2.1 6 3 6C3.1625 6 3.31875 6.00313 3.46875 6.00938C3.61875 6.01563 3.7625 6.03125 3.9 6.05625C3.725 6.31875 3.59375 6.59375 3.50625 6.88125C3.41875 7.16875 3.375 7.46875 3.375 7.78125V9H0ZM4.5 9V7.78125C4.5 7.38125 4.60938 7.01562 4.82812 6.68437C5.04688 6.35312 5.35625 6.0625 5.75625 5.8125C6.15625 5.5625 6.63438 5.375 7.19063 5.25C7.74688 5.125 8.35 5.0625 9 5.0625C9.6625 5.0625 10.2719 5.125 10.8281 5.25C11.3844 5.375 11.8625 5.5625 12.2625 5.8125C12.6625 6.0625 12.9687 6.35312 13.1812 6.68437C13.3937 7.01562 13.5 7.38125 13.5 7.78125V9H4.5ZM14.625 9V7.78125C14.625 7.45625 14.5844 7.15 14.5031 6.8625C14.4219 6.575 14.3 6.30625 14.1375 6.05625C14.275 6.03125 14.4156 6.01563 14.5594 6.00938C14.7031 6.00313 14.85 6 15 6C15.9 6 16.625 6.16562 17.175 6.49687C17.725 6.82812 18 7.26875 18 7.81875V9H14.625ZM6.09375 7.5H11.925C11.8 7.25 11.4531 7.03125 10.8844 6.84375C10.3156 6.65625 9.6875 6.5625 9 6.5625C8.3125 6.5625 7.68437 6.65625 7.11562 6.84375C6.54687 7.03125 6.20625 7.25 6.09375 7.5ZM3 5.25C2.5875 5.25 2.23437 5.10312 1.94062 4.80937C1.64687 4.51562 1.5 4.1625 1.5 3.75C1.5 3.325 1.64687 2.96875 1.94062 2.68125C2.23437 2.39375 2.5875 2.25 3 2.25C3.425 2.25 3.78125 2.39375 4.06875 2.68125C4.35625 2.96875 4.5 3.325 4.5 3.75C4.5 4.1625 4.35625 4.51562 4.06875 4.80937C3.78125 5.10312 3.425 5.25 3 5.25ZM15 5.25C14.5875 5.25 14.2344 5.10312 13.9406 4.80937C13.6469 4.51562 13.5 4.1625 13.5 3.75C13.5 3.325 13.6469 2.96875 13.9406 2.68125C14.2344 2.39375 14.5875 2.25 15 2.25C15.425 2.25 15.7813 2.39375 16.0688 2.68125C16.3563 2.96875 16.5 3.325 16.5 3.75C16.5 4.1625 16.3563 4.51562 16.0688 4.80937C15.7813 5.10312 15.425 5.25 15 5.25ZM9 4.5C8.375 4.5 7.84375 4.28125 7.40625 3.84375C6.96875 3.40625 6.75 2.875 6.75 2.25C6.75 1.6125 6.96875 1.07813 7.40625 0.646875C7.84375 0.215625 8.375 0 9 0C9.6375 0 10.1719 0.215625 10.6031 0.646875C11.0344 1.07813 11.25 1.6125 11.25 2.25C11.25 2.875 11.0344 3.40625 10.6031 3.84375C10.1719 4.28125 9.6375 4.5 9 4.5ZM9 3C9.2125 3 9.39063 2.92812 9.53438 2.78437C9.67813 2.64062 9.75 2.4625 9.75 2.25C9.75 2.0375 9.67813 1.85938 9.53438 1.71563C9.39063 1.57188 9.2125 1.5 9 1.5C8.7875 1.5 8.60937 1.57188 8.46562 1.71563C8.32187 1.85938 8.25 2.0375 8.25 2.25C8.25 2.4625 8.32187 2.64062 8.46562 2.78437C8.60937 2.92812 8.7875 3 9 3Z" fill="#475569"/>
                    </svg>
                    <span>CRM</span>
                </button>
                <button class="px-4 py-2 bg-white border border-[#E2E8F0] text-[#475569] text-sm font-medium rounded-lg flex items-center gap-2 hover:bg-gray-50">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M3 10.5H4.5V6.75H3V10.5ZM9 10.5H10.5V3H9V10.5ZM6 10.5H7.5V8.25H6V10.5ZM6 6.75H7.5V5.25H6V6.75ZM1.5 13.5C1.0875 13.5 0.734375 13.3531 0.440625 13.0594C0.146875 12.7656 0 12.4125 0 12V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H12C12.4125 0 12.7656 0.146875 13.0594 0.440625C13.3531 0.734375 13.5 1.0875 13.5 1.5V12C13.5 12.4125 13.3531 12.7656 13.0594 13.0594C12.7656 13.3531 12.4125 13.5 12 13.5H1.5ZM1.5 12H12V1.5H1.5V12Z" fill="#475569"/>
                    </svg>
                    <span>Analytics</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Couriers & Logistics Section -->
    <div>
        <div class="flex items-center gap-4 mb-4">
            <h2 class="text-sm font-medium uppercase tracking-wider text-[#94A3B8] font-poppins">Couriers & Logistics</h2>
            <div class="flex-1 h-px bg-[#E2E8F0]"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- UPS Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <!-- UPS Logo placeholder (simple box) -->
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">UPS</div>
                    </div>
                    <span class="px-2 py-1 bg-[#F0FDF4] text-[#16A34A] text-[10px] font-bold uppercase rounded-full">Certified</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">UPS</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">Enterprise-grade shipping, tracking, and logistics fulfillment solutions for scale.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- FedEx Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">FedEx</div>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">FedEx</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">International freight and courier services with integrated real-time tracking.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- DHL Express Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">DHL</div>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">DHL Express</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">Specialized international logistics and express mail for global commerce.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- Custom Logistic Partner (dashed) -->
            <div class="border-2 border-dashed border-[#E2E8F0] rounded-xl p-5 flex flex-col items-center justify-center text-center">
                <div class="w-12 h-12 bg-[#F8FAFC] rounded-full flex items-center justify-center">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M8 14H10V10H14V8H10V4H8V8H4V10H8V14ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#94A3B8"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#475569] mt-2">Custom Logistic Partner</p>
            </div>
        </div>
    </div>

    <!-- Payment Gateways Section -->
    <div>
        <div class="flex items-center gap-4 mb-4">
            <h2 class="text-sm font-medium uppercase tracking-wider text-[#94A3B8] font-poppins">Payment Gateways</h2>
            <div class="flex-1 h-px bg-[#E2E8F0]"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Stripe Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">Stripe</div>
                    </div>
                    <span class="px-2 py-1 bg-[#EFF6FF] text-[#0052CC] text-[10px] font-bold uppercase rounded-full">Popular</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">Stripe</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">Modern payment infrastructure for internet businesses.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- PayPal Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">PayPal</div>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">PayPal</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">Secure global payment processing for merchants.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- Adyen Card -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-5">
                <div class="flex justify-between items-start">
                    <div class="w-14 h-14 bg-[#F8FAFC] border border-[#F1F5F9] rounded-xl flex items-center justify-center">
                        <div class="w-10 h-10 bg-[#E5EEFF] rounded flex items-center justify-center text-xs font-bold text-[#003D9B]">Adyen</div>
                    </div>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">Adyen</h3>
                <p class="text-sm text-[#64748B] mt-1 leading-relaxed">Unified commerce platform combining payments and data.</p>
                <button class="w-full mt-4 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg">Select</button>
            </div>

            <!-- Available via request (disabled style) -->
            <div class="bg-white/50 border border-[#E2E8F0] rounded-xl p-5 flex flex-col items-center justify-center text-center opacity-60">
                <div class="w-12 h-12 bg-white border border-[#F1F5F9] rounded-full flex items-center justify-center">
                    <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                        <path d="M4 18H12V15C12 13.9 11.6083 12.9583 10.825 12.175C10.0417 11.3917 9.1 11 8 11C6.9 11 5.95833 11.3917 5.175 12.175C4.39167 12.9583 4 13.9 4 15V18ZM8 9C9.1 9 10.0417 8.60833 10.825 7.825C11.6083 7.04167 12 6.1 12 5V2H4V5C4 6.1 4.39167 7.04167 5.175 7.825C5.95833 8.60833 6.9 9 8 9ZM0 20V18H2V15C2 13.9833 2.2375 13.0292 2.7125 12.1375C3.1875 11.2458 3.85 10.5333 4.7 10C3.85 9.46667 3.1875 8.75417 2.7125 7.8625C2.2375 6.97083 2 6.01667 2 5V2H0V0H16V2H14V5C14 6.01667 13.7625 6.97083 13.2875 7.8625C12.8125 8.75417 12.15 9.46667 11.3 10C12.15 10.5333 12.8125 11.2458 13.2875 12.1375C13.7625 13.0292 14 13.9833 14 15V18H16V20H0Z" fill="#CBD5E1"/>
                    </svg>
                </div>
                <p class="text-xs text-[#94A3B8] mt-2">Available via request</p>
            </div>
        </div>
    </div>

    <!-- Custom Implementation Needed -->
    <div class="bg-white/50 border-2 border-dashed border-[#E2E8F0] rounded-2xl p-8 text-center">
        <div class="w-12 h-12 bg-[#0052CC]/5 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg width="20" height="13" viewBox="0 0 20 13" fill="none">
                <path d="M15 13V10H12V8H15V5H17V8H20V10H17V13H15ZM9 10H5C3.61667 10 2.4375 9.5125 1.4625 8.5375C0.4875 7.5625 0 6.38333 0 5C0 3.61667 0.4875 2.4375 1.4625 1.4625C2.4375 0.4875 3.61667 0 5 0H9V2H5C4.16667 2 3.45833 2.29167 2.875 2.875C2.29167 3.45833 2 4.16667 2 5C2 5.83333 2.29167 6.54167 2.875 7.125C3.45833 7.70833 4.16667 8 5 8H9V10ZM6 6V4H14V6H6ZM20 5H18C18 4.16667 17.7083 3.45833 17.125 2.875C16.5417 2.29167 15.8333 2 15 2H11V0H15C16.3833 0 17.5625 0.4875 18.5375 1.4625C19.5125 2.4375 20 3.61667 20 5Z" fill="#0052CC"/>
            </svg>
        </div>
        <h3 class="text-lg font-medium text-[#0F172A] mb-1 font-poppins">Custom Implementation Needed?</h3>
        <p class="text-sm text-[#64748B] max-w-lg mx-auto">Can't find your specific partner? Manually configure a webhook or API integration for any third-party service.</p>
        <div class="flex flex-wrap justify-center gap-4 mt-6">
            <button class="px-6 py-2 bg-[#0052CC] text-white text-sm font-semibold rounded-lg shadow-sm">+ Add New Integration</button>
            <button class="px-6 py-2 bg-white border border-[#E2E8F0] text-[#334155] text-sm font-semibold rounded-lg">Developer Documentation</button>
        </div>
    </div>

    <!-- Footer actions (Cancel / Continue) -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-4 border-t border-[#E2E8F0]">
        <button class="flex items-center gap-2 px-6 py-2 text-[#475569] font-semibold hover:bg-gray-50 rounded-lg">
            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                <path d="M1.05 10.5L0 9.45L4.2 5.25L0 1.05L1.05 0L5.25 4.2L9.45 0L10.5 1.05L6.3 5.25L10.5 9.45L9.45 10.5L5.25 6.3L1.05 10.5Z" fill="#475569"/>
            </svg>
            <span>Cancel</span>
        </button>
        <button class="px-10 py-2 bg-[#F1F5F9] text-[#94A3B8] font-bold rounded-lg">Continue to Configuration</button>
    </div>
</div>
@endsection
