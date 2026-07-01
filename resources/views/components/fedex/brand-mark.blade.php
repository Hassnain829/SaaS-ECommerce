@props([
    'variant' => 'default',
    'size' => 'md',
    'showLegalNotice' => true,
    'context' => 'capabilities',
    'showClearSpaceGuide' => false,
])

@php
    $brand = app(\App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService::class);
    $logoPath = $brand->logoPublicPath();
    $sizeClass = match ($size) {
        'sm' => 'fedex-brand-logo--sm',
        'lg' => 'fedex-brand-logo--lg',
        default => 'fedex-brand-logo--md',
    };
@endphp

<div {{ $attributes->class(['fedex-brand-safe-area', 'fedex-brand-safe-area--'.$variant]) }} data-fedex-brand-context="{{ $context }}">
    @if ($showClearSpaceGuide)
        <div class="fedex-brand-clear-space-guide" aria-hidden="true"></div>
    @endif

    @if ($logoPath)
        <img
            src="{{ asset($logoPath) }}"
            alt="FedEx"
            class="fedex-brand-logo {{ $sizeClass }}"
            width="120"
            height="34"
        >
    @else
        <p class="fedex-brand-logo-missing text-sm text-slate-600">Official FedEx logo asset required</p>
    @endif

    @if ($showLegalNotice)
        <p class="fedex-brand-legal-notice">{{ $brand->legalNotice() }}</p>
    @endif
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/carriers/fedex/fedex-brand.css') }}">
    @endpush
@endonce
