@extends('layouts.admin.admin-Sidebar')

@section('title', 'Security & Authentication')

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
            <input type="text" placeholder="Search platform settings..." class="w-full bg-[#EFF4FF] border border-transparent rounded-2xl py-2.5 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/60 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
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
        <h1 class="text-3xl md:text-4xl font-medium text-[#0B1C30] font-poppins">Security & Authentication</h1>
        <p class="text-[19px] text-[#434654] mt-2 max-w-4xl">Configure identity providers, access policies, and platform hardening settings.</p>
    </div>

    <div class="border-b border-[#C3C6D6]/35 overflow-x-auto">
        <nav class="flex gap-10 min-w-max">
            <a href="{{ route('admin-settings') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">General Platform Info</a>
            <a href="{{ route('admin-security') }}" class="pb-4 border-b-2 border-[#003D9B] text-[#003D9B] font-semibold text-base">Security & Auth</a>
           <!-- <a href=" route('admin-settings-api') " class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">API & Developer Keys</a> -->
            <a href="{{ route('admin-notifications') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-base">System Notifications</a>
        </nav>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-7 bg-white rounded-2xl border border-[#E7EBF4] p-6">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-[#6FFBBE] rounded-xl flex items-center justify-center shrink-0">
                        <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                            <path d="M6.95 13.55L12.6 7.9L11.175 6.475L6.95 10.7L4.85 8.6L3.425 10.025L6.95 13.55ZM8 20C5.68333 19.4167 3.77083 18.0875 2.2625 16.0125C0.754167 13.9375 0 11.6333 0 9.1V3L8 0L16 3V9.1C16 11.6333 15.2458 13.9375 13.7375 16.0125C12.2292 18.0875 10.3167 19.4167 8 20ZM8 17.9C9.73333 17.35 11.1667 16.25 12.3 14.6C13.4333 12.95 14 11.1167 14 9.1V4.375L8 2.125L2 4.375V9.1C2 11.1167 2.56667 12.95 3.7 14.6C4.83333 16.25 6.26667 17.35 8 17.9Z" fill="#005236"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Multi-Factor Authentication</h2>
                        <p class="text-sm text-[#434654]">Enforce extra security for all admin accounts</p>
                    </div>
                </div>
                <span class="px-4 py-1 bg-[#6FFBBE] text-[#005236] text-xs font-bold uppercase rounded-full">ACTIVE</span>
            </div>

            <div class="bg-[#EFF4FF] rounded-xl p-4 border border-[#DEE5F2] mb-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-medium text-[#1A293D] font-poppins">Enforce 2FA for all Administrators</h3>
                        <p class="text-sm text-[#4A5568]">Requires TOTP or Hardware key on every login</p>
                    </div>
                    <button class="w-12 h-7 rounded-full bg-[#003D9B] relative shrink-0" aria-label="toggle">
                        <span class="absolute right-1 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-white"></span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-[#DCE3EF] rounded-xl p-4">
                    <h4 class="text-[11px] font-medium uppercase tracking-[0.12em] text-[#003D9B] mb-3 font-poppins">Trusted Methods</h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-base text-[#1A293D]">
                            <svg width="14" height="14" viewBox="0 0 12 12" fill="none">
                                <path d="M5.01667 8.51667L9.12917 4.40417L8.3125 3.5875L5.01667 6.88333L3.35417 5.22083L2.5375 6.0375L5.01667 8.51667ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#005236"/>
                            </svg>
                            <span>Authenticator App (TOTP)</span>
                        </div>
                        <div class="flex items-center gap-2 text-base text-[#1A293D]">
                            <svg width="14" height="14" viewBox="0 0 12 12" fill="none">
                                <path d="M5.01667 8.51667L9.12917 4.40417L8.3125 3.5875L5.01667 6.88333L3.35417 5.22083L2.5375 6.0375L5.01667 8.51667ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#005236"/>
                            </svg>
                            <span>FIDO2 / WebAuthn Keys</span>
                        </div>
                    </div>
                </div>

                <div class="border border-[#DCE3EF] rounded-xl p-4 bg-[#F8FAFD]">
                    <h4 class="text-[11px] font-medium uppercase tracking-[0.12em] text-[#A1A8B5] mb-3 font-poppins">Legacy Methods</h4>
                    <div class="flex items-center gap-2 text-base text-[#8B94A5]">
                        <svg width="14" height="14" viewBox="0 0 12 12" fill="none">
                            <path d="M5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#9CA6B9"/>
                        </svg>
                        <span>SMS / Voice Verification</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-5 bg-white rounded-2xl border border-[#E7EBF4] p-6">
            <div class="flex items-start gap-3 mb-5">
                <div class="w-10 h-10 bg-[#DCE9FF] rounded-xl flex items-center justify-center shrink-0">
                    <svg width="18" height="21" viewBox="0 0 18 21" fill="none">
                        <path d="M6 2V0H12V2H6ZM8 13H10V7H8V13ZM9 21C7.76667 21 6.60417 20.7625 5.5125 20.2875C4.42083 19.8125 3.46667 19.1667 2.65 18.35C1.83333 17.5333 1.1875 16.5792 0.7125 15.4875C0.2375 14.3958 0 13.2333 0 12C0 10.7667 0.2375 9.60417 0.7125 8.5125C1.1875 7.42083 1.83333 6.46667 2.65 5.65C3.46667 4.83333 4.42083 4.1875 5.5125 3.7125C6.60417 3.2375 7.76667 3 9 3C10.0333 3 11.025 3.16667 11.975 3.5C12.925 3.83333 13.8167 4.31667 14.65 4.95L16.05 3.55L17.45 4.95L16.05 6.35C16.6833 7.18333 17.1667 8.075 17.5 9.025C17.8333 9.975 18 10.9667 18 12C18 13.2333 17.7625 14.3958 17.2875 15.4875C16.8125 16.5792 16.1667 17.5333 15.35 18.35C14.5333 19.1667 13.5792 19.8125 12.4875 20.2875C11.3958 20.7625 10.2333 21 9 21ZM9 19C10.9333 19 12.5833 18.3167 13.95 16.95C15.3167 15.5833 16 13.9333 16 12C16 10.0667 15.3167 8.41667 13.95 7.05C12.5833 5.68333 10.9333 5 9 5C7.06667 5 5.41667 5.68333 4.05 7.05C2.68333 8.41667 2 10.0667 2 12C2 13.9333 2.68333 15.5833 4.05 16.95C5.41667 18.3167 7.06667 19 9 19Z" fill="#0040A2"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Session Management</h2>
                    <p class="text-sm text-[#434654]">Control active connection limits</p>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Global Session Timeout</label>
                    <div class="flex justify-end">
                        <span class="inline-flex px-4 py-1.5 bg-[#EFF4FF] border border-[#D2D8E4] rounded-md text-base font-bold text-[#0B1C30]">60m</span>
                    </div>
                </div>

                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Concurrent Session Limit</label>
                    <div class="relative">
                        <select class="w-full appearance-none bg-[#EFF4FF] border border-[#D2D8E4] rounded-lg py-2.5 pl-3.5 pr-10 text-base text-[#0B1C30] focus:outline-none">
                            <option>3 Sessions (Standard)</option>
                            <option>5 Sessions</option>
                            <option>Unlimited</option>
                        </select>
                        <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                            <svg width="14" height="14" viewBox="0 0 12 12" fill="none">
                                <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <button class="w-full py-2.5 bg-[#FFDAD6] text-[#93000A] text-sm font-bold rounded-lg">Revoke All Active Sessions</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-6 bg-white rounded-2xl border border-[#E7EBF4] p-6">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-[#DCE9FF] rounded-xl flex items-center justify-center shrink-0">
                        <svg width="22" height="12" viewBox="0 0 22 12" fill="none">
                            <path d="M6 12C4.33333 12 2.91667 11.4167 1.75 10.25C0.583333 9.08333 0 7.66667 0 6C0 4.33333 0.583333 2.91667 1.75 1.75C2.91667 0.583333 4.33333 0 6 0C7.1 0 8.10833 0.275 9.025 0.825C9.94167 1.375 10.6667 2.1 11.2 3H22V9H20V12H14V9H11.2C10.6667 9.9 9.94167 10.625 9.025 11.175C8.10833 11.725 7.1 12 6 12ZM6 10C7.1 10 7.98333 9.6625 8.65 8.9875C9.31667 8.3125 9.71667 7.65 9.85 7H16V10H18V7H20V5H9.85C9.71667 4.35 9.31667 3.6875 8.65 3.0125C7.98333 2.3375 7.1 2 6 2C4.9 2 3.95833 2.39167 3.175 3.175C2.39167 3.95833 2 4.9 2 6C2 7.1 2.39167 8.04167 3.175 8.825C3.95833 9.60833 4.9 10 6 10Z" fill="#003D9B"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">SSO / OAuth Providers</h2>
                        <p class="text-sm text-[#434654]">Manage external identity federation</p>
                    </div>
                </div>
                <button class="px-5 py-2 bg-[#E7EEF8] text-[#003D9B] text-sm font-bold rounded-xl">Add Provider</button>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between border border-[#D8DDE8] rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full border border-[#E3E7EE] bg-[#F6F8FB] flex items-center justify-center"></div>
                        <div>
                            <div class="text-lg font-bold text-[#0B1C30] leading-5">Google Workspace</div>
                            <div class="text-xs text-[#434654]">auth.google.com/baas-admin</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 bg-[#6FFBBE] text-[#005236] text-xs font-bold uppercase rounded">ENABLED</span>
                        <button class="text-[#5E6A7E]">
                            <svg width="5" height="20" viewBox="0 0 5 20" fill="none">
                                <path d="M2.5 4C3.60457 4 4.5 3.10457 4.5 2C4.5 0.89543 3.60457 0 2.5 0C1.39543 0 0.5 0.89543 0.5 2C0.5 3.10457 1.39543 4 2.5 4ZM2.5 12C3.60457 12 4.5 11.1046 4.5 10C4.5 8.89543 3.60457 8 2.5 8C1.39543 8 0.5 8.89543 0.5 10C0.5 11.1046 1.39543 12 2.5 12ZM2.5 20C3.60457 20 4.5 19.1046 4.5 18C4.5 16.8954 3.60457 16 2.5 16C1.39543 16 0.5 16.8954 0.5 18C0.5 19.1046 1.39543 20 2.5 20Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between border border-[#D8DDE8] rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full border border-[#E3E7EE] bg-[#F6F8FB] flex items-center justify-center"></div>
                        <div>
                            <div class="text-lg font-bold text-[#0B1C30] leading-5">Microsoft Azure AD</div>
                            <div class="text-xs text-[#434654]">Not Configured</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <button class="text-[#003D9B] text-sm font-bold">Configure</button>
                        <button class="text-[#5E6A7E]">
                            <svg width="5" height="20" viewBox="0 0 5 20" fill="none">
                                <path d="M2.5 4C3.60457 4 4.5 3.10457 4.5 2C4.5 0.89543 3.60457 0 2.5 0C1.39543 0 0.5 0.89543 0.5 2C0.5 3.10457 1.39543 4 2.5 4ZM2.5 12C3.60457 12 4.5 11.1046 4.5 10C4.5 8.89543 3.60457 8 2.5 8C1.39543 8 0.5 8.89543 0.5 10C0.5 11.1046 1.39543 12 2.5 12ZM2.5 20C3.60457 20 4.5 19.1046 4.5 18C4.5 16.8954 3.60457 16 2.5 16C1.39543 16 0.5 16.8954 0.5 18C0.5 19.1046 1.39543 20 2.5 20Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between border border-[#D8DDE8] rounded-2xl px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full border border-[#E3E7EE] bg-[#F6F8FB] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 20 18" fill="none">
                                <path d="M0 18V0H10V4H20V18H0ZM2 16H8V14H2V16ZM2 12H8V10H2V12ZM2 8H8V6H2V8ZM2 4H8V2H2V4ZM10 16H18V6H10V16ZM12 10V8H16V10H12ZM12 14V12H16V14H12Z" fill="#0B1C30"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-lg font-bold text-[#0B1C30] leading-5">Okta Enterprise</div>
                            <div class="text-xs text-[#434654]">enterprise.okta.com/baas-102</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 bg-[#CBD8EC] text-[#46546B] text-xs font-bold uppercase rounded">MAINTENANCE</span>
                        <button class="text-[#5E6A7E]">
                            <svg width="5" height="20" viewBox="0 0 5 20" fill="none">
                                <path d="M2.5 4C3.60457 4 4.5 3.10457 4.5 2C4.5 0.89543 3.60457 0 2.5 0C1.39543 0 0.5 0.89543 0.5 2C0.5 3.10457 1.39543 4 2.5 4ZM2.5 12C3.60457 12 4.5 11.1046 4.5 10C4.5 8.89543 3.60457 8 2.5 8C1.39543 8 0.5 8.89543 0.5 10C0.5 11.1046 1.39543 12 2.5 12ZM2.5 20C3.60457 20 4.5 19.1046 4.5 18C4.5 16.8954 3.60457 16 2.5 16C1.39543 16 0.5 16.8954 0.5 18C0.5 19.1046 1.39543 20 2.5 20Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-6 bg-white rounded-2xl border border-[#E7EBF4] p-6">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 bg-[#5B6779] rounded-xl flex items-center justify-center shrink-0">
                    <svg width="22" height="12" viewBox="0 0 22 12" fill="none">
                        <path d="M1 12V10H21V12H1ZM2.15 5.95L0.85 5.2L1.7 3.7H0V2.2H1.7L0.85 0.75L2.15 0L3 1.45L3.85 0L5.15 0.75L4.3 2.2H6V3.7H4.3L5.15 5.2L3.85 5.95L3 4.45L2.15 5.95ZM10.15 5.95L8.85 5.2L9.7 3.7H8V2.2H9.7L8.85 0.75L10.15 0L11 1.45L11.85 0L13.15 0.75L12.3 2.2H14V3.7H12.3L13.15 5.2L11.85 5.95L11 4.45L10.15 5.95ZM18.15 5.95L16.85 5.2L17.7 3.7H16V2.2H17.7L16.85 0.75L18.15 0L19 1.45L19.85 0L21.15 0.75L20.3 2.2H22V3.7H20.3L21.15 5.2L19.85 5.95L19 4.45L18.15 5.95Z" fill="white"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Password Policy</h2>
                    <p class="text-sm text-[#434654]">Complexity and rotation standards</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Min Length</label>
                    <div class="bg-[#EFF4FF] border border-[#D2D8E4] rounded-lg px-3.5 py-2.5 text-base text-[#0B1C30]">12</div>
                </div>
                <div>
                    <label class="block text-[12px] font-bold uppercase tracking-[0.12em] text-[#333D50] mb-2">Expiry (Days)</label>
                    <div class="bg-[#EFF4FF] border border-[#D2D8E4] rounded-lg px-3.5 py-2.5 text-base text-[#0B1C30]">90</div>
                </div>
            </div>

            <div>
                <h3 class="text-[12px] font-medium uppercase tracking-[0.12em] text-[#333D50] mb-3 font-poppins">Complexity Requirements</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-8 text-base text-[#0B1C30] mb-5">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" class="w-5 h-5 accent-[#003D9B]" checked>
                        <span>Special Characters</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" class="w-5 h-5 accent-[#003D9B]" checked>
                        <span>Numeric Digits</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" class="w-5 h-5 accent-[#003D9B]" checked>
                        <span>Uppercase Mixed</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" class="w-5 h-5 border-[#C3C6D6] rounded">
                        <span>No Dict. Words</span>
                    </label>
                </div>

                <div class="p-4 bg-[#EFF4FF] border border-[#D2D8E4] rounded-xl text-sm text-[#4A5568]">
                    <span class="font-bold text-[#003D9B]">Note:</span>
                    Password policies do not apply to users logging in via SSO providers.
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end items-center gap-6 pt-2 pb-2">
        <button class="text-[#434654] font-bold text-lg">Reset to Default</button>
        <button class="px-9 py-3 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white font-bold rounded-xl shadow-[0_8px_24px_rgba(0,82,204,0.3)] hover:brightness-105 transition">Save Security Policies</button>
    </div>
</div>
@endsection
