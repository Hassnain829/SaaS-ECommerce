@extends('user_view.delivery.wizard-layout')

@section('wizard-content')
    @php
        $setup = $deliverySetup ?? [];
    @endphp

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-2xl font-poppins font-semibold text-[#0F172A]">Review your delivery setup</h2>
        <p class="mt-2 text-sm text-[#64748B]">This summary reflects saved delivery settings. Tax is configured separately under Checkout &amp; tax.</p>

        <div class="mt-6 space-y-4">
            <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Ship from</p>
                        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $setup['ship_from']['title'] ?? 'Not configured' }}</p>
                        <p class="mt-1 text-sm text-[#64748B]">{{ $setup['ship_from']['detail'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('settings.delivery.setup.ship-from') }}" class="text-sm font-semibold text-[#1D4ED8]">Edit</a>
                </div>
            </article>

            <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Deliver to</p>
                        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $setup['delivery_areas']['title'] ?? 'Not configured' }}</p>
                        <p class="mt-1 text-sm text-[#64748B]">{{ $setup['delivery_areas']['detail'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('settings.delivery.setup.deliver-to') }}" class="text-sm font-semibold text-[#1D4ED8]">Edit</a>
                </div>
            </article>

            <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Checkout delivery option</p>
                        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $setup['delivery_options']['title'] ?? 'Not configured' }}</p>
                        <p class="mt-1 text-sm text-[#64748B]">{{ $setup['delivery_options']['detail'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('settings.delivery.setup.delivery-option') }}" class="text-sm font-semibold text-[#1D4ED8]">Edit</a>
                </div>
            </article>

            <article class="rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#1D4ED8]">Checkout tax (read-only)</p>
                        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $setup['tax_summary']['title'] ?? 'Tax is off' }}</p>
                        <p class="mt-1 text-sm text-[#475569]">{{ $setup['tax_summary']['detail'] ?? '' }}</p>
                    </div>
                    <a href="{{ route('settings.taxes.index') }}" class="text-sm font-semibold text-[#1D4ED8]">Edit tax settings</a>
                </div>
            </article>
        </div>

        @if (! ($setup['is_ready'] ?? false))
            <div class="mt-5 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                Delivery setup still needs attention. Use the Delivery hub health cards after finishing to resolve remaining items.
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
            <a href="{{ route('settings.delivery.test-address') }}" class="text-sm font-semibold text-[#1D4ED8]">Test a customer address</a>
            <form method="POST" action="{{ route('settings.delivery.setup.finish') }}">
                @csrf
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-bold text-white">Finish delivery setup</button>
            </form>
        </div>
    </section>
@endsection
