@extends('layouts.user.user-sidebar')

@section('title', 'Employees - BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" placeholder="Search orders, products, or reports..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
    </div>

    <div class="flex items-center gap-3 shrink-0">
        <button class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
            </svg>
            <span>Quick Action</span>
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
<div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                <div>
                    <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Dashboard Overview</h1>
                    <p class="text-sm text-[#64748B]">Welcome back, here's what's happening with your store today.</p>
                </div>
                <div class="bg-white border border-[#E2E8F0] rounded-lg p-1 flex gap-1" role="tablist" aria-label="Time period">
                    <button type="button" class="period-btn px-4 py-1.5 text-sm font-inter font-medium text-[#64748B] rounded-md hover:bg-gray-100" data-period="daily">Daily</button>
                    <button type="button" class="period-btn px-4 py-1.5 text-sm font-inter font-medium bg-[#0052CC] text-white rounded-md" data-period="weekly" aria-selected="true">Weekly</button>
                    <button type="button" class="period-btn px-4 py-1.5 text-sm font-inter font-medium text-[#64748B] rounded-md hover:bg-gray-100" data-period="monthly">Monthly</button>
                </div>
            </div>



            <!-- Metrics grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 bg-[#0052CC]/10 rounded-lg flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8V2H20V8H12ZM2 12V2H10V12H2ZM12 20V10H20V20H12ZM2 20V14H10V20H2Z" fill="#0052CC"/></svg>
                        </div>
                        <span class="bg-green-50 text-green-600 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.933333 8L0 7.06667L4.93333 2.1L7.6 4.76667L11.0667 1.33333H9.33333V0H13.3333V4H12V2.26667L7.6 6.66667L4.93333 4L0.933333 8Z" fill="#059669"/></svg>
                            12.5%
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="text-sm text-[#64748B]">Total Revenue</div>
                        <div class="text-2xl font-medium text-[#0F172A] font-poppins">$45,230.00</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg width="20" height="16" viewBox="0 0 20 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 12C2.16667 12 1.45833 11.7083 0.875 11.125C0.291667 10.5417 0 9.83333 0 9H2V10C2 10.2833 2.09583 10.5208 2.2875 10.7125C2.47917 10.9042 2.71667 11 3 11H6V12H3ZM14 12L10 8L11.4 6.55L14 9.15V1H16V9.15L18.6 6.55L20 8L16 12H14ZM2 7V6H6V7H2ZM2 4V3H6V4H2ZM8 12V10H18V12H8ZM8 9V7H14V9H8ZM8 6V4H14V6H8Z" fill="#2563EB"/></svg>
                        </div>
                        <span class="bg-red-50 text-red-600 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.33333 8V6.66667H11.0667L7.6 3.23333L4.93333 5.9L0 0.933333L0.933333 0L4.93333 4L7.6 1.33333L12 5.73333V4H13.3333V8H9.33333Z" fill="#E11D48"/></svg>
                            2.4%
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="text-sm text-[#64748B]">Active Orders</div>
                        <div class="text-2xl font-medium text-[#0F172A] font-poppins">124</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                            <svg width="20" height="16" viewBox="0 0 20 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 8V5H12V3H14V0H16V3H19V5H16V8H14ZM8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8ZM0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0ZM2 14H14V13.2C14 13.0167 13.9542 12.85 13.8625 12.7C13.7708 12.55 13.65 12.4333 13.5 12.35C12.6 11.9 11.6917 11.5625 10.775 11.3375C9.85833 11.1125 8.93333 11 8 11C7.06667 11 6.14167 11.1125 5.225 11.3375C4.30833 11.5625 3.4 11.9 2.5 12.35C2.35 12.4333 2.22917 12.55 2.1375 12.7C2.04583 12.85 2 13.0167 2 13.2V14ZM8 6C8.55 6 9.02083 5.80417 9.4125 5.4125C9.80417 5.02083 10 4.55 10 4C10 3.45 9.80417 2.97917 9.4125 2.5875C9.02083 2.19583 8.55 2 8 2C7.45 2 6.97917 2.19583 6.5875 2.5875C6.19583 2.97917 6 3.45 6 4C6 4.55 6.19583 5.02083 6.5875 5.4125C6.97917 5.80417 7.45 6 8 6Z" fill="#D97706"/></svg>
                        </div>
                        <span class="bg-green-50 text-green-600 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.933333 8L0 7.06667L4.93333 2.1L7.6 4.76667L11.0667 1.33333H9.33333V0H13.3333V4H12V2.26667L7.6 6.66667L4.93333 4L0.933333 8Z" fill="#059669"/></svg>
                            18.1%
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="text-sm text-[#64748B]">New Customers</div>
                        <div class="text-2xl font-medium text-[#0F172A] font-poppins">89</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
                    <div class="flex justify-between items-start">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 18V16H8V15H5V9H8V8H5V6H8V5H5V3H8V2H10V3H13V5H10V6H13V8H10V9H13V15H10V16H13V18H10V19H8V18H5ZM8 6V5H5V6H8ZM8 9V8H5V9H8ZM8 15V14H5V15H8Z" fill="#9333EA"/></svg>
                        </div>
                        <span class="bg-green-50 text-green-600 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                            <svg width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.933333 8L0 7.06667L4.93333 2.1L7.6 4.76667L11.0667 1.33333H9.33333V0H13.3333V4H12V2.26667L7.6 6.66667L4.93333 4L0.933333 8Z" fill="#059669"/></svg>
                            5.3%
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="text-sm text-[#64748B]">Avg. Order Value</div>
                        <div class="text-2xl font-medium text-[#0F172A] font-poppins">$364.75</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-xl border border-[#E2E8F0] p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-[#0F172A] font-poppins">Revenue Trends</h3>
                            <p class="text-xs text-[#64748B]">Weekly performance overview</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-[#0052CC]"></span>
                                <span class="text-xs text-[#64748B]">Current Period</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-[#E2E8F0]"></span>
                                <span class="text-xs text-[#64748B]">Previous Period</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-end gap-2 h-48 pt-4">                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 65%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 35%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 45%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 55%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 70%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 30%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 50%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 50%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 85%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 15%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 60%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 40%"></div>
                        </div>
                        <div class="flex-1 flex flex-col gap-1 items-center">
                            <div class="w-full bg-[#0052CC] rounded-t" style="height: 90%"></div>
                            <div class="w-full bg-[#E2E8F0] rounded-b flex-1" style="height: 10%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-[#E2E8F0] p-6 relative">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-[#0F172A] font-poppins">New Orders</h3>
                        <p class="text-xs text-[#64748B]">Total <span class="font-semibold text-gray-700">5</span> new orders today</p>
                    </div>
                    <div class="space-y-3 max-h-64 overflow-hidden relative">                        <div class="bg-[#F8FAFC] p-3 rounded-lg flex items-center gap-3">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.66667 11.6667L12.0417 6.29167L10.875 5.125L6.66667 9.33333L4.79167 7.45833L3.625 8.625L6.66667 11.6667ZM7.83333 16.6667C6.68056 16.6667 5.59722 16.4479 4.58333 16.0104C3.56944 15.5729 2.6875 14.9792 1.9375 14.2292C1.1875 13.4792 0.59375 12.5972 0.15625 11.5833C-0.28125 10.5694 -0.5 9.48611 -0.5 8.33333C-0.5 7.18056 -0.28125 6.09722 0.15625 5.08333C0.59375 4.06944 1.1875 3.1875 1.9375 2.4375C2.6875 1.6875 3.56944 1.09375 4.58333 0.65625C5.59722 0.21875 6.68056 0 7.83333 0C8.98611 0 10.0694 0.21875 11.0833 0.65625C12.0972 1.09375 12.9792 1.6875 13.7292 2.4375C14.4792 3.1875 15.0729 4.06944 15.5104 5.08333C15.9479 6.09722 16.1667 7.18056 16.1667 8.33333C16.1667 9.48611 15.9479 10.5694 15.5104 11.5833C15.0729 12.5972 14.4792 13.4792 13.7292 14.2292C12.9792 14.9792 12.0972 15.5729 11.0833 16.0104C10.0694 16.4479 8.98611 16.6667 7.83333 16.6667Z" fill="#10B981" transform="translate(0.5, 0)"/></svg>
                            <span class="text-sm text-[#475569]">Order #1024</span>
                        </div>
                        <div class="bg-[#F8FAFC] p-3 rounded-lg flex items-center gap-3">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.66667 11.6667L12.0417 6.29167L10.875 5.125L6.66667 9.33333L4.79167 7.45833L3.625 8.625L6.66667 11.6667ZM7.83333 16.6667C6.68056 16.6667 5.59722 16.4479 4.58333 16.0104C3.56944 15.5729 2.6875 14.9792 1.9375 14.2292C1.1875 13.4792 0.59375 12.5972 0.15625 11.5833C-0.28125 10.5694 -0.5 9.48611 -0.5 8.33333C-0.5 7.18056 -0.28125 6.09722 0.15625 5.08333C0.59375 4.06944 1.1875 3.1875 1.9375 2.4375C2.6875 1.6875 3.56944 1.09375 4.58333 0.65625C5.59722 0.21875 6.68056 0 7.83333 0C8.98611 0 10.0694 0.21875 11.0833 0.65625C12.0972 1.09375 12.9792 1.6875 13.7292 2.4375C14.4792 3.1875 15.0729 4.06944 15.5104 5.08333C15.9479 6.09722 16.1667 7.18056 16.1667 8.33333C16.1667 9.48611 15.9479 10.5694 15.5104 11.5833C15.0729 12.5972 14.4792 13.4792 13.7292 14.2292C12.9792 14.9792 12.0972 15.5729 11.0833 16.0104C10.0694 16.4479 8.98611 16.6667 7.83333 16.6667Z" fill="#10B981" transform="translate(0.5, 0)"/></svg>
                            <span class="text-sm text-[#475569]">Order #1025</span>
                        </div>
                        <div class="bg-[#F8FAFC] p-3 rounded-lg flex items-center gap-3">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.66667 11.6667L12.0417 6.29167L10.875 5.125L6.66667 9.33333L4.79167 7.45833L3.625 8.625L6.66667 11.6667ZM7.83333 16.6667C6.68056 16.6667 5.59722 16.4479 4.58333 16.0104C3.56944 15.5729 2.6875 14.9792 1.9375 14.2292C1.1875 13.4792 0.59375 12.5972 0.15625 11.5833C-0.28125 10.5694 -0.5 9.48611 -0.5 8.33333C-0.5 7.18056 -0.28125 6.09722 0.15625 5.08333C0.59375 4.06944 1.1875 3.1875 1.9375 2.4375C2.6875 1.6875 3.56944 1.09375 4.58333 0.65625C5.59722 0.21875 6.68056 0 7.83333 0C8.98611 0 10.0694 0.21875 11.0833 0.65625C12.0972 1.09375 12.9792 1.6875 13.7292 2.4375C14.4792 3.1875 15.0729 4.06944 15.5104 5.08333C15.9479 6.09722 16.1667 7.18056 16.1667 8.33333C16.1667 9.48611 15.9479 10.5694 15.5104 11.5833C15.0729 12.5972 14.4792 13.4792 13.7292 14.2292C12.9792 14.9792 12.0972 15.5729 11.0833 16.0104C10.0694 16.4479 8.98611 16.6667 7.83333 16.6667Z" fill="#10B981" transform="translate(0.5, 0)"/></svg>
                            <span class="text-sm text-[#475569]">Order #1026</span>
                        </div>
                        <div class="bg-[#F8FAFC] p-3 rounded-lg flex items-center gap-3">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.66667 11.6667L12.0417 6.29167L10.875 5.125L6.66667 9.33333L4.79167 7.45833L3.625 8.625L6.66667 11.6667ZM7.83333 16.6667C6.68056 16.6667 5.59722 16.4479 4.58333 16.0104C3.56944 15.5729 2.6875 14.9792 1.9375 14.2292C1.1875 13.4792 0.59375 12.5972 0.15625 11.5833C-0.28125 10.5694 -0.5 9.48611 -0.5 8.33333C-0.5 7.18056 -0.28125 6.09722 0.15625 5.08333C0.59375 4.06944 1.1875 3.1875 1.9375 2.4375C2.6875 1.6875 3.56944 1.09375 4.58333 0.65625C5.59722 0.21875 6.68056 0 7.83333 0C8.98611 0 10.0694 0.21875 11.0833 0.65625C12.0972 1.09375 12.9792 1.6875 13.7292 2.4375C14.4792 3.1875 15.0729 4.06944 15.5104 5.08333C15.9479 6.09722 16.1667 7.18056 16.1667 8.33333C16.1667 9.48611 15.9479 10.5694 15.5104 11.5833C15.0729 12.5972 14.4792 13.4792 13.7292 14.2292C12.9792 14.9792 12.0972 15.5729 11.0833 16.0104C10.0694 16.4479 8.98611 16.6667 7.83333 16.6667Z" fill="#10B981" transform="translate(0.5, 0)"/></svg>
                            <span class="text-sm text-[#475569]">Order #1027</span>
                        </div>
                        <div class="bg-[#F8FAFC] p-3 rounded-lg flex items-center gap-3">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.66667 11.6667L12.0417 6.29167L10.875 5.125L6.66667 9.33333L4.79167 7.45833L3.625 8.625L6.66667 11.6667ZM7.83333 16.6667C6.68056 16.6667 5.59722 16.4479 4.58333 16.0104C3.56944 15.5729 2.6875 14.9792 1.9375 14.2292C1.1875 13.4792 0.59375 12.5972 0.15625 11.5833C-0.28125 10.5694 -0.5 9.48611 -0.5 8.33333C-0.5 7.18056 -0.28125 6.09722 0.15625 5.08333C0.59375 4.06944 1.1875 3.1875 1.9375 2.4375C2.6875 1.6875 3.56944 1.09375 4.58333 0.65625C5.59722 0.21875 6.68056 0 7.83333 0C8.98611 0 10.0694 0.21875 11.0833 0.65625C12.0972 1.09375 12.9792 1.6875 13.7292 2.4375C14.4792 3.1875 15.0729 4.06944 15.5104 5.08333C15.9479 6.09722 16.1667 7.18056 16.1667 8.33333C16.1667 9.48611 15.9479 10.5694 15.5104 11.5833C15.0729 12.5972 14.4792 13.4792 13.7292 14.2292C12.9792 14.9792 12.0972 15.5729 11.0833 16.0104C10.0694 16.4479 8.98611 16.6667 7.83333 16.6667Z" fill="#10B981" transform="translate(0.5, 0)"/></svg>
                            <span class="text-sm text-[#475569]">Order #1028</span>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-white to-transparent pointer-events-none"></div>
                    </div>
                    <div class="text-center mt-2">
                        <a href="/orders" class="text-xs font-semibold text-[#94A3B8] hover:text-[#64748B]">View All Orders</a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-[#E2E8F0] overflow-hidden">
                <div class="px-6 py-4 border-b border-[#F1F5F9] flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium text-[#0F172A] font-poppins">Top Performing Products</h3>
                        <p class="text-xs text-[#64748B]">Based on revenue from the last 30 days</p>
                    </div>
                    <a href="/products" class="text-sm font-semibold text-[#0052CC]">View All Products</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-[#F8FAFC] text-[#64748B] text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="text-left py-3 px-6">Name</th>
                            <th class="text-left py-3 px-6">Category</th>
                            <th class="text-right py-3 px-6">Units Sold</th>
                            <th class="text-right py-3 px-6">Revenue</th>
                            <th class="text-left py-3 px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                                                <tr>
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#F1F5F9] rounded-lg flex items-center justify-center">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#64748B"/></svg>
                                    </div>
                                    <span class="font-inter font-medium text-[#0F172A]">UltraPro Wireless Headphones</span>
                                </div>
                            </td>
                            <td class="py-4 px-6"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Accessories</span></td>
                            <td class="py-4 px-6 text-right font-inter font-medium">1,204</td>
                            <td class="py-4 px-6 text-right font-inter font-medium">$12,040.50</td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 text-xs font-bold px-2 py-1 rounded-full">
                                    <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.816667 7L0 6.18333L4.31667 1.8375L6.65 4.17083L9.68333 1.16667H8.16667V0H11.6667V3.5H10.5V1.98333L6.65 5.83333L4.31667 3.5L0.816667 7Z" fill="#059669"/></svg>
                                    Trending Up
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#F1F5F9] rounded-lg flex items-center justify-center">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#64748B"/></svg>
                                    </div>
                                    <span class="font-inter font-medium text-[#0F172A]">Minimalist Leather Wallet</span>
                                </div>
                            </td>
                            <td class="py-4 px-6"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Accessories</span></td>
                            <td class="py-4 px-6 text-right font-inter font-medium">856</td>
                            <td class="py-4 px-6 text-right font-inter font-medium">$4,280.00</td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 text-xs font-bold px-2 py-1 rounded-full">
                                    <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.816667 7L0 6.18333L4.31667 1.8375L6.65 4.17083L9.68333 1.16667H8.16667V0H11.6667V3.5H10.5V1.98333L6.65 5.83333L4.31667 3.5L0.816667 7Z" fill="#059669"/></svg>
                                    Trending Up
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#F1F5F9] rounded-lg flex items-center justify-center">
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="#64748B"/></svg>
                                    </div>
                                    <span class="font-inter font-medium text-[#0F172A]">Organic Cotton T-Shirt Pack</span>
                                </div>
                            </td>
                            <td class="py-4 px-6"><span class="bg-[#F1F5F9] text-[#475569] px-2 py-1 rounded text-xs">Apparel</span></td>
                            <td class="py-4 px-6 text-right font-inter font-medium">412</td>
                            <td class="py-4 px-6 text-right font-inter font-medium">$3,502.00</td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center gap-1 bg-red-50 text-red-600 text-xs font-bold px-2 py-1 rounded-full">
                                    <svg width="12" height="7" viewBox="0 0 12 7" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.16667 7V5.83333H9.68333L6.65 2.82917L4.31667 5.1625L0 0.816667L0.816667 0L4.31667 3.5L6.65 1.16667L10.5 5.01667V3.5H11.6667V7H8.16667Z" fill="#E11D48"/></svg>
                                    Trending Down
                                </span>
                            </td>
                        </tr>
                                            </tbody>
                </table>
                </div>
            </div>

@endsection
