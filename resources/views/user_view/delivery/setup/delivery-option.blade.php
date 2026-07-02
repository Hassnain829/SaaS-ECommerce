@extends('user_view.delivery.wizard-layout')

@section('wizard-content')
    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-2xl font-poppins font-semibold text-[#0F172A]">What should customers see at checkout?</h2>
        <p class="mt-2 text-sm text-[#64748B]">Create or update a delivery option customers can choose during checkout.</p>

        @if ($flagMismatch ?? false)
            <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                @if ($selectedMethod?->is_active && ! $selectedMethod?->enabled_for_checkout)
                    This option is active but hidden from checkout.
                @else
                    This option is shown at checkout but currently inactive.
                @endif
                Choose how to resolve this before saving.
            </div>
        @endif

        <form method="POST" action="{{ route('settings.delivery.setup.delivery-option') }}" class="mt-6 space-y-5">
            @csrf

            @if ($shippingMethods->isNotEmpty())
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Delivery option</span>
                    <select name="shipping_method_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                        <option value="">Create a new delivery option</option>
                        @foreach ($shippingMethods as $method)
                            <option value="{{ $method->id }}" @selected(old('shipping_method_id', $selectedMethod?->id) == $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Option name</span>
                <input name="name" required value="{{ old('name', $selectedMethod?->name ?? 'Standard delivery') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
            </label>

            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Customer label</span>
                <input name="delivery_speed_label" value="{{ old('delivery_speed_label', $selectedMethod?->delivery_speed_label) }}" placeholder="2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
            </label>

            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Delivery area</span>
                <select name="shipping_zone_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                    @foreach ($shippingZones as $zone)
                        <option value="{{ $zone->id }}" @selected(old('shipping_zone_id', $selectedZone?->id) == $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>

            <div>
                <span class="text-xs font-semibold text-[#64748B]">Delivery price</span>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    @foreach (['fixed' => 'Fixed price', 'free' => 'Free', 'free_over' => 'Free over amount'] as $mode => $label)
                        <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                            <input type="radio" name="delivery_price_mode" value="{{ $mode }}" @checked(old('delivery_price_mode', $priceMode) === $mode)> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Price</span><input name="flat_rate" type="number" min="0" step="0.01" value="{{ old('flat_rate', $selectedMethod?->flat_rate ?? 0) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free when order is at least</span><input name="free_over_amount" type="number" min="0" step="0.01" value="{{ old('free_over_amount', $selectedMethod?->free_over_amount) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min days</span><input name="estimated_min_days" type="number" min="0" value="{{ old('estimated_min_days', $selectedMethod?->estimated_min_days) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max days</span><input name="estimated_max_days" type="number" min="0" value="{{ old('estimated_max_days', $selectedMethod?->estimated_max_days) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
            </div>

            @if ($flagMismatch ?? false)
                <fieldset class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <legend class="px-1 text-sm font-semibold text-[#0F172A]">Checkout visibility</legend>
                    <div class="mt-2 space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="resolve_flag_mismatch" value="available" @checked(old('resolve_flag_mismatch') === 'available')> Make available to customers</label>
                        <label class="flex items-center gap-2"><input type="radio" name="resolve_flag_mismatch" value="keep" @checked(old('resolve_flag_mismatch', 'keep') === 'keep')> Keep current settings</label>
                    </div>
                </fieldset>
            @else
                <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                    <input type="hidden" name="available_to_customers" value="0">
                    <input type="checkbox" name="available_to_customers" value="1" @checked(old('available_to_customers', $selectedMethod?->enabled_for_checkout ?? true))>
                    Available to customers at checkout
                </label>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
                <a href="{{ route('settings.delivery.setup.deliver-to') }}" class="text-sm font-semibold text-[#64748B]">Back</a>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Advanced settings</a>
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-bold text-white">Save and continue</button>
                </div>
            </div>
        </form>
    </section>
@endsection
