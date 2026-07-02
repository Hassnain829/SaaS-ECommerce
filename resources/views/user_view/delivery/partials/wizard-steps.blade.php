@props(['step' => 1])

@php
    $steps = [
        1 => ['label' => 'Ship from', 'route' => 'settings.delivery.setup.ship-from'],
        2 => ['label' => 'Deliver to', 'route' => 'settings.delivery.setup.deliver-to'],
        3 => ['label' => 'Delivery option', 'route' => 'settings.delivery.setup.delivery-option'],
        4 => ['label' => 'Review', 'route' => 'settings.delivery.setup.review'],
    ];
@endphp

<nav aria-label="Delivery setup progress" class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
    <ol class="grid gap-3 sm:grid-cols-4">
        @foreach ($steps as $number => $meta)
            @php
                $active = $number === $step;
                $complete = $number < $step;
            @endphp
            <li class="rounded-xl border px-3 py-3 {{ $active ? 'border-[#BFDBFE] bg-[#EFF6FF]' : 'border-[#E2E8F0] bg-[#F8FAFC]' }}">
                @if ($complete)
                    <a href="{{ route($meta['route']) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1D4ED8] rounded-lg">
                @endif
                <p class="text-[11px] font-bold uppercase tracking-wide {{ $active ? 'text-[#1D4ED8]' : 'text-[#94A3B8]' }}">Step {{ $number }}</p>
                <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $meta['label'] }}</p>
                @if ($complete)
                    <p class="mt-1 text-xs text-[#047857]">Saved — edit</p>
                    </a>
                @elseif ($active)
                    <p class="mt-1 text-xs text-[#1D4ED8]" aria-current="step">Current step</p>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
