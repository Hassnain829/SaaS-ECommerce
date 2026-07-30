@props([
    'tone' => 'neutral',
])

@php
    $toneClass = match ($tone) {
        'brand' => 'ui-badge-brand',
        'success' => 'ui-badge-success',
        'warning' => 'ui-badge-warning',
        'danger' => 'ui-badge-danger',
        'info' => 'ui-badge-info',
        default => 'ui-badge-neutral',
    };
@endphp

<span {{ $attributes->class(['ui-badge', $toneClass]) }}>{{ $slot }}</span>
