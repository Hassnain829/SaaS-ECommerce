@props([
    'message' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div
    x-data
    {{ $attributes->class(['ui-sticky-next']) }}
    role="region"
    aria-label="Next step"
>
    <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
        <p class="text-sm font-medium text-[color:var(--color-ink)]">
            {{ $message ?? $slot }}
        </p>
        @if ($actionLabel && $actionHref)
            <x-ui.button :href="$actionHref">{{ $actionLabel }}</x-ui.button>
        @endif
    </div>
</div>
