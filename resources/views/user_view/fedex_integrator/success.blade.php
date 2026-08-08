@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Connected — '.config('app.name'))

@section('content')
    @php
        $intent = $returnIntent ?? null;
        $primaryHref = $intent
            ? route($intent['route'], $intent['params'] ?? [])
            : (($account ?? null)
                ? route('settings.shipping.fedex-integrator.manage', $account)
                : route('shippingAutomation', ['tab' => 'providers']));
        $primaryLabel = $intent['label'] ?? 'Open FedEx Center';
        $envLabel = strtoupper((string) ($session->environment ?? 'sandbox')) === 'LIVE' ? 'Live' : 'Sandbox';
    @endphp
    <div class="mx-auto max-w-[760px] space-y-6">
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-emerald-950">FedEx connected</h2>
            <p class="mt-2 text-sm text-emerald-900">Your FedEx account is connected for this store. FedEx billing stays between you and FedEx.</p>
            <dl class="mt-4 space-y-2 text-sm text-emerald-950">
                <div><span class="font-semibold">Account:</span> {{ $session->maskedAccountNumber() }}</div>
                <div><span class="font-semibold">Environment:</span> {{ $envLabel }}</div>
                <div><span class="font-semibold">Billing:</span> Handled by your FedEx account</div>
            </dl>

            @if ($directChildAuthorization ?? false)
                <div class="mt-4 rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm text-emerald-950">
                    <p class="font-semibold">Connection completed</p>
                    <p class="mt-1 text-emerald-900">Your FedEx account was verified successfully.</p>
                </div>
            @endif

            <p class="mt-4 text-sm text-emerald-900">
                Next: configure how you want to use FedEx at checkout.
            </p>

            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ $primaryHref }}" class="inline-flex rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white">{{ $primaryLabel }}</a>
                @if (($intent['key'] ?? null) !== 'fedex_center' && ($account ?? null))
                    <a href="{{ route('settings.shipping.fedex-integrator.manage', $account) }}" class="inline-flex rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-900">Open FedEx Center</a>
                @endif
            </div>
        </section>
    </div>
@endsection
