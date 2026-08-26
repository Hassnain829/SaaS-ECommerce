@extends('layouts.user.user-sidebar')

@section('title', 'Customers — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Customers" lead="Order history and profile details for the selected store.">
        <x-slot:search>
            <form action="{{ route('customers') }}" method="GET" data-turbo-frame="customers-panel" class="flex w-full items-center gap-2">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <input type="hidden" name="tag" value="{{ $currentTagId }}">
                <input name="q" value="{{ $search }}" class="h-9 min-w-0 flex-1 rounded-md border border-border bg-surface px-3 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Search customers…">
                <button class="inline-flex h-9 shrink-0 items-center rounded-md border border-border bg-surface px-3 text-xs font-semibold text-ink-secondary hover:bg-surface-muted">Search</button>
            </form>
        </x-slot:search>
        <x-slot:actions>
            @if($canManageCustomers ?? false)
                <a href="#add-customer" class="inline-flex h-9 items-center rounded-md bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover">Add customer</a>
            @endif
            @if($canManageOrders)
                <a href="{{ route('orders.create') }}" class="hidden h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink xl:inline-flex">Create order</a>
            @endif
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php($currency = $selectedStore->currency ?? 'USD')
{{-- Frame keeps filters snappy: only this block swaps, not the whole shell --}}
<turbo-frame id="customers-panel" data-turbo-action="advance">
<div class="w-full space-y-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    @if($canManageCustomers ?? false)
        <section id="add-customer" class="merchant-card overflow-hidden">
            <div class="border-b border-border px-4 py-3 md:px-5">
                <h2 class="text-base font-semibold text-ink">Add customer</h2>
                <p class="mt-0.5 text-sm text-ink-muted">Create a customer profile now, or add one automatically when you <a href="{{ route('orders.create') }}" data-turbo-frame="_top" class="font-semibold text-brand hover:underline">create a manual order</a>.</p>
            </div>
            <form action="{{ route('customers.store') }}" method="POST" data-turbo-frame="_top" class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2 md:p-5 lg:grid-cols-5">
                @csrf
                <label class="block lg:col-span-1">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">First name</span>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" maxlength="80" class="h-10 w-full rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                </label>
                <label class="block lg:col-span-1">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">Last name</span>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" maxlength="80" class="h-10 w-full rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                </label>
                <label class="block lg:col-span-1">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="h-10 w-full rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 @error('email') border-[#F87171] @enderror">
                    @error('email')
                        <span class="mt-1 block text-xs text-[#B91C1C]">{{ $message }}</span>
                    @enderror
                </label>
                <label class="block lg:col-span-1">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">Phone</span>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="80" class="h-10 w-full rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                </label>
                <div class="flex items-end lg:col-span-1">
                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-brand px-4 text-sm font-semibold text-white transition hover:bg-brand-hover">Save customer</button>
                </div>
            </form>
        </section>
    @endif

    <section class="merchant-card space-y-4 p-4">
        <form action="{{ route('customers') }}" method="GET" class="grid grid-cols-1 gap-2 sm:grid-cols-[180px_180px_auto]">
            <input type="hidden" name="q" value="{{ $search }}">
            <select name="status" class="h-9 rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                <option value="all" @selected($currentStatus === 'all')>All statuses</option>
                @foreach(['active' => 'Active', 'guest' => 'Guest', 'blocked' => 'Blocked'] as $value => $label)
                    <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }} ({{ $statusCounts[$value] ?? 0 }})</option>
                @endforeach
            </select>
            <select name="tag" class="h-9 rounded-md border border-border bg-surface px-3 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                <option value="0">All tags</option>
                @foreach($customerTags as $tag)
                    <option value="{{ $tag->id }}" @selected($currentTagId === $tag->id)>{{ $tag->name }}</option>
                @endforeach
            </select>
            <button class="h-9 rounded-md border border-border bg-surface px-4 text-sm font-semibold text-ink-secondary hover:bg-surface-muted">Filter</button>
        </form>

        @if($search !== '')
            <div class="flex items-center justify-between rounded-md bg-surface-muted px-3 py-2 text-sm text-ink-secondary">
                <span>Search: <span class="font-semibold text-ink">{{ $search }}</span></span>
                <a href="{{ route('customers', ['status' => $currentStatus, 'tag' => $currentTagId]) }}" class="font-semibold text-brand hover:text-brand-hover">Clear</a>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-sm font-semibold" data-filter-tabs>
            <a href="{{ route('customers') }}" data-filter-tab @class([
                'inline-flex h-8 items-center rounded-md px-3 transition',
                'bg-brand text-white' => $currentStatus === 'all' && $search === '' && $currentTagId === 0,
                'bg-surface-muted text-ink-secondary hover:bg-border/60' => ! ($currentStatus === 'all' && $search === '' && $currentTagId === 0),
            ])>
                All customers ({{ $statusCounts['all'] ?? 0 }})
            </a>
            @foreach(['active' => 'Active', 'guest' => 'Guest', 'blocked' => 'Blocked'] as $value => $label)
                <a href="{{ route('customers', ['status' => $value]) }}" data-filter-tab @class([
                    'inline-flex h-8 items-center rounded-md px-3 transition',
                    'bg-brand text-white' => $currentStatus === $value,
                    'bg-surface-muted text-ink-secondary hover:bg-border/60' => $currentStatus !== $value,
                ])>
                    {{ $label }} ({{ $statusCounts[$value] ?? 0 }})
                </a>
            @endforeach
        </div>
    </section>

    <section class="merchant-card overflow-hidden" data-filter-results>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1060px] text-sm">
                <thead class="border-b border-border bg-surface-muted/70 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-5 py-2.5 text-left">Customer</th>
                        <th class="px-4 py-2.5 text-left">Status</th>
                        <th class="px-4 py-2.5 text-left">Tags</th>
                        <th class="px-4 py-2.5 text-center">Orders</th>
                        <th class="px-4 py-2.5 text-right">Lifetime spend</th>
                        <th class="px-4 py-2.5 text-left">Last order</th>
                        <th class="px-4 py-2.5 text-left">Consent</th>
                        <th class="px-5 py-2.5 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr class="border-b border-border/80 hover:bg-surface-muted/40">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-9 w-9 place-items-center rounded-full bg-surface-muted text-sm font-semibold text-ink-secondary">
                                        {{ strtoupper(substr($customer->full_name ?: $customer->email, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-ink">{{ $customer->full_name ?: $customer->email }}</p>
                                        <p class="text-xs text-ink-muted">{{ $customer->email }}</p>
                                        @if($customer->phone)
                                            <p class="text-xs text-ink-muted">{{ $customer->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($customer->status === 'active')
                                    <span class="inline-flex rounded-md bg-success-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-success">Active</span>
                                @elseif($customer->status === 'blocked')
                                    <span class="inline-flex rounded-md bg-danger-soft px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-danger">Blocked</span>
                                @else
                                    <span class="inline-flex rounded-md bg-surface-muted px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-muted">{{ ucfirst($customer->status) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($customer->tags as $tag)
                                        <span class="rounded-md bg-brand-soft px-2 py-0.5 text-[11px] font-semibold text-brand-ink">{{ $tag->name }}</span>
                                    @empty
                                        <span class="text-xs text-ink-muted">No tags</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums text-ink">{{ $customer->total_orders }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ $currency }} {{ number_format((float) $customer->total_spent, 2) }}</td>
                            <td class="px-4 py-3 text-ink-secondary">{{ $customer->last_order_at ? $customer->last_order_at->format('M d, Y') : 'Never' }}</td>
                            <td class="px-4 py-3 text-ink-secondary">{{ $customer->marketing_consent || $customer->accepts_marketing ? 'Accepted' : 'Not accepted' }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('customersProfile', $customer) }}" data-turbo-frame="_top" class="text-sm font-semibold text-brand hover:text-brand-hover">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-ink-muted">
                                No customers found.
                                @if($canManageCustomers ?? false)
                                    <a href="#add-customer" class="font-semibold text-brand hover:underline">Add a customer</a>
                                    or create one from a
                                    <a href="{{ route('orders.create') }}" data-turbo-frame="_top" class="font-semibold text-brand hover:underline">manual order</a>.
                                @else
                                    Customers appear from storefront and manual orders.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="border-t border-border px-4 py-3 text-xs text-ink-muted md:px-5">
                {{ $customers->links('pagination::tailwind') }}
            </div>
        @else
            <div class="flex h-12 items-center border-t border-border px-4 text-xs text-ink-muted md:px-5">
                Showing <span class="mx-1 font-semibold text-ink">{{ $customers->count() }}</span> customers
            </div>
        @endif
    </section>
</div>
</turbo-frame>
@endsection
