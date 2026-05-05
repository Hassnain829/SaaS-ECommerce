@extends('layouts.user.user-sidebar')

@section('title', 'All Orders | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="hidden md:flex w-full md:w-[460px] max-w-full h-11 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] items-center px-4 text-[#64748B] text-sm">
        Search orders, transactions...
    </div>

    <div class="ml-auto flex items-center gap-4">
        <button class="bg-[#0052CC] text-white text-sm font-bold px-5 h-10 rounded-lg">+ Create Order</button>
        <span class="h-6 w-px bg-[#E2E8F0]"></span>

        <button class="relative p-2 rounded-full hover:bg-gray-100 transition-colors">
            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="#64748B"/>
            </svg>
            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-amber-400 border-2 border-white rounded-full"></span>
        </button>

        <button class="p-2 rounded-full hover:bg-gray-100 transition-colors text-[#64748B]">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 15H11V13H9V15ZM9 11H11V5H9V11Z" fill="currentColor"/>
            </svg>
        </button>
    </div>
</header>
@endsection

@section('content')
<div class="w-full py-2 md:py-4">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">All Orders</h1>
            <p class="text-sm text-[#64748B]">Manage and track your customer orders.</p>
        </div>
        <button class="bg-white border border-[#CBD5E1] rounded-xl px-4 h-10 text-sm font-semibold text-[#1E293B] inline-flex items-center gap-2">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 12L1 7L2.4 5.6L5 8.2V0H7V8.2L9.6 5.6L11 7L6 12Z" fill="currentColor"/></svg>
            Export
        </button>
    </div>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl p-4 md:p-5 space-y-4">
        <div class="flex flex-wrap gap-2 text-sm font-semibold">
            <a href="{{ route('orders', ['status' => 'all']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'all' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">All ({{ $statusCounts['all'] ?? 0 }})</a>
            <a href="{{ route('orders', ['status' => 'pending']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'pending' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">Pending ({{ $statusCounts['pending'] ?? 0 }})</a>
            <a href="{{ route('orders', ['status' => 'processing']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'processing' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">Processing ({{ $statusCounts['processing'] ?? 0 }})</a>
            <a href="{{ route('orders', ['status' => 'shipped']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'shipped' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">Shipped ({{ $statusCounts['shipped'] ?? 0 }})</a>
            <a href="{{ route('orders', ['status' => 'cancelled']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'cancelled' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">Cancelled ({{ $statusCounts['cancelled'] ?? 0 }})</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
            <div class="h-11 rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] px-4 flex items-center text-[#64748B]">Search by Order ID, Customer...</div>
            <button class="h-11 px-4 rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] text-[#475569] text-sm font-inter font-medium">Oct 12, 2023 - Oct 19, 2023</button>
        </div>
    </section>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden mt-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-[#64748B] text-xs uppercase tracking-[1px]">
                    <tr>
                        <th class="text-left px-6 py-4">Order ID</th>
                        <th class="text-left px-4 py-4">Date</th>
                        <th class="text-left px-4 py-4">Customer</th>
                        <th class="text-right px-4 py-4">Total</th>
                        <th class="text-left px-4 py-4">Payment</th>
                        <th class="text-left px-4 py-4">Status</th>
                        <th class="text-right px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($orders as $order)
                    <tr class="border-b border-[#F1F5F9]">
                        <td class="px-6 py-4 text-[#0052CC] font-bold">#{{ strtoupper($order->order_number) }}</td>
                        <td class="px-4 py-4 text-[#475569]">{{ $order->placed_at ? $order->placed_at->format('M d, Y') : '-' }}</td>
                        <td class="px-4 py-4">
                            <p class="font-semibold">{{ $order->customer->full_name ?? $order->customer_email }}</p>
                            <p class="text-xs text-[#64748B]">{{ $order->customer_email }}</p>
                        </td>
                        <td class="px-4 py-4 text-right font-bold">{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-4">
                            @if($order->payment_status === 'paid')
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] text-[#059669]">Paid</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#FFF7ED] px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] text-[#D97706]">{{ $order->payment_status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-[#64748B]">
                            <span class="inline-flex items-center gap-1 rounded-full bg-[#F1F5F9] px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] text-[#475569]">
                                {{ $order->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('orderViewDetails', $order->id) }}" class="text-[#0052CC] font-bold">View Details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-[#64748B]">No orders found for this status.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="border-t border-[#F1F5F9] px-4 md:px-6 py-4 flex items-center justify-between text-xs text-[#64748B]">
            <p>Showing <span class="font-bold text-[#0F172A]">{{ $orders->firstItem() }}</span> to <span class="font-bold text-[#0F172A]">{{ $orders->lastItem() }}</span> of <span class="font-bold text-[#0F172A]">{{ $orders->total() }}</span> orders</p>
            <div class="flex items-center gap-2">
                {{ $orders->links('pagination::tailwind') }}
            </div>
        </div>
        @else
        <div class="border-t border-[#F1F5F9] px-4 md:px-6 h-14 flex items-center justify-between text-xs text-[#64748B]">
            <p>Showing <span class="font-bold text-[#0F172A]">{{ $orders->count() }}</span> orders</p>
        </div>
        @endif
    </section>
</div>
@endsection
