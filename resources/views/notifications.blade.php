@extends('layouts.sidebar')

@section('title', 'Notifications | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="flex items-center gap-4 min-w-0">
        <h1 class="text-2xl font-semibold font-poppins">Notifications</h1>
        <span class="hidden md:block h-7 w-px bg-[#E2E8F0]"></span>
        <nav class="hidden md:flex items-center gap-2 text-sm font-semibold text-[#64748B]">
            <button class="px-4 h-8 rounded-full bg-[#0052CC] text-white">All</button>
            <button class="px-3 h-8">Orders</button>
            <button class="px-3 h-8">System</button>
            <button class="px-3 h-8">Security</button>
            <button class="px-3 h-8">Inventory</button>
        </nav>
    </div>

    <div class="flex items-center gap-4 shrink-0">
        <div class="hidden lg:flex w-64 h-11 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] items-center px-3 text-sm text-[#64748B]">Search notifications...</div>
        <button class="text-[#E11D48] text-sm font-medium">Clear All</button>
    </div>
</header>
@endsection

@section('content')
<div class="flex gap-6">
    <div class="flex-1 min-w-0 space-y-8">
        <section>
            <p class="text-[11px] tracking-[1.2px] uppercase font-bold text-[#94A3B8] mb-4">Today</p>
            <div class="space-y-3">
                <article class="bg-white rounded-2xl border border-[#CBD5E1] px-4 py-4 relative">
                    <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-2xl bg-[#F59E0B]"></div>
                    <div class="flex gap-4">
                        <div class="h-10 w-10 rounded-xl bg-[#FEF3C7] flex items-center justify-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 1L16 3.5V8.8C16 12.6 13.8 15.8 10 17C6.2 15.8 4 12.6 4 8.8V3.5L10 1Z" fill="#D97706"/></svg></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between gap-4"><h3 class="text-lg font-semibold">New login from unrecognized device</h3><span class="text-sm text-[#94A3B8]">10:24 AM</span></div>
                            <p class="text-base text-[#475569] truncate">A login attempt was detected from a Chrome browser on Linux in London,...</p>
                            <div class="mt-2 flex items-center gap-4 text-xs"><span class="text-[#64748B]">- MAIN HQ ADMIN</span><a class="text-[#0052CC] font-semibold" href="#">Review Security</a></div>
                        </div>
                    </div>
                </article>

                <article class="bg-white rounded-2xl border border-[#CBD5E1] px-4 py-4">
                    <div class="flex gap-4">
                        <div class="h-10 w-10 rounded-xl bg-[#FCE7F3] flex items-center justify-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="3" width="12" height="14" rx="2" stroke="#E11D48" stroke-width="1.8"/></svg></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between gap-4"><h3 class="text-lg font-semibold">Low Stock Warning: UltraPro Wireless</h3><span class="text-sm text-[#94A3B8]">08:15 AM</span></div>
                            <p class="text-base text-[#475569] truncate">Current inventory level is below 10 units. Consider restocking soon.</p>
                            <div class="mt-2 flex items-center gap-4 text-xs"><span class="text-[#64748B]">- DOWNTOWN OUTLET</span><a class="text-[#0052CC] font-semibold" href="#">Manage Inventory</a></div>
                        </div>
                    </div>
                </article>

                <article class="bg-white rounded-2xl border border-[#CBD5E1] px-4 py-4">
                    <div class="flex gap-4">
                        <div class="h-10 w-10 rounded-xl bg-[#DCFCE7] flex items-center justify-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 7.5H15V16.5H5V7.5Z" stroke="#059669" stroke-width="1.8"/></svg></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between gap-4"><h3 class="text-lg font-semibold">New high-value order #84920</h3><span class="text-sm text-[#94A3B8]">07:30 AM</span></div>
                            <p class="text-base text-[#475569] truncate">Order received for $2,499.00 from customer Sarah Jenkins.</p>
                            <div class="mt-2 flex items-center gap-4 text-xs"><span class="text-[#64748B]">- GLOBAL STOREFRONT</span><a class="text-[#0052CC] font-semibold" href="#">View Order</a></div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section>
            <p class="text-[11px] tracking-[1.2px] uppercase font-bold text-[#94A3B8] mb-4">Yesterday</p>
            <article class="bg-white rounded-2xl border border-[#CBD5E1] px-4 py-4">
                <div class="flex gap-4">
                    <div class="h-10 w-10 rounded-xl bg-[#DBEAFE] flex items-center justify-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="7" stroke="#2563EB" stroke-width="1.8"/></svg></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between gap-4"><h3 class="text-lg font-semibold">System update scheduled</h3><span class="text-sm text-[#94A3B8]">09:00 PM</span></div>
                        <p class="text-base text-[#475569] truncate">Platform maintenance will occur at 02:00 AM UTC. Expect 15 mins of...</p>
                        <div class="mt-2 flex items-center gap-4 text-xs"><span class="text-[#64748B]">- ALL TENANTS</span><a class="text-[#0052CC] font-semibold" href="#">Read Release Notes</a></div>
                    </div>
                </div>
            </article>
        </section>

        <section>
            <p class="text-[11px] tracking-[1.2px] uppercase font-bold text-[#94A3B8] mb-4">Older</p>
            <article class="bg-white rounded-2xl border border-[#E2E8F0] px-4 py-4 opacity-60">
                <div class="flex gap-4">
                    <div class="h-10 w-10 rounded-xl bg-[#F1F5F9] flex items-center justify-center"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8" stroke="#94A3B8" stroke-width="1.8"/></svg></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between gap-4"><h3 class="text-lg font-semibold text-[#334155]">Backup completed successfully</h3><span class="text-sm text-[#94A3B8]">Oct 24, 2023</span></div>
                        <p class="text-base text-[#64748B] truncate">Automated database backup for all nodes was successful.</p>
                        <div class="mt-2 text-xs text-[#64748B]">- SYSTEM CORE</div>
                    </div>
                </div>
            </article>
        </section>
    </div>

    <aside class="hidden xl:block w-80 shrink-0 bg-white border border-[#E2E8F0] rounded-xl overflow-y-auto">
        <div class="p-6 space-y-8">
            <h2 class="text-sm font-bold">Notification Settings</h2>

            <section class="space-y-4">
                <p class="text-[11px] tracking-[1.3px] uppercase font-bold text-[#94A3B8]">Channels</p>
                <div class="flex justify-between items-center"><div><p class="text-sm font-semibold">Email Notifications</p><p class="text-xs text-[#64748B]">Receive daily summaries</p></div><button class="w-9 h-5 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 w-4 h-4 rounded-full bg-white"></span></button></div>
                <div class="flex justify-between items-center"><div><p class="text-sm font-semibold">Browser Push Alerts</p><p class="text-xs text-[#64748B]">Real-time desktop alerts</p></div><button class="w-9 h-5 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 w-4 h-4 rounded-full bg-white"></span></button></div>
            </section>

            <section class="space-y-4">
                <p class="text-[11px] tracking-[1.3px] uppercase font-bold text-[#94A3B8]">Event Types</p>
                <div class="space-y-3 text-sm text-[#475569]">
                    <label class="flex justify-between items-center"><span>Order Updates</span><input type="checkbox" checked class="h-[18px] w-[18px] accent-[#0052CC]" /></label>
                    <label class="flex justify-between items-center"><span>Inventory Thresholds</span><input type="checkbox" checked class="h-[18px] w-[18px] accent-[#0052CC]" /></label>
                    <label class="flex justify-between items-center"><span>Security Alerts</span><input type="checkbox" checked class="h-[18px] w-[18px] accent-[#0052CC]" /></label>
                    <label class="flex justify-between items-center"><span>System Maintenance</span><input type="checkbox" checked class="h-[18px] w-[18px] accent-[#0052CC]" /></label>
                    <label class="flex justify-between items-center"><span>Customer Onboarding</span><input type="checkbox" class="h-[18px] w-[18px] accent-[#0052CC]" /></label>
                </div>
            </section>

            <div class="rounded-xl bg-[#0052CC]/5 border border-[#0052CC]/10 p-4">
                <p class="text-xs font-bold text-[#0052CC]">Pro Tip</p>
                <p class="text-sm text-[#0052CC]/80 leading-relaxed mt-1">Filter notifications by tenant store by using the search bar with <span class="bg-[#0052CC]/10 rounded px-1 font-mono">@storename</span></p>
            </div>
        </div>
    </aside>
</div>
@endsection
