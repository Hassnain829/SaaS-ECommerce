@extends('layouts.user.user-Sidebar')

@section('title', 'Customer Profile | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>

    <div class="hidden sm:flex items-center gap-2 text-sm min-w-0">
      <a href="{{ route('customers') }}" class="text-[#64748B]">Customers</a>
      <span class="text-[#94A3B8]">&gt;</span>
      <span class="font-semibold text-[#0F172A] truncate">Sarah Jenkins</span>
    </div>

    <div class="flex items-center gap-3 ml-auto">
      <div class="hidden md:flex w-64 h-10 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] items-center px-3 text-sm text-[#64748B]">Search data...</div>
      <button class="h-8 w-8 flex items-center justify-center text-[#64748B]" aria-label="Notifications">🔔</button>
    </div>
</header>
@endsection

@section('content')
<div class="w-full">
  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-6">
    <div class="space-y-5">
      <section class="bg-white border border-[#CBD5E1] rounded-2xl p-5">
        <div class="flex flex-wrap items-start gap-4">
          <div class="rounded-xl bg-[#F5D8BE] border border-[#E2E8F0] flex items-center justify-center text-[10px] text-[#64748B]" style="height:74px; width:74px;">Photo</div>

          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-4xl lg:text-5xl leading-tight text-[#0F172A]">Sarah Jenkins</h1>
              <span class="inline-flex rounded-full bg-[#D1FAE5] px-2.5 py-1 text-[11px] font-semibold text-[#059669]">VIP CUSTOMER</span>
            </div>

            <div class="mt-2 space-y-1 text-[#64748B] text-sm">
              <p>s.jenkins@example.com</p>
              <p>+1 (555) 234-8910</p>
              <p>Seattle, WA, USA</p>
            </div>
          </div>

          <div class="ml-auto flex items-center gap-2">
            <button class="h-10 w-10 rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] text-[#475569]">...</button>
            <button class="h-10 px-4 rounded-xl bg-[#0052CC] text-white font-semibold text-sm">Edit Profile</button>
          </div>
        </div>
      </section>

      <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <article class="bg-white border border-[#CBD5E1] rounded-2xl p-5">
          <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Lifetime Value</p>
          <p class="text-4xl leading-none font-semibold mt-2">$12,450.00</p>
          <p class="text-[#059669] text-sm font-semibold mt-2">↗14%</p>
        </article>
        <article class="bg-white border border-[#CBD5E1] rounded-2xl p-5">
          <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Average Order Value</p>
          <p class="text-4xl leading-none font-semibold mt-2">$415.00</p>
        </article>
        <article class="bg-white border border-[#CBD5E1] rounded-2xl p-5">
          <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Total Orders</p>
          <p class="text-4xl leading-none font-semibold mt-2">30</p>
        </article>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-[#E2E8F0] flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 15.5L6.2 11.3L9.3 14.4L14.8 8.9L18 12.1" stroke="#0052CC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 4H18V9" stroke="#0052CC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <h2 class="text-2xl">Customer Insights</h2>
          </div>
          <span class="rounded-md bg-[#F1F5F9] px-3 py-1 text-[11px] font-bold text-[#64748B] uppercase tracking-[1px]">Model: aASeion Retail</span>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
          <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8] mb-3">Preferred Categories</p>
            <div class="space-y-2">
              <div class="inline-flex bg-[#F8FAFC] rounded-lg px-3 py-2">Outerwear</div>
              <div class="inline-flex bg-[#F8FAFC] rounded-lg px-3 py-2">Accessories</div>
              <div class="inline-flex bg-[#F8FAFC] rounded-lg px-3 py-2">Footwear</div>
            </div>
          </div>

          <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8] mb-3">Size Profile</p>
            <div class="space-y-2 text-[#64748B]">
              <p class="flex justify-between"><span>Apparel Size</span><span class="font-semibold text-[#0F172A]">M / US 6</span></p>
              <p class="flex justify-between"><span>Shoe Size</span><span class="font-semibold text-[#0F172A]">US 8.5</span></p>
            </div>
          </div>

          <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8] mb-3">Engagement Scoring</p>
            <div class="space-y-3">
              <div>
                <div class="flex justify-between"><span>Purchase Frequency</span><span class="font-semibold">High</span></div>
                <div class="h-2 bg-[#E2E8F0] rounded-full mt-1"><div class="h-2 w-[85%] bg-[#0052CC] rounded-full"></div></div>
              </div>
              <div>
                <div class="flex justify-between"><span>Retention Risk</span><span class="font-semibold">Low</span></div>
                <div class="h-2 bg-[#E2E8F0] rounded-full mt-1"><div class="h-2 w-[15%] bg-[#10B981] rounded-full"></div></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-[#E2E8F0] flex items-center justify-between">
          <h2 class="text-2xl">Purchase History</h2>
          <button class="text-[#0052CC] font-semibold">Download CSV</button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-sm">
            <thead class="bg-[#F8FAFC] text-[#64748B] text-xs font-bold uppercase tracking-[1px]">
              <tr>
                <th class="text-left px-6 py-4">Order ID</th>
                <th class="text-left px-4 py-4">Date</th>
                <th class="text-left px-4 py-4">Status</th>
                <th class="text-left px-4 py-4">Items</th>
                <th class="text-right px-6 py-4">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-t border-[#F1F5F9]"><td class="px-6 py-4 font-bold text-[#0052CC]">#ORD-90234</td><td class="px-4 py-4">Oct 24, 2023</td><td class="px-4 py-4"><span class="rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase text-[#059669]">Delivered</span></td><td class="px-4 py-4 text-[#475569]">Leather Tote, Silk Scarf</td><td class="px-6 py-4 text-right font-bold">$890.00</td></tr>
              <tr class="border-t border-[#F1F5F9]"><td class="px-6 py-4 font-bold text-[#0052CC]">#ORD-89451</td><td class="px-4 py-4">Sep 12, 2023</td><td class="px-4 py-4"><span class="rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase text-[#059669]">Delivered</span></td><td class="px-4 py-4 text-[#475569]">Wool Pea Coat</td><td class="px-6 py-4 text-right font-bold">$1,250.00</td></tr>
              <tr class="border-t border-[#F1F5F9]"><td class="px-6 py-4 font-bold text-[#0052CC]">#ORD-88210</td><td class="px-4 py-4">Aug 05, 2023</td><td class="px-4 py-4"><span class="rounded-full bg-[#F1F5F9] px-2 py-1 text-[10px] font-bold uppercase text-[#475569]">Returned</span></td><td class="px-4 py-4 text-[#475569]">Chelsea Boots</td><td class="px-6 py-4 text-right font-bold">$320.00</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <aside class="hidden lg:flex flex-col gap-5">
      <section class="bg-white border border-[#E2E8F0] rounded-2xl p-5 space-y-4">
        <h3 class="text-sm font-bold uppercase tracking-[1px] text-[#94A3B8]">Quick Notes</h3>

        <div class="relative">
         <div class="min-h-[136px] rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] p-3 pr-12 text-[#6B7280]">Add a note about this customer...</div>
          <button class="absolute right-0 top-1/2 -translate-y-1/2 rounded-l-lg text-white flex items-center justify-center" style="height:40px; width:40px; background:#0052CC;" aria-label="Send Note">➤</button>
        </div>

        <div class="rounded-xl p-3 text-sm text-[#475569]" style="border:1px solid #DBEAFE; background:rgba(239,246,255,0.5);">
          Requested gift wrapping for order #90234. Prefers eco-friendly packaging.
          <p class="text-[11px] text-[#94A3B8] mt-2">Oct 24 • By Sarah M.</p>
        </div>
      </section>

      <section class="bg-white border border-[#E2E8F0] rounded-2xl p-5">
        <h3 class="text-sm font-bold uppercase tracking-[1px] text-[#94A3B8] mb-5">Communication History</h3>

        <div class="relative pl-9 space-y-7">
          <div class="absolute w-px bg-[#E2E8F0] left-[19px] top-2 bottom-[34px]"></div>

          <article class="relative">
            <div class="absolute h-5 w-5 -left-[27px] top-1 rounded-full border-2 border-[#0052CC] bg-white"></div>
            <h4 class="text-xl font-semibold text-[#0F172A]">Email Sent: Promo Code</h4>
            <p class="mt-1 text-sm text-[#64748B] leading-5">Sent 'AUTUMN23' voucher via marketing automation.</p>
            <p class="text-[11px] text-[#94A3B8] mt-1">Today, 10:45 AM</p>
          </article>

          <article class="relative">
            <div class="absolute h-5 w-5 -left-[27px] top-1 rounded-full border-2 border-[#CBD5E1] bg-white"></div>
            <h4 class="text-xl font-semibold text-[#0F172A]">Support Ticket #TC-441</h4>
            <p class="mt-1 text-sm text-[#64748B] leading-5">Closed: Question regarding shipping delay. Customer satisfied.</p>
            <p class="text-[11px] text-[#94A3B8] mt-1">Oct 22, 02:30 PM</p>
          </article>

          <article class="relative">
            <div class="absolute h-5 w-5 -left-[27px] top-1 rounded-full border-2 border-[#CBD5E1] bg-white"></div>
            <h4 class="text-xl font-semibold text-[#0F172A]">In-store Interaction</h4>
            <p class="mt-1 text-sm text-[#64748B] leading-5">Seattle Store Location. Customer returned sizing L for M.</p>
            <p class="text-[11px] text-[#94A3B8] mt-1">Sep 28, 11:15 AM</p>
          </article>
        </div>

        <button class="w-full h-9 rounded-lg border border-[#E2E8F0] text-sm font-semibold text-[#64748B] mt-6">View Full Timeline</button>
      </section>
    </aside>
  </div>
</div>
@endsection
