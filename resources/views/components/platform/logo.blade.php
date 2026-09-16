@props([
    'variant' => 'wordmark',
])

@php
    $file = match ($variant) {
        'icon' => 'images/brand/retailo-icon.png',
        'wordmark-on-dark' => 'images/brand/retailo-wordmark-on-dark.png',
        'wordmark-white' => 'images/brand/retailo-wordmark-white.png',
        default => 'images/brand/retailo-wordmark.png',
    };
    $alt = $attributes->get('alt', 'Retailo');
    $isIcon = $variant === 'icon';
@endphp

<img
    src="{{ asset($file) }}"
    alt="{{ $alt }}"
    {{ $attributes->except('alt')->class([
        'retailo-logo',
        'retailo-logo--icon' => $isIcon,
        'retailo-logo--wordmark' => ! $isIcon,
    ]) }}
>
