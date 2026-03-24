@extends('layouts.user.user-sidebar')

@section('title', 'Analytics | BaaS Core')

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

        <div class="flex items-center gap-3 min-w-0">
            <h1 class="text-xl md:text-[32px] text-[#0F172A] font-poppins">Business Insights</h1>
            <span class="hidden md:block h-6 w-px bg-[#E2E8F0]"></span>
            <div class="hidden md:block text-[#64748B] text-sm lg:text-base">Jan 1 - Mar 31, 2024</div>
        </div>

        <div class="flex items-center gap-3 md:gap-4">
            <button class="h-10 px-4 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Export PDF</button>
            <span class="h-6 w-px bg-[#E2E8F0]"></span>
            <button class="text-[#64748B]" aria-label="Notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 17H9V15H15V17ZM17 13H7V11H17V13ZM19 9H5V7H19V9Z" fill="currentColor" />
                </svg>
            </button>
        </div>
    </header>
@endsection

@section('content')
    <div class="flex gap-4">
        <div class="flex-1 min-w-0 space-y-6 max-w-[1200px]">
            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <article class="bg-white border border-[#CBD5E1] rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[#94A3B8] font-semibold text-xs">HEALTH: OPTIMAL</span>
                    </div>
                    <p class="text-[#475569] text-sm">Customer Acquisition Cost (CAC)</p>
                    <div class="mt-2 flex items-end gap-2">
                        <h3 class="text-4xl leading-none">$42.30</h3>
                        <span class="text-[#10B981] font-semibold text-sm">8.2%</span>
                    </div>
                    <div class="mt-4 h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                        <div class="h-2 w-[45%] bg-[#1557C9]"></div>
                    </div>
                    <p class="text-xs text-[#94A3B8] mt-2">Target: &lt; $50.00</p>
                </article>

                <article class="bg-white border border-[#CBD5E1] rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[#94A3B8] font-semibold text-xs">LTV/CAC: 4.2X</span>
                    </div>
                    <p class="text-[#475569] text-sm">Lifetime Value (LTV)</p>
                    <div class="mt-2 flex items-end gap-2">
                        <h3 class="text-4xl leading-none">$178.40</h3>
                        <span class="text-[#10B981] font-semibold text-sm">14.5%</span>
                    </div>
                    <div class="mt-4 h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                        <div class="h-2 w-[65%] bg-[#A855F7]"></div>
                    </div>
                    <p class="text-xs text-[#94A3B8] mt-2">Average customer duration: 8.4 months</p>
                </article>

                <article class="bg-white border border-[#CBD5E1] rounded-xl p-4 md:col-span-2 xl:col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[#F43F5E] font-semibold text-xs">HIGH ALERT</span>
                    </div>
                    <p class="text-[#475569] text-sm">Churn Rate (Monthly)</p>
                    <div class="mt-2 flex items-end gap-2">
                        <h3 class="text-4xl leading-none">2.8%</h3>
                        <span class="text-[#F43F5E] font-semibold text-sm">0.4%</span>
                    </div>
                    <div class="mt-4 h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                        <div class="h-2 w-[28%] bg-[#F43F5E]"></div>
                    </div>
                    <p class="text-xs text-[#94A3B8] mt-2">Benchmark: 2.1% (Retail Avg)</p>
                </article>
            </section>

            <section class="bg-white border border-[#CBD5E1] rounded-xl overflow-hidden">
                <div class="p-4 md:p-5 border-b border-[#E2E8F0] flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-2xl text-[#0F172A] font-poppins">Retention Cohort Analysis</h2>
                        <p class="text-[#64748B] text-sm">Percentage of active users by month after signup</p>
                    </div>
                    <button class="h-10 px-4 rounded-lg border border-[#CBD5E1] text-sm text-[#334155] bg-[#F8FAFC]">All
                        Categories</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-[#F8FAFC] text-[#64748B] uppercase text-[11px] tracking-[0.7px]">
                            <tr>
                                <th class="text-left px-4 py-3">Cohort Month</th>
                                <th class="text-left px-4 py-3">Users</th>
                                <th class="text-center px-4 py-3">M0</th>
                                <th class="text-center px-4 py-3">M1</th>
                                <th class="text-center px-4 py-3">M2</th>
                                <th class="text-center px-4 py-3">M3</th>
                                <th class="text-center px-4 py-3">M4</th>
                                <th class="text-center px-4 py-3">M5</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F1F5F9]">
                            <tr>
                                <td class="px-4 py-3 font-semibold">Oct 2023</td>
                                <td class="px-4 py-3 text-[#64748B]">1,240</td>
                                <td class="text-center bg-[#1D4ED8] text-white">100%</td>
                                <td class="text-center bg-[#3B82F6] text-white">78%</td>
                                <td class="text-center bg-[#4F8BE2] text-white">65%</td>
                                <td class="text-center bg-[#6A9BDE]">52%</td>
                                <td class="text-center bg-[#7EA8DF]">48%</td>
                                <td class="text-center bg-[#9BBBE6]">42%</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold">Nov 2023</td>
                                <td class="px-4 py-3 text-[#64748B]">1,520</td>
                                <td class="text-center bg-[#1D4ED8] text-white">100%</td>
                                <td class="text-center bg-[#3B82F6] text-white">82%</td>
                                <td class="text-center bg-[#4F8BE2] text-white">68%</td>
                                <td class="text-center bg-[#6A9BDE]">55%</td>
                                <td class="text-center bg-[#7EA8DF]">49%</td>
                                <td class="text-center">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold">Dec 2023</td>
                                <td class="px-4 py-3 text-[#64748B]">2,100</td>
                                <td class="text-center bg-[#1D4ED8] text-white">100%</td>
                                <td class="text-center bg-[#3B82F6] text-white">85%</td>
                                <td class="text-center bg-[#4F8BE2] text-white">72%</td>
                                <td class="text-center bg-[#7EA8DF]">58%</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold">Jan 2024</td>
                                <td class="px-4 py-3 text-[#64748B]">1,890</td>
                                <td class="text-center bg-[#1D4ED8] text-white">100%</td>
                                <td class="text-center bg-[#3B82F6] text-white">81%</td>
                                <td class="text-center bg-[#4F8BE2] text-white">70%</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-semibold">Feb 2024</td>
                                <td class="px-4 py-3 text-[#64748B]">2,450</td>
                                <td class="text-center bg-[#1D4ED8] text-white">100%</td>
                                <td class="text-center bg-[#3B82F6] text-white">84%</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <article class="bg-white border border-[#CBD5E1] rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <h3 class="text-2xl font-poppins">Sustainability Index</h3>
                        <span class="text-xs font-bold bg-[#D1FAE5] text-[#059669] px-3 py-1 rounded-full">Score:
                            88/100</span>
                    </div>
                    <div class="h-52 flex items-end gap-4 px-2">
                        <div class="w-12 bg-[#1557C9]/20 rounded-t-xl h-32 relative">
                            <div class="absolute bottom-0 left-0 right-0 h-16 bg-[#1557C9] rounded-t-xl"></div>
                        </div>
                        <div class="w-12 bg-[#1557C9]/20 rounded-t-xl h-40 relative">
                            <div class="absolute bottom-0 left-0 right-0 h-28 bg-[#1557C9] rounded-t-xl"></div>
                        </div>
                        <div class="w-12 bg-[#1557C9]/20 rounded-t-xl h-40 relative">
                            <div class="absolute bottom-0 left-0 right-0 h-12 bg-[#1557C9] rounded-t-xl"></div>
                        </div>
                        <div class="w-12 bg-[#1557C9]/20 rounded-t-xl h-40 relative">
                            <div class="absolute bottom-0 left-0 right-0 h-24 bg-[#1557C9] rounded-t-xl"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 text-center text-[11px] text-[#94A3B8] font-bold mt-2">
                        <span>GROWTH</span><span>MARGIN</span><span>CASH</span><span>BRAND</span>
                    </div>
                </article>

                <article class="bg-white border border-[#CBD5E1] rounded-xl p-5">
                    <h3 class="text-2xl mb-5 font-poppins">Store Category Health</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span class="font-inter font-medium">Electronics &amp;
                                    Hardware</span><span class="text-[#64748B]">92% Health</span></div>
                            <div class="h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                                <div class="h-2 w-[92%] bg-[#10B981]"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span class="font-inter font-medium">Fashion &amp;
                                    Apparel</span><span class="text-[#64748B]">74% Health</span></div>
                            <div class="h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                                <div class="h-2 w-[74%] bg-[#F59E0B]"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1"><span class="font-inter font-medium">Food &amp;
                                    Beverage</span><span class="text-[#64748B]">81% Health</span></div>
                            <div class="h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                                <div class="h-2 w-[81%] bg-[#10B981]"></div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </div>

        <aside class="hidden xl:flex w-80 shrink-0 bg-white border border-[#E2E8F0] rounded-xl flex-col">
            <div class="p-6 border-b border-[#F1F5F9]">
                <h3 class="text-2xl flex items-center gap-2 font-poppins"><span
                        class="w-2 h-2 rounded-full bg-[#10B981]"></span>Real-time Activity</h3>
            </div>
            <div class="flex-1 p-4 space-y-5 text-sm overflow-y-auto">
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#D1FAE5]"></div>
                    <div>
                        <p class="font-semibold">New Sale: $128.00</p>
                        <p class="text-xs text-[#64748B]">Electronics Store #04 - Just now</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#DBEAFE]"></div>
                    <div>
                        <p class="font-semibold">Traffic Spike Detected</p>
                        <p class="text-xs text-[#64748B]">Fashion Category - 2 mins ago</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#FEF3C7]"></div>
                    <div>
                        <p class="font-semibold">New Subscription Tier</p>
                        <p class="text-xs text-[#64748B]">Customer: Maria S. - 12 mins ago</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#D1FAE5]"></div>
                    <div>
                        <p class="font-semibold">New Sale: $42.50</p>
                        <p class="text-xs text-[#64748B]">Apparel Store #12 - 15 mins ago</p>
                    </div>
                </div>
                <div class="flex gap-3 opacity-60">
                    <div class="w-8 h-8 rounded-full bg-[#F1F5F9]"></div>
                    <div>
                        <p class="font-semibold">Subscription Cancelled</p>
                        <p class="text-xs text-[#64748B]">Service Store #01 - 45 mins ago</p>
                    </div>
                </div>
                <div class="flex gap-3 opacity-60">
                    <div class="w-8 h-8 rounded-full bg-[#D1FAE5]"></div>
                    <div>
                        <p class="font-semibold">New Sale: $210.00</p>
                        <p class="text-xs text-[#64748B]">Electronics Store #09 - 1 hr ago</p>
                    </div>
                </div>
            </div>
            <div
                class="h-14 border-t border-[#F1F5F9] bg-[#F8FAFC] flex items-center justify-center text-[11px] font-bold uppercase tracking-[1px] text-[#0052CC]">
                View Full Activity Log</div>
        </aside>
    </div>
@endsection