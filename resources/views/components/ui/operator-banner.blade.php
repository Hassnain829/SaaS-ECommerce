@props([
    'title' => 'Approval tools',
])

<div {{ $attributes->class(['ui-operator-banner']) }} role="status">
    <p class="font-semibold">{{ $title }}</p>
    <p class="mt-1">
        {{ $slot->isEmpty()
            ? 'These certification tools are not required for day-to-day delivery setup. Use them only when preparing a carrier approval package.'
            : $slot }}
    </p>
</div>
