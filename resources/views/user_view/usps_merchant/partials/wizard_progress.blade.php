@php
    $stepBadgeClass = static function (string $status): string {
        return match ($status) {
            'complete' => 'bg-[#ECFDF5] text-[#047857] border-[#BBF7D0]',
            'current' => 'bg-[#EFF6FF] text-[#1D4ED8] border-[#BFDBFE]',
            default => 'bg-[#F8FAFC] text-[#94A3B8] border-[#E2E8F0]',
        };
    };
@endphp
<nav aria-label="USPS setup progress" class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
    <ol class="grid gap-2 sm:grid-cols-4">
        @foreach ($progress as $item)
            <li class="rounded-xl border px-3 py-2 text-center text-xs font-semibold {{ $stepBadgeClass($item['status']) }}">
                <span class="block text-[10px] uppercase tracking-wide opacity-80">Step {{ $loop->iteration }}</span>
                <span class="mt-1 block text-sm">{{ $item['label'] }}</span>
            </li>
        @endforeach
    </ol>
</nav>
