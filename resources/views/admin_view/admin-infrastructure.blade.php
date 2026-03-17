@extends('layouts.admin.admin-sidebar')

@section('title', 'Integrations & Partner Management')

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
            <input type="text" placeholder="Search integrations, partners, or documentation..." class="w-full bg-[#EFF4FF] border border-transparent rounded-full py-2 pl-10 pr-4 text-sm text-[#0B1C30] placeholder:text-[#737685]/50 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
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

        <!-- New Project button (could be replaced with something else, but we keep as per example) -->
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
<div class="font-inter max-w-9xl mx-auto space-y-8 md:space-y-10">
    <!-- Page Header with title, subtitle and action button -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl lg:text-4xl font-medium text-[#0B1C30] font-poppins">Integrations & Partner Management</h1>
            <p class="text-sm md:text-base text-[#434654] mt-2 max-w-2xl">
                Orchestrate your e-commerce ecosystem. Seamlessly connect logistics providers, payment gateways, and secondary services to power your global storefront.
            </p>
        </div>
        <!-- Connect New Integration button -->
        <button class="flex items-center gap-2 px-6 py-3 bg-[#003D9B] text-white text-sm font-semibold rounded-xl shadow-[0_4px_6px_-4px_rgba(0,61,155,0.2),0_10px_15px_-3px_rgba(0,61,155,0.2)] hover:bg-[#002b6e] transition">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M6 8H0V6H6V0H8V6H14V8H8V14H6V8Z" fill="white"/>
            </svg>
            <span>Connect New Integration</span>
        </button>
    </div>

    <!-- Automation + Quick Stats grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Automation Routing Card (takes 2 columns on large) -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6 md:p-8 relative overflow-hidden border border-[#C3C6D6]/5">
            <!-- Background decorative circle -->
            <div class="absolute w-64 h-64 bg-[#003D9B]/5 rounded-full -right-10 -top-20"></div>
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 bg-[#6FFBBE] rounded-full px-3 py-1 mb-4">
                    <span class="w-2 h-2 bg-[#004E33] rounded-full"></span>
                    <span class="text-[#005236] text-xs font-bold uppercase tracking-wider">AI Optimized</span>
                </div>
                <h3 class="text-xl md:text-2xl font-medium text-[#0B1C30] mb-2 font-poppins">Enable Automated Routing</h3>
                <p class="text-sm text-[#434654] max-w-lg">
                    Automatically assign orders to the most cost-effective courier based on weight, destination, and real-time carrier health.
                </p>
            </div>
            <!-- Toggle switch -->
            <div class="absolute top-6 right-6">
                <svg width="56" height="32" viewBox="0 0 56 32" fill="none">
                    <rect width="56" height="32" rx="16" fill="#003D9B"/>
                    <rect x="28.5" y="4.5" width="23" height="23" rx="11.5" fill="white" stroke="white"/>
                </svg>
            </div>
        </div>

        <!-- Quick Stats Card (1 column) -->
        <div class="bg-[#0052CC] rounded-2xl p-6 shadow-[inset_0_2px_4px_rgba(0,0,0,0.05)] flex flex-col justify-between">
            <div class="flex justify-end">
                <svg width="24" height="30" viewBox="0 0 24 30" fill="none">
                    <g opacity="0.4">
                        <path d="M10.425 20.325L18.9 11.85L16.7625 9.7125L10.425 16.05L7.275 12.9L5.1375 15.0375L10.425 20.325ZM12 30C8.525 29.125 5.65625 27.1312 3.39375 24.0187C1.13125 20.9062 0 17.45 0 13.65V4.5L12 0L24 4.5V13.65C24 17.45 22.8688 20.9062 20.6063 24.0187C18.3438 27.1312 15.475 29.125 12 30ZM12 26.85C14.6 26.025 16.75 24.375 18.45 21.9C20.15 19.425 21 16.675 21 13.65V6.5625L12 3.1875L3 6.5625V13.65C3 16.675 3.85 19.425 5.55 21.9C7.25 24.375 9.4 26.025 12 26.85Z" fill="#C4D2FF"/>
                    </g>
                </svg>
            </div>
            <div>
                <div class="text-[#C4D2FF]/70 text-xs font-bold uppercase tracking-wider">Active Integrations</div>
                <div class="text-[#C4D2FF] text-4xl font-bold mt-1">14</div>
                <div class="flex items-center gap-1 mt-2 text-[#C4D2FF]">
                    <svg width="12" height="7" viewBox="0 0 12 7" fill="none">
                        <path d="M0.816667 7L0 6.18333L4.31667 1.8375L6.65 4.17083L9.68333 1.16667H8.16667V0H11.6667V3.5H10.5V1.98333L6.65 5.83333L4.31667 3.5L0.816667 7Z" fill="#C4D2FF"/>
                    </svg>
                    <span class="text-sm">+2 since last month</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Courier Services Section -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-[#C3C6D6]/30 pb-4">
            <div class="flex items-center gap-3">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none">
                    <path d="M5 16C4.16667 16 3.45833 15.7083 2.875 15.125C2.29167 14.5417 2 13.8333 2 13H0V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16V4H19L22 8V13H20C20 13.8333 19.7083 14.5417 19.125 15.125C18.5417 15.7083 17.8333 16 17 16C16.1667 16 15.4583 15.7083 14.875 15.125C14.2917 14.5417 14 13.8333 14 13H8C8 13.8333 7.70833 14.5417 7.125 15.125C6.54167 15.7083 5.83333 16 5 16ZM5 14C5.28333 14 5.52083 13.9042 5.7125 13.7125C5.90417 13.5208 6 13.2833 6 13C6 12.7167 5.90417 12.4792 5.7125 12.2875C5.52083 12.0958 5.28333 12 5 12C4.71667 12 4.47917 12.0958 4.2875 12.2875C4.09583 12.4792 4 12.7167 4 13C4 13.2833 4.09583 13.5208 4.2875 13.7125C4.47917 13.9042 4.71667 14 5 14ZM2 11H2.8C3.08333 10.7 3.40833 10.4583 3.775 10.275C4.14167 10.0917 4.55 10 5 10C5.45 10 5.85833 10.0917 6.225 10.275C6.59167 10.4583 6.91667 10.7 7.2 11H14V2H2V11ZM17 14C17.2833 14 17.5208 13.9042 17.7125 13.7125C17.9042 13.5208 18 13.2833 18 13C18 12.7167 17.9042 12.4792 17.7125 12.2875C17.5208 12.0958 17.2833 12 17 12C16.7167 12 16.4792 12.0958 16.2875 12.2875C16.0958 12.4792 16 12.7167 16 13C16 13.2833 16.0958 13.5208 16.2875 13.7125C16.4792 13.9042 16.7167 14 17 14ZM16 9H20.25L18 6H16V9Z" fill="#003D9B"/>
                </svg>
                <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Courier Services</h2>
            </div>
            <a href="{{ route('admin-infrastructure-add-logistic') }}" class="text-[#003D9B] text-sm font-semibold hover:underline">View All Logistics</a>
        </div>

        <!-- Courier cards grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- UPS -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-5 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start">
                    <div class="w-12 h-12 bg-[#E5EEFF] rounded-xl flex items-center justify-center">
                        <svg width="18" height="20" viewBox="0 0 18 20" fill="none">
                            <path d="M8 17.425V10.575L2 7.1V13.95L8 17.425ZM10 17.425L16 13.95V7.1L10 10.575V17.425ZM8 19.725L1 15.7C0.683333 15.5167 0.4375 15.275 0.2625 14.975C0.0875 14.675 0 14.3417 0 13.975V6.025C0 5.65833 0.0875 5.325 0.2625 5.025C0.4375 4.725 0.683333 4.48333 1 4.3L8 0.275C8.31667 0.0916667 8.65 0 9 0C9.35 0 9.68333 0.0916667 10 0.275L17 4.3C17.3167 4.48333 17.5625 4.725 17.7375 5.025C17.9125 5.325 18 5.65833 18 6.025V13.975C18 14.3417 17.9125 14.675 17.7375 14.975C17.5625 15.275 17.3167 15.5167 17 15.7L10 19.725C9.68333 19.9083 9.35 20 9 20C8.65 20 8.31667 19.9083 8 19.725ZM13 6.525L14.925 5.425L9 2L7.05 3.125L13 6.525ZM9 8.85L10.95 7.725L5.025 4.3L3.075 5.425L9 8.85Z" fill="#515F74"/>
                        </svg>
                    </div>
                    <span class="px-3 py-1 bg-[#4EDEA3]/20 text-[#004E33] text-[10px] font-bold uppercase rounded-full">Connected</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-[#0B1C30] font-poppins">UPS</h3>
                    <p class="text-xs text-[#434654]">United Parcel Service</p>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#434654]">API Latency</span>
                        <span class="font-semibold text-[#0B1C30]">45ms</span>
                    </div>
                    <div class="mt-1 h-1 bg-[#E5EEFF] rounded-full">
                        <div class="w-3/4 h-1 bg-[#4EDEA3] rounded-full"></div>
                    </div>
                </div>
                <div class="mt-5 flex items-center gap-2">
                    <a href="{{ route('admin-ups') }}" class="flex-1 py-2 bg-[#E5EEFF] text-[#003D9B] text-xs font-bold rounded-lg text-center">Configure</a>
                    <button class="p-2 rounded-lg hover:bg-gray-100">
                        <svg width="13" height="12" viewBox="0 0 13 12" fill="none">
                            <path d="M10.4125 7.9625L9.5375 7.05833C9.92639 6.95139 10.2424 6.74479 10.4854 6.43854C10.7285 6.13229 10.85 5.775 10.85 5.36667C10.85 4.88056 10.6799 4.46736 10.3396 4.12708C9.99931 3.78681 9.58611 3.61667 9.1 3.61667H6.76667V2.45H9.1C9.90695 2.45 10.5948 2.73438 11.1635 3.30312C11.7323 3.87187 12.0167 4.55972 12.0167 5.36667C12.0167 5.92083 11.8733 6.43125 11.5865 6.89792C11.2997 7.36458 10.9083 7.71944 10.4125 7.9625ZM8.42917 5.95L7.2625 4.78333H8.51667V5.95H8.42917ZM10.7333 11.55L0 0.816667L0.816667 0L11.55 10.7333L10.7333 11.55ZM5.6 8.28333H3.26667C2.45972 8.28333 1.77188 7.99896 1.20312 7.43021C0.634375 6.86146 0.35 6.17361 0.35 5.36667C0.35 4.69583 0.554167 4.09792 0.9625 3.57292C1.37083 3.04792 1.89583 2.70278 2.5375 2.5375L3.61667 3.61667H3.26667C2.78056 3.61667 2.36736 3.78681 2.02708 4.12708C1.68681 4.46736 1.51667 4.88056 1.51667 5.36667C1.51667 5.85278 1.68681 6.26597 2.02708 6.60625C2.36736 6.94653 2.78056 7.11667 3.26667 7.11667H5.6V8.28333ZM3.85 5.95V4.78333H4.79792L5.95 5.95H3.85Z" fill="#BA1A1A"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- FedEx -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-5 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start">
                    <div class="w-12 h-12 bg-[#E5EEFF] rounded-xl flex items-center justify-center">
                        <svg width="21" height="19" viewBox="0 0 21 19" fill="none">
                            <path d="M2 18.15V16.15H20V18.15H2ZM3.75 13.15L0 6.9L2.4 6.25L5.2 8.6L8.7 7.675L3.525 0.775L6.425 0L13.9 6.275L18.15 5.125C18.6833 4.975 19.1875 5.0375 19.6625 5.3125C20.1375 5.5875 20.45 5.99167 20.6 6.525C20.75 7.05833 20.6875 7.5625 20.4125 8.0375C20.1375 8.5125 19.7333 8.825 19.2 8.975L3.75 13.15Z" fill="#515F74"/>
                        </svg>
                    </div>
                    <span class="px-3 py-1 bg-[#4EDEA3]/20 text-[#004E33] text-[10px] font-bold uppercase rounded-full">Connected</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-[#0B1C30] font-poppins">FedEx</h3>
                    <p class="text-xs text-[#434654]">Federal Express</p>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#434654]">API Latency</span>
                        <span class="font-semibold text-[#0B1C30]">32ms</span>
                    </div>
                    <div class="mt-1 h-1 bg-[#E5EEFF] rounded-full">
                        <div class="w-5/6 h-1 bg-[#4EDEA3] rounded-full"></div>
                    </div>
                </div>
                <div class="mt-5 flex items-center gap-2">
                    <button class="flex-1 py-2 bg-[#E5EEFF] text-[#003D9B] text-xs font-bold rounded-lg">Configure</button>
                    <button class="p-2 rounded-lg hover:bg-gray-100">
                        <svg width="13" height="12" viewBox="0 0 13 12" fill="none">
                            <path d="M10.4125 7.9625L9.5375 7.05833C9.92639 6.95139 10.2424 6.74479 10.4854 6.43854C10.7285 6.13229 10.85 5.775 10.85 5.36667C10.85 4.88056 10.6799 4.46736 10.3396 4.12708C9.99931 3.78681 9.58611 3.61667 9.1 3.61667H6.76667V2.45H9.1C9.90695 2.45 10.5948 2.73438 11.1635 3.30312C11.7323 3.87187 12.0167 4.55972 12.0167 5.36667C12.0167 5.92083 11.8733 6.43125 11.5865 6.89792C11.2997 7.36458 10.9083 7.71944 10.4125 7.9625ZM8.42917 5.95L7.2625 4.78333H8.51667V5.95H8.42917ZM10.7333 11.55L0 0.816667L0.816667 0L11.55 10.7333L10.7333 11.55ZM5.6 8.28333H3.26667C2.45972 8.28333 1.77188 7.99896 1.20312 7.43021C0.634375 6.86146 0.35 6.17361 0.35 5.36667C0.35 4.69583 0.554167 4.09792 0.9625 3.57292C1.37083 3.04792 1.89583 2.70278 2.5375 2.5375L3.61667 3.61667H3.26667C2.78056 3.61667 2.36736 3.78681 2.02708 4.12708C1.68681 4.46736 1.51667 4.88056 1.51667 5.36667C1.51667 5.85278 1.68681 6.26597 2.02708 6.60625C2.36736 6.94653 2.78056 7.11667 3.26667 7.11667H5.6V8.28333ZM3.85 5.95V4.78333H4.79792L5.95 5.95H3.85Z" fill="#BA1A1A"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- DHL Express (Not Connected) -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-5 border border-[#C3C6D6]/10 opacity-60">
                <div class="flex justify-between items-start">
                    <div class="w-12 h-12 bg-[#E5EEFF] rounded-xl flex items-center justify-center">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95V16C8.45 16 7.97917 15.8042 7.5875 15.4125C7.19583 15.0208 7 14.55 7 14V13L2.2 8.2C2.15 8.5 2.10417 8.8 2.0625 9.1C2.02083 9.4 2 9.7 2 10C2 12.0167 2.6625 13.7833 3.9875 15.3C5.3125 16.8167 6.98333 17.7 9 17.95ZM15.9 15.4C16.5833 14.65 17.1042 13.8125 17.4625 12.8875C17.8208 11.9625 18 11 18 10C18 8.36667 17.5458 6.875 16.6375 5.525C15.7292 4.175 14.5167 3.2 13 2.6V3C13 3.55 12.8042 4.02083 12.4125 4.4125C12.0208 4.80417 11.55 5 11 5H9V7C9 7.28333 8.90417 7.52083 8.7125 7.7125C8.52083 7.90417 8.28333 8 8 8H6V10H12C12.2833 10 12.5208 10.0958 12.7125 10.2875C12.9042 10.4792 13 10.7167 13 11V14H14C14.4333 14 14.825 14.1292 15.175 14.3875C15.525 14.6458 15.7667 14.9833 15.9 15.4Z" fill="#515F74"/>
                        </svg>
                    </div>
                    <span class="px-3 py-1 bg-[#E5EEFF] text-[#434654] text-[10px] font-bold uppercase rounded-full">Not Connected</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-[#0B1C30] font-poppins">DHL Express</h3>
                    <p class="text-xs text-[#434654]">International Logistics</p>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#434654]">Last Pulse</span>
                        <span class="font-semibold text-[#0B1C30]">Never</span>
                    </div>
                    <div class="mt-1 h-1 bg-[#E5EEFF] rounded-full"></div>
                </div>
                <div class="mt-5">
                    <button class="w-full py-2 bg-[#003D9B] text-white text-xs font-bold rounded-lg">Connect Now</button>
                </div>
            </div>

            <!-- Canada Post -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-5 border border-[#C3C6D6]/10">
                <div class="flex justify-between items-start">
                    <div class="w-12 h-12 bg-[#E5EEFF] rounded-xl flex items-center justify-center">
                        <svg width="20" height="16" viewBox="0 0 20 16" fill="none">
                            <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V14C20 14.55 19.8042 15.0208 19.4125 15.4125C19.0208 15.8042 18.55 16 18 16H2ZM10 9L2 4V14H18V4L10 9ZM10 7L18 2H2L10 7ZM2 4V2V4V14V4Z" fill="#515F74"/>
                        </svg>
                    </div>
                    <span class="px-3 py-1 bg-[#4EDEA3]/20 text-[#004E33] text-[10px] font-bold uppercase rounded-full">Connected</span>
                </div>
                <div class="mt-4">
                    <h3 class="text-lg font-medium text-[#0B1C30] font-poppins">Canada Post</h3>
                    <p class="text-xs text-[#434654]">Domestic Services</p>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-xs">
                        <span class="text-[#434654]">API Latency</span>
                        <span class="font-semibold text-[#0B1C30]">68ms</span>
                    </div>
                    <div class="mt-1 h-1 bg-[#E5EEFF] rounded-full">
                        <div class="w-1/2 h-1 bg-[#003D9B]/40 rounded-full"></div>
                    </div>
                </div>
                <div class="mt-5 flex items-center gap-2">
                    <button class="flex-1 py-2 bg-[#E5EEFF] text-[#003D9B] text-xs font-bold rounded-lg">Configure</button>
                    <button class="p-2 rounded-lg hover:bg-gray-100">
                        <svg width="13" height="12" viewBox="0 0 13 12" fill="none">
                            <path d="M10.4125 7.9625L9.5375 7.05833C9.92639 6.95139 10.2424 6.74479 10.4854 6.43854C10.7285 6.13229 10.85 5.775 10.85 5.36667C10.85 4.88056 10.6799 4.46736 10.3396 4.12708C9.99931 3.78681 9.58611 3.61667 9.1 3.61667H6.76667V2.45H9.1C9.90695 2.45 10.5948 2.73438 11.1635 3.30312C11.7323 3.87187 12.0167 4.55972 12.0167 5.36667C12.0167 5.92083 11.8733 6.43125 11.5865 6.89792C11.2997 7.36458 10.9083 7.71944 10.4125 7.9625ZM8.42917 5.95L7.2625 4.78333H8.51667V5.95H8.42917ZM10.7333 11.55L0 0.816667L0.816667 0L11.55 10.7333L10.7333 11.55ZM5.6 8.28333H3.26667C2.45972 8.28333 1.77188 7.99896 1.20312 7.43021C0.634375 6.86146 0.35 6.17361 0.35 5.36667C0.35 4.69583 0.554167 4.09792 0.9625 3.57292C1.37083 3.04792 1.89583 2.70278 2.5375 2.5375L3.61667 3.61667H3.26667C2.78056 3.61667 2.36736 3.78681 2.02708 4.12708C1.68681 4.46736 1.51667 4.88056 1.51667 5.36667C1.51667 5.85278 1.68681 6.26597 2.02708 6.60625C2.36736 6.94653 2.78056 7.11667 3.26667 7.11667H5.6V8.28333ZM3.85 5.95V4.78333H4.79792L5.95 5.95H3.85Z" fill="#BA1A1A"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Gateways Section -->
    <div class="space-y-4">
        <div class="flex items-center gap-3 border-b border-[#C3C6D6]/30 pb-4">
            <svg width="22" height="16" viewBox="0 0 22 16" fill="none">
                <path d="M13 9C12.1667 9 11.4583 8.70833 10.875 8.125C10.2917 7.54167 10 6.83333 10 6C10 5.16667 10.2917 4.45833 10.875 3.875C11.4583 3.29167 12.1667 3 13 3C13.8333 3 14.5417 3.29167 15.125 3.875C15.7083 4.45833 16 5.16667 16 6C16 6.83333 15.7083 7.54167 15.125 8.125C14.5417 8.70833 13.8333 9 13 9ZM6 12C5.45 12 4.97917 11.8042 4.5875 11.4125C4.19583 11.0208 4 10.55 4 10V2C4 1.45 4.19583 0.979167 4.5875 0.5875C4.97917 0.195833 5.45 0 6 0H20C20.55 0 21.0208 0.195833 21.4125 0.5875C21.8042 0.979167 22 1.45 22 2V10C22 10.55 21.8042 11.0208 21.4125 11.4125C21.0208 11.8042 20.55 12 20 12H6ZM8 10H18C18 9.45 18.1958 8.97917 18.5875 8.5875C18.9792 8.19583 19.45 8 20 8V4C19.45 4 18.9792 3.80417 18.5875 3.4125C18.1958 3.02083 18 2.55 18 2H8C8 2.55 7.80417 3.02083 7.4125 3.4125C7.02083 3.80417 6.55 4 6 4V8C6.55 8 7.02083 8.19583 7.4125 8.5875C7.80417 8.97917 8 9.45 8 10ZM19 16H2C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V3H2V14H19V16ZM6 10V2V10Z" fill="#003D9B"/>
            </svg>
            <h2 class="text-xl font-medium text-[#0B1C30] font-poppins">Payment Gateways</h2>
        </div>

        <!-- Payment cards grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Stripe -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6 border border-[#C3C6D6]/5 relative">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-[#003D9B]/10 rounded-2xl flex items-center justify-center">
                            <svg width="24" height="23" viewBox="0 0 24 23" fill="none">
                                <path d="M2.5 20V2.5C2.5 2.5 2.5 2.96354 2.5 3.89062C2.5 4.81771 2.5 6.02083 2.5 7.5V15C2.5 16.4792 2.5 17.6823 2.5 18.6094C2.5 19.5365 2.5 20 2.5 20ZM2.5 22.5C1.8125 22.5 1.22396 22.2552 0.734375 21.7656C0.244792 21.276 0 20.6875 0 20V2.5C0 1.8125 0.244792 1.22396 0.734375 0.734375C1.22396 0.244792 1.8125 0 2.5 0H20C20.6875 0 21.276 0.244792 21.7656 0.734375C22.2552 1.22396 22.5 1.8125 22.5 2.5V5.625H20V2.5H2.5V20H20V16.875H22.5V20C22.5 20.6875 22.2552 21.276 21.7656 21.7656C21.276 22.2552 20.6875 22.5 20 22.5H2.5ZM12.5 17.5C11.8125 17.5 11.224 17.2552 10.7344 16.7656C10.2448 16.276 10 15.6875 10 15V7.5C10 6.8125 10.2448 6.22396 10.7344 5.73438C11.224 5.24479 11.8125 5 12.5 5H21.25C21.9375 5 22.526 5.24479 23.0156 5.73438C23.5052 6.22396 23.75 6.8125 23.75 7.5V15C23.75 15.6875 23.5052 16.276 23.0156 16.7656C22.526 17.2552 21.9375 17.5 21.25 17.5H12.5ZM21.25 15V7.5H12.5V15H21.25ZM16.25 13.125C16.7708 13.125 17.2135 12.9427 17.5781 12.5781C17.9427 12.2135 18.125 11.7708 18.125 11.25C18.125 10.7292 17.9427 10.2865 17.5781 9.92188C17.2135 9.55729 16.7708 9.375 16.25 9.375C15.7292 9.375 15.2865 9.55729 14.9219 9.92188C14.5573 10.2865 14.375 10.7292 14.375 11.25C14.375 11.7708 14.5573 12.2135 14.9219 12.5781C15.2865 12.9427 15.7292 13.125 16.25 13.125Z" fill="#003D9B"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-medium text-[#0B1C30] font-poppins">Stripe</h3>
                            <p class="text-sm text-[#434654]">Primary Processor</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2 py-0.5 bg-[#004E33] text-[#4EDEA3] text-[10px] font-bold uppercase rounded">Active</span>
                        <p class="text-xs text-[#434654] mt-1">v2023-10-16</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="bg-[#EFF4FF] rounded-xl p-4">
                        <div class="text-[#434654] text-[10px] font-bold uppercase">Vol (24h)</div>
                        <div class="text-lg font-bold text-[#0B1C30]">$124,500</div>
                    </div>
                    <div class="bg-[#EFF4FF] rounded-xl p-4">
                        <div class="text-[#434654] text-[10px] font-bold uppercase">Auth Rate</div>
                        <div class="text-lg font-bold text-[#0B1C30]">98.2%</div>
                    </div>
                </div>
                <button class="w-full mt-4 py-3 bg-[#003D9B]/5 text-[#003D9B] text-sm font-bold rounded-xl">Manage Account</button>
            </div>

            <!-- PayPal -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6 border border-[#C3C6D6]/5 relative">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-[#003D9B]/10 rounded-2xl flex items-center justify-center">
                            <svg width="25" height="24" viewBox="0 0 25 24" fill="none">
                                <path d="M17.5 11.25C17.8542 11.25 18.151 11.1302 18.3906 10.8906C18.6302 10.651 18.75 10.3542 18.75 10C18.75 9.64583 18.6302 9.34896 18.3906 9.10938C18.151 8.86979 17.8542 8.75 17.5 8.75C17.1458 8.75 16.849 8.86979 16.6094 9.10938C16.3698 9.34896 16.25 9.64583 16.25 10C16.25 10.3542 16.3698 10.651 16.6094 10.8906C16.849 11.1302 17.1458 11.25 17.5 11.25ZM7.5 8.75H13.75V6.25H7.5V8.75ZM3.125 23.75C2.41667 21.375 1.71875 19.0052 1.03125 16.6406C0.34375 14.276 0 11.8542 0 9.375C0 7.45833 0.666667 5.83333 2 4.5C3.33333 3.16667 4.95833 2.5 6.875 2.5H13.125C13.7292 1.70833 14.4635 1.09375 15.3281 0.65625C16.1927 0.21875 17.125 0 18.125 0C18.6458 0 19.0885 0.182292 19.4531 0.546875C19.8177 0.911458 20 1.35417 20 1.875C20 2 19.9844 2.125 19.9531 2.25C19.9219 2.375 19.8854 2.48958 19.8438 2.59375C19.7604 2.82292 19.6823 3.05729 19.6094 3.29688C19.5365 3.53646 19.4792 3.78125 19.4375 4.03125L22.2812 6.875H25V15.5938L21.4688 16.75L19.375 23.75H12.5V21.25H10V23.75H3.125ZM5 21.25H7.5V18.75H15V21.25H17.5L19.4375 14.8125L22.5 13.7812V9.375H21.25L16.875 5C16.875 4.58333 16.901 4.18229 16.9531 3.79688C17.0052 3.41146 17.0833 3.02083 17.1875 2.625C16.5833 2.79167 16.0521 3.07812 15.5938 3.48438C15.1354 3.89062 14.8021 4.39583 14.5938 5H6.875C5.66667 5 4.63542 5.42708 3.78125 6.28125C2.92708 7.13542 2.5 8.16667 2.5 9.375C2.5 11.4167 2.78125 13.4115 3.34375 15.3594C3.90625 17.3073 4.45833 19.2708 5 21.25Z" fill="#003D9B"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-medium text-[#0B1C30] font-poppins">PayPal</h3>
                            <p class="text-sm text-[#434654]">Wallet & Direct</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2 py-0.5 bg-[#004E33] text-[#4EDEA3] text-[10px] font-bold uppercase rounded">Active</span>
                        <p class="text-xs text-[#434654] mt-1">REST API</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="bg-[#EFF4FF] rounded-xl p-4">
                        <div class="text-[#434654] text-[10px] font-bold uppercase">Vol (24h)</div>
                        <div class="text-lg font-bold text-[#0B1C30]">$42,800</div>
                    </div>
                    <div class="bg-[#EFF4FF] rounded-xl p-4">
                        <div class="text-[#434654] text-[10px] font-bold uppercase">Auth Rate</div>
                        <div class="text-lg font-bold text-[#0B1C30]">96.5%</div>
                    </div>
                </div>
                <button class="w-full mt-4 py-3 bg-[#003D9B]/5 text-[#003D9B] text-sm font-bold rounded-xl">Manage Account</button>
            </div>

            <!-- Authorize.net (Inactive) -->
            <div class="bg-white rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.05)] p-6 border border-[#C3C6D6]/5 relative">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-[#003D9B]/10 rounded-2xl flex items-center justify-center">
                            <svg width="25" height="20" viewBox="0 0 25 20" fill="none">
                                <path d="M25 2.5V17.5C25 18.1875 24.7552 18.776 24.2656 19.2656C23.776 19.7552 23.1875 20 22.5 20H2.5C1.8125 20 1.22396 19.7552 0.734375 19.2656C0.244792 18.776 0 18.1875 0 17.5V2.5C0 1.8125 0.244792 1.22396 0.734375 0.734375C1.22396 0.244792 1.8125 0 2.5 0H22.5C23.1875 0 23.776 0.244792 24.2656 0.734375C24.7552 1.22396 25 1.8125 25 2.5ZM2.5 5H22.5V2.5H2.5V5ZM2.5 10V17.5H22.5V10H2.5ZM2.5 17.5V2.5V17.5Z" fill="#003D9B"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-medium text-[#0B1C30] font-poppins">Authorize.net</h3>
                            <p class="text-sm text-[#434654]">Legacy Processor</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-2 py-0.5 bg-[#FFDAD6] text-[#93000A] text-[10px] font-bold uppercase rounded">Inactive</span>
                        <p class="text-xs text-[#434654] mt-1">SOAP API</p>
                    </div>
                </div>
                <div class="mt-4 border-2 border-dashed border-[#C3C6D6]/20 rounded-2xl p-6 text-center">
                    <svg width="24" height="32" viewBox="0 0 24 32" fill="none" class="mx-auto mb-2">
                        <path d="M3 10.5H16.5V7.5C16.5 6.25 16.0625 5.1875 15.1875 4.3125C14.3125 3.4375 13.25 3 12 3C10.75 3 9.6875 3.4375 8.8125 4.3125C7.9375 5.1875 7.5 6.25 7.5 7.5H4.5C4.5 5.425 5.23125 3.65625 6.69375 2.19375C8.15625 0.73125 9.925 0 12 0C14.075 0 15.8438 0.73125 17.3062 2.19375C18.7687 3.65625 19.5 5.425 19.5 7.5V10.5H21C21.825 10.5 22.5312 10.7938 23.1187 11.3813C23.7062 11.9688 24 12.675 24 13.5V28.5C24 29.325 23.7062 30.0312 23.1187 30.6187C22.5312 31.2062 21.825 31.5 21 31.5H3C2.175 31.5 1.46875 31.2062 0.88125 30.6187C0.29375 30.0312 0 29.325 0 28.5V13.5C0 12.675 0.29375 11.9688 0.88125 11.3813C1.46875 10.7938 2.175 10.5 3 10.5ZM3 28.5H21V13.5H3V28.5ZM12 24C12.825 24 13.5312 23.7062 14.1187 23.1187C14.7062 22.5312 15 21.825 15 21C15 20.175 14.7062 19.4688 14.1187 18.8813C13.5312 18.2938 12.825 18 12 18C11.175 18 10.4688 18.2938 9.88125 18.8813C9.29375 19.4688 9 20.175 9 21C9 21.825 9.29375 22.5312 9.88125 23.1187C10.4688 23.7062 11.175 24 12 24ZM3 28.5V13.5V28.5Z" fill="#C3C6D6"/>
                    </svg>
                    <p class="text-xs text-[#434654]">Credentials expired or<br>connection refused.</p>
                </div>
                <button class="w-full mt-4 py-3 bg-[#003D9B] text-white text-sm font-bold rounded-xl shadow-[0_4px_6px_-4px_rgba(0,61,155,0.2),0_10px_15px_-3px_rgba(0,61,155,0.2)]">Re-authenticate</button>
            </div>
        </div>
    </div>

    <!-- Marketplace Integrations Section -->
    <div class="bg-[#EFF4FF] rounded-3xl p-6 md:p-8 border border-[#C3C6D6]/10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-xl md:text-2xl font-medium text-[#0B1C30] font-poppins">Marketplace Integrations</h2>
                <p class="text-sm text-[#434654]">Explore 200+ native connectors for your e-commerce stack.</p>
            </div>
            <!-- Filter input -->
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                    <svg width="18" height="24" viewBox="0 0 18 24" fill="none">
                        <path d="M7 12V10H11V12H7ZM3 7V5H15V7H3ZM0 2V0H18V2H0Z" fill="#434654"/>
                    </svg>
                </div>
                <input type="text" placeholder="Filter by category (CRM, Tax, ERP)..." class="w-full bg-white border border-transparent rounded-xl py-3 pl-12 pr-4 text-sm text-[#0B1C30] placeholder:text-[#6B7280] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
        </div>

        <!-- Integration icons grid -->
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
            <!-- Mailchimp -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm">
                <div class="w-12 h-12 bg-[#EFF4FF] rounded-full flex items-center justify-center mb-2">
                    <svg width="20" height="16" viewBox="0 0 20 16" fill="none">
                        <path d="M16 9V7H20V9H16ZM17.2 16L14 13.6L15.2 12L18.4 14.4L17.2 16ZM15.2 4L14 2.4L17.2 0L18.4 1.6L15.2 4ZM3 15V11H2C1.45 11 0.979167 10.8042 0.5875 10.4125C0.195833 10.0208 0 9.55 0 9V7C0 6.45 0.195833 5.97917 0.5875 5.5875C0.979167 5.19583 1.45 5 2 5H6L11 2V14L6 11H5V15H3ZM9 10.45V5.55L6.55 7H2V9H6.55L9 10.45ZM12 11.35V4.65C12.45 5.05 12.8125 5.5375 13.0875 6.1125C13.3625 6.6875 13.5 7.31667 13.5 8C13.5 8.68333 13.3625 9.3125 13.0875 9.8875C12.8125 10.4625 12.45 10.95 12 11.35Z" fill="#434654"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#0B1C30]">Mailchimp</span>
            </div>
            <!-- HubSpot -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm">
                <div class="w-12 h-12 bg-[#EFF4FF] rounded-full flex items-center justify-center mb-2">
                    <svg width="24" height="23" viewBox="0 0 24 23" fill="none">
                        <path d="M6 23C5.16667 23 4.45833 22.7083 3.875 22.125C3.29167 21.5417 3 20.8333 3 20C3 19.1667 3.29167 18.4583 3.875 17.875C4.45833 17.2917 5.16667 17 6 17C6.23333 17 6.45 17.025 6.65 17.075C6.85 17.125 7.04167 17.1917 7.225 17.275L8.65 15.5C8.18333 14.9833 7.85833 14.4 7.675 13.75C7.49167 13.1 7.45 12.45 7.55 11.8L5.525 11.125C5.24167 11.5417 4.88333 11.875 4.45 12.125C4.01667 12.375 3.53333 12.5 3 12.5C2.16667 12.5 1.45833 12.2083 0.875 11.625C0.291667 11.0417 0 10.3333 0 9.5C0 8.66667 0.291667 7.95833 0.875 7.375C1.45833 6.79167 2.16667 6.5 3 6.5C3.83333 6.5 4.54167 6.79167 5.125 7.375C5.70833 7.95833 6 8.66667 6 9.5C6 9.53333 6 9.56667 6 9.6C6 9.63333 6 9.66667 6 9.7L8.025 10.4C8.35833 9.8 8.80417 9.29167 9.3625 8.875C9.92083 8.45833 10.55 8.19167 11.25 8.075V5.9C10.6 5.71667 10.0625 5.3625 9.6375 4.8375C9.2125 4.3125 9 3.7 9 3C9 2.16667 9.29167 1.45833 9.875 0.875C10.4583 0.291667 11.1667 0 12 0C12.8333 0 13.5417 0.291667 14.125 0.875C14.7083 1.45833 15 2.16667 15 3C15 3.7 14.7833 4.3125 14.35 4.8375C13.9167 5.3625 13.3833 5.71667 12.75 5.9V8.075C13.45 8.19167 14.0792 8.45833 14.6375 8.875C15.1958 9.29167 15.6417 9.8 15.975 10.4L18 9.7C18 9.66667 18 9.63333 18 9.6C18 9.56667 18 9.53333 18 9.5C18 8.66667 18.2917 7.95833 18.875 7.375C19.4583 6.79167 20.1667 6.5 21 6.5C21.8333 6.5 22.5417 6.79167 23.125 7.375C23.7083 7.95833 24 8.66667 24 9.5C24 10.3333 23.7083 11.0417 23.125 11.625C22.5417 12.2083 21.8333 12.5 21 12.5C20.4667 12.5 19.9792 12.375 19.5375 12.125C19.0958 11.875 18.7417 11.5417 18.475 11.125L16.45 11.8C16.55 12.45 16.5083 13.0958 16.325 13.7375C16.1417 14.3792 15.8167 14.9667 15.35 15.5L16.775 17.25C16.9583 17.1667 17.15 17.1042 17.35 17.0625C17.55 17.0208 17.7667 17 18 17C18.8333 17 19.5417 17.2917 20.125 17.875C20.7083 18.4583 21 19.1667 21 20C21 20.8333 20.7083 21.5417 20.125 22.125C19.5417 22.7083 18.8333 23 18 23C17.1667 23 16.4583 22.7083 15.875 22.125C15.2917 21.5417 15 20.8333 15 20C15 19.6667 15.0542 19.3458 15.1625 19.0375C15.2708 18.7292 15.4167 18.45 15.6 18.2L14.175 16.425C13.4917 16.8083 12.7625 17 11.9875 17C11.2125 17 10.4833 16.8083 9.8 16.425L8.4 18.2C8.58333 18.45 8.72917 18.7292 8.8375 19.0375C8.94583 19.3458 9 19.6667 9 20C9 20.8333 8.70833 21.5417 8.125 22.125C7.54167 22.7083 6.83333 23 6 23ZM3 10.5C3.28333 10.5 3.52083 10.4042 3.7125 10.2125C3.90417 10.0208 4 9.78333 4 9.5C4 9.21667 3.90417 8.97917 3.7125 8.7875C3.52083 8.59583 3.28333 8.5 3 8.5C2.71667 8.5 2.47917 8.59583 2.2875 8.7875C2.09583 8.97917 2 9.21667 2 9.5C2 9.78333 2.09583 10.0208 2.2875 10.2125C2.47917 10.4042 2.71667 10.5 3 10.5ZM6 21C6.28333 21 6.52083 20.9042 6.7125 20.7125C6.90417 20.5208 7 20.2833 7 20C7 19.7167 6.90417 19.4792 6.7125 19.2875C6.52083 19.0958 6.28333 19 6 19C5.71667 19 5.47917 19.0958 5.2875 19.2875C5.09583 19.4792 5 19.7167 5 20C5 20.2833 5.09583 20.5208 5.2875 20.7125C5.47917 20.9042 5.71667 21 6 21ZM12 4C12.2833 4 12.5208 3.90417 12.7125 3.7125C12.9042 3.52083 13 3.28333 13 3C13 2.71667 12.9042 2.47917 12.7125 2.2875C12.5208 2.09583 12.2833 2 12 2C11.7167 2 11.4792 2.09583 11.2875 2.2875C11.0958 2.47917 11 2.71667 11 3C11 3.28333 11.0958 3.52083 11.2875 3.7125C11.4792 3.90417 11.7167 4 12 4ZM12 15C12.7 15 13.2917 14.7583 13.775 14.275C14.2583 13.7917 14.5 13.2 14.5 12.5C14.5 11.8 14.2583 11.2083 13.775 10.725C13.2917 10.2417 12.7 10 12 10C11.3 10 10.7083 10.2417 10.225 10.725C9.74167 11.2083 9.5 11.8 9.5 12.5C9.5 13.2 9.74167 13.7917 10.225 14.275C10.7083 14.7583 11.3 15 12 15ZM18 21C18.2833 21 18.5208 20.9042 18.7125 20.7125C18.9042 20.5208 19 20.2833 19 20C19 19.7167 18.9042 19.4792 18.7125 19.2875C18.5208 19.0958 18.2833 19 18 19C17.7167 19 17.4792 19.0958 17.2875 19.2875C17.0958 19.4792 17 19.7167 17 20C17 20.2833 17.0958 20.5208 17.2875 20.7125C17.4792 20.9042 17.7167 21 18 21ZM21 10.5C21.2833 10.5 21.5208 10.4042 21.7125 10.2125C21.9042 10.0208 22 9.78333 22 9.5C22 9.21667 21.9042 8.97917 21.7125 8.7875C21.5208 8.59583 21.2833 8.5 21 8.5C20.7167 8.5 20.4792 8.59583 20.2875 8.7875C20.0958 8.97917 20 9.21667 20 9.5C20 9.78333 20.0958 10.0208 20.2875 10.2125C20.4792 10.4042 20.7167 10.5 21 10.5Z" fill="#434654"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#0B1C30]">HubSpot</span>
            </div>
            <!-- Avalara -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm">
                <div class="w-12 h-12 bg-[#EFF4FF] rounded-full flex items-center justify-center mb-2">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M5 15H6.5V13H8.5V11.5H6.5V9.5H5V11.5H3V13H5V15ZM10 14.25H15V12.75H10V14.25ZM10 11.75H15V10.25H10V11.75ZM11.1 7.95L12.5 6.55L13.9 7.95L14.95 6.9L13.55 5.45L14.95 4.05L13.9 3L12.5 4.4L11.1 3L10.05 4.05L11.45 5.45L10.05 6.9L11.1 7.95ZM3.25 6.2H8.25V4.7H3.25V6.2ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2ZM2 16H16V2H2V16ZM2 2V16V2Z" fill="#434654"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#0B1C30]">Avalara</span>
            </div>
            <!-- Datadog -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm">
                <div class="w-12 h-12 bg-[#EFF4FF] rounded-full flex items-center justify-center mb-2">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M0 18V16L2 14V18H0ZM4 18V12L6 10V18H4ZM8 18V10L10 12.025V18H8ZM12 18V12.025L14 10.025V18H12ZM16 18V8L18 6V18H16ZM0 12.825V10L7 3L11 7L18 0V2.825L11 9.825L7 5.825L0 12.825Z" fill="#434654"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#0B1C30]">Datadog</span>
            </div>
            <!-- NetSuite -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm">
                <div class="w-12 h-12 bg-[#EFF4FF] rounded-full flex items-center justify-center mb-2">
                    <svg width="22" height="16" viewBox="0 0 22 16" fill="none">
                        <path d="M9.35 13L15 7.35L13.55 5.9L9.325 10.125L7.225 8.025L5.8 9.45L9.35 13ZM5.5 16C3.98333 16 2.6875 15.475 1.6125 14.425C0.5375 13.375 0 12.0917 0 10.575C0 9.275 0.391667 8.11667 1.175 7.1C1.95833 6.08333 2.98333 5.43333 4.25 5.15C4.66667 3.61667 5.5 2.375 6.75 1.425C8 0.475 9.41667 0 11 0C12.95 0 14.6042 0.679167 15.9625 2.0375C17.3208 3.39583 18 5.05 18 7C19.15 7.13333 20.1042 7.62917 20.8625 8.4875C21.6208 9.34583 22 10.35 22 11.5C22 12.75 21.5625 13.8125 20.6875 14.6875C19.8125 15.5625 18.75 16 17.5 16H5.5ZM5.5 14H17.5C18.2 14 18.7917 13.7583 19.275 13.275C19.7583 12.7917 20 12.2 20 11.5C20 10.8 19.7583 10.2083 19.275 9.725C18.7917 9.24167 18.2 9 17.5 9H16V7C16 5.61667 15.5125 4.4375 14.5375 3.4625C13.5625 2.4875 12.3833 2 11 2C9.61667 2 8.4375 2.4875 7.4625 3.4625C6.4875 4.4375 6 5.61667 6 7H5.5C4.53333 7 3.70833 7.34167 3.025 8.025C2.34167 8.70833 2 9.53333 2 10.5C2 11.4667 2.34167 12.2917 3.025 12.975C3.70833 13.6583 4.53333 14 5.5 14Z" fill="#434654"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#0B1C30]">NetSuite</span>
            </div>
            <!-- View All -->
            <div class="bg-white rounded-2xl p-4 flex flex-col items-center justify-center text-center shadow-sm border-2 border-[#003D9B]/20">
                <div class="w-12 h-12 bg-[#003D9B]/5 rounded-full flex items-center justify-center mb-2">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14C0 13.45 0.195833 12.9792 0.5875 12.5875C0.979167 12.1958 1.45 12 2 12C2.55 12 3.02083 12.1958 3.4125 12.5875C3.80417 12.9792 4 13.45 4 14C4 14.55 3.80417 15.0208 3.4125 15.4125C3.02083 15.8042 2.55 16 2 16ZM8 16C7.45 16 6.97917 15.8042 6.5875 15.4125C6.19583 15.0208 6 14.55 6 14C6 13.45 6.19583 12.9792 6.5875 12.5875C6.97917 12.1958 7.45 12 8 12C8.55 12 9.02083 12.1958 9.4125 12.5875C9.80417 12.9792 10 13.45 10 14C10 14.55 9.80417 15.0208 9.4125 15.4125C9.02083 15.8042 8.55 16 8 16ZM14 16C13.45 16 12.9792 15.8042 12.5875 15.4125C12.1958 15.0208 12 14.55 12 14C12 13.45 12.1958 12.9792 12.5875 12.5875C12.9792 12.1958 13.45 12 14 12C14.55 12 15.0208 12.1958 15.4125 12.5875C15.8042 12.9792 16 13.45 16 14C16 14.55 15.8042 15.0208 15.4125 15.4125C15.0208 15.8042 14.55 16 14 16ZM2 10C1.45 10 0.979167 9.80417 0.5875 9.4125C0.195833 9.02083 0 8.55 0 8C0 7.45 0.195833 6.97917 0.5875 6.5875C0.979167 6.19583 1.45 6 2 6C2.55 6 3.02083 6.19583 3.4125 6.5875C3.80417 6.97917 4 7.45 4 8C4 8.55 3.80417 9.02083 3.4125 9.4125C3.02083 9.80417 2.55 10 2 10ZM8 10C7.45 10 6.97917 9.80417 6.5875 9.4125C6.19583 9.02083 6 8.55 6 8C6 7.45 6.19583 6.97917 6.5875 6.5875C6.97917 6.19583 7.45 6 8 6C8.55 6 9.02083 6.19583 9.4125 6.5875C9.80417 6.97917 10 7.45 10 8C10 8.55 9.80417 9.02083 9.4125 9.4125C9.02083 9.80417 8.55 10 8 10ZM14 10C13.45 10 12.9792 9.80417 12.5875 9.4125C12.1958 9.02083 12 8.55 12 8C12 7.45 12.1958 6.97917 12.5875 6.5875C12.9792 6.19583 13.45 6 14 6C14.55 6 15.0208 6.19583 15.4125 6.5875C15.8042 6.97917 16 7.45 16 8C16 8.55 15.8042 9.02083 15.4125 9.4125C15.0208 9.80417 14.55 10 14 10ZM2 4C1.45 4 0.979167 3.80417 0.5875 3.4125C0.195833 3.02083 0 2.55 0 2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0C2.55 0 3.02083 0.195833 3.4125 0.5875C3.80417 0.979167 4 1.45 4 2C4 2.55 3.80417 3.02083 3.4125 3.4125C3.02083 3.80417 2.55 4 2 4ZM8 4C7.45 4 6.97917 3.80417 6.5875 3.4125C6.19583 3.02083 6 2.55 6 2C6 1.45 6.19583 0.979167 6.5875 0.5875C6.97917 0.195833 7.45 0 8 0C8.55 0 9.02083 0.195833 9.4125 0.5875C9.80417 0.979167 10 1.45 10 2C10 2.55 9.80417 3.02083 9.4125 3.4125C9.02083 3.80417 8.55 4 8 4ZM14 4C13.45 4 12.9792 3.80417 12.5875 3.4125C12.1958 3.02083 12 2.55 12 2C12 1.45 12.1958 0.979167 12.5875 0.5875C12.9792 0.195833 13.45 0 14 0C14.55 0 15.0208 0.195833 15.4125 0.5875C15.8042 0.979167 16 1.45 16 2C16 2.55 15.8042 3.02083 15.4125 3.4125C15.0208 3.80417 14.55 4 14 4Z" fill="#003D9B"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-[#003D9B]">View All</span>
            </div>
        </div>
    </div>

    <!-- Footer (optional, but included from HTML) -->
    <div class="pt-6 border-t border-[#C3C6D6]/10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-sm text-[#434654]">
        <div class="flex gap-6">
            <a href="#" class="hover:underline">Documentation</a>
            <a href="#" class="hover:underline">API Status</a>
            <a href="#" class="hover:underline">Security</a>
        </div>
        <div>Â© 2024 Admin BaaS. All partner trademarks are property of their respective owners.</div>
    </div>
</div>
@endsection
