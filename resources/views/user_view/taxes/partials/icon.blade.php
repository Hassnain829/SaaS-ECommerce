@php
    $size = (int) ($size ?? 18);
    $name = (string) ($name ?? 'check');
@endphp
@switch($name)
    @case('plus')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('more')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="5" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="19" cy="12" r="1.4" fill="currentColor"/></svg>
        @break
    @case('search')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('close')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('chevron')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('check')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('minus')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('pin')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.4" stroke="currentColor" stroke-width="1.8"/></svg>
        @break
    @case('info')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11.2V17M12 7v.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('edit')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m15 4 5 5M4 15 16 3a2 2 0 0 1 3 0l2 2a2 2 0 0 1 0 3L9 20l-6 1 1-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('settings')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M12 3.5v2.2M12 18.3V21M4.9 6.5l1.6 1.6M17.5 16.9l1.6 1.6M3.5 12h2.2M18.3 12H21M4.9 17.5l1.6-1.6M17.5 7.1l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('shield')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 3 8 3v5c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-3Z" stroke="currentColor" stroke-width="1.8"/><path d="m8.5 12 2.5 2.5 4.5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('trash')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('globe')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><ellipse cx="12" cy="12" rx="4" ry="9" stroke="currentColor" stroke-width="1.8"/><path d="M3 12h18" stroke="currentColor" stroke-width="1.8"/></svg>
        @break
    @case('file')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 3h9l5 5v13H5V3Z" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v5h5M8 13h8M8 17h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('back')
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m11 5-7 7 7 7M4 12h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @default
        <svg class="trb-icon" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/></svg>
@endswitch
