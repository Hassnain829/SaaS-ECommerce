@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'size' => 'md',
])

@php
    $classes = match ($variant) {
        'secondary' => 'ui-btn ui-btn-secondary',
        'danger' => 'ui-btn ui-btn-danger',
        'ghost' => 'ui-btn ui-btn-ghost',
        default => 'ui-btn ui-btn-primary',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </button>
@endif
