@extends('layouts.user.user-sidebar')

@section('title', 'Customers | BaaS Core')

@section('topbar')
    <header
        class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
        <button id="sidebarToggle" onclick="openSidebar()"
            class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0"
            aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor" />
            </svg>
        </button>

        <div
            class="hidden md:flex w-full md:w-[460px] max-w-full h-11 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] items-center px-4 text-[#64748B] text-sm">
            Search orders, products, or reports...
        </div>

        <div class="ml-auto flex items-center gap-4">
            <button class="bg-[#0052CC] text-white text-sm font-bold px-4 h-10 rounded-lg shadow-sm">+ Add Customer</button>
            <span class="h-6 w-px bg-[#E2E8F0]"></span>

            <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
                <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z"
                        fill="#64748B" />
                </svg>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
            </button>

            <button class="p-2 rounded-full hover:bg-gray-100 transition-colors text-[#64748B]">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 15H11V13H9V15ZM9 11H11V5H9V11Z"
                        fill="currentColor" />
                </svg>
            </button>
        </div>
    </header>
@endsection

@section('content')
    <div class="w-full space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-4xl text-[#0F172A] font-poppins">Customers</h1>
                <p class="text-base text-[#64748B] mt-1">Manage and view detailed insights of your customer base.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="h-9 px-4 rounded-full bg-[#0052CC] text-white text-sm font-semibold">All Customers</button>
            <button
                class="h-9 px-4 rounded-full bg-white border border-[#E2E8F0] text-[#475569] text-sm font-semibold">New</button>
            <button
                class="h-9 px-4 rounded-full bg-white border border-[#E2E8F0] text-[#475569] text-sm font-semibold">Returning</button>
            <button
                class="h-9 px-4 rounded-full bg-white border border-[#E2E8F0] text-[#475569] text-sm font-semibold">Subscribed</button>
            <button class="h-9 px-4 rounded-full bg-white border border-[#E2E8F0] text-[#475569] text-sm font-semibold">High
                Value</button>
            <div
                class="ml-auto w-full lg:w-[380px] h-11 rounded-xl bg-white border border-[#E2E8F0] px-4 flex items-center text-sm text-[#6B7280]">
                Search by name or email...</div>
        </div>

        <section class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px]">
                    <thead
                        class="bg-[#F8FAFC] border-b border-[#F1F5F9] text-[12px] uppercase tracking-[0.6px] text-[#64748B] font-bold">
                        <tr>
                            <th class="text-left px-6 py-4">Customer</th>
                            <th class="text-left px-4 py-4">Store Category</th>
                            <th class="text-center px-4 py-4">Orders</th>
                            <th class="text-right px-4 py-4">Total Spent</th>
                            <th class="text-left px-4 py-4">Last Active</th>
                            <th class="text-left px-4 py-4">Status</th>
                            <th class="text-right px-6 py-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <tr class="border-b border-[#F1F5F9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0]"></div>
                                    <div>
                                        <p class="font-bold">Sarah Chen</p>
                                        <p class="text-xs text-[#64748B]">sarah.chen@example.com</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex px-2.5 py-1 rounded border border-[#E2E8F0] bg-[#F1F5F9] text-xs font-medium text-[#475569]">Fashion</span>
                            </td>
                            <td class="px-4 py-4 text-center font-medium">18</td>
                            <td class="px-4 py-4 text-right font-bold">$2,450.00</td>
                            <td class="px-4 py-4 text-[#475569]">Oct 20, 2023</td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#059669]">Active</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile') }}" class="text-[#0052CC] text-xs font-bold">View
                                    Profile</a>
                            </td>
                        </tr>
                        <tr class="border-b border-[#F1F5F9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0]"></div>
                                    <div>
                                        <p class="font-bold">Michael Ross</p>
                                        <p class="text-xs text-[#64748B]">m.ross@corporate.io</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex px-2.5 py-1 rounded border border-[#E2E8F0] bg-[#F1F5F9] text-xs font-medium text-[#475569]">Electronics</span>
                            </td>
                            <td class="px-4 py-4 text-center font-medium">54</td>
                            <td class="px-4 py-4 text-right font-bold">$12,890.25</td>
                            <td class="px-4 py-4 text-[#475569]">Oct 24, 2023</td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#059669]">Active</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile') }}" class="text-[#0052CC] text-xs font-bold">View
                                    Profile</a>
                            </td>
                        </tr>
                        <tr class="border-b border-[#F1F5F9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0]"></div>
                                    <div>
                                        <p class="font-bold">Elena Rodriguez</p>
                                        <p class="text-xs text-[#64748B]">elena.r@design.co</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex px-2.5 py-1 rounded border border-[#E2E8F0] bg-[#F1F5F9] text-xs font-medium text-[#475569]">Home
                                    Office</span></td>
                            <td class="px-4 py-4 text-center font-medium">6</td>
                            <td class="px-4 py-4 text-right font-bold">$842.00</td>
                            <td class="px-4 py-4 text-[#475569]">Oct 17, 2023</td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#F8FAFC] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#64748B]">Inactive</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile') }}" class="text-[#0052CC] text-xs font-bold">View
                                    Profile</a>
                            </td>
                        </tr>
                        <tr class="border-b border-[#F1F5F9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0]"></div>
                                    <div>
                                        <p class="font-bold">David Park</p>
                                        <p class="text-xs text-[#64748B]">dpark@techcloud.com</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex px-2.5 py-1 rounded border border-[#E2E8F0] bg-[#F1F5F9] text-xs font-medium text-[#475569]">Electronics</span>
                            </td>
                            <td class="px-4 py-4 text-center font-medium">29</td>
                            <td class="px-4 py-4 text-right font-bold">$5,630.50</td>
                            <td class="px-4 py-4 text-[#475569]">Oct 23, 2023</td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#059669]">Active</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile') }}" class="text-[#0052CC] text-xs font-bold">View
                                    Profile</a>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0]"></div>
                                    <div>
                                        <p class="font-bold">Anna Schmidt</p>
                                        <p class="text-xs text-[#64748B]">anna.s@berlin-style.com</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex px-2.5 py-1 rounded border border-[#E2E8F0] bg-[#F1F5F9] text-xs font-medium text-[#475569]">Fashion</span>
                            </td>
                            <td class="px-4 py-4 text-center font-medium">12</td>
                            <td class="px-4 py-4 text-right font-bold">$1,210.00</td>
                            <td class="px-4 py-4 text-[#475569]">Oct 12, 2023</td>
                            <td class="px-4 py-4"><span
                                    class="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#059669]">Active</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile') }}" class="text-[#0052CC] text-xs font-bold">View
                                    Profile</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="bg-[#F8FAFC]/50 border-t border-[#F1F5F9] px-4 md:px-6 h-14 flex items-center justify-between text-xs text-[#64748B]">
                <p>Showing 1 to 5 of 1,248 customers</p>
                <div class="flex items-center gap-1">
                    <button class="w-8 h-8 rounded text-[#94A3B8]">&lsaquo;</button>
                    <button class="w-8 h-8 rounded bg-[#0052CC] text-white font-bold">1</button>
                    <button class="w-8 h-8 rounded text-[#0F172A] font-bold">2</button>
                    <button class="w-8 h-8 rounded text-[#0F172A] font-bold">3</button>
                    <span class="px-2 text-[#94A3B8]">...</span>
                    <button class="w-8 h-8 rounded text-[#0F172A] font-bold">250</button>
                    <button class="w-8 h-8 rounded text-[#0F172A]">&rsaquo;</button>
                </div>
            </div>
        </section>
    </div>
@endsection