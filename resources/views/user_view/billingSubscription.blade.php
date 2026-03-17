@extends('layouts.user.user-sidebar')

@section('title', 'Billing & Subscription | BaaS Core')
@section('sidebar_brand_title', 'BaaS Platform')
@section('sidebar_brand_subtitle', 'Management Console')

@section('sidebar_logo')
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
            d="M12 14L10 12L12 10L14 12L12 14ZM9.875 8.125L7.375 5.625L12 1L16.625 5.625L14.125 8.125L12 6L9.875 8.125ZM5.625 16.625L1 12L5.625 7.375L8.125 9.875L6 12L8.125 14.125L5.625 16.625ZM18.375 16.625L15.875 14.125L18 12L15.875 9.875L18.375 7.375L23 12L18.375 16.625ZM12 23L7.375 18.375L9.875 15.875L12 18L14.125 15.875L16.625 18.375L12 23Z"
            fill="white" />
    </svg>
@endsection

@section('topbar')
    <header
        class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <button id="sidebarToggle" onclick="openSidebar()"
            class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0"
            aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor" />
            </svg>
        </button>

        <div class="hidden sm:block relative w-full max-w-xs md:max-w-md">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#94A3B8]">
                <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M13.8333 15L8.58333 9.75C8.16667 10.0833 7.6875 10.3472 7.14583 10.5417C6.60417 10.7361 6.02778 10.8333 5.41667 10.8333C3.90278 10.8333 2.62153 10.309 1.57292 9.26042C0.524305 8.21181 0 6.93056 0 5.41667C0 3.90278 0.524305 2.62153 1.57292 1.57292C2.62153 0.524305 3.90278 0 5.41667 0C6.93056 0 8.21181 0.524305 9.26042 1.57292C10.309 2.62153 10.8333 3.90278 10.8333 5.41667C10.8333 6.02778 10.7361 6.60417 10.5417 7.14583C10.3472 7.6875 10.0833 8.16667 9.75 8.58333L15 13.8333L13.8333 15Z"
                        fill="currentColor" />
                </svg>
            </span>
            <input type="text" placeholder="Search billing records..."
                class="w-full h-10 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg pl-10 pr-3 text-sm text-[#0F172A] placeholder:text-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
        </div>

        <div class="flex items-center gap-3 ml-auto">
            <button class="relative p-2 rounded-full text-[#64748B] hover:bg-gray-100" aria-label="Notifications">
                <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z"
                        fill="currentColor" />
                </svg>
                <span class="absolute top-1.5 right-1.5 h-2 w-2 bg-[#EF4444] rounded-full border-2 border-white"></span>
            </button>
        </div>
    </header>
@endsection

@section('content')
    <div class="max-w-[1120px] mx-auto space-y-5">
        <section class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
            <div>
                <h1 class="text-2xl text-[#0F172A]">Billing & Subscription</h1>
                <p class="text-[#64748B] text-sm">Manage your professional plan, usage, and billing history.</p>
            </div>
            <span
                class="inline-flex h-8 items-center rounded-full bg-[#DCFCE7] px-4 text-xs font-semibold text-[#15803D] self-start">ACTIVE</span>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-8 bg-white border border-[#CBD5E1] rounded-xl overflow-hidden shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-[210px_minmax(0,1fr)]">
                    <div class="bg-[#F1F5F9] p-5 flex flex-col items-center justify-center text-center gap-2">
                        <div
                            class="h-12 w-12 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] flex items-center justify-center">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3L20 6V11C20 16 16.6 20.74 12 22C7.4 20.74 4 16 4 11V6L12 3Z" stroke="#0052CC"
                                    stroke-width="1.8" fill="none" />
                                <path d="M12 9V13M10 11H14" stroke="#0052CC" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                        <h3 class="text-2xl">Professional</h3>
                        <p class="text-xs text-[#64748B]">Billed monthly</p>
                    </div>
                    <div class="p-5 flex flex-col gap-5">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-[1.2px] font-bold text-[#0052CC]">Current Plan</p>
                                <h2 class="text-2xl mt-1">Professional Plan</h2>
                                <p class="text-[#475569] text-sm mt-1">Next billing cycle: Oct 12, 2023</p>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-5xl font-semibold leading-none">$49.00</p>
                                <p class="text-xs text-[#64748B] font-semibold mt-1">PER MONTH</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button class="h-10 px-6 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Change
                                Plan</button>
                            <button
                                class="h-10 px-6 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] text-[#475569] text-sm font-semibold">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 bg-white border border-[#CBD5E1] rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M2 10C2 5.58172 5.58172 2 10 2H14V4H10C6.68629 4 4 6.68629 4 10C4 13.3137 6.68629 16 10 16H14C16.2091 16 18 14.2091 18 12V11H10V9H18V10V12C18 15.3137 15.3137 18 12 18H10C5.58172 18 2 14.4183 2 10Z"
                            fill="#0052CC" />
                    </svg>
                    <h3 class="text-lg uppercase font-bold">Usage Summary</h3>
                </div>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-2"><span class="text-[#64748B]">API Calls</span><span
                                class="font-semibold text-[#0F172A]">75k / 100k</span></div>
                        <div class="h-2 rounded-full bg-[#E2E8F0]">
                            <div class="h-2 w-3/4 rounded-full bg-[#0052CC]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-2"><span class="text-[#64748B]">Storage</span><span
                                class="font-semibold">4.2 GB / 10 GB</span></div>
                        <div class="h-2 rounded-full bg-[#E2E8F0]">
                            <div class="h-2 w-[42%] rounded-full bg-[#0052CC]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-2"><span class="text-[#64748B]">Active Nodes</span><span
                                class="font-semibold">8 / 15</span></div>
                        <div class="h-2 rounded-full bg-[#E2E8F0]">
                            <div class="h-2 w-[53%] rounded-full bg-[#0052CC]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-6 bg-white border border-[#CBD5E1] rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg uppercase font-bold">Payment Methods</h3>
                    <button class="text-[#0052CC] font-semibold text-sm">Add New</button>
                </div>
                <div class="space-y-3">
                    <div
                        class="rounded-lg border border-[#93C5FD] bg-[#EFF6FF] p-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div
                                class="h-7 w-10 rounded bg-white border border-[#E2E8F0] flex items-center justify-center text-[10px] font-bold text-[#64748B]">
                                VISA</div>
                            <div>
                                <p class="font-semibold">Visa ending in 4242</p>
                                <p class="text-sm text-[#64748B]">Expires 12/26 - Default</p>
                            </div>
                        </div>
                        <div class="h-6 w-6 rounded-full border-2 border-[#0052CC] flex items-center justify-center">
                            <svg width="11" height="8" viewBox="0 0 11 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 4L4 7L10 1" stroke="#0052CC" stroke-width="1.7" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>
                    <div class="rounded-lg border border-[#E2E8F0] bg-white p-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <div
                                class="h-7 w-10 rounded bg-white border border-[#E2E8F0] flex items-center justify-center text-[10px] font-bold text-[#64748B]">
                                MC</div>
                            <div>
                                <p class="font-semibold">Mastercard ending in 8831</p>
                                <p class="text-sm text-[#64748B]">Expires 05/25</p>
                            </div>
                        </div>
                        <button class="text-xs uppercase tracking-[1px] font-bold text-[#94A3B8]">Set Default</button>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-6 bg-white border border-[#CBD5E1] rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg uppercase font-bold">Billing Details</h3>
                    <button class="text-[#0052CC] font-semibold text-sm">Edit</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs uppercase tracking-[1.2px] font-bold text-[#94A3B8] mb-1">Company Name</p>
                        <p class="text-xl font-semibold">Riviera Commerce Inc.</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[1.2px] font-bold text-[#94A3B8] mb-1">VAT/Tax ID</p>
                        <p class="text-xl font-semibold">EU938475029</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs uppercase tracking-[1.2px] font-bold text-[#94A3B8] mb-1">Billing Address</p>
                        <p class="text-[#475569] text-sm leading-6">123 Tech Avenue, Suite 400<br />San Francisco, CA 94103,
                            United States</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
                <h3 class="text-lg uppercase font-bold">Recent Invoices</h3>
                <button class="text-[#94A3B8] text-sm font-semibold flex items-center gap-1">View all <span
                        aria-hidden="true">></span></button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px]">
                    <thead class="bg-[#F8FAFC] text-[#94A3B8] text-xs uppercase tracking-[1px] font-bold">
                        <tr>
                            <th class="text-left px-6 py-3">Date</th>
                            <th class="text-left px-6 py-3">Invoice ID</th>
                            <th class="text-left px-6 py-3">Amount</th>
                            <th class="text-left px-6 py-3">Status</th>
                            <th class="text-right px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr class="border-t border-[#E2E8F0]">
                            <td class="px-6 py-4 text-[#64748B]">Sep 12, 2023</td>
                            <td class="px-6 py-4 font-semibold">INV-2023-009</td>
                            <td class="px-6 py-4 font-semibold">$49.00</td>
                            <td class="px-6 py-4"><span
                                    class="inline-flex rounded-md bg-[#DCFCE7] px-2 py-1 text-xs font-bold text-[#15803D]">PAID</span>
                            </td>
                            <td class="px-6 py-4 text-right text-[#0052CC]">
                                <button aria-label="Download invoice"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7 1V8M7 8L4.5 5.5M7 8L9.5 5.5M2 10.5V12H12V10.5" stroke="#0052CC"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr class="border-t border-[#E2E8F0]">
                            <td class="px-6 py-4 text-[#64748B]">Aug 12, 2023</td>
                            <td class="px-6 py-4 font-semibold">INV-2023-008</td>
                            <td class="px-6 py-4 font-semibold">$49.00</td>
                            <td class="px-6 py-4"><span
                                    class="inline-flex rounded-md bg-[#DCFCE7] px-2 py-1 text-xs font-bold text-[#15803D]">PAID</span>
                            </td>
                            <td class="px-6 py-4 text-right text-[#0052CC]">
                                <button aria-label="Download invoice"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7 1V8M7 8L4.5 5.5M7 8L9.5 5.5M2 10.5V12H12V10.5" stroke="#0052CC"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr class="border-t border-[#E2E8F0]">
                            <td class="px-6 py-4 text-[#64748B]">Jul 12, 2023</td>
                            <td class="px-6 py-4 font-semibold">INV-2023-007</td>
                            <td class="px-6 py-4 font-semibold">$84.50</td>
                            <td class="px-6 py-4"><span
                                    class="inline-flex rounded-md bg-[#FEF3C7] px-2 py-1 text-xs font-bold text-[#B45309]">PENDING</span>
                            </td>
                            <td class="px-6 py-4 text-right text-[#0052CC]">
                                <button aria-label="Download invoice"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-[#EFF6FF]">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7 1V8M7 8L4.5 5.5M7 8L9.5 5.5M2 10.5V12H12V10.5" stroke="#0052CC"
                                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="py-5 text-center text-sm text-[#94A3B8]">
            Secure payments processed by Stripe
            <span class="mx-3">&bull;</span>
            Need help?
            <a href="#" class="text-[#0052CC] font-semibold">Contact Support</a>
        </footer>
    </div>
@endsection