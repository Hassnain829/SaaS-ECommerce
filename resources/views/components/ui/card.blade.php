@props([
    'padding' => true,
])

<div {{ $attributes->class(['ui-card', 'p-5' => $padding]) }}>
    {{ $slot }}
</div>
