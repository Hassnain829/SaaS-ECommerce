@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Verification | BaaS Core')

@section('content')
    <div class="mx-auto max-w-[760px] space-y-6">
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">Step 4 — FedEx verification</h2>
            @if ($session->status === \App\Models\CarrierAccountRegistrationSession::STATUS_PIN_PENDING)
                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.verify-pin', $session) }}" class="mt-4 space-y-3">
                    @csrf
                    <p class="text-sm text-[#64748B]">Enter the PIN FedEx sent via {{ $session->mfa_method }}@if ($session->mfa_destination_masked) to {{ $session->mfa_destination_masked }}@endif.</p>
                    @unless ($pinEndpointConfigured ?? false)
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">PIN validation endpoint is not configured yet. Set <code>FEDEX_MFA_PIN_VALIDATION_PATH</code> from the FedEx Developer Portal before submitting a code.</p>
                    @endunless
                    <input name="pin" required class="h-10 w-full max-w-xs rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="6-digit PIN">
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Verify PIN</button>
                </form>
            @elseif ($session->status === \App\Models\CarrierAccountRegistrationSession::STATUS_INVOICE_PENDING)
                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.verify-invoice', $session) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    @unless ($invoiceEndpointConfigured ?? false)
                        <p class="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Invoice validation endpoint is not configured yet. Set <code>FEDEX_MFA_INVOICE_VALIDATION_PATH</code> from the FedEx Developer Portal.</p>
                    @endunless
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Invoice number</span><input name="invoice_number" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Invoice date</span><input name="invoice_date" type="date" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Currency</span><input name="invoice_currency" value="USD" maxlength="3" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Amount</span><input name="invoice_amount" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    <div class="sm:col-span-2"><button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Verify invoice</button></div>
                </form>
            @else
                <p class="mt-2 text-sm text-[#64748B]">FedEx requires additional verification before child credentials can be issued. Choose a method:</p>
                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.mfa-method', $session) }}" class="mt-4 space-y-3">
                    @csrf
                    @forelse ($mfaOptions as $option)
                        <label class="flex items-start gap-2 rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#334155]">
                            <input type="radio" name="mfa_method" value="{{ $option['raw_key'] ?? 'email' }}" required class="mt-1">
                            <span>
                                <span class="font-semibold text-[#0F172A]">{{ $option['label'] ?? 'FedEx verification' }}</span>
                                @if (! empty($option['destination_masked']))
                                    <span class="block text-xs text-[#64748B]">{{ $option['destination_masked'] }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        @foreach (['email' => 'Email PIN', 'sms' => 'SMS PIN', 'call' => 'Phone call PIN', 'invoice' => 'Invoice verification'] as $value => $label)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mfa_method" value="{{ $value }}" required> {{ $label }}</label>
                        @endforeach
                    @endforelse
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Continue</button>
                </form>
            @endif
            @if ($session->fedex_transaction_id)
                <p class="mt-4 text-xs text-[#64748B]">FedEx transaction reference: {{ $session->fedex_transaction_id }}</p>
            @endif
        </section>
    </div>
@endsection
