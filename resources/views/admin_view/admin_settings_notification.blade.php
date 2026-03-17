@extends('layouts.admin.admin-Sidebar')

@section('title', 'System Notifications')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white/60 backdrop-blur-sm border-b border-[#C3C6D6]/15 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="flex-1 flex justify-start md:justify-center">
        <div class="relative w-full max-w-[460px]">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M16.6 18L10.3 11.7C9.8 12.1 9.225 12.4167 8.575 12.65C7.925 12.8833 7.23333 13 6.5 13C4.68333 13 3.14583 12.3708 1.8875 11.1125C0.629167 9.85417 0 8.31667 0 6.5C0 4.68333 0.629167 3.14583 1.8875 1.8875C3.14583 0.629167 4.68333 0 6.5 0C8.31667 0 9.85417 0.629167 11.1125 1.8875C12.3708 3.14583 13 4.68333 13 6.5C13 7.23333 12.8833 7.925 12.65 8.575C12.4167 9.225 12.1 9.8 11.7 10.3L18 16.6L16.6 18ZM6.5 11C7.75 11 8.8125 10.5625 9.6875 9.6875C10.5625 8.8125 11 7.75 11 6.5C11 5.25 10.5625 4.1875 9.6875 3.3125C8.8125 2.4375 7.75 2 6.5 2C5.25 2 4.1875 2.4375 3.3125 3.3125C2.4375 4.1875 2 5.25 2 6.5C2 7.75 2.4375 8.8125 3.3125 9.6875C4.1875 10.5625 5.25 11 6.5 11Z" fill="#737685"/>
                </svg>
            </div>
            <input type="text" placeholder="Search configuration..." class="w-full bg-[#EFF4FF] border border-transparent rounded-2xl py-2.5 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/60 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>
    </div>

    <div class="flex items-center gap-4 shrink-0">
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors" aria-label="Notifications">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#434654"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#BA1A1A] border-2 border-white rounded-full"></span>
        </button>

        <button class="p-2 rounded-full hover:bg-gray-100 transition-colors" aria-label="Help">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 18C7.76667 18 5.875 17.225 4.325 15.675C2.775 14.125 2 12.2333 2 10C2 7.76667 2.775 5.875 4.325 4.325C5.875 2.775 7.76667 2 10 2C12.2333 2 14.125 2.775 15.675 4.325C17.225 5.875 18 7.76667 18 10C18 12.2333 17.225 14.125 15.675 15.675C14.125 17.225 12.2333 18 10 18ZM10 16C11.6833 16 13.1042 15.4208 14.2625 14.2625C15.4208 13.1042 16 11.6833 16 10C16 8.31667 15.4208 6.89583 14.2625 5.7375C13.1042 4.57917 11.6833 4 10 4C8.31667 4 6.89583 4.57917 5.7375 5.7375C4.57917 6.89583 4 8.31667 4 10C4 11.6833 4.57917 13.1042 5.7375 14.2625C6.89583 15.4208 8.31667 16 10 16ZM9 13H11V11H9V13ZM9 9.8H11C11 9.2 11.0708 8.77917 11.2125 8.5375C11.3542 8.29583 11.7 7.95 12.25 7.5C12.6833 7.15 13.0083 6.8125 13.225 6.4875C13.4417 6.1625 13.55 5.75 13.55 5.25C13.55 4.48333 13.2417 3.85833 12.625 3.375C12.0083 2.89167 11.2167 2.65 10.25 2.65C9.35 2.65 8.57917 2.8875 7.9375 3.3625C7.29583 3.8375 6.88333 4.46667 6.7 5.25L8.5 5.95C8.58333 5.51667 8.775 5.18333 9.075 4.95C9.375 4.71667 9.74167 4.6 10.175 4.6C10.6417 4.6 11.0125 4.70833 11.2875 4.925C11.5625 5.14167 11.7 5.425 11.7 5.775C11.7 6.075 11.6208 6.34167 11.4625 6.575C11.3042 6.80833 11.025 7.08333 10.625 7.4C9.95833 7.95 9.50417 8.425 9.2625 8.825C9.02083 9.225 8.93333 9.55 9 9.8Z" fill="#434654"/>
            </svg>
        </button>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter max-w-9xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl md:text-4xl font-medium text-[#0B1C30] font-poppins">System Notifications</h1>
        <p class="text-[19px] text-[#434654] mt-2 max-w-4xl">Manage your global platform configurations and communication preferences.</p>
    </div>

    <div class="border-b border-[#C3C6D6]/35 overflow-x-auto">
        <nav class="flex gap-10 min-w-max">
            <a href="{{ route('admin-settings') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">General Platform Info</a>
            <a href="{{ route('admin-security') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">Security & Auth</a>
            <!-- <a href=" " class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">API & Developer Keys</a>-->
            <a href="{{ route('admin-notifications') }}" class="pb-4 border-b-2 border-[#003D9B] text-[#003D9B] font-semibold text-base">System Notifications</a>
        </nav>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-8 bg-white rounded-2xl border border-[#E7EBF4] p-6 md:p-8">
            <div class="flex items-center gap-3 mb-6">
                <svg width="24" height="18" viewBox="0 0 20 16" fill="none">
                    <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H2ZM10 9L2 4V14H18V4L10 9ZM10 7L18 2H2L10 7ZM2 4V2V4V14V4Z" fill="#003D9B"/>
                </svg>
                <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Global Email Settings</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-[#E7EBF4]">
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Sender Name</label>
                    <input type="text" value="BaaS Admin Services" class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-base text-[#0B1C30]"/>
                </div>
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Support Email</label>
                    <input type="email" value="support@baas-enterprise.com" class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-base text-[#0B1C30]"/>
                </div>
            </div>

            <div class="pt-6">
                <h3 class="text-lg font-medium text-[#0B1C30] mb-4 font-poppins">SMTP Configuration</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm md:text-[13px] text-[#434654] mb-2">SMTP Host</label>
                        <input type="text" value="smtp.provider.com" class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-[#6B7280] text-base"/>
                    </div>
                    <div>
                        <label class="block text-sm md:text-[13px] text-[#434654] mb-2">Port</label>
                        <input type="text" value="587" class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-[#0B1C30] text-base"/>
                    </div>
                    <div>
                        <label class="block text-sm md:text-[13px] text-[#434654] mb-2">Encryption</label>
                        <div class="relative">
                            <select class="w-full appearance-none bg-[#EFF4FF] border border-[#D2D8E4] rounded-xl px-4 py-2.5 text-[#0B1C30] text-base">
                                <option>TLS</option>
                                <option>SSL</option>
                                <option>None</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg width="14" height="14" viewBox="0 0 12 12" fill="none">
                                    <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-5">
                <button class="px-8 py-3 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white text-sm font-semibold rounded-xl shadow-[0_8px_24px_rgba(0,82,204,0.3)] hover:brightness-105 transition">Save Email Config</button>
            </div>
        </div>

        <div class="xl:col-span-4 bg-white rounded-2xl border border-[#E7EBF4] p-6 md:p-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Alert Triggers</h2>
                <span class="px-3 py-1 bg-[#DAE2FF] text-[#001848] text-xs font-bold uppercase rounded-lg">LIVE</span>
            </div>

            <div class="space-y-5">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-[#EFF4FF] rounded-xl flex items-center justify-center shrink-0">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M12.5 18.925L8.25 14.675L9.65 13.275L12.5 16.125L18.15 10.475L19.55 11.875L12.5 18.925ZM18 9H16V4H14V7H4V4H2V18H8V20H2C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V4C0 3.45 0.195833 2.97917 0.5875 2.5875C0.979167 2.19583 1.45 2 2 2H6.175C6.35833 1.41667 6.71667 0.9375 7.25 0.5625C7.78333 0.1875 8.36667 0 9 0C9.66667 0 10.2625 0.1875 10.7875 0.5625C11.3125 0.9375 11.6667 1.41667 11.85 2H16C16.55 2 17.0208 2.19583 17.4125 2.5875C17.8042 2.97917 18 3.45 18 4V9Z" fill="#434654"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-lg md:text-base font-bold text-[#0B1C30] leading-6">Low Stock Warning</span>
                            <span class="w-12 h-6 rounded-full bg-[#003D9B] relative shrink-0"><span class="absolute right-1 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white"></span></span>
                        </div>
                        <p class="text-base md:text-sm text-[#434654] mt-1">Notify when inventory falls below 15% threshold.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-[#FFDAD6]/20 rounded-xl flex items-center justify-center shrink-0">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 15C10.2833 15 10.5208 14.9042 10.7125 14.7125C10.9042 14.5208 11 14.2833 11 14C11 13.7167 10.9042 13.4792 10.7125 13.2875C10.5208 13.0958 10.2833 13 10 13C9.71667 13 9.47917 13.0958 9.2875 13.2875C9.09583 13.4792 9 13.7167 9 14C9 14.2833 9.09583 14.5208 9.2875 14.7125C9.47917 14.9042 9.71667 15 10 15ZM9 11H11V5H9V11ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#BA1A1A"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-lg md:text-base font-bold text-[#0B1C30] leading-6">API Error Spike</span>
                            <span class="w-12 h-6 rounded-full bg-[#003D9B] relative shrink-0"><span class="absolute right-1 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white"></span></span>
                        </div>
                        <p class="text-base md:text-sm text-[#434654] mt-1">Trigger alert if 5xx errors exceed 2% in 5 min.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-[#EFF4FF] rounded-xl flex items-center justify-center shrink-0">
                        <svg width="22" height="16" viewBox="0 0 22 16" fill="none"><path d="M17 10V7H14V5H17V2H19V5H22V7H19V10H17ZM8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8ZM0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0Z" fill="#434654"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-lg md:text-base font-bold text-[#0B1C30] leading-6">Enterprise Signup</span>
                            <span class="w-12 h-6 rounded-full bg-[#E7EBF4] relative shrink-0"><span class="absolute left-1 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white"></span></span>
                        </div>
                        <p class="text-base md:text-sm text-[#434654] mt-1">Daily digest of new platform tenants.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-[#EFF4FF] rounded-xl flex items-center justify-center shrink-0">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M12.95 5.75C12.75 5.25 12.4542 4.85833 12.0625 4.575C11.6708 4.29167 11.2 4.15 10.65 4.15C10.35 4.15 10.0583 4.19167 9.775 4.275C9.49167 4.35833 9.23333 4.51667 9 4.75L7.55 3.3C7.78333 3.06667 8.1 2.85417 8.5 2.6625C8.9 2.47083 9.26667 2.35 9.6 2.3V0.2H11.6V2.25C12.35 2.4 13.0083 2.70417 13.575 3.1625C14.1417 3.62083 14.5667 4.21667 14.85 4.95L12.95 5.75ZM18.4 19.8L13.8 15.2C13.55 15.45 13.2083 15.6542 12.775 15.8125C12.3417 15.9708 11.95 16.0667 11.6 16.1V18.2H9.6V16.05C8.66667 15.8167 7.8875 15.3917 7.2625 14.775C6.6375 14.1583 6.18333 13.3833 5.9 12.45L7.9 11.65C8.1 12.35 8.4375 12.95 8.9125 13.45C9.3875 13.95 10.0167 14.2 10.8 14.2C11.1 14.2 11.375 14.1625 11.625 14.0875C11.875 14.0125 12.1167 13.9 12.35 13.75L0 1.4L1.4 0L19.8 18.4L18.4 19.8Z" fill="#434654"/></svg>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-lg md:text-base font-bold text-[#0B1C30] leading-6">Payout Failed</span>
                            <span class="w-12 h-6 rounded-full bg-[#003D9B] relative shrink-0"><span class="absolute right-1 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white"></span></span>
                        </div>
                        <p class="text-base md:text-sm text-[#434654] mt-1">Immediate SMS and Email to Financial Ops.</p>
                    </div>
                </div>
            </div>

            <button class="w-full mt-6 py-3 border border-dashed border-[#B8C1D1] rounded-xl text-base md:text-sm font-bold text-[#434654] hover:bg-gray-50 transition">+ Create Custom Trigger</button>
        </div>

        <div class="xl:col-span-4 bg-white rounded-2xl border border-[#E7EBF4] p-6 md:p-8">
            <div class="flex items-center gap-3 mb-5">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M0 20V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H4L0 20ZM3.15 14H18V2H2V15.125L3.15 14ZM2 14V2V14Z" fill="#003D9B"/>
                </svg>
                <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">SMS Gateway</h2>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-[#EFF4FF] rounded-xl border-l-4 border-l-[#003D9B]">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                            <svg width="17" height="17" viewBox="0 0 17 17" fill="none"><path d="M8.25 9.75L6.75 8.25L8.25 6.75L9.75 8.25L8.25 9.75ZM6.65625 5.34375L4.78125 3.46875L8.25 0L11.7188 3.46875L9.84375 5.34375L8.25 3.75L6.65625 5.34375ZM3.46875 11.7188L0 8.25L3.46875 4.78125L5.34375 6.65625L3.75 8.25L5.34375 9.84375L3.46875 11.7188ZM13.0312 11.7188L11.1562 9.84375L12.75 8.25L11.1562 6.65625L13.0312 4.78125L16.5 8.25L13.0312 11.7188ZM8.25 16.5L4.78125 13.0312L6.65625 11.1562L8.25 12.75L9.84375 11.1562L11.7188 13.0312L8.25 16.5Z" fill="#003D9B"/></svg>
                        </div>
                        <div class="text-lg md:text-[17px] font-bold text-[#0B1C30]">Twilio Integration</div>
                    </div>
                    <span class="px-3 py-1 bg-[#4EDEA3] text-[#005236] text-xs font-bold uppercase rounded-full">ACTIVE</span>
                </div>

                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Account SID</label>
                    <input type="text" value="AC8b772c912e7536..." class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-[#0B1C30] text-base"/>
                </div>
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Auth Token</label>
                    <input type="password" value="****************" class="w-full bg-[#EFF4FF] border border-[#7A8699] rounded-xl px-4 py-2.5 text-[#0B1C30] text-base"/>
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 bg-white rounded-2xl border border-[#E7EBF4] p-6 md:p-8">
            <div class="flex items-center gap-3 mb-5">
                <svg width="20" height="19" viewBox="0 0 20 19" fill="none">
                    <path d="M5 19C3.61667 19 2.4375 18.5125 1.4625 17.5375C0.4875 16.5625 0 15.3833 0 14C0 12.7833 0.379167 11.7208 1.1375 10.8125C1.89583 9.90417 2.85 9.33333 4 9.1V11.175C3.41667 11.375 2.9375 11.7333 2.5625 12.25C2.1875 12.7667 2 13.35 2 14C2 14.8333 2.29167 15.5417 2.875 16.125C3.45833 16.7083 4.16667 17 5 17C5.83333 17 6.54167 16.7083 7.125 16.125C7.70833 15.5417 8 14.8333 8 14V13H13.875C14.0083 12.85 14.1708 12.7292 14.3625 12.6375C14.5542 12.5458 14.7667 12.5 15 12.5C15.4167 12.5 15.7708 12.6458 16.0625 12.9375C16.3542 13.2292 16.5 13.5833 16.5 14C16.5 14.4167 16.3542 14.7708 16.0625 15.0625C15.7708 15.3542 15.4167 15.5 15 15.5C14.7667 15.5 14.5542 15.4542 14.3625 15.3625C14.1708 15.2708 14.0083 15.15 13.875 15H9.9C9.66667 16.15 9.09583 17.1042 8.1875 17.8625C7.27917 18.6208 6.21667 19 5 19Z" fill="#003D9B"/>
                </svg>
                <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Webhooks</h2>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#EEF2FF] rounded-lg flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 12H12V10H4V12ZM4 9H16V7H4V9ZM4 6H16V4H4V6ZM0 20V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H4L0 20ZM3.15 14H18V2H2V15.125L3.15 14ZM2 14V2V14Z" fill="#4F46E5"/></svg>
                        </div>
                        <div>
                            <div class="text-base font-bold text-[#0B1C30]">Slack Alerts</div>
                            <div class="text-sm text-[#434654]">#ops-critical-alerts</div>
                        </div>
                    </div>
                    <svg width="8" height="12" viewBox="0 0 8 12" fill="none"><path d="M4.6 6L0 1.4L1.4 0L7.4 6L1.4 12L0 10.6L4.6 6Z" fill="#737685"/></svg>
                </div>

                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg cursor-pointer">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#EFF6FF] rounded-lg flex items-center justify-center">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M0 20V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H4L0 20ZM3.15 14H18V2H2V15.125L3.15 14ZM2 14V2V14Z" fill="#2563EB"/></svg>
                        </div>
                        <div>
                            <div class="text-base font-bold text-[#0B1C30]">Discord Webhook</div>
                            <div class="text-sm text-[#434654]">Not Configured</div>
                        </div>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M6 8H0V6H6V0H8V6H14V8H8V14H6V8Z" fill="#737685"/></svg>
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 self-start bg-white rounded-2xl border border-[#E7EBF4] p-5 border-l-4 border-l-[#003D9B] flex items-end justify-between gap-4 min-h-[126px]">
            <div>
                <div class="text-[12px] font-bold uppercase tracking-[0.12em] text-[#003D9B] mb-2">Node Health Service</div>
                <div class="text-[40px] leading-none font-bold text-[#0B1C30]">99.98%</div>
                <div class="text-sm text-[#434654] mt-1">Uptime Delivery Performance</div>
            </div>
            <div class="flex items-end gap-1.5 pb-1">
                <div class="w-2 h-6 bg-[#DAE2FF] rounded"></div>
                <div class="w-2 h-10 bg-[#DAE2FF] rounded"></div>
                <div class="w-2 h-14 bg-[#DAE2FF] rounded"></div>
                <div class="w-2 h-12 bg-[#DAE2FF] rounded"></div>
                <div class="w-2 h-16 bg-[#003D9B] rounded"></div>
            </div>
        </div>
    </div>
</div>
@endsection
