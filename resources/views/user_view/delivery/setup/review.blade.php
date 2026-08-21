@extends('user_view.delivery.wizard-layout')

@section('wizard-content')
    @php
        $setup = $deliverySetup ?? [];
    @endphp

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-2xl font-semibold text-[#0F172A]">Review your delivery setup</h2>
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
                        <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Checkout shipping</p>
                        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $checkoutModeLabel ?? ($setup['delivery_options']['title'] ?? 'Not configured') }}</p>
                        <p class="mt-1 text-sm text-[#64748B]">{{ $checkoutModeDetail ?? ($setup['delivery_options']['detail'] ?? '') }}</p>
                        @if (! empty($fedExAccountMasked))
                            <p class="mt-2 text-xs text-[#64748B]">FedEx account: {{ $fedExAccountMasked }}</p>
                        @endif
                        @if (! empty($fedExAccountMasked) && ! ($fedExCheckoutRatesPlatformEnabled ?? false))
                            <p class="mt-2 text-xs text-amber-800">Platform checkout rates are currently off. Saved FedEx options will show live prices once checkout rates are enabled.</p>
                        @endif
                    </div>
                    <a href="{{ route('settings.delivery.setup.delivery-option') }}" class="text-sm font-semibold text-[#1D4ED8]">Edit</a>
                </div>
            </article>
        </div>

        @if (! ($setup['is_ready'] ?? false))
            <div class="mt-5 space-y-3 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
                <p class="font-semibold">Delivery setup is not ready to finish yet.</p>
                <ul class="list-disc space-y-1 pl-5">
                    @forelse (collect($setup['blocking_items'] ?? [])->take(5) as $item)
                        <li>
                            {{ $item['message'] ?? 'A delivery setting still needs attention.' }}
                            @if (! empty($item['action_href']))
                                <a href="{{ $item['action_href'] }}" class="font-semibold text-[#1D4ED8] hover:underline">{{ $item['action_label'] ?? 'Fix' }}</a>
                            @elseif (! empty($item['action_tab']))
                                <a href="{{ route('shippingAutomation', ['tab' => $item['action_tab']]) }}" class="font-semibold text-[#1D4ED8] hover:underline">{{ $item['action_label'] ?? 'Fix' }}</a>
                            @endif
                        </li>
                    @empty
                        <li>Confirm ship-from, an active delivery area, and at least one checkout-visible delivery option with valid pricing.</li>
                    @endforelse
                </ul>
            </div>
        @elseif ($setup['has_blocking_health'] ?? false)
            <div class="mt-5 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                You can finish setup. Some delivery options still need cleanup — review them from the Delivery hub after finishing.
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
            <a href="{{ route('settings.delivery.test-address') }}" class="text-sm font-semibold text-[#1D4ED8]">Preview checkout delivery</a>
            <form method="POST" action="{{ route('settings.delivery.setup.finish') }}">
                @csrf
                <button
                    type="submit"
                    @disabled(! ($setup['is_ready'] ?? false))
                    class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Finish delivery setup
                </button>
            </form>
        </div>
    </section>
@endsection
