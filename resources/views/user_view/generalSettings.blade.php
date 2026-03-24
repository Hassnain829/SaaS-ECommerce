@extends('layouts.user.user-sidebar')

@section('title', 'General Settings | BaaS Core')

@section('sidebar_logo')
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 14L10 12L12 10L14 12L12 14ZM9.875 8.125L7.375 5.625L12 1L16.625 5.625L14.125 8.125L12 6L9.875 8.125ZM5.625 16.625L1 12L5.625 7.375L8.125 9.875L6 12L8.125 14.125L5.625 16.625ZM18.375 16.625L15.875 14.125L18 12L15.875 9.875L18.375 7.375L23 12L18.375 16.625ZM12 23L7.375 18.375L9.875 15.875L12 18L14.125 15.875L16.625 18.375L12 23Z" fill="white"/>
    </svg>
@endsection

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <h1 class="text-lg md:text-xl font-poppins font-semibold">General Settings</h1>
        <div class="flex items-center gap-2 md:gap-3 ml-auto">
            <button class="h-10 px-4 rounded-lg text-sm font-semibold text-[#475569] hover:bg-[#F1F5F9]">Discard</button>
            <button class="h-10 px-5 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Save Changes</button>
        </div>
    </header>
@endsection

@section('content')
    <div class="max-w-9xl mx-auto px-4 lg:px-0 space-y-8">
        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl font-poppins">Store Profile</h2>
                <p class="text-sm text-[#64748B]">Public identity and appearance of your storefront.</p>
            </div>
            <div class="p-5 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-[128px_minmax(0,1fr)] gap-5">
                    <div>
                        <p class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B] mb-2">Store Logo</p>
                        <button class="h-32 w-32 rounded-xl border-2 border-dashed border-[#CBD5E1] bg-[#F8FAFC] flex flex-col items-center justify-center gap-2 text-[#94A3B8]">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 12V3.85L4.4 6.45L3 5L8 0L13 5L11.6 6.45L9 3.85V12H7ZM2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V11H2V14H14V11H16V14C16 14.55 15.8042 15.0208 15.4125 15.4125C15.0208 15.8042 14.55 16 14 16H2Z" fill="currentColor"/></svg>
                            <span class="text-[10px] uppercase font-bold tracking-[0.8px]">Upload Logo</span>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Store Name</span>
                                <input value="Modern Commerce Hub" class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm" />
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Contact Email</span>
                                <input value="hello@moderncommerce.com" class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm" />
                            </label>
                        </div>
                        <label class="space-y-1.5 block">
                            <span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Physical Address</span>
                            <textarea class="w-full rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm h-11 resize-none">123 Commerce St, San Francisco, CA 94103</textarea>
                        </label>
                    </div>
                </div>

                <hr class="border-[#F1F5F9]" />
                <div>
                    <h3 class="text-sm uppercase font-poppins tracking-[0.7px] font-bold mb-3">Branding</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex items-center gap-4">
                            <div class="p-2 rounded-lg bg-white border border-[#E2E8F0]"><div class="w-7 h-6 bg-[#0052CC] rounded-[2px]"></div></div>
                            <div><p class="font-semibold">Primary Color</p><p class="text-xs text-[#64748B]">Buttons, links, and active states.</p></div>
                        </div>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex items-center gap-4">
                            <div class="p-2 rounded-lg bg-white border border-[#E2E8F0]"><div class="w-7 h-6 bg-[#0F172A] rounded-[2px]"></div></div>
                            <div><p class="font-semibold">Secondary Color</p><p class="text-xs text-[#64748B]">Navigation backgrounds and accents.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl font-poppins">Regional & Financials</h2>
                <p class="text-sm text-[#64748B]">Localization, currency, and tax configurations.</p>
            </div>
            <div class="p-5 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="space-y-1.5"><span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Currency</span><select class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm"><option>USD - US Dollar</option></select></label>
                    <label class="space-y-1.5"><span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Timezone</span><select class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm"><option>(GMT-08:00) Pacific Time</option></select></label>
                    <label class="space-y-1.5"><span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Language</span><select class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm"><option>English (US)</option></select></label>
                </div>
                <hr class="border-[#F1F5F9]" />
                <div class="space-y-3">
                    <h3 class="text-sm uppercase font-poppins tracking-[0.7px] font-bold">Taxes & VAT</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="space-y-1.5">
                            <span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Tax ID / VAT Number</span>
                            <input placeholder="e.g. US123456789" class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm" />
                        </label>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex items-center justify-between">
                            <div><p class="font-semibold">Automated Tax Calculation</p><p class="text-xs text-[#64748B]">Calculate taxes based on customer location.</p></div>
                            <button class="h-6 w-11 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl font-poppins">Business Configuration</h2>
                <p class="text-sm text-[#64748B]">Operational status and market placement.</p>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-3">
                        <label class="space-y-1.5 block">
                            <span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Main Category</span>
                            <select class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm"><option>Electronics & Gadgets</option></select>
                        </label>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex items-center justify-between">
                            <div>
                                <p class="font-semibold">Maintenance Mode</p>
                                <p class="text-xs text-[#64748B]">Show a placeholder page to visitors.</p>
                            </div>
                            <button class="h-6 w-11 rounded-full bg-[#CBD5E1] relative"><span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                        </div>
                        <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex items-center justify-between">
                            <div>
                                <p class="font-semibold">Store Availability</p>
                                <p class="text-xs text-[#64748B]">Toggle public access to the storefront.</p>
                            </div>
                            <button class="h-6 w-11 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] p-4 flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">Shipping & Courier Automation</p>
                                <p class="text-xs text-[#64748B]">Automatically calculate rates and labels.</p>
                            </div>
                            <button class="h-6 w-11 rounded-full bg-[#0052CC] relative shrink-0"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                        </div>
                        <div class="mt-8">
                            <p class="text-[10px] uppercase tracking-[0.8px] text-[#94A3B8] font-bold text-right mb-2">Integrated Carriers</p>
                            <div class="flex items-center justify-end gap-5 text-sm font-semibold">
                                <span class="text-[#1E293B]">UPS</span>
                                <span class="text-[#1D4ED8]">FedEx</span>
                                <span class="text-[#DC2626]">DHL</span>
                                <span class="text-[#DC2626]">CANADA POST</span>
                            </div>
                        </div>
                        <a href="{{ route('shippingAutomation') }}" class="mt-4 h-10 rounded-lg bg-[#0052CC] text-white text-sm font-semibold inline-flex items-center justify-center">Configure Shipping & Courier</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl font-poppins">Compliance & Legal</h2>
                <p class="text-sm text-[#64748B]">Manage policies and regulatory requirements.</p>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="space-y-1.5"><span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Privacy Policy URL</span><input value="https://moderncommerce.com/privacy" class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm" /></label>
                <label class="space-y-1.5"><span class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Terms of Service URL</span><input value="https://moderncommerce.com/terms" class="w-full h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-sm" /></label>
            </div>
        </section>

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl">Developer API Keys</h2>
                <p class="text-sm text-[#64748B]">Access tokens for custom integrations and apps.</p>
            </div>
            <div class="p-5">
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 space-y-3">
                    <p class="text-xs uppercase tracking-[0.6px] font-bold text-[#64748B]">Client ID</p>
                    <div class="flex flex-col md:flex-row gap-3">
                        <input value="baas_live_******************7a3b" class="flex-1 h-10 rounded-lg border border-[#E2E8F0] bg-white px-3 text-sm font-mono text-[#64748B]" />
                        <button class="h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] flex items-center justify-center" aria-label="Copy key">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 2H3C2.44772 2 2 2.44772 2 3V10C2 10.5523 2.44772 11 3 11H10C10.5523 11 11 10.5523 11 10V3C11 2.44772 10.5523 2 10 2Z" stroke="currentColor" stroke-width="1.4"/><path d="M6 5H13C13.5523 5 14 5.44772 14 6V13C14 13.5523 13.5523 14 13 14H6C5.44772 14 5 13.5523 5 13V6C5 5.44772 5.44772 5 6 5Z" stroke="currentColor" stroke-width="1.4"/></svg>
                        </button>
                        <button class="h-10 px-4 rounded-lg border border-[#E2E8F0] bg-white text-sm font-semibold text-[#334155]">Generate New Key</button>
                    </div>
                    <p class="text-xs text-[#D97706] flex items-center gap-1.5">
                        <span aria-hidden="true">!</span>
                        <span>Generating a new key will immediately invalidate your current production API token. Use with caution.</span>
                    </p>
                </div>
            </div>
        </section>

        <section class="bg-white border border-[#E2E8F0] rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F1F5F9]">
                <h2 class="text-2xl">Automation & Notifications</h2>
                <p class="text-sm text-[#64748B]">System communication preferences.</p>
            </div>
            <div class="divide-y divide-[#F1F5F9]">
                <div class="p-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-lg bg-[#EFF6FF] text-[#2563EB] flex items-center justify-center">
                            <svg width="16" height="14" viewBox="0 0 16 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 2.5C1 1.67157 1.67157 1 2.5 1H13.5C14.3284 1 15 1.67157 15 2.5V11.5C15 12.3284 14.3284 13 13.5 13H2.5C1.67157 13 1 12.3284 1 11.5V2.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M2 3L8 7.5L14 3" stroke="currentColor" stroke-width="1.5"/></svg>
                        </div>
                        <div><p class="font-semibold">Automated Order Emails</p><p class="text-xs text-[#64748B]">Send order confirmations and tracking updates automatically.</p></div>
                    </div>
                    <button class="h-6 w-11 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                </div>
                <div class="p-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-lg bg-[#FFF7ED] text-[#D97706] flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 1.5L14.5 12.5H1.5L8 1.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 6V9.5M8 12V12.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </div>
                        <div><p class="font-semibold">System Alerts</p><p class="text-xs text-[#64748B]">Get notified about low inventory, payment issues, and security events.</p></div>
                    </div>
                    <button class="h-6 w-11 rounded-full bg-[#0052CC] relative"><span class="absolute right-0.5 top-0.5 h-5 w-5 rounded-full bg-white"></span></button>
                </div>
            </div>
        </section>
    </div>
@endsection
