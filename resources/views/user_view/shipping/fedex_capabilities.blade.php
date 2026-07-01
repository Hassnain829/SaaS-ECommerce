@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Capabilities | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-8">
        <div>
            <h1 class="font-poppins text-lg font-semibold text-[#0F172A] md:text-xl">
                @if ($evidenceMode)
                    FedEx capability disclosure — evidence capture
                @else
                    FedEx capabilities
                @endif
            </h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Supported services and handling in {{ config('app.name') }}</p>
        </div>
        @unless ($evidenceMode)
            <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation', $account) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back to validation workspace</a>
        @endunless
    </header>
@endsection

@section('content')
    <div @class(['mx-auto max-w-[960px] space-y-6', 'p-6' => $evidenceMode])>
        @if ($evidenceMode)
            <div class="rounded-xl border border-[#CBD5E1] bg-white p-4 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">{{ config('app.name') }} — FedEx capability disclosure</p>
                <p class="mt-1">Evidence capture mode · Registry {{ $registryVersion }} · Captured {{ $capturedAt }}</p>
                @if ($logoHash)
                    <p class="mt-1 text-xs">Logo hash suffix: {{ substr($logoHash, -8) }}</p>
                @endif
            </div>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <x-fedex.brand-mark :show-clear-space-guide="$evidenceMode" context="capabilities-page" />
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#0F172A]">Supported FedEx services</h2>
            <p class="mt-1 text-sm text-[#64748B]">Services genuinely available in this application today.</p>
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
                <a href="{{ route('settings.shipping.carrier-accounts.fedex.capabilities', [$account, 'evidence_mode' => 1]) }}" class="font-semibold text-[#0052CC]">evidence capture mode</a>.
            </p>
        @endunless
    </div>
@endsection
