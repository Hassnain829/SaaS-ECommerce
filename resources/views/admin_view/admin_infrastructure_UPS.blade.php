@extends('layouts.admin.admin-Sidebar')

@section('title', 'UPS Configuration')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white/80 backdrop-blur-sm border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Centered search (hidden on very small, visible on sm and up) -->
    <div class="flex-1 flex justify-center">
        <div class="relative w-full max-w-md hidden sm:block">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15ZM5.41667 9.16667C6.45833 9.16667 7.34375 8.80208 8.07292 8.07292C8.80208 7.34375 9.16667 6.45833 9.16667 5.41667C9.16667 4.375 8.80208 3.48958 8.07292 2.76042C7.34375 2.03125 6.45833 1.66667 5.41667 1.66667C4.375 1.66667 3.48958 2.03125 2.76042 2.76042C2.03125 3.48958 1.66667 4.375 1.66667 5.41667C1.66667 6.45833 2.03125 7.34375 2.76042 8.07292C3.48958 8.80208 4.375 9.16667 5.41667 9.16667Z" fill="#434654"/>
                </svg>
            </div>
            <input type="text" placeholder="Search systems, logs or users..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/50 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>
    </div>

    <!-- Right icons -->
    <div class="flex items-center gap-3 shrink-0">
        <!-- Notification bell with red dot -->
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#434654"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#BA1A1A] border-2 border-white rounded-full"></span>
        </button>

        <!-- Vertical divider -->
        <div class="w-px h-8 bg-[#C3C6D6]/30 hidden sm:block"></div>

        <!-- User info and avatar -->
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-[#0B1C30]">Alex Thompson</div>
                <div class="text-[10px] text-[#434654]">Systems Architect</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#EFF4FF] overflow-hidden">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <circle cx="20" cy="15" r="6" fill="#94A3B8"/>
                    <path d="M32 30C32 25 28 23 20 23C12 23 8 25 8 30" fill="#94A3B8"/>
                </svg>
            </div>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter max-w-9l mx-auto space-y-6 md:space-y-8">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs">
        <a href="#" class="text-[#434654] hover:underline">Integrations</a>
        <svg width="5" height="7" viewBox="0 0 5 7" fill="none">
            <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#434654"/>
        </svg>
        <a href="#" class="text-[#434654] hover:underline">Courier Services</a>
        <svg width="5" height="7" viewBox="0 0 5 7" fill="none">
            <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#434654"/>
        </svg>
        <span class="text-[#0B1C30] font-medium">UPS Configuration</span>
    </div>

    <!-- Page Header with logo, title, status and action buttons -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-4">
            <!-- UPS Logo -->
            <div class="w-16 h-16 bg-white rounded-2xl shadow-sm border border-[#C3C6D6]/10 flex items-center justify-center">
                <svg width="38" height="38" viewBox="0 0 38 38" fill="none">
                    <rect width="38" height="38" fill="url(#pattern0)" />
                    <defs>
                        <pattern id="pattern0" patternContentUnits="objectBoundingBox" width="1" height="1">
                            <use xlink:href="#image0" transform="scale(0.0131579)" />
                        </pattern>
                        <image id="image0" width="76" height="76" xlink:href="data:image/jpeg;base64,/9j/2wBDAAgICAgJCAkKCgkNDgwODRMREBARExwUFhQWFBwrGx8bGx8bKyYuJSMlLiZENS8vNUROQj5CTl9VVV93cXecnNH/2wBDAQgICAgJCAkKCgkNDgwODRMREBARExwUFhQWFBwrGx8bGx8bKyYuJSMlLiZENS8vNUROQj5CTl9VVV93cXecnNH/wgARCABMAEwDASIAAhEBAxEB/8QAGwABAQADAQEBAAAAAAAAAAAAAAEFBgcIBAL/xAAZAQEBAQEBAQAAAAAAAAAAAAAAAwQCAQX/2gAMAwEAAhADEAAAAOMly6YoiiAAqD30Xn/XstNXw+767mrq+NzWE3BsK8UH7dY5Ht+DTuvzYLW8Vv31zMYf6uRLLzoH1fK89zfnDpdXzZbhKCCoKgqCwP/EAC0QAAICAQMBBwQCAwEAAAAAAAECAwQFBhESExQhIjE1UQcgMEBQUSMkQmFx/9oACAEBAAE/AKqldbCucoxnJ8oxk+E2VbQ0GySje7a5P2V2cU/0y90p6VW62m4pF2h0VT4hdZL8EdM0i1cV6pXN/LnwZPTNpU+q6nL5xT6YyZbDQMWeNSusXw3z/AJLN05tNSr01RoivGMfFh+lWaZU51z1G+ucX4+2Uj1i7XbI66pWZEXzxGfT/AAY+nb3ol1V1qXj5ThMp0Pc0uHqEYuP5OS4/2d6yNn7nT5ptja/FuM+H/wAZTqmMqcuGRlyb5/H7ZqO36s/Md99s12cVHw6fCPl/6dtZbqrW2pR7HtrY9y5gvLw+P2J+Tmj6RZon2NstRuXbSXMQAAAAOCHHjgpx2z/xAAkEQABAwIFBQEAAAAAAAAAAAABAAIRAxITICExQRAiUWFxkf/aAAgBAgEBPwC+0SdFd7KcEaogI+lYdysB3lNpEHUqoY9IgRg0puA7wHycgjZGhBgl3CNJh3VtqMO0It4OqtDvKp06bqep5+LEQMyfSPzP/xAAlEQACAgECBAcAAAAAAAAAAAABAgADERIhEBMgMRAyQEFSYYH/2gAIAQMBAT8A3MpYh1E7CNhmyD0VZtQuZiVnUJq1LFOZRZw0nPp1rVt4qi+1Y1Ksg5I8Lqy67R6WpI2MOzCDcQeE5TfaKDjef//Z" />
                    </defs>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl md:text-3xl font-medium text-[#0B1C30] font-poppins">UPS Global Integration</h1>
                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-[#4EDEA3]/20 rounded-full text-[#004E33] text-[10px] font-bold uppercase">
                        <span class="w-2 h-2 bg-[#006846] rounded-full"></span>
                        Connected
                    </span>
                </div>
                <p class="text-sm text-[#434654]">Configure delivery rules, service levels, and API authentication for UPS.</p>
            </div>
        </div>
        <!-- Action buttons -->
        <div class="flex items-center gap-3">
            <button class="px-5 py-2.5 bg-white border border-[#C3C6D6]/20 rounded-xl text-[#BA1A1A] text-sm font-bold hover:bg-gray-50 transition">Disconnect</button>
            <button class="px-6 py-2.5 bg-gradient-to-b from-[#003D9B] to-[#0052CC] text-white text-sm font-bold rounded-xl shadow-[0_4px_6px_-4px_rgba(0,61,155,0.2),0_10px_15px_-3px_rgba(0,61,155,0.2)] hover:brightness-105 transition">Save Changes</button>
        </div>
    </div>

    <!-- Main configuration grid: two columns -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- LEFT COLUMN -->
        <div class="lg:col-span-3 space-y-6">
            <!-- API Credentials Card -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border border-[#C3C6D6]/10 p-6 relative">
                <!-- left accent line -->
                <div class="absolute left-0 top-1 bottom-1 w-1 bg-[#003D9B] rounded-l"></div>
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-2">
                        <svg width="22" height="12" viewBox="0 0 22 12" fill="none">
                            <path d="M6 12C4.33333 12 2.91667 11.4167 1.75 10.25C0.583333 9.08333 0 7.66667 0 6C0 4.33333 0.583333 2.91667 1.75 1.75C2.91667 0.583333 4.33333 0 6 0C7.1 0 8.10833 0.275 9.025 0.825C9.94167 1.375 10.6667 2.1 11.2 3H22V9H20V12H14V9H11.2C10.6667 9.9 9.94167 10.625 9.025 11.175C8.10833 11.725 7.1 12 6 12ZM6 10C7.1 10 7.98333 9.6625 8.65 8.9875C9.31667 8.3125 9.71667 7.65 9.85 7H16V10H18V7H20V5H9.85C9.71667 4.35 9.31667 3.6875 8.65 3.0125C7.98333 2.3375 7.1 2 6 2C4.9 2 3.95833 2.39167 3.175 3.175C2.39167 3.95833 2 4.9 2 6C2 7.1 2.39167 8.04167 3.175 8.825C3.95833 9.60833 4.9 10 6 10ZM6 8C6.55 8 7.02083 7.80417 7.4125 7.4125C7.80417 7.02083 8 6.55 8 6C8 5.45 7.80417 4.97917 7.4125 4.5875C7.02083 4.19583 6.55 4 6 4C5.45 4 4.97917 4.19583 4.5875 4.5875C4.19583 4.97917 4 5.45 4 6C4 6.55 4.19583 7.02083 4.5875 7.4125C4.97917 7.80417 5.45 8 6 8Z" fill="#003D9B"/>
                        </svg>
                        <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">API Credentials</h2>
                    </div>
                    <a href="#" class="flex items-center gap-1 text-[#003D9B] text-xs font-bold">
                        <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                            <path d="M1.16667 10.5C0.845833 10.5 0.571181 10.3858 0.342708 10.1573C0.114236 9.92882 0 9.65417 0 9.33333V1.16667C0 0.845833 0.114236 0.571181 0.342708 0.342708C0.571181 0.114236 0.845833 0 1.16667 0H5.25V1.16667H1.16667V9.33333H9.33333V5.25H10.5V9.33333C10.5 9.65417 10.3858 9.92882 10.1573 10.1573C9.92882 10.3858 9.65417 10.5 9.33333 10.5H1.16667ZM3.90833 7.40833L3.09167 6.59167L8.51667 1.16667H6.41667V0H10.5V4.08333H9.33333V1.98333L3.90833 7.40833Z" fill="#003D9B"/>
                        </svg>
                        UPS Developer Portal
                    </a>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">API Key</label>
                        <div class="bg-[#EFF4FF] rounded-xl px-4 py-3 text-sm font-medium text-[#0B1C30]">prod_4920kdj302_as82</div>
                    </div>
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Access License Number</label>
                        <div class="bg-[#EFF4FF] rounded-xl px-4 py-3 text-sm font-medium text-[#0B1C30]">ALN-88392-XXP</div>
                    </div>
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">User ID</label>
                        <div class="bg-[#EFF4FF] rounded-xl px-4 py-3 text-sm font-medium text-[#0B1C30]">ups_admin_main</div>
                    </div>
                    <div class="relative">
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Password</label>
                        <div class="bg-[#EFF4FF] rounded-xl px-4 py-3 text-sm font-medium text-[#0B1C30] flex justify-between items-center">
                            <span>â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢</span>
                            <svg width="17" height="12" viewBox="0 0 17 12" fill="none" class="cursor-pointer">
                                <path d="M8.25 9C9.1875 9 9.98438 8.67188 10.6406 8.01562C11.2969 7.35938 11.625 6.5625 11.625 5.625C11.625 4.6875 11.2969 3.89062 10.6406 3.23438C9.98438 2.57812 9.1875 2.25 8.25 2.25C7.3125 2.25 6.51562 2.57812 5.85938 3.23438C5.20312 3.89062 4.875 4.6875 4.875 5.625C4.875 6.5625 5.20312 7.35938 5.85938 8.01562C6.51562 8.67188 7.3125 9 8.25 9ZM8.25 7.65C7.6875 7.65 7.20938 7.45312 6.81563 7.05937C6.42188 6.66562 6.225 6.1875 6.225 5.625C6.225 5.0625 6.42188 4.58438 6.81563 4.19063C7.20938 3.79688 7.6875 3.6 8.25 3.6C8.8125 3.6 9.29062 3.79688 9.68437 4.19063C10.0781 4.58438 10.275 5.0625 10.275 5.625C10.275 6.1875 10.0781 6.66562 9.68437 7.05937C9.29062 7.45312 8.8125 7.65 8.25 7.65ZM8.25 11.25C6.425 11.25 4.7625 10.7406 3.2625 9.72188C1.7625 8.70312 0.675 7.3375 0 5.625C0.675 3.9125 1.7625 2.54688 3.2625 1.52813C4.7625 0.509375 6.425 0 8.25 0C10.075 0 11.7375 0.509375 13.2375 1.52813C14.7375 2.54688 15.825 3.9125 16.5 5.625C15.825 7.3375 14.7375 8.70312 13.2375 9.72188C11.7375 10.7406 10.075 11.25 8.25 11.25ZM8.25 9.75C9.6625 9.75 10.9594 9.37812 12.1406 8.63437C13.3219 7.89062 14.225 6.8875 14.85 5.625C14.225 4.3625 13.3219 3.35938 12.1406 2.61562C10.9594 1.87187 9.6625 1.5 8.25 1.5C6.8375 1.5 5.54063 1.87187 4.35938 2.61562C3.17812 3.35938 2.275 4.3625 1.65 5.625C2.275 6.8875 3.17812 7.89062 4.35938 8.63437C5.54063 9.37812 6.8375 9.75 8.25 9.75Z" fill="#434654"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button class="flex items-center gap-2 px-5 py-2 bg-[#DCE9FF] text-[#003D9B] text-sm font-bold rounded-lg">
                        <svg width="15" height="14" viewBox="0 0 15 14" fill="none">
                            <path d="M3.75 13.5L0 9.75L3.75 6L4.81875 7.05L2.86875 9H14.25V10.5H2.86875L4.81875 12.45L3.75 13.5ZM11.25 7.5L10.1812 6.45L12.1313 4.5H0.75V3H12.1313L10.1812 1.05L11.25 0L15 3.75L11.25 7.5Z" fill="#003D9B"/>
                        </svg>
                        Test Connection
                    </button>
                </div>
            </div>

            <!-- Shipping Logic & Automation Card -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border border-[#C3C6D6]/10 p-6 relative">
                <div class="absolute left-0 top-1 bottom-1 w-1 bg-[#0052CC] rounded-l"></div>
                <div class="flex items-center gap-2 mb-6">
                    <svg width="21" height="21" viewBox="0 0 21 21" fill="none">
                        <path d="M18 6L17.05 3.95L15 3L17.05 2.05L18 0L18.95 2.05L21 3L18.95 3.95L18 6ZM6.5 6L5.55 3.95L3.5 3L5.55 2.05L6.5 0L7.45 2.05L9.5 3L7.45 3.95L6.5 6ZM18 17.5L17.05 15.45L15 14.5L17.05 13.55L18 11.5L18.95 13.55L21 14.5L18.95 15.45L18 17.5ZM3.1 20.7L0.3 17.9C0.1 17.7 0 17.4583 0 17.175C0 16.8917 0.1 16.65 0.3 16.45L11.45 5.3C11.65 5.1 11.8917 5 12.175 5C12.4583 5 12.7 5.1 12.9 5.3L15.7 8.1C15.9 8.3 16 8.54167 16 8.825C16 9.10833 15.9 9.35 15.7 9.55L4.55 20.7C4.35 20.9 4.10833 21 3.825 21C3.54167 21 3.3 20.9 3.1 20.7ZM3.85 18.6L11 11.4L9.6 10L2.4 17.15L3.85 18.6Z" fill="#003D9B"/>
                    </svg>
                    <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Shipping Logic & Automation</h2>
                </div>
                <div class="space-y-3">
                    <!-- Rule 1 -->
                    <div class="bg-[#EFF4FF] rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white shadow-sm rounded-lg flex items-center justify-center">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95V16C8.45 16 7.97917 15.8042 7.5875 15.4125C7.19583 15.0208 7 14.55 7 14V13L2.2 8.2C2.15 8.5 2.10417 8.8 2.0625 9.1C2.02083 9.4 2 9.7 2 10C2 12.0167 2.6625 13.7833 3.9875 15.3C5.3125 16.8167 6.98333 17.7 9 17.95ZM15.9 15.4C16.5833 14.65 17.1042 13.8125 17.4625 12.8875C17.8208 11.9625 18 11 18 10C18 8.36667 17.5458 6.875 16.6375 5.525C15.7292 4.175 14.5167 3.2 13 2.6V3C13 3.55 12.8042 4.02083 12.4125 4.4125C12.0208 4.80417 11.55 5 11 5H9V7C9 7.28333 8.90417 7.52083 8.7125 7.7125C8.52083 7.90417 8.28333 8 8 8H6V10H12C12.2833 10 12.5208 10.0958 12.7125 10.2875C12.9042 10.4792 13 10.7167 13 11V14H14C14.4333 14 14.825 14.1292 15.175 14.3875C15.525 14.6458 15.7667 14.9833 15.9 15.4Z" fill="#003D9B"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-[#0B1C30]">Default for North America</div>
                                <div class="text-xs text-[#434654]">Prioritize UPS for all domestic US and Canada shipments.</div>
                            </div>
                        </div>
                        <svg width="44" height="24" viewBox="0 0 44 24" fill="none">
                            <rect width="44" height="24" rx="12" fill="#003D9B"/>
                            <rect x="22.5" y="2.5" width="19" height="19" rx="9.5" fill="white" stroke="white"/>
                        </svg>
                    </div>
                    <!-- Rule 2 -->
                    <div class="bg-[#EFF4FF] rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-white shadow-sm rounded-lg flex items-center justify-center">
                                <svg width="16" height="18" viewBox="0 0 16 18" fill="none">
                                    <path d="M1.99693 16H13.9969L12.5719 6H3.42193L1.99693 16ZM7.99693 4C8.28026 4 8.51776 3.90417 8.70943 3.7125C8.9011 3.52083 8.99693 3.28333 8.99693 3C8.99693 2.71667 8.9011 2.47917 8.70943 2.2875C8.51776 2.09583 8.28026 2 7.99693 2C7.7136 2 7.4761 2.09583 7.28443 2.2875C7.09276 2.47917 6.99693 2.71667 6.99693 3C6.99693 3.28333 7.09276 3.52083 7.28443 3.7125C7.4761 3.90417 7.7136 4 7.99693 4ZM10.8219 4H12.5719C13.0719 4 13.5053 4.16667 13.8719 4.5C14.2386 4.83333 14.4636 5.24167 14.5469 5.725L15.9719 15.725C16.0553 16.325 15.9011 16.8542 15.5094 17.3125C15.1178 17.7708 14.6136 18 13.9969 18H1.99693C1.38026 18 0.876096 17.7708 0.48443 17.3125C0.0927632 16.8542 -0.0614035 16.325 0.0219298 15.725L1.44693 5.725C1.53026 5.24167 1.75526 4.83333 2.12193 4.5C2.4886 4.16667 2.92193 4 3.42193 4H5.17193C5.12193 3.83333 5.08026 3.67083 5.04693 3.5125C5.0136 3.35417 4.99693 3.18333 4.99693 3C4.99693 2.16667 5.2886 1.45833 5.87193 0.875C6.45526 0.291667 7.1636 0 7.99693 0C8.83026 0 9.5386 0.291667 10.1219 0.875C10.7053 1.45833 10.9969 2.16667 10.9969 3C10.9969 3.18333 10.9803 3.35417 10.9469 3.5125C10.9136 3.67083 10.8719 3.83333 10.8219 4ZM1.99693 16H13.9969H1.99693Z" fill="#003D9B"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-[#0B1C30]">Max Weight Threshold</div>
                                <div class="text-xs text-[#434654]">Switch to LTL carriers for orders exceeding 150 lbs.</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="px-3 py-1 bg-white border border-[#C3C6D6]/20 rounded-lg text-sm font-bold text-[#0B1C30]">150 lbs</span>
                            <svg width="44" height="24" viewBox="0 0 44 24" fill="none">
                                <rect width="44" height="24" rx="12" fill="#003D9B"/>
                                <rect x="22.5" y="2.5" width="19" height="19" rx="9.5" fill="white" stroke="white"/>
                            </svg>
                        </div>
                    </div>
                    <!-- Add Rule Button -->
                    <button class="w-full py-4 border-2 border-dashed border-[#C3C6D6]/30 rounded-xl flex items-center justify-center gap-2 text-[#434654] font-bold text-sm hover:bg-gray-50">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M9 15H11V11H15V9H11V5H9V9H5V11H9V15ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#434654"/>
                        </svg>
                        Add Automation Rule
                    </button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Supported Services Card -->
            <div class="bg-[#EFF4FF] rounded-2xl shadow-sm border border-[#C3C6D6]/10 p-6">
                <h3 class="text-sm font-medium uppercase tracking-wider text-[#0B1C30] pb-4 border-b border-[#C3C6D6]/20 mb-4 font-poppins">Supported Services</h3>
                <div class="space-y-3">
                    <!-- Checked items -->
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS Ground</span>
                        <span class="w-5 h-5 bg-[#003D9B] rounded flex items-center justify-center">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M9.2585 2.99147C9.7465 3.4796 9.7465 4.27085 9.2585 4.75897L4.0085 10.009C3.5204 10.4969 2.72915 10.4969 2.24103 10.009L0.741025 8.509C0.267309 8.0185 0.274092 7.23886 0.756249 6.7567C1.23842 6.27453 2.01805 6.26776 2.50853 6.74147L3.62478 7.85772L7.491 3.99147C7.9792 3.50349 8.7704 3.50349 9.2585 2.99147Z" fill="white"/>
                            </svg>
                        </span>
                    </div>
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS Next Day Air</span>
                        <span class="w-5 h-5 bg-[#003D9B] rounded flex items-center justify-center">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M9.2585 2.99147C9.7465 3.4796 9.7465 4.27085 9.2585 4.75897L4.0085 10.009C3.5204 10.4969 2.72915 10.4969 2.24103 10.009L0.741025 8.509C0.267309 8.0185 0.274092 7.23886 0.756249 6.7567C1.23842 6.27453 2.01805 6.26776 2.50853 6.74147L3.62478 7.85772L7.491 3.99147C7.9792 3.50349 8.7704 3.50349 9.2585 2.99147Z" fill="white"/>
                            </svg>
                        </span>
                    </div>
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS 2nd Day Air</span>
                        <span class="w-5 h-5 bg-[#003D9B] rounded flex items-center justify-center">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M9.2585 2.99147C9.7465 3.4796 9.7465 4.27085 9.2585 4.75897L4.0085 10.009C3.5204 10.4969 2.72915 10.4969 2.24103 10.009L0.741025 8.509C0.267309 8.0185 0.274092 7.23886 0.756249 6.7567C1.23842 6.27453 2.01805 6.26776 2.50853 6.74147L3.62478 7.85772L7.491 3.99147C7.9792 3.50349 8.7704 3.50349 9.2585 2.99147Z" fill="white"/>
                            </svg>
                        </span>
                    </div>
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS Worldwide Express</span>
                        <span class="w-5 h-5 bg-[#003D9B] rounded flex items-center justify-center">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M9.2585 2.99147C9.7465 3.4796 9.7465 4.27085 9.2585 4.75897L4.0085 10.009C3.5204 10.4969 2.72915 10.4969 2.24103 10.009L0.741025 8.509C0.267309 8.0185 0.274092 7.23886 0.756249 6.7567C1.23842 6.27453 2.01805 6.26776 2.50853 6.74147L3.62478 7.85772L7.491 3.99147C7.9792 3.50349 8.7704 3.50349 9.2585 2.99147Z" fill="white"/>
                            </svg>
                        </span>
                    </div>
                    <!-- Unchecked items -->
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS Standard</span>
                        <span class="w-5 h-5 border border-[#C3C6D6] rounded bg-white"></span>
                    </div>
                    <div class="flex items-center justify-between bg-white rounded-xl px-4 py-3">
                        <span class="font-medium text-[#0B1C30]">UPS Worldwide Saver</span>
                        <span class="w-5 h-5 border border-[#C3C6D6] rounded bg-white"></span>
                    </div>
                </div>
            </div>

            <!-- Webhooks Card -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] border border-[#C3C6D6]/10 p-6">
                <div class="flex items-center gap-2 mb-4">
                    <svg width="17" height="16" viewBox="0 0 17 16" fill="none">
                        <path d="M4.16667 15.8333C3.01389 15.8333 2.03125 15.4271 1.21875 14.6146C0.40625 13.8021 0 12.8194 0 11.6667C0 10.6528 0.315972 9.76736 0.947917 9.01042C1.57986 8.25347 2.375 7.77778 3.33333 7.58333V9.3125C2.84722 9.47917 2.44792 9.77778 2.13542 10.2083C1.82292 10.6389 1.66667 11.125 1.66667 11.6667C1.66667 12.3611 1.90972 12.9514 2.39583 13.4375C2.88194 13.9236 3.47222 14.1667 4.16667 14.1667C4.86111 14.1667 5.45139 13.9236 5.9375 13.4375C6.42361 12.9514 6.66667 12.3611 6.66667 11.6667V10.8333H11.5625C11.6736 10.7083 11.809 10.6076 11.9688 10.5312C12.1285 10.4549 12.3056 10.4167 12.5 10.4167C12.8472 10.4167 13.1424 10.5382 13.3854 10.7812C13.6285 11.0243 13.75 11.3194 13.75 11.6667C13.75 12.0139 13.6285 12.309 13.3854 12.5521C13.1424 12.7951 12.8472 12.9167 12.5 12.9167C12.3056 12.9167 12.1285 12.8785 11.9688 12.8021C11.809 12.7257 11.6736 12.625 11.5625 12.5H8.25C8.05556 13.4583 7.57986 14.2535 6.82292 14.8854C6.06597 15.5174 5.18056 15.8333 4.16667 15.8333ZM12.5 15.8333C11.7222 15.8333 11.0174 15.6424 10.3854 15.2604C9.75347 14.8785 9.25694 14.375 8.89583 13.75H11.125C11.3194 13.8889 11.5347 13.9931 11.7708 14.0625C12.0069 14.1319 12.25 14.1667 12.5 14.1667C13.1944 14.1667 13.7847 13.9236 14.2708 13.4375C14.7569 12.9514 15 12.3611 15 11.6667C15 10.9722 14.7569 10.3819 14.2708 9.89583C13.7847 9.40972 13.1944 9.16667 12.5 9.16667C12.2222 9.16667 11.9653 9.20486 11.7292 9.28125C11.4931 9.35764 11.2708 9.47222 11.0625 9.625L8.52083 5.39583C8.22917 5.34028 7.98611 5.20139 7.79167 4.97917C7.59722 4.75694 7.5 4.48611 7.5 4.16667C7.5 3.81944 7.62153 3.52431 7.86458 3.28125C8.10764 3.03819 8.40278 2.91667 8.75 2.91667C9.09722 2.91667 9.39236 3.03819 9.63542 3.28125C9.87847 3.52431 10 3.81944 10 4.16667C10 4.23611 10 4.29514 10 4.34375C10 4.39236 9.98611 4.45139 9.95833 4.52083L11.7708 7.5625C11.8819 7.53472 12 7.51736 12.125 7.51042C12.25 7.50347 12.375 7.5 12.5 7.5C13.6528 7.5 14.6354 7.90625 15.4479 8.71875C16.2604 9.53125 16.6667 10.5139 16.6667 11.6667C16.6667 12.8194 16.2604 13.8021 15.4479 14.6146C14.6354 15.4271 13.6528 15.8333 12.5 15.8333ZM4.16667 12.9167C3.81944 12.9167 3.52431 12.7951 3.28125 12.5521C3.03819 12.309 2.91667 12.0139 2.91667 11.6667C2.91667 11.3611 3.01389 11.0972 3.20833 10.875C3.40278 10.6528 3.63889 10.5069 3.91667 10.4375L5.875 7.1875C5.47222 6.8125 5.15625 6.36458 4.92708 5.84375C4.69792 5.32292 4.58333 4.76389 4.58333 4.16667C4.58333 3.01389 4.98958 2.03125 5.80208 1.21875C6.61458 0.40625 7.59722 0 8.75 0C9.90278 0 10.8854 0.40625 11.6979 1.21875C12.5104 2.03125 12.9167 3.01389 12.9167 4.16667H11.25C11.25 3.47222 11.0069 2.88194 10.5208 2.39583C10.0347 1.90972 9.44444 1.66667 8.75 1.66667C8.05556 1.66667 7.46528 1.90972 6.97917 2.39583C6.49306 2.88194 6.25 3.47222 6.25 4.16667C6.25 4.76389 6.43056 5.28819 6.79167 5.73958C7.15278 6.19097 7.61111 6.47917 8.16667 6.60417L5.35417 11.2917C5.38194 11.3611 5.39931 11.4236 5.40625 11.4792C5.41319 11.5347 5.41667 11.5972 5.41667 11.6667C5.41667 12.0139 5.29514 12.309 5.05208 12.5521C4.80903 12.7951 4.51389 12.9167 4.16667 12.9167Z" fill="#003D9B"/>
                    </svg>
                    <h3 class="text-sm font-medium text-[#0B1C30] font-poppins">Webhooks</h3>
                </div>
                <p class="text-xs text-[#434654] mb-4">Configure where UPS will push tracking and status updates for active shipments.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[#434654] text-[10px] font-bold uppercase mb-1">Webhook URL</label>
                        <div class="bg-[#EFF4FF] rounded-lg px-3 py-2 text-xs text-[#0B1C30]">https://api.platform.com/v1/hooks/ups</div>
                    </div>
                    <div>
                        <label class="block text-[#434654] text-[10px] font-bold uppercase mb-1">Secret Key</label>
                        <div class="flex gap-2">
                            <div class="flex-1 bg-[#EFF4FF] rounded-lg px-3 py-2 text-xs text-[#0B1C30]">sk_test_51MzZk</div>
                            <button class="p-2 bg-[#DCE9FF] rounded-lg">
                                <svg width="13" height="15" viewBox="0 0 13 15" fill="none">
                                    <path d="M4.5 12C4.0875 12 3.73438 11.8531 3.44062 11.5594C3.14687 11.2656 3 10.9125 3 10.5V1.5C3 1.0875 3.14687 0.734375 3.44062 0.440625C3.73438 0.146875 4.0875 0 4.5 0H11.25C11.6625 0 12.0156 0.146875 12.3094 0.440625C12.6031 0.734375 12.75 1.0875 12.75 1.5V10.5C12.75 10.9125 12.6031 11.2656 12.3094 11.5594C12.0156 11.8531 11.6625 12 11.25 12H4.5ZM4.5 10.5H11.25V1.5H4.5V10.5ZM1.5 15C1.0875 15 0.734375 14.8531 0.440625 14.5594C0.146875 14.2656 0 13.9125 0 13.5V3H1.5V13.5H9.75V15H1.5Z" fill="#003D9B"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Verified status -->
                    <div class="bg-[#4EDEA3]/5 border border-[#4EDEA3]/20 rounded-lg p-3">
                        <div class="flex items-center gap-2">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                <path d="M4.43333 12.25L3.325 10.3833L1.225 9.91667L1.42917 7.75833L0 6.125L1.42917 4.49167L1.225 2.33333L3.325 1.86667L4.43333 0L6.41667 0.845833L8.4 0L9.50833 1.86667L11.6083 2.33333L11.4042 4.49167L12.8333 6.125L11.4042 7.75833L11.6083 9.91667L9.50833 10.3833L8.4 12.25L6.41667 11.4042L4.43333 12.25ZM4.92917 10.7625L6.41667 10.1208L7.93333 10.7625L8.75 9.3625L10.3542 8.98333L10.2083 7.35L11.2875 6.125L10.2083 4.87083L10.3542 3.2375L8.75 2.8875L7.90417 1.4875L6.41667 2.12917L4.9 1.4875L4.08333 2.8875L2.47917 3.2375L2.625 4.87083L1.54583 6.125L2.625 7.35L2.47917 9.0125L4.08333 9.3625L4.92917 10.7625ZM5.80417 8.19583L9.1 4.9L8.28333 4.05417L5.80417 6.53333L4.55 5.30833L3.73333 6.125L5.80417 8.19583Z" fill="#006846"/>
                            </svg>
                            <span class="text-[#006846] text-[10px] font-bold uppercase">Verified & Active</span>
                        </div>
                        <p class="text-[#004E33] text-[10px] mt-1">System receiving 1,240 events/min via this endpoint.</p>
                    </div>
                </div>
            </div>

            <!-- Need Assistance Card -->
            <div class="bg-[#003D9B] rounded-2xl p-6 text-white relative overflow-hidden">
                <div class="absolute w-24 h-24 bg-white/10 rounded-full -right-4 -top-4 blur-2xl"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-2">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M9.95 16C10.3 16 10.5958 15.8792 10.8375 15.6375C11.0792 15.3958 11.2 15.1 11.2 14.75C11.2 14.4 11.0792 14.1042 10.8375 13.8625C10.5958 13.6208 10.3 13.5 9.95 13.5C9.6 13.5 9.30417 13.6208 9.0625 13.8625C8.82083 14.1042 8.7 14.4 8.7 14.75C8.7 15.1 8.82083 15.3958 9.0625 15.6375C9.30417 15.8792 9.6 16 9.95 16ZM9.05 12.15H10.9C10.9 11.6 10.9625 11.1667 11.0875 10.85C11.2125 10.5333 11.5667 10.1 12.15 9.55C12.5833 9.11667 12.925 8.70417 13.175 8.3125C13.425 7.92083 13.55 7.45 13.55 6.9C13.55 5.96667 13.2083 5.25 12.525 4.75C11.8417 4.25 11.0333 4 10.1 4C9.15 4 8.37917 4.25 7.7875 4.75C7.19583 5.25 6.78333 5.85 6.55 6.55L8.2 7.2C8.28333 6.9 8.47083 6.575 8.7625 6.225C9.05417 5.875 9.5 5.7 10.1 5.7C10.6333 5.7 11.0333 5.84583 11.3 6.1375C11.5667 6.42917 11.7 6.75 11.7 7.1C11.7 7.43333 11.6 7.74583 11.4 8.0375C11.2 8.32917 10.95 8.6 10.65 8.85C9.91667 9.5 9.46667 9.99167 9.3 10.325C9.13333 10.6583 9.05 11.2667 9.05 12.15ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="white"/>
                        </svg>
                        <h3 class="text-base font-medium font-poppins">Need Assistance?</h3>
                    </div>
                    <p class="text-sm text-white/80 mb-4">Our logistics engineering team can help with custom UPS routing and rate calculations.</p>
                    <a href="#" class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 rounded-lg text-white text-xs font-bold hover:bg-white/20 transition">
                        Contact Support
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                            <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="white"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer meta -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-6 border-t border-[#C3C6D6]/20 text-xs text-[#434654]/60">
        <div>Last modified by user_admin_02 on Oct 24, 2023 at 14:22 PM</div>
        <div class="flex gap-4">
            <div class="flex items-center gap-1">
                <svg width="10" height="13" viewBox="0 0 10 13" fill="none">
                    <path d="M1.16667 12.25C0.845833 12.25 0.571181 12.1358 0.342708 11.9073C0.114236 11.6788 0 11.4042 0 11.0833V5.25C0 4.92917 0.114236 4.65451 0.342708 4.42604C0.571181 4.19757 0.845833 4.08333 1.16667 4.08333H1.75V2.91667C1.75 2.10972 2.03438 1.42188 2.60313 0.853125C3.17188 0.284375 3.85972 0 4.66667 0C5.47361 0 6.16146 0.284375 6.73021 0.853125C7.29896 1.42188 7.58333 2.10972 7.58333 2.91667V4.08333H8.16667C8.4875 4.08333 8.76215 4.19757 8.99063 4.42604C9.2191 4.65451 9.33333 4.92917 9.33333 5.25V11.0833C9.33333 11.4042 9.2191 11.6788 8.99063 11.9073C8.76215 12.1358 8.4875 12.25 8.16667 12.25H1.16667Z" fill="#434654" fill-opacity="0.6"/>
                </svg>
                ISO 27001 Compliant
            </div>
            <div class="flex items-center gap-1">
                <svg width="13" height="10" viewBox="0 0 13 10" fill="none">
                    <path d="M5.45417 7.58333L8.75 4.2875L7.90417 3.44167L5.43958 5.90625L4.21458 4.68125L3.38333 5.5125L5.45417 7.58333ZM3.20833 9.33333C2.32361 9.33333 1.56771 9.02708 0.940625 8.41458C0.313542 7.80208 0 7.05347 0 6.16875C0 5.41042 0.228472 4.73472 0.685417 4.14167C1.14236 3.54861 1.74028 3.16944 2.47917 3.00417C2.72222 2.10972 3.20833 1.38542 3.9375 0.83125C4.66667 0.277083 5.49306 0 6.41667 0C7.55417 0 8.5191 0.396181 9.31146 1.18854C10.1038 1.9809 10.5 2.94583 10.5 4.08333C11.1708 4.16111 11.7274 4.45035 12.1698 4.95104C12.6122 5.45174 12.8333 6.0375 12.8333 6.70833C12.8333 7.4375 12.5781 8.05729 12.0677 8.56771C11.5573 9.07812 10.9375 9.33333 10.2083 9.33333H3.20833Z" fill="#434654" fill-opacity="0.6"/>
                </svg>
                Enterprise BaaS Core v4.2.0
            </div>
        </div>
    </div>
</div>
@endsection
