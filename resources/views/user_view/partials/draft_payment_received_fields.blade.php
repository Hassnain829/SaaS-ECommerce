@php
    use App\Services\ManualOrderPaymentService;

    $paymentReceived = filter_var(old('payment_received', false), FILTER_VALIDATE_BOOLEAN);
    $selectedMethod = old('payment_method', ManualOrderPaymentService::METHOD_CASH);
@endphp

<div data-draft-payment-received>
    <h3 class="text-sm font-semibold text-[#0F172A]">Payment</h3>
    <p class="mt-1 text-xs text-[#64748B]">Creating the order does not charge a card. Record payment only if you already collected it.</p>

    <input type="hidden" name="payment_received" value="0">
    <label class="mt-3 flex items-start gap-2 text-sm text-[#334155]">
        <input
            type="checkbox"
            name="payment_received"
            value="1"
            class="mt-0.5 rounded border-[#CBD5E1]"
            data-payment-received-checkbox
            @checked($paymentReceived)
        >
        <span>Payment already received</span>
    </label>

    <div class="mt-3 space-y-3 {{ $paymentReceived ? '' : 'hidden' }}" data-payment-received-fields>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">How was it paid?</span>
            <select
                name="payment_method"
                class="mt-1 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm"
                data-payment-method-input
                @disabled(! $paymentReceived)
            >
                @foreach (ManualOrderPaymentService::methods() as $value => $label)
                    <option value="{{ $value }}" @selected($selectedMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('payment_method')
                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-[#64748B]">Reference <span class="font-normal">(optional)</span></span>
            <input
                name="payment_reference"
                value="{{ old('payment_reference') }}"
                maxlength="120"
                class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm"
                placeholder="Receipt number or note"
                data-payment-reference-input
                @disabled(! $paymentReceived)
            >
            @error('payment_reference')
                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        </label>
    </div>
    @error('payment')
        <p class="mt-2 text-xs text-[#B91C1C]">{{ $message }}</p>
    @enderror
</div>
