@extends('layouts.user.user-sidebar')

@section('title', 'Connect Carrier Account | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Connect carrier account" lead="Choose a carrier and ship-from fulfillment location.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="mx-auto max-w-[1100px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Choose a carrier</h2>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">FedEx uses integrator provider registration for merchant-owned accounts. USPS merchant connection uses Label Provider authorization in the USPS Business Portal. Manual/local delivery is for couriers and store pickup without a live carrier API.</p>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach ($carrierCards as $card)
                    <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-[#0F172A]">{{ $card['name'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $card['summary'] }}</p>
                            </div>
                            @if ($card['deferred'] ?? false)
                                <span class="rounded-full bg-[#F1F5F9] px-2.5 py-1 text-xs font-bold text-[#64748B]">Coming later</span>
                            @elseif ($card['blocked'] ?? false)
                                <span class="rounded-full bg-[#FEF2F2] px-2.5 py-1 text-xs font-bold text-[#991B1B]">Carrier support required</span>
                            @elseif ($card['available'] ?? false)
                                <span class="rounded-full bg-[#ECFDF5] px-2.5 py-1 text-xs font-bold text-[#047857]">Available</span>
                            @else
                                <span class="rounded-full bg-[#FEF3C7] px-2.5 py-1 text-xs font-bold text-[#92400E]">Setup required</span>
                            @endif
                        </div>
                        <div class="mt-4">
                            @if (($card['available'] ?? false) && ! ($card['deferred'] ?? false) && ($canManageShipping ?? false))
                                @php
                                    $connectRouteName = $card['connect_route'] ?? 'shipping.carriers.connect.show';
                                    $connectRouteParams = $connectRouteName === 'shipping.carriers.connect.show' ? $card['code'] : [];
                                @endphp
                                <a href="{{ route($connectRouteName, $connectRouteParams) }}" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">{{ $card['action'] }}</a>
                            @else
                                <span class="text-sm font-semibold text-[#64748B]">{{ $card['action'] }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
