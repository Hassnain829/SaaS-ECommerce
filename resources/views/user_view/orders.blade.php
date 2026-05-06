@extends('layouts.user.user-sidebar')

@section('title', 'Orders | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div>
        <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Orders</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Review order, payment, and fulfillment state separately.</p>
    </div>
</header>
@endsection

@section('content')
<div class="w-full py-2 md:py-4 space-y-4">
    @include('user_view.partials.flash_success')

    <div>
        <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">All orders</h1>
        <p class="text-sm text-[#64748B]">Track customer orders for {{ $selectedStore?->name ?? 'this store' }}.</p>
    </div>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl p-4 md:p-5">
        <div class="flex flex-wrap gap-2 text-sm font-semibold">
            <a href="{{ route('orders', ['status' => 'all']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'all' ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                All ({{ $statusCounts['all'] ?? 0 }})
            </a>
            @foreach($orderStatuses as $status)
                <a href="{{ route('orders', ['status' => $status]) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === $status ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                    {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }} ({{ $statusCounts[$status] ?? 0 }})
                </a>
            @endforeach
        </div>
    </section>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px]">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-[#64748B] text-xs uppercase tracking-[1px]">
                    <tr>
                        <th class="text-left px-6 py-4">Order</th>
                        <th class="text-left px-4 py-4">Date</th>
                        <th class="text-left px-4 py-4">Customer</th>
                        <th class="text-right px-4 py-4">Total</th>
                        <th class="text-left px-4 py-4">Order state</th>
                        <th class="text-left px-4 py-4">Payment</th>
                        <th class="text-left px-4 py-4">Fulfillment</th>
                        <th class="text-right px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($orders as $order)
                    <tr class="border-b border-[#F1F5F9]">
                        <td class="px-6 py-4 text-[#0052CC] font-bold">{{ strtoupper($order->order_number) }}</td>
                        <td class="px-4 py-4 text-[#475569]">{{ $order->placed_at ? $order->placed_at->format('M d, Y') : '-' }}</td>
                        <td class="px-4 py-4">
                            <p class="font-semibold">{{ $order->customer->full_name ?? $order->customer_email }}</p>
                            <p class="text-xs text-[#64748B]">{{ $order->customer_email }}</p>
                        </td>
                        <td class="px-4 py-4 text-right font-bold">{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">
                                {{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                                {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                                {{ \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('orderViewDetails', $order->id) }}" class="text-[#0052CC] font-bold">View details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-[#64748B]">No orders found for this status.</td>
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
