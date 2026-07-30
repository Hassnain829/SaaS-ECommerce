@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Capabilities | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar
        :title="$evidenceMode ? 'FedEx capability disclosure' : 'FedEx capabilities'"
        lead="Supported services and packaging shown with registered FedEx service marks"
    >
        <x-slot:actions>
            @unless ($evidenceMode)
                <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation', $account) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back to validation workspace</a>
            @endunless
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
<div @class(['mx-auto max-w-[960px] space-y-6', 'p-6' => $evidenceMode])>
        @if ($evidenceMode)
            <div class="rounded-xl border border-[#CBD5E1] bg-white p-4 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">{{ config('app.name') }} — FedEx capability disclosure</p>
                <p class="mt-1">Evidence capture mode · Captured {{ $capturedAt }}</p>
            </div>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <x-fedex.brand-mark :show-clear-space-guide="$evidenceMode" :show-legal-notice="false" context="capabilities-page" />
            <p class="fedex-brand-legal-notice mt-4 text-sm text-[#475569]">{{ $legalNotice }}</p>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#0F172A]">Supported FedEx services</h2>
            <p class="mt-1 text-sm text-[#64748B]">Services available in this application, shown with registered service marks.</p>
            <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                @forelse ($customerCapabilities['services'] ?? [] as $service)
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">
                        <span class="font-semibold">{{ $service['display_name'] ?? 'Service' }}</span>
                        @if (! empty($service['conditions']))
                            <p class="mt-1 text-xs text-[#64748B]">{{ implode(' ', $service['conditions']) }}</p>
                        @endif
                    </li>
                @empty
                    <li class="text-[#64748B]">No production-enabled services are advertised yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#0F172A]">Supported packaging</h2>
            <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                @forelse ($customerCapabilities['packaging'] ?? [] as $packaging)
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">{{ $packaging['display_name'] ?? 'Packaging' }}</li>
                @empty
                    <li class="text-[#64748B]">No production packaging types are listed yet.</li>
                @endforelse
            </ul>
        </section>

        @if ($evidenceMode)
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#0F172A]">Registered service marks used in this application</h2>
                <p class="mt-1 text-sm text-[#64748B]">Customer-facing display names for branding evidence.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold text-[#0F172A]">Services</h3>
                        <ul class="mt-2 space-y-2 text-sm text-[#334155]">
                            @foreach ($brandingEvidenceNames['services'] ?? [] as $name)
                                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 font-semibold">{{ $name }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-[#0F172A]">Packaging</h3>
                        <ul class="mt-2 space-y-2 text-sm text-[#334155]">
                            @foreach ($brandingEvidenceNames['packaging'] ?? [] as $name)
                                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 font-semibold">{{ $name }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#0F172A]">Shipment-level special handling</h2>
            <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                @forelse ($customerCapabilities['shipment_special_services'] ?? [] as $service)
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">{{ $service['display_name'] ?? 'Special service' }}</li>
                @empty
                    <li class="text-[#64748B]">No shipment-level special services are production-enabled.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#0F172A]">Package-level special handling</h2>
            <ul class="mt-4 space-y-2 text-sm text-[#334155]">
                @forelse ($customerCapabilities['package_special_services'] ?? [] as $service)
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">{{ $service['display_name'] ?? 'Special service' }}</li>
                @empty
                    <li class="text-[#64748B]">No package-level special services are production-enabled.</li>
                @endforelse
            </ul>
        </section>

        @unless ($evidenceMode)
            <p class="text-xs text-[#64748B]">
                For FedEx validation screenshots, open
                <a href="{{ route('settings.shipping.carrier-accounts.fedex.capabilities', [$account, 'evidence_mode' => 1]) }}" class="font-semibold text-[#0052CC]">branding evidence mode</a>.
            </p>
        @endunless
    </div>
@endsection
