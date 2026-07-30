@props([
    'title',
    'lead' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->class(['ui-empty-state']) }}>
    <h3 class="font-sans text-base font-semibold text-[color:var(--color-ink)]">{{ $title }}</h3>
    @if ($lead)
        <p class="mx-auto mt-2 max-w-md text-sm text-[color:var(--color-ink-muted)]">{{ $lead }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @elseif ($actionLabel && $actionHref)
        <div class="mt-4">
            <x-ui.button :href="$actionHref">{{ $actionLabel }}</x-ui.button>
        </div>
    @endif
</div>
