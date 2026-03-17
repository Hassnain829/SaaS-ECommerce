@extends('layouts.admin.admin-Sidebar')

@section('title', 'User Directory')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white/80 backdrop-blur-sm border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <!-- Mobile sidebar toggle (already in layout, but we keep it for consistency) -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Centered search (hidden on very small, visible on sm and up) -->
    <div class="flex-1 flex justify-center">
        <div class="relative w-full max-w-md hidden sm:block">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M12.45 13.5L7.725 8.775C7.35 9.075 6.91875 9.3125 6.43125 9.4875C5.94375 9.6625 5.425 9.75 4.875 9.75C3.5125 9.75 2.35938 9.27813 1.41562 8.33438C0.471875 7.39063 0 6.2375 0 4.875C0 3.5125 0.471875 2.35938 1.41562 1.41562C2.35938 0.471875 3.5125 0 4.875 0C6.2375 0 7.39063 0.471875 8.33438 1.41562C9.27813 2.35938 9.75 3.5125 9.75 4.875C9.75 5.425 9.6625 5.94375 9.4875 6.43125C9.3125 6.91875 9.075 7.35 8.775 7.725L13.5 12.45L12.45 13.5ZM4.875 8.25C5.8125 8.25 6.60938 7.92188 7.26562 7.26562C7.92188 6.60938 8.25 5.8125 8.25 4.875C8.25 3.9375 7.92188 3.14062 7.26562 2.48438C6.60938 1.82812 5.8125 1.5 4.875 1.5C3.9375 1.5 3.14062 1.82812 2.48438 2.48438C1.82812 3.14062 1.5 3.9375 1.5 4.875C1.5 5.8125 1.82812 6.60938 2.48438 7.26562C3.14062 7.92188 3.9375 8.25 4.875 8.25Z" fill="#434654"/>
                </svg>
            </div>
            <input type="text" placeholder="Search across platform..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/50 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
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

        <!-- New Project button -->
        <button class="hidden sm:flex items-center gap-2 px-4 py-2 bg-[#003D9B] text-white text-sm font-medium rounded-lg shadow-sm hover:bg-[#002b6e] transition-colors">
            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                <path d="M4.5 6H0V4.5H4.5V0H6V4.5H10.5V6H6V10.5H4.5V6Z" fill="white"/>
            </svg>
            <span>New Project</span>
        </button>

        <!-- Profile placeholder -->
        <div class="w-8 h-8 rounded-full border border-[#C3C6D6]/30 overflow-hidden">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                <circle cx="16" cy="12" r="5" fill="#94A3B8"/>
                <path d="M26 26C26 22 22 20 16 20C10 20 6 22 6 26" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="font-inter max-w-9xl mx-auto space-y-6 md:space-y-8">
    <!-- Breadcrumb + title + subtitle + actions -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-xs uppercase tracking-wider mb-1">
                <span class="text-[#434654]">Management</span>
                <svg width="4" height="6" viewBox="0 0 4 6" fill="none">
                    <path d="M2.3 3L0 0.7L0.7 0L3.7 3L0.7 6L0 5.3L2.3 3Z" fill="#434654"/>
                </svg>
                <span class="text-[#003D9B] font-bold">Directories</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-medium text-[#0B1C30] font-poppins">User Directory</h1>
            <p class="text-sm md:text-base text-[#434654] mt-1">Manage global user access, permissions, and security protocols.</p>
        </div>
        <!-- Action buttons -->
        <div class="flex items-center gap-3">
            <button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-[#C3C6D6]/20 rounded-lg text-[#434654] text-sm font-medium shadow-sm hover:bg-gray-50 transition">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M6 9L2.25 5.25L3.3 4.1625L5.25 6.1125V0H6.75V6.1125L8.7 4.1625L9.75 5.25L6 9ZM1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5V8.25H1.5V10.5H10.5V8.25H12V10.5C12 10.9125 11.8531 11.2656 11.5594 11.5594C11.2656 11.8531 10.9125 12 10.5 12H1.5Z" fill="#434654"/>
                </svg>
                <span>Export CSV</span>
            </button>
            <button class="flex items-center gap-2 px-5 py-2.5 bg-[#003D9B] text-white text-sm font-medium rounded-lg shadow-[0_2px_4px_-2px_rgba(0,61,155,0.2),0_4px_6px_-1px_rgba(0,61,155,0.2)] hover:bg-[#002b6e] transition">
                <svg width="17" height="12" viewBox="0 0 17 12" fill="none">
                    <path d="M12.75 7.5V5.25H10.5V3.75H12.75V1.5H14.25V3.75H16.5V5.25H14.25V7.5H12.75ZM6 6C5.175 6 4.46875 5.70625 3.88125 5.11875C3.29375 4.53125 3 3.825 3 3C3 2.175 3.29375 1.46875 3.88125 0.88125C4.46875 0.29375 5.175 0 6 0C6.825 0 7.53125 0.29375 8.11875 0.88125C8.70625 1.46875 9 2.175 9 3C9 3.825 8.70625 4.53125 8.11875 5.11875C7.53125 5.70625 6.825 6 6 6ZM0 12V9.9C0 9.475 0.109375 9.08437 0.328125 8.72812C0.546875 8.37187 0.8375 8.1 1.2 7.9125C1.975 7.525 2.7625 7.23438 3.5625 7.04063C4.3625 6.84688 5.175 6.75 6 6.75C6.825 6.75 7.6375 6.84688 8.4375 7.04063C9.2375 7.23438 10.025 7.525 10.8 7.9125C11.1625 8.1 11.4531 8.37187 11.6719 8.72812C11.8906 9.08437 12 9.475 12 9.9V12H0ZM1.5 10.5H10.5V9.9C10.5 9.7625 10.4656 9.6375 10.3969 9.525C10.3281 9.4125 10.2375 9.325 10.125 9.2625C9.45 8.925 8.76875 8.67188 8.08125 8.50313C7.39375 8.33438 6.7 8.25 6 8.25C5.3 8.25 4.60625 8.33438 3.91875 8.50313C3.23125 8.67188 2.55 8.925 1.875 9.2625C1.7625 9.325 1.67188 9.4125 1.60312 9.525C1.53437 9.6375 1.5 9.7625 1.5 9.9V10.5ZM6 4.5C6.4125 4.5 6.76562 4.35312 7.05937 4.05937C7.35312 3.76562 7.5 3.4125 7.5 3C7.5 2.5875 7.35312 2.23438 7.05937 1.94062C6.76562 1.64687 6.4125 1.5 6 1.5C5.5875 1.5 5.23438 1.64687 4.94063 1.94062C4.64688 2.23438 4.5 2.5875 4.5 3C4.5 3.4125 4.64688 3.76562 4.94063 4.05937C5.23438 4.35312 5.5875 4.5 6 4.5Z" fill="white"/>
                </svg>
                <span>Add User</span>
            </button>
        </div>
    </div>

    <!-- Filters & Quick Stats Bento Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        <!-- Filters card (3 columns) -->
        <div class="lg:col-span-3 bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05),0_0_0_1px_rgba(195,198,214,0.1)] p-5 md:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Filter by Role -->
                <div>
                    <label class="block text-[#434654] text-xs font-bold uppercase tracking-wider mb-2">Filter by Role</label>
                    <div class="relative">
                        <select class="w-full appearance-none bg-[#EFF4FF] border border-transparent rounded-lg py-2.5 pl-3 pr-8 text-sm text-[#0B1C30] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            <option>All Roles</option>
                            <option>Admin</option>
                            <option>Store Owner</option>
                            <option>Staff</option>
                        </select>
                        <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <!-- Tenant Category -->
                <div>
                    <label class="block text-[#434654] text-xs font-bold uppercase tracking-wider mb-2">Tenant Category</label>
                    <div class="relative">
                        <select class="w-full appearance-none bg-[#EFF4FF] border border-transparent rounded-lg py-2.5 pl-3 pr-8 text-sm text-[#0B1C30] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            <option>All Categories</option>
                            <option>Enterprise</option>
                            <option>Growth</option>
                            <option>Startup</option>
                        </select>
                        <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                            </svg>
                        </div>
                    </div>
                </div>
                <!-- Security Status -->
                <div>
                    <label class="block text-[#434654] text-xs font-bold uppercase tracking-wider mb-2">Security Status</label>
                    <div class="relative">
                        <select class="w-full appearance-none bg-[#EFF4FF] border border-transparent rounded-lg py-2.5 pl-3 pr-8 text-sm text-[#0B1C30] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            <option>All Security</option>
                            <option>2FA Enabled</option>
                            <option>2FA Disabled</option>
                            <option>Pending</option>
                        </select>
                        <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats card (1 column) -->
        <div class="bg-[#0052CC] rounded-xl p-5 md:p-6 shadow-[inset_0_2px_4px_rgba(0,0,0,0.05)] flex flex-col justify-between">
            <div class="flex justify-end">
                <svg width="24" height="30" viewBox="0 0 24 30" fill="none">
                    <g opacity="0.4">
                        <path d="M10.425 20.325L18.9 11.85L16.7625 9.7125L10.425 16.05L7.275 12.9L5.1375 15.0375L10.425 20.325ZM12 30C8.525 29.125 5.65625 27.1312 3.39375 24.0187C1.13125 20.9062 0 17.45 0 13.65V4.5L12 0L24 4.5V13.65C24 17.45 22.8688 20.9062 20.6063 24.0187C18.3438 27.1312 15.475 29.125 12 30ZM12 26.85C14.6 26.025 16.75 24.375 18.45 21.9C20.15 19.425 21 16.675 21 13.65V6.5625L12 3.1875L3 6.5625V13.65C3 16.675 3.85 19.425 5.55 21.9C7.25 24.375 9.4 26.025 12 26.85Z" fill="#C4D2FF"/>
                    </g>
                </svg>
            </div>
            <div>
                <div class="text-[#C4D2FF]/70 text-sm font-medium">Global Compliance</div>
                <div class="text-[#C4D2FF] text-3xl font-bold">94.2%</div>
            </div>
        </div>
    </div>

    <!-- User Directory Table -->
    <div class="bg-white rounded-xl shadow-[0_1px_2px_rgba(0,0,0,0.05),0_0_0_1px_rgba(195,198,214,0.1)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#EFF4FF]/50 text-[#434654] text-xs font-bold uppercase tracking-wider border-b border-[#C3C6D6]/10">
                    <tr>
                        <th class="text-left py-4 px-6">User</th>
                        <th class="text-left py-4 px-6">Role</th>
                        <th class="text-left py-4 px-6">Primary Tenant</th>
                        <th class="text-left py-4 px-6">2FA Status</th>
                        <th class="text-left py-4 px-6">Last Activity</th>
                        <th class="text-right py-4 px-6">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#C3C6D6]/5">
                    <!-- Sarah Chen -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#EFF4FF] flex items-center justify-center overflow-hidden">
                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                                        <circle cx="18" cy="13" r="5" fill="#94A3B8"/>
                                        <path d="M28 26C28 22 24 20 18 20C12 20 8 22 8 26" fill="#94A3B8"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-[#0B1C30]">Sarah Chen</div>
                                    <div class="text-xs text-[#434654]">sarah.c@cloudops.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-3 py-1 bg-[#DCE9FF] text-[#003D9B] text-xs font-medium rounded-full">Admin</span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-[#0B1C30]">Main Cloud Cluster</div>
                            <div class="text-xs text-[#434654]">ID: T-4920</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-[#4EDEA3] text-[#002113] text-[10px] font-bold uppercase rounded-full">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M5.01667 8.51667L9.12917 4.40417L8.3125 3.5875L5.01667 6.88333L3.35417 5.22083L2.5375 6.0375L5.01667 8.51667ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#002113"/>
                                </svg>
                                Enabled
                            </span>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">2 mins ago</td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0">
                                <!-- hidden action icons as in original -->
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-3 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Marcus Thorne -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#EFF4FF] flex items-center justify-center overflow-hidden">
                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                                        <circle cx="18" cy="13" r="5" fill="#94A3B8"/>
                                        <path d="M28 26C28 22 24 20 18 20C12 20 8 22 8 26" fill="#94A3B8"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-[#0B1C30]">Marcus Thorne</div>
                                    <div class="text-xs text-[#434654]">m.thorne@luxestore.io</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-3 py-1 bg-[#D5E3FC] text-[#57657A] text-xs font-medium rounded-full">Store Owner</span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-[#0B1C30]">Luxe Retail Solutions</div>
                            <div class="text-xs text-[#434654]">ID: T-8122</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-[#FFDAD6] text-[#93000A] text-[10px] font-bold uppercase rounded-full">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M3.73333 8.75L5.83333 6.65L7.93333 8.75L8.75 7.93333L6.65 5.83333L8.75 3.73333L7.93333 2.91667L5.83333 5.01667L3.73333 2.91667L2.91667 3.73333L5.01667 5.83333L2.91667 7.93333L3.73333 8.75ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#93000A"/>
                                </svg>
                                Disabled
                            </span>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">4 hours ago</td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0">
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-3 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Elena Rodriguez -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#EFF4FF] flex items-center justify-center overflow-hidden">
                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                                        <circle cx="18" cy="13" r="5" fill="#94A3B8"/>
                                        <path d="M28 26C28 22 24 20 18 20C12 20 8 22 8 26" fill="#94A3B8"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-[#0B1C30]">Elena Rodriguez</div>
                                    <div class="text-xs text-[#434654]">elena.rod@fintech-api.net</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-3 py-1 bg-[#6FFBBE]/30 text-[#004E33] text-xs font-medium rounded-full">Staff</span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-[#0B1C30]">FinTech API Node</div>
                            <div class="text-xs text-[#434654]">ID: T-1022</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-[#4EDEA3] text-[#002113] text-[10px] font-bold uppercase rounded-full">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M5.01667 8.51667L9.12917 4.40417L8.3125 3.5875L5.01667 6.88333L3.35417 5.22083L2.5375 6.0375L5.01667 8.51667ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#002113"/>
                                </svg>
                                Enabled
                            </span>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">Yesterday</td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0">
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-3 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                            </div>
                        </td>
                    </tr>
                    <!-- Julian Voigt -->
                    <tr>
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#EFF4FF] flex items-center justify-center overflow-hidden">
                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                                        <circle cx="18" cy="13" r="5" fill="#94A3B8"/>
                                        <path d="M28 26C28 22 24 20 18 20C12 20 8 22 8 26" fill="#94A3B8"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-[#0B1C30]">Julian Voigt</div>
                                    <div class="text-xs text-[#434654]">j.voigt@berlin-ops.de</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="px-3 py-1 bg-[#6FFBBE]/30 text-[#004E33] text-xs font-medium rounded-full">Staff</span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="font-medium text-[#0B1C30]">Berlin Infrastructure</div>
                            <div class="text-xs text-[#434654]">ID: T-3301</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-[#4EDEA3] text-[#002113] text-[10px] font-bold uppercase rounded-full">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M5.01667 8.51667L9.12917 4.40417L8.3125 3.5875L5.01667 6.88333L3.35417 5.22083L2.5375 6.0375L5.01667 8.51667ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667Z" fill="#002113"/>
                                </svg>
                                Enabled
                            </span>
                        </td>
                        <td class="py-4 px-6 text-[#434654]">Oct 24, 2023</td>
                        <td class="py-4 px-6 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0">
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-3 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                                <button class="p-1.5 rounded-lg hover:bg-gray-100"><div class="w-4 h-4 bg-[#434654]"></div></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Table Pagination -->
        <div class="bg-[#EFF4FF]/30 px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-[#C3C6D6]/10">
            <div class="text-[#434654] text-sm">
                <span class="font-medium text-[#0B1C30]">Showing 1-4</span> of <span class="font-medium text-[#0B1C30]">2,482</span> users
            </div>
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 flex items-center justify-center border border-[#C3C6D6]/20 rounded-lg text-[#434654] hover:bg-gray-50" disabled>
                    <svg width="5" height="7" viewBox="0 0 5 7" fill="none"><path d="M3.5 7L0 3.5L3.5 0L4.31667 0.816667L1.63333 3.5L4.31667 6.18333L3.5 7Z" fill="currentColor"/></svg>
                </button>
                <button class="w-8 h-8 flex items-center justify-center bg-[#003D9B] text-white font-bold rounded-lg text-sm">1</button>
                <button class="w-8 h-8 flex items-center justify-center border border-[#C3C6D6]/20 rounded-lg text-[#434654] hover:bg-gray-50 font-bold text-sm">2</button>
                <button class="w-8 h-8 flex items-center justify-center border border-[#C3C6D6]/20 rounded-lg text-[#434654] hover:bg-gray-50 font-bold text-sm">3</button>
                <button class="w-8 h-8 flex items-center justify-center border border-[#C3C6D6]/20 rounded-lg text-[#434654] hover:bg-gray-50">
                    <svg width="5" height="7" viewBox="0 0 5 7" fill="none"><path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="currentColor"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Security Insights Section (two cards) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Critical Security Alert -->
        <div class="bg-[#EFF4FF] rounded-2xl p-6 border-l-4 border-l-[#003D9B]">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-medium text-[#0B1C30] font-poppins">Critical Security Alert</h3>
                <span class="px-2 py-1 bg-[#BA1A1A] text-white text-[10px] font-bold uppercase rounded">Action Required</span>
            </div>
            <p class="text-sm text-[#434654] leading-relaxed mb-4">
                There are <span class="text-[#BA1A1A] font-bold">12 accounts</span> currently operating without 2FA in high-tier tenants. It is recommended to enforce MFA across all Enterprise category accounts to maintain SOC2 compliance.
            </p>
            <a href="#" class="inline-flex items-center gap-1 text-[#003D9B] font-bold text-sm hover:underline">
                Review affected accounts
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                    <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#003D9B"/>
                </svg>
            </a>
        </div>

        <!-- User Growth Velocity -->
        <div class="bg-white rounded-2xl p-6 shadow-[0_1px_2px_rgba(0,0,0,0.05),0_0_0_1px_rgba(195,198,214,0.1)] relative overflow-hidden">
            <div class="relative z-10">
                <h3 class="text-lg font-medium text-[#0B1C30] mb-1 font-poppins">User Growth Velocity</h3>
                <p class="text-sm text-[#434654]">+12% growth in new users this month</p>
            </div>
            <!-- decorative abstract pattern -->
            <div class="absolute -right-10 -top-10 w-48 h-48 bg-[#003D9B]/5 rounded-full blur-3xl"></div>
            <div class="relative z-10 mt-6 flex items-end gap-1 h-12">
                <div class="w-12 h-4 bg-[#003D9B]/10 rounded-t-md"></div>
                <div class="w-12 h-8 bg-[#003D9B]/10 rounded-t-md"></div>
                <div class="w-12 h-6 bg-[#003D9B]/10 rounded-t-md"></div>
                <div class="w-12 h-10 bg-[#003D9B]/10 rounded-t-md"></div>
                <div class="w-12 h-7 bg-[#003D9B]/10 rounded-t-md"></div>
                <div class="w-12 h-12 bg-[#003D9B] rounded-t-md"></div>
            </div>
        </div>
    </div>
</div>
@endsection
