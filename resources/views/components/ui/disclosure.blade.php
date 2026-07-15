@props([
    'summary',
    'open' => false,
])

<details {{ $attributes->class(['ui-disclosure']) }} @if ($open) open @endif>
    <summary>
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 text-sm font-semibold text-[color:var(--color-ink)]">{{ $summary }}</div>
            <span class="shrink-0 text-xs font-semibold text-[color:var(--color-ink-muted)]">Expand</span>
        </div>
    </summary>
    <div class="mt-4 border-t border-[color:var(--color-border)] pt-4">
        {{ $slot }}
    </div>
</details>
