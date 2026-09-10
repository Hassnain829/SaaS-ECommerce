@php
    $size = (int) ($size ?? 17);
    $name = (string) ($name ?? 'check');
@endphp
@switch($name)
    @case('shield-check')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 5 6v6c0 5 3.4 8.4 7 9 3.6-.6 7-4 7-9V6l-7-3Z" stroke="currentColor" stroke-width="1.8"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('circle')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.8"/></svg>
        @break
    @case('circle-dot')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
        @break
    @case('lock')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('credit-card')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('chevron-right')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('more-vertical')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="6" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="18" r="1.4" fill="currentColor"/></svg>
        @break
    @case('check')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5.5 12.5 4.2 4.2 8.8-9.2" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('banner-check')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 12.2 4 4.1 8.2-8.6" stroke="#fff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('banner-alert')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 6.5v7.2" stroke="#fff" stroke-width="2.8" stroke-linecap="round"/><circle cx="12" cy="17.4" r="1.45" fill="#fff"/></svg>
        @break
    @case('circle-check')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><path d="m8.5 12.2 2.3 2.3 4.7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('copy-check')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.8"/><path d="m11 14 1.6 1.6L16.5 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('list-checks')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M11 6h10M11 12h10M11 18h10M4.5 6l1.2 1.2L8 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="m4.5 12 1.2 1.2L8 11M4.5 18l1.2 1.2 2.3-2.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('arrow-up-down')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 4v16M5 7l3-3 3 3M16 20V4M13 17l3 3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('info')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('triangle-alert')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4 3 19h18L12 4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('link')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5.93" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 18.07" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('flask')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 3v7L4.4 20.2A2 2 0 0 0 6.1 23h11.8a2 2 0 0 0 1.7-2.8L12 10V3" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8.5 3h7M7.5 16h9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('rocket')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3c4 2 7 6.2 7 11.2 0 1.4-.2 2.6-.6 3.8L12 21l-6.4-3c-.4-1.2-.6-2.4-.6-3.8C5 9.2 8 5 12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="11" r="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 20s.6-2.2 2-3M16 20s-.6-2.2-2-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('refresh')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.2-5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M20 5v5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('unlink')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 13a5 5 0 0 0 7.07 0l1.2-1.2M15 11a5 5 0 0 0-7.07 0L6.7 12.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 7 7 4M17 20l3-3M8 4h.01M16 20h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('x')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('arrow-right')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @default
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/></svg>
@endswitch
