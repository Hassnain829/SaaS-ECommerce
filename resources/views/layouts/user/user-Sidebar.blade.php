<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard - BaaS Core')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-[#F5F7F8] flex flex-col md:flex-row md:overflow-hidden md:h-screen overflow-x-hidden font-[Inter]">
<div id="sidebarOverlay" class="hidden fixed inset-0 z-40 bg-black/30 md:hidden" onclick="closeSidebar()"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 shrink-0 bg-white border-r border-[#E2E8F0] flex flex-col transform -translate-x-full transition-transform duration-300 md:static md:translate-x-0 md:z-auto w-64 bg-white border-r border-gray-200 h-full">
    <div class="p-6 flex items-center gap-3">
        <div class="w-10 h-10 bg-[#0052CC] rounded-lg flex items-center justify-center shrink-0">
            @hasSection('sidebar_logo')
                @yield('sidebar_logo')
            @else
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 21L13 15L15 13L19 17L21 15L23 17L19 21ZM13 17L9 13L10 12L13 15L19 9L21 11L13 19L8 14L9 13L13 17ZM5 21C4.45 21 3.97917 20.8042 3.5875 20.4125C3.19583 20.0208 3 19.55 3 19V5C3 4.45 3.19583 3.97917 3.5875 3.5875C3.97917 3.19583 4.45 3 5 3H19C19.55 3 20.0208 3.19583 20.4125 3.5875C20.8042 3.97917 21 4.45 21 5V11L19 9V5H5V19H13L15 21H5Z" fill="white"/>
                </svg>
            @endif
        </div>
        <div>
            <div class="text-lg font-bold text-[#0F172A]">@yield('sidebar_brand_title', 'BaaS Core')</div>
            <div class="text-xs text-[#64748B]">@yield('sidebar_brand_subtitle', 'Enterprise Admin')</div>
        </div>
    </div>

    <nav class="flex-1 px-4 py-2 space-y-1 overflow-y-auto">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 6V0H18V6H10ZM0 10V0H8V10H0ZM10 18V8H18V18H10ZM0 18V12H8V18H0Z" fill="currentColor"/>
            </svg>
            <span class="text-sm font-medium">Dashboard</span></a>

        <a href="{{ route('products') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('products') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('products') ? 'font-semibold' : 'font-medium' }}">Products</span></a>

        <a href="{{ route('orders') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('orders') || request()->routeIs('orderViewDetails') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 20C5.45 20 4.97917 19.8042 4.5875 19.4125C4.19583 19.0208 4 18.55 4 18C4 17.45 4.19583 16.9792 4.5875 16.5875C4.97917 16.1958 5.45 16 6 16C6.55 16 7.02083 16.1958 7.4125 16.5875C7.80417 16.9792 8 17.45 8 18C8 18.55 7.80417 19.0208 7.4125 19.4125C7.02083 19.8042 6.55 20 6 20ZM16 20C15.45 20 14.9792 19.8042 14.5875 19.4125C14.1958 19.0208 14 18.55 14 18C14 17.45 14.1958 16.9792 14.5875 16.5875C14.9792 16.1958 15.45 16 16 16C16.55 16 17.0208 16.1958 17.4125 16.5875C17.8042 16.9792 18 17.45 18 18C18 18.55 17.8042 19.0208 17.4125 19.4125C17.0208 19.8042 16.55 20 16 20ZM5.15 4L7.55 9H14.55L17.3 4H5.15ZM4.2 2H18.95C19.3333 2 19.625 2.17083 19.825 2.5125C20.025 2.85417 20.0333 3.2 19.85 3.55L16.3 9.95C16.1167 10.2833 15.8708 10.5417 15.5625 10.725C15.2542 10.9083 14.9167 11 14.55 11H7.1L6 13H18V15H6C5.25 15 4.68333 14.6708 4.3 14.0125C3.91667 13.3542 3.9 12.7 4.25 12.05L5.6 9.6L2 2H0V0H3.25L4.2 2Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('orders') || request()->routeIs('orderViewDetails') ? 'font-semibold' : 'font-medium' }}">Orders</span></a>

        <a href="{{ route('customers') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('customers') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}"><svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 16V13.2C0 12.6333 0.145833 12.1125 0.4375 11.6375C0.729167 11.1625 1.11667 10.8 1.6 10.55C2.63333 10.0333 3.68333 9.64583 4.75 9.3875C5.81667 9.12917 6.9 9 8 9C9.1 9 10.1833 9.12917 11.25 9.3875C12.3167 9.64583 13.3667 10.0333 14.4 10.55C14.8833 10.8 15.2708 11.1625 15.5625 11.6375C15.8542 12.1125 16 12.6333 16 13.2V16H0ZM18 16V13C18 12.2667 17.7958 11.5625 17.3875 10.8875C16.9792 10.2125 16.4 9.63333 15.65 9.15C16.5 9.25 17.3 9.42083 18.05 9.6625C18.8 9.90417 19.5 10.2 20.15 10.55C20.75 10.8833 21.2083 11.2542 21.525 11.6625C21.8417 12.0708 22 12.5167 22 13V16H18ZM8 8C6.9 8 5.95833 7.60833 5.175 6.825C4.39167 6.04167 4 5.1 4 4C4 2.9 4.39167 1.95833 5.175 1.175C5.95833 0.391667 6.9 0 8 0C9.1 0 10.0417 0.391667 10.825 1.175C11.6083 1.95833 12 2.9 12 4C12 5.1 11.6083 6.04167 10.825 6.825C10.0417 7.60833 9.1 8 8 8Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('customers') ? 'font-semibold' : 'font-medium' }}">Customers</span></a>

        <a href="{{ route('analytics') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('analytics') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 14H6V9H4V14ZM12 14H14V4H12V14ZM8 14H10V11H8V14ZM8 9H10V7H8V9ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('analytics') ? 'font-semibold' : 'font-medium' }}">Analytics</span></a>

        <a href="{{ route('notifications') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('notifications') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('notifications') ? 'font-semibold' : 'font-medium' }}">Notifications</span></a>

        <div class="pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-[#94A3B8] px-3">Settings</div>

        <a href="{{ route('billingSubscription') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('billingSubscription') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 5C2 3.89543 2.89543 3 4 3H16C17.1046 3 18 3.89543 18 5V15C18 16.1046 17.1046 17 16 17H4C2.89543 17 2 16.1046 2 15V5ZM4 7H16V5H4V7ZM4 11V15H16V11H4Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('billingSubscription') ? 'font-semibold' : 'font-medium' }}">Billing</span></a>

        <a href="{{ route('generalSettings') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('generalSettings') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7.3 20L6.9 16.8C6.68333 16.7167 6.47917 16.6167 6.2875 16.5C6.09583 16.3833 5.90833 16.2583 5.725 16.125L2.75 17.375L0 12.625L2.575 10.675C2.55833 10.5583 2.55 10.4458 2.55 10.3375C2.55 10.2292 2.55 10.1167 2.55 10C2.55 9.88333 2.55 9.77083 2.55 9.6625C2.55 9.55417 2.55833 9.44167 2.575 9.325L0 7.375L2.75 2.625L5.725 3.875C5.90833 3.74167 6.1 3.61667 6.3 3.5C6.5 3.38333 6.7 3.28333 6.9 3.2L7.3 0H12.8L13.2 3.2C13.4167 3.28333 13.6208 3.38333 13.8125 3.5C14.0042 3.61667 14.1917 3.74167 14.375 3.875L17.35 2.625L20.1 7.375L17.525 9.325C17.5417 9.44167 17.55 9.55417 17.55 9.6625C17.55 9.77083 17.55 9.88333 17.55 10C17.55 10.1167 17.55 10.2292 17.55 10.3375C17.55 10.4458 17.5333 10.5583 17.5 10.675L20.075 12.625L17.325 17.375L14.375 16.125C14.1917 16.2583 14 16.3833 13.8 16.5C13.6 16.6167 13.4 16.7167 13.2 16.8L12.8 20H7.3Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('generalSettings') ? 'font-semibold' : 'font-medium' }}">General</span></a>

    <!--    <a href="{{ route('shippingAutomation') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('shippingAutomation') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 17C5.45 17 4.97917 16.8042 4.5875 16.4125C4.19583 16.0208 4 15.55 4 15C4 14.45 4.19583 13.9792 4.5875 13.5875C4.97917 13.1958 5.45 13 6 13C6.55 13 7.02083 13.1958 7.4125 13.5875C7.80417 13.9792 8 14.45 8 15C8 15.55 7.80417 16.0208 7.4125 16.4125C7.02083 16.8042 6.55 17 6 17ZM14 17C13.45 17 12.9792 16.8042 12.5875 16.4125C12.1958 16.0208 12 15.55 12 15C12 14.45 12.1958 13.9792 12.5875 13.5875C12.9792 13.1958 13.45 13 14 13C14.55 13 15.0208 13.1958 15.4125 13.5875C15.8042 13.9792 16 14.45 16 15C16 15.55 15.8042 16.0208 15.4125 16.4125C15.0208 16.8042 14.55 17 14 17ZM3 3H7L9 7H17C17.2833 7 17.5208 7.09583 17.7125 7.2875C17.9042 7.47917 18 7.71667 18 8C18 8.08333 17.9917 8.17083 17.975 8.2625C17.9583 8.35417 17.925 8.44167 17.875 8.525L16.1 11.75C15.9167 12.0833 15.6708 12.3333 15.3625 12.5C15.0542 12.6667 14.7167 12.75 14.35 12.75H8.25C7.88333 12.75 7.56667 12.675 7.3 12.525C7.03333 12.375 6.83333 12.1667 6.7 11.9L3 4H1V2H3V3Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('shippingAutomation') ? 'font-semibold' : 'font-medium' }}">Shipping</span></a> -->

        <a href="{{ route('security') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('security') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 20C5.68333 19.4167 3.77083 18.0875 2.2625 16.0125C0.754167 13.9375 0 11.6333 0 9.1V3L8 0L16 3V9.1C16 11.6333 15.2458 13.9375 13.7375 16.0125C12.2292 18.0875 10.3167 19.4167 8 20ZM8 17.9C9.61667 17.4 10.9667 16.4125 12.05 14.9375C13.1333 13.4625 13.7667 11.8167 13.95 10H8V2.125L2 4.375V9.1C2 9.28333 2 9.43333 2 9.55C2 9.66667 2.01667 9.81667 2.05 10H8V17.9Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('security') ? 'font-semibold' : 'font-medium' }}">Security</span></a>

  <!--      <a href="{{ route('profileSettings') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('profileSettings') ? 'bg-[#0052CC]/10 text-[#0052CC]' : 'text-[#475569] hover:bg-gray-50' }}">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 10C8.9 10 7.95833 9.60833 7.175 8.825C6.39167 8.04167 6 7.1 6 6C6 4.9 6.39167 3.95833 7.175 3.175C7.95833 2.39167 8.9 2 10 2C11.1 2 12.0417 2.39167 12.825 3.175C13.6083 3.95833 14 4.9 14 6C14 7.1 13.6083 8.04167 12.825 8.825C12.0417 9.60833 11.1 10 10 10ZM2 18V15.2C2 14.6333 2.14583 14.1125 2.4375 13.6375C2.72917 13.1625 3.11667 12.8 3.6 12.55C4.63333 12.0333 5.68333 11.6458 6.75 11.3875C7.81667 11.1292 8.9 11 10 11C11.1 11 12.1833 11.1292 13.25 11.3875C14.3167 11.6458 15.3667 12.0333 16.4 12.55C16.8833 12.8 17.2708 13.1625 17.5625 13.6375C17.8542 14.1125 18 14.6333 18 15.2V18H2Z" fill="currentColor"/>
            </svg>
            <span class="text-sm {{ request()->routeIs('profileSettings') ? 'font-semibold' : 'font-medium' }}">Profile</span></a> -->
    </nav>

    <div class="border-t border-gray-200 p-4 space-y-2">
        <a href="{{ route('profileSettings') }}" class="flex items-center gap-3 rounded-xl p-2 transition-colors {{ request()->routeIs('profileSettings') ? 'bg-[#0052CC]/10' : 'hover:bg-[#0052CC]/5' }}">
            <div class="w-8 h-8 rounded-full bg-[#E2E8F0] overflow-hidden shrink-0">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <circle cx="16" cy="12" r="5" fill="#94A3B8"/>
                    <path d="M26 26C26 22 22 20 16 20C10 20 6 22 6 26" fill="#94A3B8"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="text-sm font-semibold text-[#0F172A] truncate">Alex Rivers</div>
                <div class="text-xs {{ request()->routeIs('profileSettings') ? 'text-[#0052CC]' : 'text-[#64748B]' }}">Admin Account</div>
            </div>
        </a>
        <a href="{{ route('register') }}" class="flex items-center justify-center gap-2 rounded-xl p-2.5 text-sm font-semibold text-[#BA1A1A] border border-[#FFDAD6] bg-[#FFF6F5] hover:bg-[#FFEDEC] transition-colors">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M6 14C4.33333 14 2.91667 13.4167 1.75 12.25C0.583333 11.0833 0 9.66667 0 8C0 6.33333 0.583333 4.91667 1.75 3.75C2.91667 2.58333 4.33333 2 6 2H9V4H6C4.9 4 3.95833 4.39167 3.175 5.175C2.39167 5.95833 2 6.9 2 8C2 9.1 2.39167 10.0417 3.175 10.825C3.95833 11.6083 4.9 12 6 12H9V14H6ZM11 11L9.625 9.55L11.175 8H5V6H11.175L9.625 4.45L11 3L15 7L11 11Z" fill="currentColor"/>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden">
    @hasSection('topbar')
        @yield('topbar')
    @endif

    <div class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6">
        @yield('content')
    </div>
</main>

<script>
    function closeAllProfileMenus() {
      document.querySelectorAll('.profileMenu').forEach(function (menu) {
        menu.classList.add('hidden');
      });
    }

    function initTopbarProfileMenu(profileSettingsUrl, logoutUrl) {
      var headers = document.querySelectorAll('header');
      headers.forEach(function (header) {
        var avatar = header.querySelector('div.rounded-full.overflow-hidden');
        if (avatar && avatar.closest('aside')) return;
        if (avatar && avatar.closest('.profile-menu-wrapper')) return;

        function createProfileWrapper() {
          var wrapper = document.createElement('div');
          wrapper.className = 'relative profile-menu-wrapper shrink-0';

          var trigger = document.createElement('button');
          trigger.type = 'button';
          trigger.className = 'w-9 h-9 rounded-full bg-[#E2E8F0] border border-[#CBD5E1] overflow-hidden shrink-0 profileMenuToggle';
          trigger.setAttribute('aria-haspopup', 'true');
          trigger.setAttribute('aria-expanded', 'false');
          trigger.setAttribute('aria-label', 'Open profile menu');
          trigger.innerHTML =
            '<svg width=\"36\" height=\"36\" viewBox=\"0 0 36 36\" fill=\"none\">' +
            '<circle cx=\"18\" cy=\"13\" r=\"6\" fill=\"#94A3B8\"/>' +
            '<path d=\"M28 28C28 24 24 22 18 22C12 22 8 24 8 28\" fill=\"#94A3B8\"/>' +
            '</svg>';

          var menu = document.createElement('div');
          menu.className = 'profileMenu hidden absolute right-0 mt-2 w-44 bg-white border border-[#E2E8F0] rounded-xl shadow-[0_10px_30px_rgba(2,6,23,0.12)] py-1 z-50';
          menu.innerHTML =
            '<a href=\"' + profileSettingsUrl + '\" class=\"block px-4 py-2 text-sm text-[#0F172A] hover:bg-[#F8FAFC]\">Profile Settings</a>' +
            '<a href=\"' + logoutUrl + '\" class=\"block px-4 py-2 text-sm text-[#BA1A1A] hover:bg-[#FFF1F1]\">Logout</a>';

          wrapper.appendChild(trigger);
          wrapper.appendChild(menu);
          return { wrapper: wrapper, trigger: trigger, menu: menu };
        }

        var parts = createProfileWrapper();
        var wrapper = parts.wrapper;
        var trigger = parts.trigger;
        var menu = parts.menu;

        if (avatar) {
          trigger.className = avatar.className + ' profileMenuToggle';
          trigger.innerHTML = avatar.innerHTML;
          avatar.replaceWith(wrapper);
        } else {
          var rightActions = header.querySelector('.flex.items-center.gap-3.shrink-0, .flex.items-center.gap-4.shrink-0, .flex.items-center.gap-2.shrink-0');
          if (rightActions) {
            rightActions.appendChild(wrapper);
          } else {
            header.appendChild(wrapper);
          }
        }

        trigger.addEventListener('click', function (e) {
          e.stopPropagation();
          var isOpen = !menu.classList.contains('hidden');
          closeAllProfileMenus();
          if (!isOpen) {
            menu.classList.remove('hidden');
            trigger.setAttribute('aria-expanded', 'true');
          } else {
            trigger.setAttribute('aria-expanded', 'false');
          }
        });
      });

      document.addEventListener('click', function () {
        closeAllProfileMenus();
      });
    }

    function openSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebarOverlay');
      if (!sidebar || !overlay) return;
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }

    function closeSidebar() {
      var sidebar = document.getElementById('sidebar');
      var overlay = document.getElementById('sidebarOverlay');
      if (!sidebar || !overlay) return;
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 768) {
        var overlay = document.getElementById('sidebarOverlay');
        if (overlay) overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }
    });

    document.addEventListener('DOMContentLoaded', function () {
      initTopbarProfileMenu("{{ route('profileSettings') }}", "{{ route('register') }}");
    });
</script>
</body>
</html>
