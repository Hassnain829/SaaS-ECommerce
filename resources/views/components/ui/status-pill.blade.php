@props([
    'status' => 'not_configured',
])

@php
    $map = [
        'not_configured' => ['label' => 'Not configured', 'class' => 'ui-badge-neutral'],
        'setup_required' => ['label' => 'Setup needed', 'class' => 'ui-badge-warning'],
        'ready' => ['label' => 'Ready', 'class' => 'ui-badge-success'],
        'connected' => ['label' => 'Connected', 'class' => 'ui-badge-success'],
        'needs_attention' => ['label' => 'Needs attention', 'class' => 'ui-badge-warning'],
        'disabled' => ['label' => 'Disabled', 'class' => 'ui-badge-neutral'],
        'failed' => ['label' => 'Needs attention', 'class' => 'ui-badge-danger'],
    ];
    $meta = $map[$status] ?? ['label' => str((string) $status)->replace('_', ' ')->title()->toString(), 'class' => 'ui-badge-neutral'];
@endphp

<span {{ $attributes->class(['ui-status-pill', $meta['class']]) }}>{{ $slot->isEmpty() ? $meta['label'] : $slot }}</span>
