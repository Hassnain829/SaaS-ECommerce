@props([
    'variant' => 'badge', // badge | wordmark
    'size' => 24,
])

@php
    $size = (int) $size;
    $wordmarkWidth = max(48, $size);
    $wordmarkHeight = (int) round($wordmarkWidth * (214 / 512));
@endphp

@if ($variant === 'wordmark')
    <span {{ $attributes->class(['pay-stripe-mark', 'pay-stripe-mark-wordmark']) }}>
        @include('components.brand.partials.stripe-wordmark-svg', [
            'width' => $wordmarkWidth,
            'height' => $wordmarkHeight,
        ])
    </span>
@else
    <span {{ $attributes->class(['pay-stripe-mark', 'pay-stripe-mark-badge']) }}>
        @include('components.brand.partials.stripe-badge-svg', [
            'size' => $size,
        ])
    </span>
@endif
