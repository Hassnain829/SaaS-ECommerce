@extends('layouts.admin.admin-Sidebar')

@section('title', 'Platform Settings')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white/50 backdrop-blur-sm border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
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
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M16.6 18L10.3 11.7C9.8 12.1 9.225 12.4167 8.575 12.65C7.925 12.8833 7.23333 13 6.5 13C4.68333 13 3.14583 12.3708 1.8875 11.1125C0.629167 9.85417 0 8.31667 0 6.5C0 4.68333 0.629167 3.14583 1.8875 1.8875C3.14583 0.629167 4.68333 0 6.5 0C8.31667 0 9.85417 0.629167 11.1125 1.8875C12.3708 3.14583 13 4.68333 13 6.5C13 7.23333 12.8833 7.925 12.65 8.575C12.4167 9.225 12.1 9.8 11.7 10.3L18 16.6L16.6 18ZM6.5 11C7.75 11 8.8125 10.5625 9.6875 9.6875C10.5625 8.8125 11 7.75 11 6.5C11 5.25 10.5625 4.1875 9.6875 3.3125C8.8125 2.4375 7.75 2 6.5 2C5.25 2 4.1875 2.4375 3.3125 3.3125C2.4375 4.1875 2 5.25 2 6.5C2 7.75 2.4375 8.8125 3.3125 9.6875C4.1875 10.5625 5.25 11 6.5 11Z" fill="#737685"/>
                </svg>
            </div>
            <input type="text" placeholder="Search system logs, tenants, or settings..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/60 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
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

        <!-- Admin Console label -->
        <div class="hidden sm:block text-right">
            <div class="text-xs font-bold uppercase text-[#434654] tracking-wider">Admin Console</div>
        </div>

        <!-- Profile avatar with border -->
        <div class="w-10 h-10 rounded-full border-2 border-[#DCE9FF] overflow-hidden">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                <circle cx="20" cy="15" r="6" fill="#94A3B8"/>
                <path d="M30 28C30 24 26 22 20 22C14 22 10 24 10 28" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter max-w-9xl mx-auto space-y-8">
    <!-- Page Header -->
    <div>
        <h1 class="text-3xl md:text-4xl font-medium text-[#0B1C30] font-poppins">Platform Settings</h1>
        <p class="text-base md:text-lg text-[#434654] mt-2 max-w-2xl">
            Manage global configurations, security protocols, and system-level preferences.
        </p>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[#C3C6D6]/30 overflow-x-auto">
        <nav class="flex gap-8 min-w-max">
            <a href="{{ route('admin-settings') }}" class="pb-4 border-b-2 border-[#003D9B] text-[#003D9B] font-semibold text-sm">General Platform Info</a>
            <a href="{{ route('admin-security') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-sm">Security & Auth</a>
           <!-- <a href="" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-sm">API & Developer Keys</a> -->
            <a href="{{ route('admin-notifications') }}" class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-sm">System Notifications</a>
        </nav>
    </div>

    <!-- Main twoâ€‘column grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left column -->
        <div class="space-y-6">
            <!-- Platform Branding Card -->
            <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-6 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Platform Branding</h2>
                        <p class="text-sm text-[#434654]">Global visual identity and instance assets.</p>
                    </div>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 20C8.63333 20 7.34167 19.7375 6.125 19.2125C4.90833 18.6875 3.84583 17.9708 2.9375 17.0625C2.02917 16.1542 1.3125 15.0917 0.7875 13.875C0.2625 12.6583 0 11.3667 0 10C0 8.61667 0.270833 7.31667 0.8125 6.1C1.35417 4.88333 2.0875 3.825 3.0125 2.925C3.9375 2.025 5.01667 1.3125 6.25 0.7875C7.48333 0.2625 8.8 0 10.2 0C11.5333 0 12.7917 0.229167 13.975 0.6875C15.1583 1.14583 16.1958 1.77917 17.0875 2.5875C17.9792 3.39583 18.6875 4.35417 19.2125 5.4625C19.7375 6.57083 20 7.76667 20 9.05C20 10.9667 19.4167 12.4375 18.25 13.4625C17.0833 14.4875 15.6667 15 14 15H12.15C12 15 11.8958 15.0417 11.8375 15.125C11.7792 15.2083 11.75 15.3 11.75 15.4C11.75 15.6 11.875 15.8875 12.125 16.2625C12.375 16.6375 12.5 17.0667 12.5 17.55C12.5 18.3833 12.2708 19 11.8125 19.4C11.3542 19.8 10.75 20 10 20Z" fill="#0052CC"/>
                    </svg>
                </div>

                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Logo upload area -->
                    <div class="w-32 h-32 bg-[#E5EEFF] rounded-xl border-2 border-[#C3C6D6] flex flex-col items-center justify-center shrink-0">
                        <svg width="16" height="28" viewBox="0 0 16 28" fill="none">
                            <path d="M7 17H9V12.825L10.6 14.425L12 13L8 9L4 13L5.425 14.4L7 12.825V17ZM2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H10L16 6V18C16 18.55 15.8042 19.0208 15.4125 19.4125C15.0208 19.8042 14.55 20 14 20H2ZM9 7V2H2V18H14V7H9Z" fill="#737685"/>
                        </svg>
                        <span class="text-[10px] font-bold uppercase text-[#737685] mt-1">Upload Logo</span>
                    </div>

                    <!-- Instance Name and Colors -->
                    <div class="flex-1 space-y-4">
                        <div>
                            <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Instance Name</label>
                            <div class="bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg px-4 py-3 text-[#0B1C30] font-medium">BaaS Admin Console</div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Primary Color</label>
                                <div class="bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg p-2 flex items-center gap-3">
                                    <span class="w-8 h-8 rounded bg-[#003D9B]"></span>
                                    <span class="text-xs font-mono text-[#0B1C30]">#003d9b</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Secondary Color</label>
                                <div class="bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg p-2 flex items-center gap-3">
                                    <span class="w-8 h-8 rounded bg-[#515F74]"></span>
                                    <span class="text-xs font-mono text-[#0B1C30]">#515f74</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Registration Policies Card -->
            <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-6 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Tenant Registration Policies</h2>
                        <p class="text-sm text-[#434654]">Onboarding logic and default configurations.</p>
                    </div>
                    <svg width="20" height="16" viewBox="0 0 20 16" fill="none">
                        <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H2ZM2 14H18V4H2V14Z" fill="#0052CC"/>
                    </svg>
                </div>

                <div class="space-y-4">
                    <!-- Allow public signups toggle -->
                    <div class="bg-[#F8F9FF] rounded-xl p-4 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-[#0B1C30]">Allow public signups</div>
                            <div class="text-xs text-[#434654]">Enable the registration portal for new users.</div>
                        </div>
                        <svg width="48" height="24" viewBox="0 0 48 24" fill="none">
                            <rect width="48" height="24" rx="12" fill="#003D9B"/>
                            <rect x="28" y="4" width="16" height="16" rx="8" fill="white"/>
                        </svg>
                    </div>

                    <!-- Require email verification toggle -->
                    <div class="bg-[#F8F9FF] rounded-xl p-4 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-[#0B1C30]">Require email verification</div>
                            <div class="text-xs text-[#434654]">Strict authentication before tenant activation.</div>
                        </div>
                        <svg width="48" height="24" viewBox="0 0 48 24" fill="none">
                            <rect width="48" height="24" rx="12" fill="#003D9B"/>
                            <rect x="28" y="4" width="16" height="16" rx="8" fill="white"/>
                        </svg>
                    </div>

                    <!-- Default tenant plan select -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Default tenant plan</label>
                        <div class="relative bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg">
                            <select class="w-full appearance-none bg-transparent py-3 pl-4 pr-10 text-[#0B1C30] font-medium focus:outline-none">
                                <option>Professional Tier</option>
                                <option>Starter</option>
                                <option>Enterprise</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg width="14" height="8" viewBox="0 0 14 8" fill="none">
                                    <path d="M7 8L0 1L1.4 0L7 5.6L12.6 0L14 1L7 8Z" fill="#6B7280"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="space-y-6">
            <!-- Maintenance & Availability Card -->
            <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-6 border border-[#C3C6D6]/10 border-l-4 border-l-[#C3C6D6]/10">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Maintenance & Availability</h2>
                        <p class="text-sm text-[#434654]">Control instance-wide uptime states.</p>
                    </div>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.6 0.2625 7.29583 0.7875 6.0875C1.3125 4.87917 2.025 3.825 2.925 2.925L4.325 4.325C3.59167 5.05833 3.02083 5.90833 2.6125 6.875C2.20417 7.84167 2 8.88333 2 10C2 12.2333 2.775 14.125 4.325 15.675C5.875 17.225 7.76667 18 10 18C12.2333 18 14.125 17.225 15.675 15.675C17.225 14.125 18 12.2333 18 10C18 8.88333 17.7958 7.84167 17.3875 6.875C16.9792 5.90833 16.4083 5.05833 15.675 4.325L17.075 2.925C17.975 3.825 18.6875 4.87917 19.2125 6.0875C19.7375 7.29583 20 8.6 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#003D9B"/>
                    </svg>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-[#0B1C30]">Maintenance Mode</span>
                        <svg width="48" height="24" viewBox="0 0 48 24" fill="none">
                            <rect width="48" height="24" rx="12" fill="#C3C6D6" fill-opacity="0.3"/>
                            <rect x="4" y="4" width="16" height="16" rx="8" fill="white"/>
                        </svg>
                    </div>

                    <div class="opacity-50">
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Custom Message</label>
                        <textarea rows="3" class="w-full bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg p-4 text-sm text-[#6B7280] resize-none">We are currently upgrading our core clusters. Expected downtime: 30 minutes.</textarea>
                    </div>
                </div>
            </div>

            <!-- Global Legal Links Card -->
            <div class="bg-white rounded-xl shadow-[0_8px_30px_rgba(11,28,48,0.04)] p-6 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Global Legal Links</h2>
                        <p class="text-sm text-[#434654]">Policy documentation requirements.</p>
                    </div>
                    <svg width="18" height="19" viewBox="0 0 18 19" fill="none">
                        <path d="M0 19V17H12V19H0ZM5.65 14.15L0 8.5L2.1 6.35L7.8 12L5.65 14.15ZM12 7.8L6.35 2.1L8.5 0L14.15 5.65L12 7.8ZM16.6 18L3.55 4.95L4.95 3.55L18 16.6L16.6 18Z" fill="#0052CC"/>
                    </svg>
                </div>

                <div class="space-y-4">
                    <!-- Terms of Service URL -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Terms of Service URL</label>
                        <div class="flex items-stretch bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg overflow-hidden">
                            <span class="bg-[#E5EEFF] px-4 py-3 text-sm text-[#434654] border-r border-[#C3C6D6]/20">https://</span>
                            <input type="text" value="baas-platform.com/legal/terms" class="flex-1 bg-transparent px-4 py-3 text-sm text-[#0B1C30] focus:outline-none">
                        </div>
                    </div>
                    <!-- Privacy Policy URL -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Privacy Policy URL</label>
                        <div class="flex items-stretch bg-[#F8F9FF] shadow-[0_8px_30px_rgba(11,28,48,0.04)] rounded-lg overflow-hidden">
                            <span class="bg-[#E5EEFF] px-4 py-3 text-sm text-[#434654] border-r border-[#C3C6D6]/20">https://</span>
                            <input type="text" value="baas-platform.com/legal/privacy" class="flex-1 bg-transparent px-4 py-3 text-sm text-[#0B1C30] focus:outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Changes Card -->
            <div class="bg-[#0052CC]/5 rounded-2xl p-6 border border-[#003D9B]/10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="text-sm text-[#003D9B]/70 max-w-xs">
                    Unsaved changes will be discarded if you navigate away.
                </div>
                <button class="px-8 py-3 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white font-bold rounded-lg shadow-[0_4px_6px_-4px_rgba(0,61,155,0.2),0_10px_15px_-3px_rgba(0,61,155,0.2)] hover:brightness-105 transition">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
