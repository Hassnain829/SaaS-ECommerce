@extends('layouts.user.user-sidebar')

@section('title', 'Customers | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>
    <div>
        <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Customers</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Customer profiles, order history, tags, and consent.</p>
    </div>
    @if($canManageOrders)
        <a href="{{ route('orders.create') }}" class="h-10 px-4 rounded-lg bg-[#0052CC] text-white text-sm font-semibold inline-flex items-center justify-center">Create order manually</a>
    @endif
</header>
@endsection

@section('content')
@php($currency = $selectedStore->currency ?? 'USD')
<div class="w-full py-2 md:py-4 space-y-4">
    <div>
        <h1 class="text-2xl font-medium text-[#0F172A] font-poppins">Customer CRM</h1>
        <p class="text-sm text-[#64748B]">Use real order history and profile details to support your customers.</p>
    </div>

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-4 md:p-5 space-y-4">
        <form action="{{ route('customers') }}" method="GET" class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_180px_180px_100px]">
            <input name="q" value="{{ $search }}" class="h-10 rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="Search name, email, or phone">
            <select name="status" class="h-10 rounded-lg border border-[#CBD5E1] px-3 text-sm">
                <option value="all" @selected($currentStatus === 'all')>All statuses</option>
                @foreach(['active' => 'Active', 'guest' => 'Guest', 'blocked' => 'Blocked'] as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }} ({{ $statusCounts[$value] ?? 0 }})</option>
                @endforeach
            </select>
            <select name="tag" class="h-10 rounded-lg border border-[#CBD5E1] px-3 text-sm">
                <option value="0">All tags</option>
                @foreach($customerTags as $tag)
                    <option value="{{ $tag->id }}" @selected($currentTagId === $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
            <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Filter</button>
        </form>

        <div class="flex flex-wrap gap-2 text-sm font-semibold">
            <a href="{{ route('customers') }}" class="h-9 px-4 rounded-full inline-flex items-center {{ $currentStatus === 'all' && $search === '' && $currentTagId === 0 ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                All customers ({{ $statusCounts['all'] ?? 0 }})
            </a>
            @foreach(['active' => 'Active', 'guest' => 'Guest', 'blocked' => 'Blocked'] as $value => $label)
                <a href="{{ route('customers', ['status' => $value]) }}" class="h-9 px-4 rounded-full inline-flex items-center {{ $currentStatus === $value ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                    {{ $label }} ({{ $statusCounts[$value] ?? 0 }})
                </a>
            @endforeach
        </div>
    </section>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1060px]">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-[#64748B] text-xs uppercase tracking-[1px]">
                    <tr>
                        <th class="text-left px-6 py-4">Customer</th>
                        <th class="text-left px-4 py-4">Status</th>
                        <th class="text-left px-4 py-4">Tags</th>
                        <th class="text-center px-4 py-4">Orders</th>
                        <th class="text-right px-4 py-4">Lifetime spend</th>
                        <th class="text-left px-4 py-4">Last order</th>
                        <th class="text-left px-4 py-4">Consent</th>
                        <th class="text-right px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($customers as $customer)
                        <tr class="border-b border-[#F1F5F9]">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-[#E2E8F0] grid place-items-center text-[#475569] font-bold">
                                        {{ strtoupper(substr($customer->full_name ?: $customer->email, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#0F172A]">{{ $customer->full_name ?: $customer->email }}</p>
                                        <p class="text-xs text-[#64748B]">{{ $customer->email }}</p>
                                        @if($customer->phone)
                                            <p class="text-xs text-[#64748B]">{{ $customer->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if($customer->status === 'active')
                                    <span class="inline-flex rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#059669]">Active</span>
                                @elseif($customer->status === 'blocked')
                                    <span class="inline-flex rounded-full bg-[#FEF2F2] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#BA1A1A]">Blocked</span>
                                @else
                                    <span class="inline-flex rounded-full bg-[#F8FAFC] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.5px] text-[#64748B]">{{ ucfirst($customer->status) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($customer->tags as $tag)
                                        <span class="rounded-full bg-[#EEF2FF] px-2 py-1 text-[11px] font-semibold text-[#3730A3]">{{ $tag->name }}</span>
                                    @empty
                                        <span class="text-xs text-[#94A3B8]">No tags</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center font-medium">{{ $customer->total_orders }}</td>
                            <td class="px-4 py-4 text-right font-bold">{{ $currency }} {{ number_format((float) $customer->total_spent, 2) }}</td>
                            <td class="px-4 py-4 text-[#475569]">{{ $customer->last_order_at ? $customer->last_order_at->format('M d, Y') : 'Never' }}</td>
                            <td class="px-4 py-4 text-[#475569]">{{ $customer->marketing_consent || $customer->accepts_marketing ? 'Accepted' : 'Not accepted' }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customersProfile', $customer) }}" class="text-[#0052CC] text-xs font-bold">View profile</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-[#64748B]">
                                No customers found. Customers are created from storefront orders or manual orders.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="border-t border-[#F1F5F9] px-4 md:px-6 py-4 text-xs text-[#64748B]">
                {{ $customers->links('pagination::tailwind') }}
            </div>
        @else
            <div class="border-t border-[#F1F5F9] px-4 md:px-6 h-14 flex items-center text-xs text-[#64748B]">
                Showing <span class="mx-1 font-bold text-[#0F172A]">{{ $customers->count() }}</span> customers
            </div>
        @endif
    </section>
</div>
@endsection
