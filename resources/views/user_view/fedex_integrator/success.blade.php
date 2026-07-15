@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Connected | BaaS Core')

@section('content')
    <div class="mx-auto max-w-[760px] space-y-6">
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-emerald-950">Step 5 — FedEx account connected</h2>
            <p class="mt-2 text-sm text-emerald-900">Merchant-owned FedEx account connected through platform integrator registration.</p>
            <dl class="mt-4 space-y-2 text-sm text-emerald-950">
                <div><span class="font-semibold">Account:</span> {{ $session->maskedAccountNumber() }}</div>
                <div><span class="font-semibold">Environment:</span> {{ strtoupper($session->environment) }}</div>
                <div><span class="font-semibold">Billing:</span> Handled by merchant (FedEx bills you directly)</div>
            </dl>

            @if ($directChildAuthorization ?? false)
                <div class="mt-4 rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm text-emerald-950">
                    <p class="font-semibold">Direct Child Authorization completed</p>
                    <p class="mt-1 text-emerald-900">
                        Child credentials were returned after address validation. PIN, SMS, phone, and invoice verification were skipped.
                    </p>
                </div>
            @endif

            <p class="mt-4 text-sm text-emerald-900">Rates, labels, tracking, pickup, and checkout live rates remain disabled until each capability is separately validated.</p>
            <a href="{{ route('shippingAutomation', ['tab' => 'carriers']) }}" class="mt-5 inline-flex rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Back to Shipping &amp; Delivery</a>
        </section>
    </div>
@endsection
