@extends('layouts.user.user-sidebar')

@section('title', 'Customer Profile | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Customer profile" :lead="$customer->full_name ?: $customer->email">
        <x-slot:actions>
            <a href="{{ route('customers') }}" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 hover:bg-stone-50">Back to customers</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $currency = $selectedStore->currency ?? 'USD';
    $initials = collect(explode(' ', $customer->full_name ?: $customer->email))->filter()->map(fn ($part) => substr($part, 0, 1))->take(2)->join('');
    $defaultShipping = $customer->addresses->where('type', 'shipping')->where('is_default', true)->first() ?? $customer->addresses->where('type', 'shipping')->first();
    $defaultBilling = $customer->addresses->where('type', 'billing')->where('is_default', true)->first() ?? $customer->addresses->where('type', 'billing')->first();
@endphp

<div class="w-full py-2 md:py-4 space-y-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4 min-w-0">
                <div class="h-16 w-16 rounded-2xl bg-[#EFF6FF] text-[#1D4ED8] grid place-items-center text-xl font-bold shrink-0">{{ strtoupper($initials) ?: 'C' }}</div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-2xl md:text-3xl font-semibold text-[#0F172A]">{{ $customer->full_name ?: $customer->email }}</h2>
                        @if($customer->status === 'blocked')
                            <span class="rounded-full bg-[#FEF2F2] px-2 py-1 text-[10px] font-bold uppercase tracking-[.6px] text-[#BA1A1A]">Blocked</span>
                        @elseif($customer->status === 'active')
                            <span class="rounded-full bg-[#ECFDF5] px-2 py-1 text-[10px] font-bold uppercase tracking-[.6px] text-[#059669]">Active</span>
                        @else
                            <span class="rounded-full bg-[#F8FAFC] px-2 py-1 text-[10px] font-bold uppercase tracking-[.6px] text-[#64748B]">{{ ucfirst($customer->status) }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-[#64748B]">{{ $customer->email }}</p>
                    @if($customer->phone)
                        <p class="text-sm text-[#64748B]">{{ $customer->phone }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($customer->tags as $tag)
                            <span class="inline-flex items-center gap-2 rounded-full bg-[#EEF2FF] px-3 py-1 text-xs font-semibold text-[#3730A3]">
                                {{ $tag->name }}
                                @if($canManageCustomers)
                                    <form action="{{ route('customers.tags.destroy', [$customer, $tag]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-bold text-[#64748B]" aria-label="Remove tag">x</button>
                                    </form>
                                @endif
                            </span>
                        @empty
                            <span class="text-sm text-[#94A3B8]">No tags yet</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if($canManageCustomers)
                <div class="w-full lg:w-72 space-y-3">
                    <form action="{{ route('customers.tags.store', $customer) }}" method="POST" class="flex gap-2">
                        @csrf
                        <input name="name" class="h-10 min-w-0 flex-1 rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="Add tag">
                        <button class="h-10 rounded-lg bg-brand px-3 text-sm font-semibold text-white">Add</button>
                    </form>
                    <form action="{{ route('customers.marketing.update', $customer) }}" method="POST" class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-sm">
                        @csrf
                        @method('PATCH')
                        <label class="flex items-center gap-2 text-[#475569]">
                            <input type="checkbox" name="marketing_consent" value="1" @checked($customer->marketing_consent || $customer->accepts_marketing) class="rounded border-[#CBD5E1]">
                            Marketing consent accepted
                        </label>
                        <input name="marketing_consent_source" value="{{ $customer->marketing_consent_source ?? 'dashboard' }}" class="mt-2 h-9 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="Consent source">
                        <button class="mt-2 h-9 w-full rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A]">Save consent</button>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Lifetime spend</p>
            <p class="mt-2 text-3xl font-semibold text-[#0F172A]">{{ $currency }} {{ number_format((float) $customer->total_spent, 2) }}</p>
        </article>
        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Orders</p>
            <p class="mt-2 text-3xl font-semibold text-[#0F172A]">{{ $customer->total_orders }}</p>
        </article>
        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Average order</p>
            <p class="mt-2 text-3xl font-semibold text-[#0F172A]">{{ $currency }} {{ number_format((float) $customer->average_order_value, 2) }}</p>
        </article>
        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Last order</p>
            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $customer->last_order_at ? $customer->last_order_at->format('M d') : 'None' }}</p>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
        <div class="space-y-4">
            <article class="rounded-2xl border border-[#CBD5E1] bg-white overflow-hidden">
                <div class="border-b border-[#E2E8F0] px-5 py-4">
                    <h3 class="text-lg font-semibold text-[#0F172A]">Order history</h3>
                    <p class="text-sm text-[#64748B]">Real orders linked to this customer.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-[#F8FAFC] text-xs uppercase tracking-[1px] text-[#64748B]">
                            <tr>
                                <th class="px-5 py-3 text-left">Order</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">State</th>
                                <th class="px-4 py-3 text-center">Items</th>
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customer->orders as $order)
                                <tr class="border-t border-[#F1F5F9]">
                                    <td class="px-5 py-4 font-bold text-[#0052CC]"><a href="{{ route('orderViewDetails', $order) }}">{{ strtoupper($order->order_number) }}</a></td>
                                    <td class="px-4 py-4">{{ $order->placed_at ? $order->placed_at->format('M d, Y') : '-' }}</td>
                                    <td class="px-4 py-4">
                                        <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">{{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-center">{{ $order->item_count ?: $order->items->count() }}</td>
                                    <td class="px-5 py-4 text-right font-bold">{{ $currency }} {{ number_format((float) ($order->grand_total ?: $order->total), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-[#64748B]">No orders found for this customer.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-semibold text-[#0F172A]">Addresses</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded-xl bg-[#F8FAFC] p-4 text-sm">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Default shipping</p>
                        @if($defaultShipping)
                            <p class="mt-2 font-semibold text-[#0F172A]">{{ $defaultShipping->name ?: $customer->full_name }}</p>
                            <p class="mt-1 text-[#475569]">{{ $defaultShipping->address_line1 }}<br>{{ $defaultShipping->city }}, {{ $defaultShipping->state }} {{ $defaultShipping->postal_code }}<br>{{ $defaultShipping->country }}</p>
                        @else
                            <p class="mt-2 text-[#64748B]">No shipping address saved.</p>
                        @endif
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] p-4 text-sm">
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Default billing</p>
                        @if($defaultBilling)
                            <p class="mt-2 font-semibold text-[#0F172A]">{{ $defaultBilling->name ?: $customer->full_name }}</p>
                            <p class="mt-1 text-[#475569]">{{ $defaultBilling->address_line1 }}<br>{{ $defaultBilling->city }}, {{ $defaultBilling->state }} {{ $defaultBilling->postal_code }}<br>{{ $defaultBilling->country }}</p>
                        @else
                            <p class="mt-2 text-[#64748B]">No billing address saved.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach($customer->addresses as $address)
                        <form action="{{ route('customers.addresses.update', [$customer, $address]) }}" method="POST" class="rounded-xl border border-[#E2E8F0] p-4">
                            @csrf
                            @method('PATCH')
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-semibold text-[#0F172A]">{{ ucfirst($address->type) }} address @if($address->is_default)<span class="ml-2 text-xs text-[#059669]">Default</span>@endif</h4>
                                @if($canManageCustomers)
                                    <div class="flex gap-2">
                                        <button class="h-8 rounded-lg border border-[#CBD5E1] bg-white px-3 text-xs font-semibold text-[#0F172A]">Save address</button>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                <select name="type" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" @disabled(! $canManageCustomers)>
                                    <option value="shipping" @selected($address->type === 'shipping')>Shipping</option>
                                    <option value="billing" @selected($address->type === 'billing')>Billing</option>
                                </select>
                                <input name="name" value="{{ $address->name }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Name" @readonly(! $canManageCustomers)>
                                <input name="address_line1" value="{{ $address->address_line1 }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm md:col-span-2" placeholder="Address line 1" @readonly(! $canManageCustomers)>
                                <input name="city" value="{{ $address->city }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="City" @readonly(! $canManageCustomers)>
                                <input name="state" value="{{ $address->state }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="State" @readonly(! $canManageCustomers)>
                                <input name="postal_code" value="{{ $address->postal_code }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Postal code" @readonly(! $canManageCustomers)>
                                <input name="country" value="{{ $address->country }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Country" @readonly(! $canManageCustomers)>
                            </div>
                        </form>
                        @if($canManageCustomers)
                            <div class="-mt-3 flex flex-wrap gap-2 px-2">
                                <form action="{{ route('customers.addresses.default', [$customer, $address]) }}" method="POST">
                                    @csrf
                                    <button class="text-xs font-semibold text-[#0052CC]">Make default {{ $address->type }}</button>
                                </form>
                                <form action="{{ route('customers.addresses.destroy', [$customer, $address]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-semibold text-[#BA1A1A]">Remove address</button>
                                </form>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if($canManageCustomers)
                    <form action="{{ route('customers.addresses.store', $customer) }}" method="POST" class="mt-5 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] p-4">
                        @csrf
                        <h4 class="font-semibold text-[#0F172A]">Add address</h4>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <select name="type" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm"><option value="shipping">Shipping</option><option value="billing">Billing</option></select>
                            <input name="name" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Name">
                            <input name="address_line1" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm md:col-span-2" placeholder="Address line 1">
                            <input name="city" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="City">
                            <input name="state" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="State">
                            <input name="postal_code" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Postal code">
                            <input name="country" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-sm" placeholder="Country">
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="is_default" value="1"> Make default for this address type</label>
                        <button class="mt-3 h-10 rounded-lg bg-brand px-4 text-sm font-semibold text-white">Add address</button>
                    </form>
                @endif
            </article>
        </div>

        <aside class="space-y-4">
            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-semibold text-[#0F172A]">Customer status</h3>
                @if($canManageCustomers)
                    <form action="{{ route('customers.status.update', $customer) }}" method="POST" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                            <option value="active" @selected($customer->status !== 'blocked')>Active</option>
                            <option value="blocked" @selected($customer->status === 'blocked')>Blocked</option>
                        </select>
                        <textarea name="blocked_reason" rows="3" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Reason if blocked">{{ $customer->blocked_reason }}</textarea>
                        <button class="w-full h-10 rounded-lg bg-brand text-sm font-semibold text-white">Save status</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-[#64748B]">You can view this customer, but your store role cannot change status.</p>
                @endif
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-semibold text-[#0F172A]">Customer notes</h3>
                @if($canManageCustomers)
                    <form action="{{ route('customers.notes.store', $customer) }}" method="POST" class="mt-4 space-y-3">
                        @csrf
                        <textarea name="body" rows="3" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Add a note"></textarea>
                        <button class="w-full h-10 rounded-lg bg-brand text-sm font-semibold text-white">Add note</button>
                    </form>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse($customer->profileNotes as $note)
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-sm">
                            <p class="whitespace-pre-line text-[#334155]">{{ $note->body }}</p>
                            <p class="mt-2 text-xs text-[#94A3B8]">{{ $note->user?->name ?? 'Team member' }} - {{ $note->created_at?->format('M d, Y h:i A') }}</p>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">No notes yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-semibold text-[#0F172A]">Marketing consent</h3>
                <p class="mt-3 text-sm text-[#475569]">{{ $customer->marketing_consent || $customer->accepts_marketing ? 'Customer has accepted marketing messages.' : 'Customer has not accepted marketing messages.' }}</p>
                @if($customer->marketing_consent_at)
                    <p class="mt-1 text-xs text-[#94A3B8]">Recorded {{ $customer->marketing_consent_at->format('M d, Y') }} from {{ $customer->marketing_consent_source ?: 'dashboard' }}.</p>
                @endif
            </article>
        </aside>
    </section>
</div>
@endsection
