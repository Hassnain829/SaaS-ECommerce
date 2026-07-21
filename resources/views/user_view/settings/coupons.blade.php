@extends('layouts.user.user-sidebar')

@section('title', 'Discounts | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Discounts" lead="Create simple coupon codes for platform checkout." />
@endsection

@section('content')
    @php
        $field = 'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/15';
        $label = 'block text-xs font-semibold uppercase tracking-wide text-slate-600';
    @endphp

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($canManageCoupons)
            <details class="rounded-2xl border border-slate-200 bg-white shadow-sm" @if($errors->any()) open @endif>
                <summary class="cursor-pointer list-none px-5 py-4 text-sm font-semibold text-slate-900">
                    Create coupon
                    <span class="ml-2 text-xs font-normal text-slate-500">One code, one clear discount rule</span>
                </summary>
                <form method="POST" action="{{ route('settings.coupons.store') }}" class="grid gap-4 border-t border-slate-100 p-5 md:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <label class="{{ $label }}">Coupon code
                        <input name="code" value="{{ old('code') }}" required maxlength="100" placeholder="WELCOME10" class="{{ $field }} uppercase">
                    </label>
                    <label class="{{ $label }}">Internal name
                        <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Welcome offer" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Discount type
                        <select name="type" class="{{ $field }}">
                            <option value="percentage" @selected(old('type', 'percentage') === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(old('type') === 'fixed')>Fixed amount</option>
                        </select>
                    </label>
                    <label class="{{ $label }}">Value
                        <input type="number" name="value" value="{{ old('value') }}" min="0.0001" step="0.0001" required class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Minimum order ({{ $currencyCode }})
                        <input type="number" name="minimum_order_amount" value="{{ old('minimum_order_amount', 0) }}" min="0" step="0.01" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Maximum discount (optional)
                        <input type="number" name="maximum_discount_amount" value="{{ old('maximum_discount_amount') }}" min="0.01" step="0.01" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Total uses (optional)
                        <input type="number" name="total_usage_limit" value="{{ old('total_usage_limit') }}" min="1" step="1" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Uses per customer (optional)
                        <input type="number" name="per_customer_usage_limit" value="{{ old('per_customer_usage_limit') }}" min="1" step="1" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Starts (optional)
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }}">Expires (optional)
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="{{ $field }}">
                    </label>
                    <label class="{{ $label }} md:col-span-2">Eligible product SKUs (optional)
                        <input name="product_skus" value="{{ old('product_skus') }}" placeholder="SKU-100, SKU-200 — leave blank for all products" class="{{ $field }}">
                    </label>
                    @if ($categories->isNotEmpty())
                        <fieldset class="md:col-span-2 xl:col-span-4">
                            <legend class="{{ $label }}">Eligible categories (optional)</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($categories as $category)
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', [])))>
                                        {{ $category->name }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        Active immediately
                    </label>
                    <div class="flex justify-end md:col-span-2 xl:col-span-3">
                        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Create coupon</button>
                    </div>
                </form>
            </details>
        @endif

        <section class="space-y-3">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Coupon codes</h2>
                    <p class="text-sm text-slate-500">A coupon applies once when a platform checkout is created.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $coupons->count() }} total</span>
            </div>

            @forelse ($coupons as $coupon)
                <details class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <summary class="grid cursor-pointer list-none gap-3 px-5 py-4 md:grid-cols-[1fr_auto_auto_auto] md:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <code class="rounded-lg bg-slate-900 px-2.5 py-1 text-sm font-bold text-white">{{ $coupon->code }}</code>
                                <span class="font-semibold text-slate-900">{{ $coupon->name }}</span>
                                <span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-700' => $coupon->is_active, 'bg-slate-100 text-slate-600' => ! $coupon->is_active])>
                                    {{ $coupon->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $coupon->type === 'percentage' ? rtrim(rtrim($coupon->value, '0'), '.').'%' : $currencyCode.' '.number_format((float) $coupon->value, 2) }} off
                                · {{ $coupon->products->isEmpty() && $coupon->categories->isEmpty() ? 'All products' : 'Selected products/categories' }}
                            </p>
                        </div>
                        <span class="text-sm text-slate-600">{{ $coupon->redeemed_count }} redeemed</span>
                        <span class="text-sm text-slate-600">{{ $coupon->expires_at ? 'Ends '.$coupon->expires_at->format('M j, Y') : 'No expiry' }}</span>
                        <span class="text-xs font-semibold text-indigo-600">View / edit</span>
                    </summary>

                    <div class="border-t border-slate-100 p-5">
                        @if ($canManageCoupons)
                            <form method="POST" action="{{ route('settings.coupons.update', $coupon) }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                @csrf
                                @method('PATCH')
                                <label class="{{ $label }}">Coupon code<input name="code" value="{{ $coupon->code }}" required class="{{ $field }} uppercase"></label>
                                <label class="{{ $label }}">Internal name<input name="name" value="{{ $coupon->name }}" required class="{{ $field }}"></label>
                                <label class="{{ $label }}">Discount type
                                    <select name="type" class="{{ $field }}">
                                        <option value="percentage" @selected($coupon->type === 'percentage')>Percentage</option>
                                        <option value="fixed" @selected($coupon->type === 'fixed')>Fixed amount</option>
                                    </select>
                                </label>
                                <label class="{{ $label }}">Value<input type="number" name="value" value="{{ $coupon->value }}" min="0.0001" step="0.0001" required class="{{ $field }}"></label>
                                <label class="{{ $label }}">Minimum order<input type="number" name="minimum_order_amount" value="{{ $coupon->minimum_order_amount }}" min="0" step="0.01" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Maximum discount<input type="number" name="maximum_discount_amount" value="{{ $coupon->maximum_discount_amount }}" min="0.01" step="0.01" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Total uses<input type="number" name="total_usage_limit" value="{{ $coupon->total_usage_limit }}" min="1" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Uses per customer<input type="number" name="per_customer_usage_limit" value="{{ $coupon->per_customer_usage_limit }}" min="1" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Starts<input type="datetime-local" name="starts_at" value="{{ $coupon->starts_at?->format('Y-m-d\TH:i') }}" class="{{ $field }}"></label>
                                <label class="{{ $label }}">Expires<input type="datetime-local" name="expires_at" value="{{ $coupon->expires_at?->format('Y-m-d\TH:i') }}" class="{{ $field }}"></label>
                                <label class="{{ $label }} md:col-span-2">Eligible product SKUs<input name="product_skus" value="{{ $coupon->products->pluck('sku')->filter()->implode(', ') }}" class="{{ $field }}" placeholder="Leave blank for all products"></label>
                                @if ($categories->isNotEmpty())
                                    <fieldset class="md:col-span-2 xl:col-span-4">
                                        <legend class="{{ $label }}">Eligible categories</legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($categories as $category)
                                                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked($coupon->categories->contains('id', $category->id))>
                                                    {{ $category->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endif
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($coupon->is_active)>
                                    Active
                                </label>
                                <div class="flex justify-end md:col-span-2 xl:col-span-3">
                                    <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('settings.coupons.destroy', $coupon) }}" class="mt-4 border-t border-slate-100 pt-4" onsubmit="return confirm('Delete this coupon? Existing order records will remain.');">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm font-semibold text-red-600 hover:text-red-700">Delete coupon</button>
                            </form>
                        @else
                            <p class="text-sm text-slate-600">You can view coupon settings, but only a store owner can change them.</p>
                        @endif
                    </div>
                </details>
            @empty
                <x-ui.empty-state title="No coupons yet" lead="Create a fixed or percentage discount code when you are ready." />
            @endforelse
        </section>
    </div>
@endsection
