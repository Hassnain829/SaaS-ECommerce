@php
    $current = $current ?? 'payments';
    $steps = [
        [
            'key' => 'payments',
            'title' => 'Payments',
            'description' => 'Decide who collects money and connect Stripe only if platform checkout is used.',
            'route' => route('settings.payments.index'),
        ],
        [
            'key' => 'taxes',
            'title' => 'Checkout & tax',
            'description' => 'Define tax behavior and country/region rates for platform checkout.',
            'route' => route('settings.taxes.index'),
        ],
        [
            'key' => 'delivery',
            'title' => 'Delivery',
            'description' => 'Set ship-from locations, delivery areas, and customer-facing delivery options.',
            'route' => route('shippingAutomation'),
        ],
    ];

    $currentIndex = collect($steps)->search(fn ($step) => $step['key'] === $current);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp

<section class="settings-journey">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Setup flow</p>
            <p class="mt-1 text-sm text-[#475569]">Follow this sequence to avoid conflicting checkout behavior.</p>
        </div>
        <p class="text-xs font-semibold text-[#64748B]">Step {{ $currentIndex + 1 }} of {{ count($steps) }}</p>
    </div>

    <div class="settings-journey-steps mt-3">
        @foreach($steps as $index => $step)
            @php
                $isCurrent = $step['key'] === $current;
                $isComplete = $index < $currentIndex;
            @endphp
            <a href="{{ $step['route'] }}"
               @class([
                   'settings-journey-step',
                   'settings-journey-step-active' => $isCurrent,
                   'settings-journey-step-complete' => $isComplete,
               ])>
                <div class="flex items-start gap-2">
                    <span class="settings-journey-step-index">{{ $index + 1 }}</span>
                    <div>
                        <p class="text-sm font-semibold text-[#0F172A]">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs text-[#64748B]">{{ $step['description'] }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</section>
