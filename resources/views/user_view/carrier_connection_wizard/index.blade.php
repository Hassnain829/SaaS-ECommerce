@extends('layouts.user.user-sidebar')

@section('title', 'Connect Carrier Account | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Connect carrier account</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Choose a carrier and set up a ship-from fulfillment location.</p>
        </div>
        <a href="{{ route('shippingAutomation') }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back to Shipping &amp; Delivery</a>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[1100px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-poppins font-semibold text-[#0F172A]">Step 1 — Choose carrier</h2>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">Pick the carrier you want to connect. Platform testing connections use platform credentials and are not your merchant-owned carrier account.</p>

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
                                <a href="{{ route('shipping.carriers.connect.show', $card['code']) }}" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">{{ $card['action'] }}</a>
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
