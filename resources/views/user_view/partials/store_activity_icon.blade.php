@php
    $type = strtolower((string) ($event->event_type ?? ''));
    $kind = match (true) {
        str_starts_with($type, 'shipment.tracking') => 'tracking',
        str_starts_with($type, 'shipment.status') => 'shipment_status',
        str_contains($type, 'shipment') || str_contains($type, 'shipping') => 'shipment',
        $type === 'fulfillment.origin_selected' => 'warehouse',
        str_contains($type, 'fulfillment') => 'package',
        str_contains($type, 'inventory') => 'inventory',
        str_contains($type, 'payment.failed') => 'payment_failed',
        str_contains($type, 'refund') => 'refund',
        str_contains($type, 'payment') || str_contains($type, 'checkout') => 'payment',
        str_contains($type, 'return') => 'return',
        str_contains($type, 'exchange') => 'exchange',
        str_contains($type, 'cancelled') => 'cancelled',
        str_contains($type, 'note') => 'note',
        str_contains($type, 'order.completed') => 'completed',
        default => 'order',
    };
    $tone = match ($kind) {
        'payment', 'payment_failed' => 'blue',
        'inventory' => 'navy',
        'refund', 'return', 'cancelled' => 'red',
        'warehouse', 'package', 'exchange' => 'violet',
        'tracking' => 'amber',
        default => '',
    };
@endphp
<span class="sd-activity-icon {{ $tone }}" aria-hidden="true">
    @switch ($kind)
        @case('shipment')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="18" cy="18" r="1.6"/></svg>
            @break
        @case('shipment_status')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10v8H3zM13 11h3.5L19 14v2h-6"/><circle cx="7" cy="17.5" r="1.4"/><circle cx="16.5" cy="17.5" r="1.4"/><path stroke-linecap="round" d="M19.5 6.5a2.6 2.6 0 1 0-.2 1.5M19.3 5.2v1.6h1.6"/></svg>
            @break
        @case('tracking')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
            @break
        @case('warehouse')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20V9l9-5 9 5v11M7 20v-6h4v6M14 14h4v6"/></svg>
            @break
        @case('package')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M3 8.5 12 4l9 4.5v11L12 20 3 19.5z"/><path stroke-linecap="round" d="M12 20V9.5M3 8.5l9 4.5 9-4.5"/></svg>
            @break
        @case('inventory')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M4 10h16v8H4zM7 10V7h10v3M8 14h4"/></svg>
            @break
        @case('payment')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h4"/></svg>
            @break
        @case('payment_failed')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/><path stroke-linecap="round" d="M3 10h18M15 15l2 2m0-2-2 2"/></svg>
            @break
        @case('refund')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10V6h4M4 6l3.5 3.5A7 7 0 1 1 6 16"/><path stroke-linecap="round" d="M12 8v8M10 10.5c.5-.7 1.2-1 2-1s1.6.4 2 1-.2 1.4-2 1.8-2.4.8-2 1.7.9 1 2 1 1.6-.3 2-1"/></svg>
            @break
        @case('return')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 11 5 7l4-4M5 7h9a6 6 0 1 1 0 12H8"/></svg>
            @break
        @case('exchange')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h11l-3-3M17 17H6l3 3M18 7v4M6 17v-4"/></svg>
            @break
        @case('cancelled')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" d="m9 9 6 6M15 9l-6 6"/></svg>
            @break
        @case('note')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M5 5h14v11H8l-3 3z"/><path stroke-linecap="round" d="M8 9h8M8 12h5"/></svg>
            @break
        @case('completed')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12.2 2.3 2.3 4.7-5"/></svg>
            @break
        @default
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linejoin="round" d="M6 7h12l1 13H5L6 7z"/><path stroke-linecap="round" d="M9 7V6a3 3 0 0 1 6 0v1"/></svg>
    @endswitch
</span>
