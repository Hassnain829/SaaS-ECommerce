@extends('layouts.user.user-Sidebar')

@section('title', 'Shipping Automation Settings | BaaS Core')
@section('sidebar_brand_title', 'BaaS Platform')
@section('sidebar_brand_subtitle', 'Management Console')

@section('sidebar_logo')
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 14L10 12L12 10L14 12L12 14ZM9.875 8.125L7.375 5.625L12 1L16.625 5.625L14.125 8.125L12 6L9.875 8.125ZM5.625 16.625L1 12L5.625 7.375L8.125 9.875L6 12L8.125 14.125L5.625 16.625ZM18.375 16.625L15.875 14.125L18 12L15.875 9.875L18.375 7.375L23 12L18.375 16.625ZM12 23L7.375 18.375L9.875 15.875L12 18L14.125 15.875L16.625 18.375L12 23Z" fill="white"/>
    </svg>
@endsection

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center gap-3">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>

        <div class="relative hidden md:block w-full max-w-[330px]">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                <svg width="16" height="16" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z" fill="currentColor"/></svg>
            </span>
            <input type="search" placeholder="Search shipping logs..." class="h-11 w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
        </div>

        <div class="ml-auto flex items-center gap-1.5 md:gap-2">
            <button class="grid h-10 w-10 place-items-center rounded-lg text-[#64748B] hover:bg-[#F1F5F9]" aria-label="Notifications">
                <svg width="18" height="18" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/></svg>
            </button>
            <a href="{{ route('generalSettings') }}" class="grid h-10 w-10 place-items-center rounded-lg text-[#64748B] hover:bg-[#F1F5F9]" aria-label="Settings">
                <svg width="18" height="18" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.3 20L6.9 16.8C6.68333 16.7167 6.47917 16.6167 6.2875 16.5C6.09583 16.3833 5.90833 16.2583 5.725 16.125L2.75 17.375L0 12.625L2.575 10.675C2.55833 10.5583 2.55 10.4458 2.55 10.3375C2.55 10.2292 2.55 10.1167 2.55 10C2.55 9.88333 2.55 9.77083 2.55 9.6625C2.55 9.55417 2.55833 9.44167 2.575 9.325L0 7.375L2.75 2.625L5.725 3.875C5.90833 3.74167 6.1 3.61667 6.3 3.5C6.5 3.38333 6.7 3.28333 6.9 3.2L7.3 0H12.8L13.2 3.2C13.4167 3.28333 13.6208 3.38333 13.8125 3.5C14.0042 3.61667 14.1917 3.74167 14.375 3.875L17.35 2.625L20.1 7.375L17.525 9.325C17.5417 9.44167 17.55 9.55417 17.55 9.6625C17.55 9.77083 17.55 9.88333 17.55 10C17.55 10.1167 17.55 10.2292 17.55 10.3375C17.55 10.4458 17.5333 10.5583 17.5 10.675L20.075 12.625L17.325 17.375L14.375 16.125C14.1917 16.2583 14 16.3833 13.8 16.5C13.6 16.6167 13.4 16.7167 13.2 16.8L12.8 20H7.3Z" fill="currentColor"/></svg>
            </a>
        </div>
    </header>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-[1440px]">
        <div class="mb-5 flex flex-wrap items-center gap-2 text-sm text-[#64748B]">
            <a href="{{ route('generalSettings') }}" class="hover:text-[#0F172A]">Settings</a>
            <span>&gt;</span>
            <span class="font-medium text-[#0F172A]">Shipping &amp; Courier Automation</span>
        </div>

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-3xl md:text-4xl text-[#0F172A]">Shipping Automation Settings</h1>
                <p class="mt-1.5 text-base md:text-lg text-[#64748B]">Manage courier integrations and smart fulfillment logic for your logistics workflow.</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="h-12 rounded-xl border border-[#D1D9E6] bg-white px-6 text-lg font-medium text-[#1E293B]">Export Logs</button>
                <button class="h-12 rounded-xl bg-[#0052CC] px-6 text-lg font-medium text-white">Save Changes</button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-[#D8E1EC] bg-white p-5 md:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="grid h-14 w-14 place-items-center rounded-2xl bg-[#EEF4FF] text-[#0052CC]">
                                <svg width="26" height="26" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.75 19.9375L11.8125 15.625L7.5 13.6875L11.8125 11.7188L13.75 7.4375L15.7188 11.7188L20 13.6875L15.7188 15.625L13.75 19.9375ZM13.75 27.4375C11.5 27.4375 9.39062 26.9219 7.42188 25.8906C5.45312 24.8594 3.8125 23.4167 2.5 21.5625V24.9375H0V17.4375H7.5V19.9375H4.4375C5.5 21.5 6.84896 22.724 8.48438 23.6094C10.1198 24.4948 11.875 24.9375 13.75 24.9375C16.1458 24.9375 18.3177 24.25 20.2656 22.875C22.2135 21.5 23.5833 19.6771 24.375 17.4062L26.8125 17.9688C25.875 20.8021 24.2083 23.0885 21.8125 24.8281C19.4167 26.5677 16.7292 27.4375 13.75 27.4375Z" fill="currentColor"/></svg>
                            </div>
                            <div>
                                <h2 class="text-2xl leading-tight text-[#0F172A]">Global Automation</h2>
                                <p class="text-sm text-[#64748B]">Automatically handle carrier selection, labels, and tracking.</p>
                            </div>
                        </div>
                        <button class="relative h-8 w-16 rounded-full bg-[#C5D7F5]" aria-label="Automation enabled"><span class="absolute right-0.5 top-0.5 h-7 w-7 rounded-full bg-[#0052CC]"></span></button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-[#D8E1EC] bg-white">
                    <div class="flex h-16 items-center justify-between border-b border-[#E2E8F0] px-5 md:px-6">
                        <h2 class="text-2xl leading-tight text-[#0F172A]">Integrated Courier Services</h2>
                        <button class="text-base font-medium text-[#0052CC]">+ Add Carrier</button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <article class="flex items-center justify-between gap-3 border-b border-[#EEF2F7] p-5 md:border-r"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">UPS</div><div><h3 class="text-xl leading-tight">UPS</h3><p class="text-sm text-[#64748B]">Next Day Air, Ground</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                        <article class="flex items-center justify-between gap-3 border-b border-[#EEF2F7] p-5"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">FDX</div><div><h3 class="text-xl leading-tight">FedEx</h3><p class="text-sm text-[#64748B]">Priority Overnight</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                        <article class="flex items-center justify-between gap-3 border-b border-[#EEF2F7] p-5 md:border-r"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">USP</div><div><h3 class="text-xl leading-tight">USPS</h3><p class="text-sm text-[#64748B]">Priority Mail</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                        <article class="flex items-center justify-between gap-3 border-b border-[#EEF2F7] p-5"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">DHL</div><div><h3 class="text-xl leading-tight">DHL Express</h3><p class="text-sm text-[#64748B]">Global forwarding</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                        <article class="flex items-center justify-between gap-3 border-b border-[#EEF2F7] p-5 md:border-r md:border-b-0"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">PUR</div><div><h3 class="text-xl leading-tight">Purolator</h3><p class="text-sm text-[#64748B]">Domestic Canada</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                        <article class="flex items-center justify-between gap-3 p-5"><div class="flex items-center gap-3"><div class="grid h-11 w-11 place-items-center rounded-xl bg-[#F1F5F9] font-semibold text-[#64748B]">CAN</div><div><h3 class="text-xl leading-tight">Canada Post</h3><p class="text-sm text-[#64748B]">Expedited Parcel</p></div></div><span class="rounded-md bg-[#DCFCE7] px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#15803D]">Connected</span></article>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#D8E1EC] bg-white p-5 md:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-2xl leading-tight text-[#0F172A]">Smart Routing Rules</h2>
                        <span class="rounded-md bg-[#F1F5F9] px-2 py-1 text-xs font-medium text-[#94A3B8]">AI-Driven</span>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-xl border border-[#BFD5FF] bg-[#F5F9FF] p-4"><div class="flex gap-3"><span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-[#0052CC]"><span class="h-2.5 w-2.5 rounded-full bg-[#0052CC]"></span></span><div class="flex-1"><div class="mb-1 flex items-center justify-between gap-2"><h3 class="text-lg leading-tight">Cheapest Reliable</h3><span class="text-xs font-semibold tracking-wide text-[#0052CC]">RECOMMENDED</span></div><p class="text-sm text-[#64748B]">Algorithm selects the lowest cost carrier that maintains a &gt;95% on-time delivery rate for the specific route.</p></div></div></div>
                        <div class="rounded-xl border border-[#E2E8F0] p-4"><div class="flex gap-3"><span class="mt-0.5 h-5 w-5 rounded-full border border-[#94A3B8]"></span><div><h3 class="text-lg leading-tight">Fastest Delivery</h3><p class="text-sm text-[#64748B]">Prioritize speed above all else. Selects the service with the earliest estimated delivery window.</p></div></div></div>
                        <div class="rounded-xl border border-[#E2E8F0] p-4"><div class="flex gap-3"><span class="mt-0.5 h-5 w-5 rounded-full border border-[#94A3B8]"></span><div><h3 class="text-lg leading-tight">Balanced Optimizer</h3><p class="text-sm text-[#64748B]">Optimizes for a mix of cost-effectiveness and premium tracking visibility for higher customer satisfaction.</p></div></div></div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-[#D8E1EC] bg-white p-5 md:p-6">
                    <h2 class="mb-4 text-2xl leading-tight">Regional Preferences</h2>
                    <label class="mb-3 block"><span class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-[#94A3B8]">Primary Market</span><select class="h-11 w-full rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-sm"><option>United States (US)</option></select></label>
                    <div class="rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] p-4">
                        <div class="mb-2 flex items-center justify-between"><p class="text-sm font-semibold text-[#2563EB]">Regional Logic Applied</p><button class="relative h-6 w-11 rounded-full bg-[#0052CC]" aria-label="Regional logic enabled"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button></div>
                        <p class="text-sm text-[#2563EB]">System will prioritize Canada Post and Purolator for domestic shipments when CA market is selected.</p>
                        <label class="mt-3 block"><span class="mb-1.5 block text-base font-medium text-[#737373]">Store Currency</span><select class="h-11 w-full rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#94A3B8]"><option>USD - United States Dollar ($)</option></select></label>
                        <label class="mt-3 block"><span class="mb-1.5 block text-base font-medium text-[#737373]">Time Zone</span><select class="h-11 w-full rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] px-3 text-[#94A3B8]"><option>(UTC-08:00) Pacific Time (US)</option></select></label>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#D8E1EC] bg-white p-5 md:p-6">
                    <h2 class="mb-4 text-2xl leading-tight">Automation Insights</h2>
                    <div class="space-y-4">
                        <div class="flex items-start justify-between"><div><p class="text-lg leading-tight">Active Label Gen</p><p class="text-sm text-[#64748B]">2,450 labels/mo</p></div><p class="text-lg leading-tight font-semibold text-[#16A34A]">+12.5%</p></div>
                        <div class="flex items-start justify-between"><div><p class="text-lg leading-tight">Smart Selection</p><p class="text-sm text-[#64748B]">98% of orders</p></div><p class="text-lg leading-tight text-[#2563EB]">↗</p></div>
                        <hr class="border-[#E2E8F0]" />
                        <div>
                            <p class="mb-3 text-xs font-bold uppercase tracking-widest text-[#94A3B8]">Carrier Health</p>
                            <div class="space-y-3 text-sm">
                                <div><p>UPS Performance</p><div class="mt-1.5 h-2 rounded-full bg-[#E2E8F0]"><div class="h-2 w-[88%] rounded-full bg-[#22C55E]"></div></div></div>
                                <div><p>FedEx Reliability</p><div class="mt-1.5 h-2 rounded-full bg-[#E2E8F0]"><div class="h-2 w-[84%] rounded-full bg-[#22C55E]"></div></div></div>
                                <div><p>Canada Post Efficiency</p><div class="mt-1.5 h-2 rounded-full bg-[#E2E8F0]"><div class="h-2 w-[74%] rounded-full bg-[#EAB308]"></div></div></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#D8E1EC] bg-white p-5 md:p-6">
                    <h2 class="mb-4 text-2xl leading-tight text-[#0F172A]">Notification Preferences</h2>
                    <div class="space-y-4">
                        <label class="flex items-start justify-between gap-3"><span><span class="block text-lg leading-tight">Email Notifications</span><span class="text-sm text-[#64748B]">Summary of daily transactions</span></span><input type="checkbox" checked class="mt-1 h-6 w-6 accent-[#0052CC]" /></label>
                        <label class="flex items-start justify-between gap-3"><span><span class="block text-lg leading-tight">SMS Alerts</span><span class="text-sm text-[#64748B]">Critical system failures only</span></span><input type="checkbox" class="mt-1 h-6 w-6 accent-[#0052CC]" /></label>
                        <button class="h-11 w-full rounded-xl bg-[#F1F5F9] text-sm font-semibold text-[#1E293B]">Add Email and Number</button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
