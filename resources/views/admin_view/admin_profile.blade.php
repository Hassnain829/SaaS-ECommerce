@extends('layouts.admin.admin-Sidebar')

@section('title', 'Admin Profile')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white/50 backdrop-blur-sm border-b border-[#C3C6D6]/10 px-4 lg:px-8 py-3 flex items-center justify-between gap-4 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Search bar -->
    <div class="flex-1 flex justify-center">
        <div class="relative w-full max-w-md">
            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M16.6 18L10.3 11.7C9.8 12.1 9.225 12.4167 8.575 12.65C7.925 12.8833 7.23333 13 6.5 13C4.68333 13 3.14583 12.3708 1.8875 11.1125C0.629167 9.85417 0 8.31667 0 6.5C0 4.68333 0.629167 3.14583 1.8875 1.8875C3.14583 0.629167 4.68333 0 6.5 0C8.31667 0 9.85417 0.629167 11.1125 1.8875C12.3708 3.14583 13 4.68333 13 6.5C13 7.23333 12.8833 7.925 12.65 8.575C12.4167 9.225 12.1 9.8 11.7 10.3L18 16.6L16.6 18ZM6.5 11C7.75 11 8.8125 10.5625 9.6875 9.6875C10.5625 8.8125 11 7.75 11 6.5C11 5.25 10.5625 4.1875 9.6875 3.3125C8.8125 2.4375 7.75 2 6.5 2C5.25 2 4.1875 2.4375 3.3125 3.3125C2.4375 4.1875 2 5.25 2 6.5C2 7.75 2.4375 8.8125 3.3125 9.6875C4.1875 10.5625 5.25 11 6.5 11Z" fill="#737685"/>
                </svg>
            </div>
            <input type="text" placeholder="Search resources, API keys, or logs..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/60 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
        </div>
    </div>

    <!-- Right icons -->
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

        <!-- Profile avatar (placeholder) -->
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
<div class="font-inter max-w-9xl mx-auto space-y-6 md:space-y-8">
    <!-- Hero Section -->
    <div class="bg-gradient-to-b from-[#0052CC] to-[#003D9B] rounded-2xl p-6 md:p-8 text-white relative overflow-hidden">
        <!-- Abstract background element (optional) -->
        <div class="absolute right-0 top-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-end gap-6">
            <!-- Avatar with edit button -->
            <div class="relative w-24 h-24 md:w-32 md:h-32 shrink-0">
                <div class="w-full h-full rounded-2xl bg-[#EFF4FF] border-4 border-white/20 shadow-2xl overflow-hidden">
                    <!-- Placeholder avatar (can be replaced with user upload) -->
                    <svg class="w-full h-full" viewBox="0 0 128 128" fill="none">
                        <circle cx="64" cy="45" r="25" fill="#94A3B8"/>
                        <path d="M100 98C100 85 84 80 64 80C44 80 28 85 28 98" fill="#94A3B8"/>
                    </svg>
                </div>
                <!-- Edit button -->
                <button class="absolute -bottom-1 -right-1 bg-white rounded-xl p-2 shadow-lg">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M1.5 12H2.56875L9.9 4.66875L8.83125 3.6L1.5 10.9312V12ZM0 13.5V10.3125L9.9 0.43125C10.05 0.29375 10.2156 0.1875 10.3969 0.1125C10.5781 0.0375 10.7688 0 10.9688 0C11.1687 0 11.3625 0.0375 11.55 0.1125C11.7375 0.1875 11.9 0.3 12.0375 0.45L13.0688 1.5C13.2188 1.6375 13.3281 1.8 13.3969 1.9875C13.4656 2.175 13.5 2.3625 13.5 2.55C13.5 2.75 13.4656 2.94062 13.3969 3.12188C13.3281 3.30313 13.2188 3.46875 13.0688 3.61875L3.1875 13.5H0Z" fill="#003D9B"/>
                    </svg>
                </button>
            </div>
            <!-- User info -->
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl md:text-4xl font-medium font-poppins">Alex Rivers</h1>
                    <span class="inline-flex items-center gap-1 px-3 py-1 bg-[#4EDEA3] text-[#002113] text-xs font-bold uppercase rounded-full">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M4.43333 11.25L3.325 9.38333L1.225 8.91667L1.42917 6.75833L0 5.125L1.42917 3.49167L1.225 1.33333L3.325 0.866667L4.43333 0L6.41667 0.845833L8.4 0L9.50833 1.86667L11.6083 2.33333L11.4042 4.49167L12.8333 6.125L11.4042 7.75833L11.6083 9.91667L9.50833 10.3833L8.4 12.25L6.41667 11.4042L4.43333 11.25ZM5.80417 7.19583L9.1 3.9L8.28333 3.05417L5.80417 5.53333L4.55 4.30833L3.73333 5.125L5.80417 7.19583Z" fill="#002113"/>
                        </svg>
                        Verified
                    </span>
                </div>
                <div class="text-white/80 text-lg mt-1">Master Administrator</div>
                <div class="flex flex-wrap gap-4 mt-4">
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-3 py-1.5">
                        <svg width="10" height="12" viewBox="0 0 10 12" fill="none">
                            <path d="M4.66667 5.83333C4.9875 5.83333 5.26215 5.7191 5.49062 5.49062C5.7191 5.26215 5.83333 4.9875 5.83333 4.66667C5.83333 4.34583 5.7191 4.07118 5.49062 3.84271C5.26215 3.61424 4.9875 3.5 4.66667 3.5C4.34583 3.5 4.07118 3.61424 3.84271 3.84271C3.61424 4.07118 3.5 4.34583 3.5 4.66667C3.5 4.9875 3.61424 5.26215 3.84271 5.49062C4.07118 5.7191 4.34583 5.83333 4.66667 5.83333ZM4.66667 10.1208C5.85278 9.03194 6.73264 8.04271 7.30625 7.15312C7.87986 6.26354 8.16667 5.47361 8.16667 4.78333C8.16667 3.72361 7.82882 2.8559 7.15312 2.18021C6.47743 1.50451 5.64861 1.16667 4.66667 1.16667C3.68472 1.16667 2.8559 1.50451 2.18021 2.18021C1.50451 2.8559 1.16667 3.72361 1.16667 4.78333C1.16667 5.47361 1.45347 6.26354 2.02708 7.15312C2.60069 8.04271 3.48056 9.03194 4.66667 10.1208ZM4.66667 11.6667C3.10139 10.3347 1.93229 9.09757 1.15937 7.95521C0.386458 6.81285 0 5.75556 0 4.78333C0 3.325 0.469097 2.16319 1.40729 1.29792C2.34549 0.432639 3.43194 0 4.66667 0C5.90139 0 6.98785 0.432639 7.92604 1.29792C8.86424 2.16319 9.33333 3.325 9.33333 4.78333C9.33333 5.75556 8.94688 6.81285 8.17396 7.95521C7.40104 9.09757 6.23194 10.3347 4.66667 11.6667Z" fill="white"/>
                        </svg>
                        <span class="text-sm">San Francisco, CA</span>
                    </div>
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-3 py-1.5">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                            <path d="M7.75833 8.575L8.575 7.75833L6.41667 5.6V2.91667H5.25V6.06667L7.75833 8.575ZM5.83333 11.6667C5.02639 11.6667 4.26806 11.5135 3.55833 11.2073C2.84861 10.901 2.23125 10.4854 1.70625 9.96042C1.18125 9.43542 0.765625 8.81806 0.459375 8.10833C0.153125 7.39861 0 6.64028 0 5.83333C0 5.02639 0.153125 4.26806 0.459375 3.55833C0.765625 2.84861 1.18125 2.23125 1.70625 1.70625C2.23125 1.18125 2.84861 0.765625 3.55833 0.459375C4.26806 0.153125 5.02639 0 5.83333 0C6.64028 0 7.39861 0.153125 8.10833 0.459375C8.81806 0.765625 9.43542 1.18125 9.96042 1.70625C10.4854 2.23125 10.901 2.84861 11.2073 3.55833C11.5135 4.26806 11.6667 5.02639 11.6667 5.83333C11.6667 6.64028 11.5135 7.39861 11.2073 8.10833C10.901 8.81806 10.4854 9.43542 9.96042 9.96042C9.43542 10.4854 8.81806 10.901 8.10833 11.2073C7.39861 11.5135 6.64028 11.6667 5.83333 11.6667ZM5.83333 10.5C7.12639 10.5 8.22743 10.0455 9.13646 9.13646C10.0455 8.22743 10.5 7.12639 10.5 5.83333C10.5 4.54028 10.0455 3.43924 9.13646 2.53021C8.22743 1.62118 7.12639 1.16667 5.83333 1.16667C4.54028 1.16667 3.43924 1.62118 2.53021 2.53021C1.62118 3.43924 1.16667 4.54028 1.16667 5.83333C1.16667 7.12639 1.62118 8.22743 2.53021 9.13646C3.43924 10.0455 4.54028 10.5 5.83333 10.5Z" fill="white"/>
                        </svg>
                        <span class="text-sm">Local: 10:42 AM (GMT-8)</span>
                    </div>
                </div>
            </div>
            <!-- Save Changes button -->
            <div class="md:self-end">
                <button class="px-6 py-2 bg-white text-[#003D9B] font-semibold rounded-xl shadow-sm hover:bg-gray-50 transition">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-[#C3C6D6]/30 overflow-x-auto">
        <nav class="flex gap-8 min-w-max">
            <button class="pb-4 border-b-2 border-[#003D9B] text-[#003D9B] font-semibold text-sm">Profile</button>
            <button class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-sm">Security</button>
            <button class="pb-4 border-b-2 border-transparent text-[#434654] font-medium text-sm">Activity</button>
        </nav>
    </div>

    <!-- Main twoâ€‘column grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Personal Information Card -->
            <div class="bg-white rounded-2xl p-6 border border-[#C3C6D6]/20 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Personal Information</h2>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8ZM0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0Z" fill="#434654" fill-opacity="0.4"/>
                    </svg>
                </div>
                <div class="space-y-4">
                    <!-- Full Name -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Full Name</label>
                        <div class="flex items-center gap-3 bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3">
                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                <path d="M1.5 15C1.0875 15 0.734375 14.8531 0.440625 14.5594C0.146875 14.2656 0 13.9125 0 13.5V5.25C0 4.8375 0.146875 4.48438 0.440625 4.19063C0.734375 3.89688 1.0875 3.75 1.5 3.75H5.25V1.5C5.25 1.0875 5.39688 0.734375 5.69063 0.440625C5.98438 0.146875 6.3375 0 6.75 0H8.25C8.6625 0 9.01562 0.146875 9.30937 0.440625C9.60312 0.734375 9.75 1.0875 9.75 1.5V3.75H13.5C13.9125 3.75 14.2656 3.89688 14.5594 4.19063C14.8531 4.48438 15 4.8375 15 5.25V13.5C15 13.9125 14.8531 14.2656 14.5594 14.5594C14.2656 14.8531 13.9125 15 13.5 15H1.5ZM1.5 13.5H13.5V5.25H9.75C9.75 5.6625 9.60312 6.01562 9.30937 6.30937C9.01562 6.60312 8.6625 6.75 8.25 6.75H6.75C6.3375 6.75 5.98438 6.60312 5.69063 6.30937C5.39688 6.01562 5.25 5.6625 5.25 5.25H1.5V13.5ZM3 12H7.5V11.6625C7.5 11.45 7.44063 11.2531 7.32188 11.0719C7.20312 10.8906 7.0375 10.75 6.825 10.65C6.575 10.5375 6.32188 10.4531 6.06563 10.3969C5.80938 10.3406 5.5375 10.3125 5.25 10.3125C4.9625 10.3125 4.69062 10.3406 4.43437 10.3969C4.17812 10.4531 3.925 10.5375 3.675 10.65C3.4625 10.75 3.29688 10.8906 3.17812 11.0719C3.05937 11.2531 3 11.45 3 11.6625V12Z" fill="#737685"/>
                            </svg>
                            <span class="text-sm text-[#0B1C30]">Alex Rivers</span>
                        </div>
                    </div>
                    <!-- Email Address -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Email Address</label>
                        <div class="flex items-center gap-3 bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3">
                            <svg width="15" height="12" viewBox="0 0 15 12" fill="none">
                                <path d="M1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H13.5C13.9125 0 14.2656 0.146875 14.5594 0.440625C14.8531 0.734375 15 1.0875 15 1.5V10.5C15 10.9125 14.8531 11.2656 14.5594 11.5594C14.2656 11.8531 13.9125 12 13.5 12H1.5ZM7.5 6.75L1.5 3V10.5H13.5V3L7.5 6.75ZM7.5 5.25L13.5 1.5H1.5L7.5 5.25ZM1.5 3V1.5V3V10.5V3Z" fill="#737685"/>
                            </svg>
                            <span class="text-sm text-[#0B1C30]">alex.rivers@enterprise-baas.io</span>
                        </div>
                    </div>
                    <!-- Phone Number -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Phone Number</label>
                        <div class="flex items-center gap-3 bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M12.7125 13.5C11.15 13.5 9.60625 13.1594 8.08125 12.4781C6.55625 11.7969 5.16875 10.8313 3.91875 9.58125C2.66875 8.33125 1.70312 6.94375 1.02188 5.41875C0.340625 3.89375 0 2.35 0 0.7875C0 0.5625 0.075 0.375 0.225 0.225C0.375 0.075 0.5625 0 0.7875 0H3.825C4 0 4.15625 0.059375 4.29375 0.178125C4.43125 0.296875 4.5125 0.4375 4.5375 0.6L5.025 3.225C5.05 3.425 5.04375 3.59375 5.00625 3.73125C4.96875 3.86875 4.9 3.9875 4.8 4.0875L2.98125 5.925C3.23125 6.3875 3.52813 6.83437 3.87188 7.26562C4.21562 7.69688 4.59375 8.1125 5.00625 8.5125C5.39375 8.9 5.8 9.25937 6.225 9.59062C6.65 9.92188 7.1 10.225 7.575 10.5L9.3375 8.7375C9.45 8.625 9.59688 8.54062 9.77812 8.48438C9.95937 8.42813 10.1375 8.4125 10.3125 8.4375L12.9 8.9625C13.075 9.0125 13.2188 9.10312 13.3313 9.23438C13.4438 9.36563 13.5 9.5125 13.5 9.675V12.7125C13.5 12.9375 13.425 13.125 13.275 13.275C13.125 13.425 12.9375 13.5 12.7125 13.5Z" fill="#737685"/>
                            </svg>
                            <span class="text-sm text-[#0B1C30]">+1 (555) 012-3456</span>
                        </div>
                    </div>
                    <!-- Professional Title -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Professional Title</label>
                        <div class="flex items-center gap-3 bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3">
                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                <path d="M1.5 14.25C1.0875 14.25 0.734375 14.1031 0.440625 13.8094C0.146875 13.5156 0 13.1625 0 12.75V4.5C0 4.0875 0.146875 3.73438 0.440625 3.44062C0.734375 3.14687 1.0875 3 1.5 3H4.5V1.5C4.5 1.0875 4.64688 0.734375 4.94063 0.440625C5.23438 0.146875 5.5875 0 6 0H9C9.4125 0 9.76562 0.146875 10.0594 0.440625C10.3531 0.734375 10.5 1.0875 10.5 1.5V3H13.5C13.9125 3 14.2656 3.14687 14.5594 3.44062C14.8531 3.73438 15 4.0875 15 4.5V12.75C15 13.1625 14.8531 13.5156 14.5594 13.8094C14.2656 14.1031 13.9125 14.25 13.5 14.25H1.5ZM1.5 12.75H13.5V4.5H1.5V12.75Z" fill="#737685"/>
                            </svg>
                            <span class="text-sm text-[#0B1C30]">Master Administrator</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Preferences Card -->
            <div class="bg-white rounded-2xl p-6 border border-[#C3C6D6]/20 shadow-sm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-medium text-[#0B1C30] font-poppins">Account Preferences</h2>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M8 18V12H10V14H18V16H10V18H8ZM0 16V14H6V16H0ZM4 12V10H0V8H4V6H6V12H4ZM8 10V8H18V10H8ZM12 6V0H14V2H18V4H14V6H12ZM0 4V2H10V4H0Z" fill="#434654" fill-opacity="0.4"/>
                    </svg>
                </div>
                <div class="space-y-4">
                    <!-- UI Theme toggle -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 border-b border-[#C3C6D6]/10">
                        <div>
                            <div class="font-semibold text-[#0B1C30]">UI Theme</div>
                            <div class="text-sm text-[#434654]">Switch between light and dark interface</div>
                        </div>
                        <div class="flex bg-[#EFF4FF] rounded-full p-1 mt-2 sm:mt-0">
                            <button class="flex items-center gap-2 px-4 py-1.5 bg-white shadow-sm rounded-full text-[#003D9B] text-xs font-bold">
                                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                                    <path d="M6.41667 8.16667C6.90278 8.16667 7.31597 7.99653 7.65625 7.65625C7.99653 7.31597 8.16667 6.90278 8.16667 6.41667C8.16667 5.93056 7.99653 5.51736 7.65625 5.17708C7.31597 4.83681 6.90278 4.66667 6.41667 4.66667C5.93056 4.66667 5.51736 4.83681 5.17708 5.17708C4.83681 5.51736 4.66667 5.93056 4.66667 6.41667C4.66667 6.90278 4.83681 7.31597 5.17708 7.65625C5.51736 7.99653 5.93056 8.16667 6.41667 8.16667ZM6.41667 9.33333C5.60972 9.33333 4.92188 9.04896 4.35313 8.48021C3.78438 7.91146 3.5 7.22361 3.5 6.41667C3.5 5.60972 3.78438 4.92188 4.35313 4.35313C4.92188 3.78438 5.60972 3.5 6.41667 3.5C7.22361 3.5 7.91146 3.78438 8.48021 4.35313C9.04896 4.92188 9.33333 5.60972 9.33333 6.41667C9.33333 7.22361 9.04896 7.91146 8.48021 8.48021C7.91146 9.04896 7.22361 9.33333 6.41667 9.33333ZM2.33333 7H0V5.83333H2.33333V7ZM12.8333 7H10.5V5.83333H12.8333V7ZM5.83333 2.33333V0H7V2.33333H5.83333ZM5.83333 12.8333V10.5H7V12.8333H5.83333ZM3.15 3.9375L1.67708 2.52292L2.50833 1.6625L3.90833 3.12083L3.15 3.9375ZM10.325 11.1708L8.91042 9.69792L9.68333 8.89583L11.1562 10.3104L10.325 11.1708ZM8.89583 3.15L10.3104 1.67708L11.1708 2.50833L9.7125 3.90833L8.89583 3.15ZM1.6625 10.325L3.13542 8.91042L3.9375 9.68333L2.52292 11.1562L1.6625 10.325Z" fill="#003D9B"/>
                                </svg>
                                Light
                            </button>
                            <button class="flex items-center gap-2 px-4 py-1.5 text-[#434654] text-xs font-bold">
                                <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                                    <path d="M5.25 10.5C3.79167 10.5 2.55208 9.98958 1.53125 8.96875C0.510417 7.94792 0 6.70833 0 5.25C0 3.79167 0.510417 2.55208 1.53125 1.53125C2.55208 0.510417 3.79167 0 5.25 0C5.38611 0 5.51979 0.00486111 5.65104 0.0145833C5.78229 0.0243056 5.91111 0.0388889 6.0375 0.0583333C5.63889 0.340278 5.32049 0.707292 5.08229 1.15937C4.8441 1.61146 4.725 2.1 4.725 2.625C4.725 3.5 5.03125 4.24375 5.64375 4.85625C6.25625 5.46875 7 5.775 7.875 5.775C8.40972 5.775 8.90069 5.6559 9.34792 5.41771C9.79514 5.17951 10.1597 4.86111 10.4417 4.4625C10.4611 4.58889 10.4757 4.71771 10.4854 4.84896C10.4951 4.98021 10.5 5.11389 10.5 5.25C10.5 6.70833 9.98958 7.94792 8.96875 8.96875C7.94792 9.98958 6.70833 10.5 5.25 10.5Z" fill="#434654"/>
                                </svg>
                                Dark
                            </button>
                        </div>
                    </div>
                    <!-- Language select -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Language</label>
                        <div class="relative">
                            <select class="w-full appearance-none bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3 text-sm text-[#0B1C30] focus:outline-none">
                                <option>English (United States)</option>
                                <option>Spanish</option>
                                <option>French</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <!-- Timezone select -->
                    <div>
                        <label class="block text-[#434654] text-xs font-bold uppercase mb-1">Timezone</label>
                        <div class="relative">
                            <select class="w-full appearance-none bg-[#EFF4FF] border border-[#C3C6D6]/20 rounded-xl px-4 py-3 text-sm text-[#0B1C30] focus:outline-none">
                                <option>(GMT-08:00) Pacific Time</option>
                                <option>(GMT-05:00) Eastern Time</option>
                                <option>(GMT+00:00) UTC</option>
                            </select>
                            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M6.3 8.4L3 5.1L3.9 4.2L6.3 6.6L8.7 4.2L9.6 5.1L6.3 8.4Z" fill="#6B7280"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column (1/3) -->
        <div class="space-y-6">
            <!-- Organization Card -->
            <div class="bg-[#EFF4FF] rounded-2xl p-6 border border-[#003D9B]/10">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-[#0052CC] rounded-2xl flex items-center justify-center">
                        <svg width="20" height="18" viewBox="0 0 20 18" fill="none">
                            <path d="M0 18V0H10V4H20V18H0ZM2 16H8V14H2V16ZM2 12H8V10H2V12ZM2 8H8V6H2V8ZM2 4H8V2H2V4ZM10 16H18V6H10V16ZM12 10V8H16V10H12ZM12 14V12H16V14H12Z" fill="white"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold uppercase text-[#434654]">Organization</div>
                        <div class="text-xl font-bold text-[#0B1C30]">Enterprise BaaS</div>
                    </div>
                </div>

                <!-- Platform Permissions -->
                <div class="space-y-3">
                    <div class="text-xs font-bold uppercase text-[#434654]">Platform Permissions</div>
                    <!-- Permission 1 -->
                    <div class="bg-white rounded-xl p-4 border border-[#C3C6D6]/20 flex items-start gap-4">
                        <span class="w-2 h-8 rounded-full bg-[#003D9B]"></span>
                        <div>
                            <div class="font-semibold text-[#0B1C30]">Full System Access</div>
                            <div class="text-xs text-[#434654]">Global read/write/execute</div>
                        </div>
                    </div>
                    <!-- Permission 2 -->
                    <div class="bg-white rounded-xl p-4 border border-[#C3C6D6]/20 flex items-start gap-4">
                        <span class="w-2 h-8 rounded-full bg-[#4EDEA3]"></span>
                        <div>
                            <div class="font-semibold text-[#0B1C30]">User Orchestration</div>
                            <div class="text-xs text-[#434654]">Manage all 1,240 platform users</div>
                        </div>
                    </div>
                    <!-- Permission 3 -->
                    <div class="bg-white rounded-xl p-4 border border-[#C3C6D6]/20 flex items-start gap-4">
                        <span class="w-2 h-8 rounded-full bg-[#BA1A1A]"></span>
                        <div>
                            <div class="font-semibold text-[#0B1C30]">Billing Authority</div>
                            <div class="text-xs text-[#434654]">Invoice management & refunds</div>
                        </div>
                    </div>
                </div>

                <!-- View Full Access Report link -->
                <a href="#" class="mt-4 inline-flex items-center gap-2 text-[#003D9B] font-bold text-sm">
                    View Full Access Report
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M9.13125 6.75H0V5.25H9.13125L4.93125 1.05L6 0L12 6L6 12L4.93125 10.95L9.13125 6.75Z" fill="#003D9B"/>
                    </svg>
                </a>
            </div>

            <!-- Identity Verification Card -->
            <div class="bg-white rounded-2xl p-6 border border-[#C3C6D6]/20 text-center">
                <div class="text-xs font-bold uppercase text-[#434654] mb-4">Identity Verification</div>
                <div class="flex justify-center mb-3">
                    <div class="w-20 h-20 bg-[#EFF4FF] rounded-full flex items-center justify-center">
                        <svg width="20" height="25" viewBox="0 0 20 25" fill="none">
                            <path d="M8.6875 16.9375L15.75 9.875L13.9688 8.09375L8.6875 13.375L6.0625 10.75L4.28125 12.5312L8.6875 16.9375ZM10 25C7.10417 24.2708 4.71354 22.6094 2.82812 20.0156C0.942708 17.4219 0 14.5417 0 11.375V3.75L10 0L20 3.75V11.375C20 14.5417 19.0573 17.4219 17.1719 20.0156C15.2865 22.6094 12.8958 24.2708 10 25ZM10 22.375C12.1667 21.6875 13.9583 20.3125 15.375 18.25C16.7917 16.1875 17.5 13.8958 17.5 11.375V5.46875L10 2.65625L2.5 5.46875V11.375C2.5 13.8958 3.20833 16.1875 4.625 18.25C6.04167 20.3125 7.83333 21.6875 10 22.375Z" fill="#003D9B"/>
                        </svg>
                    </div>
                </div>
                <div class="font-bold text-[#0B1C30]">2-Step Auth Enabled</div>
                <div class="text-sm text-[#434654] mt-1 max-w-xs mx-auto">Last verified via Biometric Key 2 hours ago</div>
                <button class="mt-4 text-[#003D9B] font-bold text-sm">Update Security Method</button>
            </div>
        </div>
    </div>
</div>
@endsection
