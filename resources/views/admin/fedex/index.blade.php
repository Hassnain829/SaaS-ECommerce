@extends('layouts.admin.admin-Sidebar')

@section('title', 'FedEx — '.config('app.name'))

@section('content')
    @php
        $tabLabels = [
            'overview' => 'Overview',
            'connections' => 'Connections',
            'api-events' => 'API events',
            'shipments' => 'Shipments',
            'trade-documents' => 'Trade documents',
        ];
        $tabQuery = request()->except('tab', 'page');
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">FedEx</h1>
            <p class="mt-1 text-sm text-slate-600">Platform operations console. Credentials, tokens, and raw FedEx payloads are never shown.</p>
        </div>

        <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-1" aria-label="FedEx sections">
            @foreach ($tabs as $tabKey)
                <a
                    href="{{ route('admin.fedex.index', array_merge(['tab' => $tabKey], $tabKey === 'api-events' ? $tabQuery : [])) }}"
                    class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors {{ $tab === $tabKey ? 'border border-b-0 border-slate-200 bg-white text-slate-900' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}"
                    @if ($tab === $tabKey) aria-current="page" @endif
                >
                    {{ $tabLabels[$tabKey] ?? $tabKey }}
                </a>
            @endforeach
        </nav>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Platform flags</h2>
            <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <li>Production: <strong>{{ ($flags['production'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Checkout rates: <strong>{{ ($flags['checkout_rates'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Ship labels: <strong>{{ ($flags['ship_labels'] ?? false) ? 'on' : 'off' }}</strong></li>
                <li>Tracking: <strong>{{ ($flags['tracking'] ?? false) ? 'on' : 'off' }}</strong></li>
            </ul>
        </section>

        @if ($tab === 'overview')
            @include('admin.fedex.partials.overview')
        @elseif ($tab === 'connections')
            @include('admin.fedex.partials.connections')
        @elseif ($tab === 'api-events')
            @include('admin.fedex.partials.api-events')
        @elseif ($tab === 'shipments')
            @include('admin.fedex.partials.shipments')
        @elseif ($tab === 'trade-documents')
            @include('admin.fedex.partials.trade-documents')
        @endif
    </div>
@endsection
