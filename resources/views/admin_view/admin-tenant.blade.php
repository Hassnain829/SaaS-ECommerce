@extends('layouts.admin.admin-sidebar')

@section('title', 'Managed Stores')

@section('topbar')
<header class="font-inter sticky top-0 z-30 bg-white border-b border-gray-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
    <!-- mobile toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- search bar -->
    <div class="relative flex-1 max-w-xs sm:max-w-sm md:max-w-md">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/>
            </svg>
        </span>
        <input type="text" placeholder="Search stores, domains, or IDs..." class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg py-2 pl-10 pr-4 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
    </div>

    <!-- action buttons -->
    <div class="flex items-center gap-3 shrink-0">
        <!-- Create New Store button -->
        <button class="hidden sm:flex items-center gap-2 bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm hover:bg-[#0047B3] transition-colors">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="white"/>
            </svg>
            <span>Create Store</span>
        </button>

        <div class="w-px h-6 bg-[#E2E8F0] hidden sm:block"></div>

        <!-- Notification -->
        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
        </button>

        <!-- Help icon -->
        <button class="p-2 rounded-full hover:bg-gray-100 transition-colors hidden sm:flex">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95C11.2333 17.95 13.125 17.175 14.675 15.625C16.225 14.075 17 12.1833 17 9.95C17 7.71667 16.225 5.825 14.675 4.275C13.125 2.725 11.2333 1.95 9 1.95C6.76667 1.95 4.875 2.725 3.325 4.275C1.775 5.825 1 7.71667 1 9.95C1 12.1833 1.775 14.075 3.325 15.625C4.875 17.175 6.76667 17.95 9 17.95ZM9 15C9.28333 15 9.52083 14.9042 9.7125 14.7125C9.90417 14.5208 10 14.2833 10 14C10 13.7167 9.90417 13.4792 9.7125 13.2875C9.52083 13.0958 9.28333 13 9 13C8.71667 13 8.47917 13.0958 8.2875 13.2875C8.09583 13.4792 8 13.7167 8 14C8 14.2833 8.09583 14.5208 8.2875 14.7125C8.47917 14.9042 8.71667 15 9 15ZM9 11H10V5H8V6H9V11Z" fill="#64748B"/>
            </svg>
        </button>

        <!-- Avatar -->
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
<!-- hidden symbol for link icon (optional, you can inline the path instead) -->
<svg style="display:none;">
    <symbol id="link-icon" viewBox="0 0 12 6">
        <path d="M5.25 5.83333H2.91667C2.10972 5.83333 1.42188 5.54896 0.853125 4.98021C0.284375 4.41146 0 3.72361 0 2.91667C0 2.10972 0.284375 1.42188 0.853125 0.853125C1.42188 0.284375 2.10972 0 2.91667 0H5.25V1.16667H2.91667C2.43056 1.16667 2.01736 1.33681 1.67708 1.67708C1.33681 2.01736 1.16667 2.43056 1.16667 2.91667C1.16667 3.40278 1.33681 3.81597 1.67708 4.15625C2.01736 4.49653 2.43056 4.66667 2.91667 4.66667H5.25V5.83333ZM3.5 3.5V2.33333H8.16667V3.5H3.5ZM6.41667 5.83333V4.66667H8.75C9.23611 4.66667 9.64931 4.49653 9.98958 4.15625C10.3299 3.81597 10.5 3.40278 10.5 2.91667C10.5 2.43056 10.3299 2.01736 9.98958 1.67708C9.64931 1.33681 9.23611 1.16667 8.75 1.16667H6.41667V0H8.75C9.55694 0 10.2448 0.284375 10.8135 0.853125C11.3823 1.42188 11.6667 2.10972 11.6667 2.91667C11.6667 3.72361 11.3823 4.41146 10.8135 4.98021C10.2448 5.54896 9.55694 5.83333 8.75 5.83333H6.41667Z" fill="#64748B"/>
    </symbol>
</svg>

<div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
    <div>
        <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Managed Stores</h1>
        <p class="text-sm text-[#64748B]">You currently have access to 142 active multi-tenant instances.</p>
    </div>
    <!-- view toggle (grid/list) -->
    <div class="bg-white border border-[#E2E8F0] rounded-lg p-1 flex gap-1 self-start">
        <button class="p-2 bg-[#F8FAFC] rounded-md">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M0 6.66667V0H6.66667V6.66667H0ZM0 15V8.33333H6.66667V15H0ZM8.33333 6.66667V0H15V6.66667H8.33333ZM8.33333 15V8.33333H15V15H8.33333ZM1.66667 5H5V1.66667H1.66667V5ZM10 5H13.3333V1.66667H10V5ZM10 13.3333H13.3333V10H10V13.3333ZM1.66667 13.3333H5V10H1.66667V13.3333Z" fill="#0F172A"/>
            </svg>
        </button>
        <button class="p-2 rounded-md hover:bg-slate-100">
            <svg width="15" height="9" viewBox="0 0 15 9" fill="none">
                <path d="M3.33333 1.66667V0H15V1.66667H3.33333ZM3.33333 5V3.33333H15V5H3.33333ZM3.33333 8.33333V6.66667H15V8.33333H3.33333ZM0.833333 1.66667C0.597222 1.66667 0.399306 1.58681 0.239583 1.42708C0.0798611 1.26736 0 1.06944 0 0.833333C0 0.597222 0.0798611 0.399306 0.239583 0.239583C0.399306 0.0798611 0.597222 0 0.833333 0C1.06944 0 1.26736 0.0798611 1.42708 0.239583C1.58681 0.399306 1.66667 0.597222 1.66667 0.833333C1.66667 1.06944 1.58681 1.26736 1.42708 1.42708C1.26736 1.58681 1.06944 1.66667 0.833333 1.66667ZM0.833333 5C0.597222 5 0.399306 4.92014 0.239583 4.76042C0.0798611 4.60069 0 4.40278 0 4.16667C0 3.93056 0.0798611 3.73264 0.239583 3.57292C0.399306 3.41319 0.597222 3.33333 0.833333 3.33333C1.06944 3.33333 1.26736 3.41319 1.42708 3.57292C1.58681 3.73264 1.66667 3.93056 1.66667 4.16667C1.66667 4.40278 1.58681 4.60069 1.42708 4.76042C1.26736 4.92014 1.06944 5 0.833333 5ZM0.833333 8.33333C0.597222 8.33333 0.399306 8.25347 0.239583 8.09375C0.0798611 7.93403 0 7.73611 0 7.5C0 7.26389 0.0798611 7.06597 0.239583 6.90625C0.399306 6.74653 0.597222 6.66667 0.833333 6.66667C1.06944 6.66667 1.26736 6.74653 1.42708 6.90625C1.58681 7.06597 1.66667 7.26389 1.66667 7.5C1.66667 7.73611 1.58681 7.93403 1.42708 8.09375C1.26736 8.25347 1.06944 8.33333 0.833333 8.33333Z" fill="#64748B"/>
            </svg>
        </button>
    </div>
</div>

    <!-- quick filters -->
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 mr-1">Quick Filters:</span>
        <button class="px-3 py-1.5 text-xs font-semibold rounded-full bg-[#0052CC]/10 text-[#0052CC] border border-[#0052CC]/20">All Stores</button>
        <button class="px-3 py-1.5 text-xs font-semibold rounded-full bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">Fashion (24)</button>
        <button class="px-3 py-1.5 text-xs font-semibold rounded-full bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">Digital Goods (18)</button>
        <button class="px-3 py-1.5 text-xs font-semibold rounded-full bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">Services (12)</button>
        <button class="px-3 py-1.5 text-xs font-semibold rounded-full bg-white text-slate-600 border border-slate-200 hover:bg-slate-50">Enterprise (6)</button>
    </div>

    <!-- card grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- 1. Velvet & Vine (Fashion) -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm hover:shadow-md transition">
            <div class="p-5">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-lg bg-[#EFF6FF] flex items-center justify-center">
                        <svg width="20" height="16" viewBox="0 0 25 20" fill="none">
                            <path d="M1.25 20C0.895833 20 0.598958 19.8802 0.359375 19.6406C0.119792 19.401 0 19.1042 0 18.75C0 18.5417 0.0416667 18.349 0.125 18.1719C0.208333 17.9948 0.333333 17.8542 0.5 17.75L11.25 9.6875V7.5C11.25 7.14583 11.375 6.84896 11.625 6.60938C11.875 6.36979 12.1771 6.25 12.5312 6.25C13.0521 6.25 13.4896 6.0625 13.8438 5.6875C14.1979 5.3125 14.375 4.86458 14.375 4.34375C14.375 3.82292 14.1927 3.38542 13.8281 3.03125C13.4635 2.67708 13.0208 2.5 12.5 2.5C11.9792 2.5 11.5365 2.68229 11.1719 3.04688C10.8073 3.41146 10.625 3.85417 10.625 4.375H8.125C8.125 3.16667 8.55208 2.13542 9.40625 1.28125C10.2604 0.427083 11.2917 0 12.5 0C13.7083 0 14.7396 0.421875 15.5938 1.26562C16.4479 2.10938 16.875 3.13542 16.875 4.34375C16.875 5.32292 16.5885 6.19792 16.0156 6.96875C15.4427 7.73958 14.6875 8.27083 13.75 8.5625V9.6875L24.5 17.75C24.6667 17.8542 24.7917 17.9948 24.875 18.1719C24.9583 18.349 25 18.5417 25 18.75C25 19.1042 24.8802 19.401 24.6406 19.6406C24.401 19.8802 24.1042 20 23.75 20H1.25ZM5 17.5H20L12.5 11.875L5 17.5Z" fill="#2563EB"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 bg-[#F1F5F9] rounded text-[#64748B]">Fashion</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">Velvet & Vine</h3>
                <div class="flex items-center gap-1 text-xs text-[#94A3B8] mt-0.5">
                    <svg width="12" height="6" viewBox="0 0 12 6" fill="none">
                        <use href="#link-icon" />
                    </svg>
                    <span>velvet-vine.baas-core.com</span>
                </div>
                <div class="flex justify-between items-center mt-4 py-3 border-y border-slate-100">
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-400">Revenue</div>
                        <div class="text-base font-semibold text-slate-900">$12.4k</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-400">Status</div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                            <span class="text-sm font-semibold text-emerald-600">Healthy</span>
                        </div>
                    </div>
                </div>
                <button class="w-full mt-4 py-2.5 bg-[#F1F5F9] hover:bg-[#E2E8F0] text-[#334155] rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M6.75037 13.5V12H12.0004V1.5H6.75037V0H12.0004C12.4129 0 12.766 0.146875 13.0597 0.440625C13.3535 0.734375 13.5004 1.0875 13.5004 1.5V12C13.5004 12.4125 13.3535 12.7656 13.0597 13.0594C12.766 13.3531 12.4129 13.5 12.0004 13.5H6.75037ZM5.25037 10.5L4.21912 9.4125L6.13162 7.5H0.000366211V6H6.13162L4.21912 4.0875L5.25037 3L9.00037 6.75L5.25037 10.5Z" fill="currentColor"/>
                    </svg>
                    Login as Store Admin
                </button>
            </div>
        </div>

        <!-- 2. Pixel Perfect Assets (Digital Goods) -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm hover:shadow-md">
            <div class="p-5">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-lg bg-[#FAF5FF] flex items-center justify-center">
                        <svg width="20" height="16" viewBox="0 0 25 20" fill="none">
                            <path d="M2.5 20C1.8125 20 1.22396 19.7552 0.734375 19.2656C0.244792 18.776 0 18.1875 0 17.5V2.5C0 1.8125 0.244792 1.22396 0.734375 0.734375C1.22396 0.244792 1.8125 0 2.5 0H22.5C23.1875 0 23.776 0.244792 24.2656 0.734375C24.7552 1.22396 25 1.8125 25 2.5V17.5C25 18.1875 24.7552 18.776 24.2656 19.2656C23.776 19.7552 23.1875 20 22.5 20H2.5ZM2.5 17.5H22.5V5H2.5V17.5ZM6.875 16.25L5.125 14.5L8.34375 11.25L5.09375 8L6.875 6.25L11.875 11.25L6.875 16.25ZM12.5 16.25V13.75H20V16.25H12.5Z" fill="#9333EA"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 bg-[#F1F5F9] rounded text-[#64748B]">Digital Goods</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 leading-tight font-poppins">Pixel Perfect<br>Assets</h3>
                <div class="flex items-center gap-1 text-xs text-[#94A3B8] mt-0.5">
                    <svg width="12" height="6" viewBox="0 0 12 6" fill="none"><use href="#link-icon" /></svg>
                    <span>pixel-perfect.baas-core.com</span>
                </div>
                <div class="flex justify-between items-center mt-4 py-3 border-y border-[#F1F5F9]">
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Revenue</div><div class="text-base font-semibold text-[#0F172A]">$8.2k</div></div>
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Status</div><div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span><span class="text-sm font-semibold text-emerald-600">Healthy</span></div></div>
                </div>
                <button class="w-full mt-4 py-2.5 bg-[#F1F5F9] hover:bg-[#E2E8F0] text-[#334155] rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M6.75037 13.5V12H12.0004V1.5H6.75037V0H12.0004C12.4129 0 12.766 0.146875 13.0597 0.440625C13.3535 0.734375 13.5004 1.0875 13.5004 1.5V12C13.5004 12.4125 13.3535 12.7656 13.0597 13.0594C12.766 13.3531 12.4129 13.5 12.0004 13.5H6.75037ZM5.25037 10.5L4.21912 9.4125L6.13162 7.5H0.000366211V6H6.13162L4.21912 4.0875L5.25037 3L9.00037 6.75L5.25037 10.5Z" fill="currentColor"/></svg> Login as Store Admin</button>
            </div>
        </div>

        <!-- 3. Crave & Co (Food & Drink) -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm hover:shadow-md">
            <div class="p-5">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-lg bg-[#FFFBEB] flex items-center justify-center">
                        <svg width="15" height="20" viewBox="0 0 19 25" fill="none">
                            <path d="M3.75 25V13.5625C2.6875 13.2708 1.79688 12.6875 1.07812 11.8125C0.359375 10.9375 0 9.91667 0 8.75V0H2.5V8.75H3.75V0H6.25V8.75H7.5V0H10V8.75C10 9.91667 9.64062 10.9375 8.92188 11.8125C8.20312 12.6875 7.3125 13.2708 6.25 13.5625V25H3.75ZM16.25 25V15H12.5V6.25C12.5 4.52083 13.1094 3.04688 14.3281 1.82812C15.5469 0.609375 17.0208 0 18.75 0V25H16.25Z" fill="#D97706"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 bg-[#F1F5F9] rounded text-[#64748B]">Food & Drink</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">Crave & Co</h3>
                <div class="flex items-center gap-1 text-xs text-[#94A3B8] mt-0.5">
                    <svg width="12" height="6" viewBox="0 0 12 6" fill="none"><use href="#link-icon" /></svg>
                    <span>crave-co.baas-core.com</span>
                </div>
                <div class="flex justify-between items-center mt-4 py-3 border-y border-[#F1F5F9]">
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Revenue</div><div class="text-base font-semibold text-[#0F172A]">$45.1k</div></div>
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Status</div><div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-amber-500 rounded-full"></span><span class="text-sm font-semibold text-amber-600">Maintenance</span></div></div>
                </div>
                <button class="w-full mt-4 py-2.5 bg-[#F1F5F9] hover:bg-[#E2E8F0] text-[#334155] rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M6.75037 13.5V12H12.0004V1.5H6.75037V0H12.0004C12.4129 0 12.766 0.146875 13.0597 0.440625C13.3535 0.734375 13.5004 1.0875 13.5004 1.5V12C13.5004 12.4125 13.3535 12.7656 13.0597 13.0594C12.766 13.3531 12.4129 13.5 12.0004 13.5H6.75037ZM5.25037 10.5L4.21912 9.4125L6.13162 7.5H0.000366211V6H6.13162L4.21912 4.0875L5.25037 3L9.00037 6.75L5.25037 10.5Z" fill="currentColor"/></svg> Login as Store Admin</button>
            </div>
        </div>

        <!-- 4. Apex Activewear (Fitness) -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm hover:shadow-md">
            <div class="p-5">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-lg bg-[#ECFDF5] flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 25 25" fill="none">
                            <path d="M14.125 24.75L12.375 23L16.8125 18.5625L6.1875 7.9375L1.75 12.375L0 10.625L1.75 8.8125L0 7.0625L2.625 4.4375L0.875 2.625L2.625 0.875L4.4375 2.625L7.0625 0L8.8125 1.75L10.625 0L12.375 1.75L7.9375 6.1875L18.5625 16.8125L23 12.375L24.75 14.125L23 15.9375L24.75 17.6875L22.125 20.3125L23.875 22.125L22.125 23.875L20.3125 22.125L17.6875 24.75L15.9375 23L14.125 24.75Z" fill="#059669"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 bg-[#F1F5F9] rounded text-[#64748B]">Fitness</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 font-poppins">Apex Activewear</h3>
                <div class="flex items-center gap-1 text-xs text-[#94A3B8] mt-0.5">
                    <svg width="12" height="6" viewBox="0 0 12 6" fill="none"><use href="#link-icon" /></svg>
                    <span>apex-active.baas-core.com</span>
                </div>
                <div class="flex justify-between items-center mt-4 py-3 border-y border-[#F1F5F9]">
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Revenue</div><div class="text-base font-semibold text-[#0F172A]">$5.7k</div></div>
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Status</div><div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span><span class="text-sm font-semibold text-emerald-600">Healthy</span></div></div>
                </div>
                <button class="w-full mt-4 py-2.5 bg-[#F1F5F9] hover:bg-[#E2E8F0] text-[#334155] rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M6.75037 13.5V12H12.0004V1.5H6.75037V0H12.0004C12.4129 0 12.766 0.146875 13.0597 0.440625C13.3535 0.734375 13.5004 1.0875 13.5004 1.5V12C13.5004 12.4125 13.3535 12.7656 13.0597 13.0594C12.766 13.3531 12.4129 13.5 12.0004 13.5H6.75037ZM5.25037 10.5L4.21912 9.4125L6.13162 7.5H0.000366211V6H6.13162L4.21912 4.0875L5.25037 3L9.00037 6.75L5.25037 10.5Z" fill="currentColor"/></svg> Login as Store Admin</button>
            </div>
        </div>

        <!-- 5. The Knowledge Hub (Education) -->
        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-sm hover:shadow-md">
            <div class="p-5">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-lg bg-[#FFF1F2] flex items-center justify-center">
                        <svg width="16" height="20" viewBox="0 0 20 25" fill="none">
                            <path d="M2.5 25C1.8125 25 1.22396 24.7552 0.734375 24.2656C0.244792 23.776 0 23.1875 0 22.5V2.5C0 1.8125 0.244792 1.22396 0.734375 0.734375C1.22396 0.244792 1.8125 0 2.5 0H17.5C18.1875 0 18.776 0.244792 19.2656 0.734375C19.7552 1.22396 20 1.8125 20 2.5V22.5C20 23.1875 19.7552 23.776 19.2656 24.2656C18.776 24.7552 18.1875 25 17.5 25H2.5ZM2.5 22.5H17.5V2.5H15V11.25L11.875 9.375L8.75 11.25V2.5H2.5V22.5Z" fill="#E11D48"/>
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-1 bg-[#F1F5F9] rounded text-[#64748B]">Education</span>
                </div>
                <h3 class="text-lg font-medium text-[#0F172A] mt-3 leading-tight font-poppins">The Knowledge<br>Hub</h3>
                <div class="flex items-center gap-1 text-xs text-[#94A3B8] mt-0.5">
                    <svg width="12" height="6" viewBox="0 0 12 6" fill="none"><use href="#link-icon" /></svg>
                    <span>k-hub.baas-core.com</span>
                </div>
                <div class="flex justify-between items-center mt-4 py-3 border-y border-[#F1F5F9]">
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Revenue</div><div class="text-base font-semibold text-[#0F172A]">$1.4k</div></div>
                    <div><div class="text-[10px] font-bold uppercase text-[#94A3B8]">Status</div><div class="flex items-center gap-1.5"><span class="w-2 h-2 bg-rose-500 rounded-full"></span><span class="text-sm font-semibold text-rose-600">Suspended</span></div></div>
                </div>
                <button class="w-full mt-4 py-2.5 bg-[#F1F5F9] hover:bg-[#E2E8F0] text-[#334155] rounded-lg text-sm font-bold flex items-center justify-center gap-2 transition-colors"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M6.75037 13.5V12H12.0004V1.5H6.75037V0H12.0004C12.4129 0 12.766 0.146875 13.0597 0.440625C13.3535 0.734375 13.5004 1.0875 13.5004 1.5V12C13.5004 12.4125 13.3535 12.7656 13.0597 13.0594C12.766 13.3531 12.4129 13.5 12.0004 13.5H6.75037ZM5.25037 10.5L4.21912 9.4125L6.13162 7.5H0.000366211V6H6.13162L4.21912 4.0875L5.25037 3L9.00037 6.75L5.25037 10.5Z" fill="currentColor"/></svg> Login as Store Admin</button>
            </div>
        </div>

        <!-- 6. Provision New Tenant card (special) -->
        <div class="bg-[#F8FAFC] rounded-xl border-2 border-dashed border-[#E2E8F0] flex flex-col items-center justify-center p-6 text-center">
            <div class="w-10 h-10 bg-white rounded-full border border-[#E2E8F0] flex items-center justify-center mb-3">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M6 8H0V6H6V0H8V6H14V8H8V14H6V8Z" fill="#94A3B8"/>
                </svg>
            </div>
            <h4 class="text-base font-medium text-[#0F172A] font-poppins">Provision New Tenant</h4>
            <p class="text-xs text-[#94A3B8] mt-1 max-w-[180px]">Spin up a new dedicated store instance in seconds.</p>
        </div>
    </div>

    <!-- pagination -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 border-t border-[#E2E8F0]">
        <div class="text-sm text-[#64748B]">Showing 1 to 12 of 142 stores</div>
        <div class="flex items-center gap-2">
            <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#94A3B8] disabled:opacity-50" disabled>
                <svg width="7" height="10" viewBox="0 0 7 10" fill="none">
                    <path d="M5 10L0 5L5 0L6.16667 1.16667L2.33333 5L6.16667 8.83333L5 10Z" fill="currentColor"/>
                </svg>
            </button>
            <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-[#0052CC] text-white font-bold">1</button>
            <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#0F172A] hover:bg-[#F8FAFC]">2</button>
            <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#0F172A] hover:bg-[#F8FAFC]">3</button>
            <span class="text-[#94A3B8]">...</span>
            <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#0F172A] hover:bg-[#F8FAFC]">12</button>
            <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#0F172A] hover:bg-[#F8FAFC]">
                <svg width="7" height="10" viewBox="0 0 7 10" fill="none">
                    <path d="M3.83364 5L0.000305176 1.16667L1.16697 0L6.16697 5L1.16697 10L0.000305176 8.83333L3.83364 5Z" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </div>
</div>
@endsection
