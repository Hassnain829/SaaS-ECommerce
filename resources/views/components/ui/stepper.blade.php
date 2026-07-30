@props([
    'steps' => [],
    'current' => null,
])

<ol {{ $attributes->class(['ui-stepper']) }} aria-label="Progress">
    @foreach ($steps as $index => $step)
        @php
            $key = is_array($step) ? ($step['key'] ?? $index) : $index;
            $label = is_array($step) ? ($step['label'] ?? (string) $step) : (string) $step;
            $done = is_array($step) ? (bool) ($step['done'] ?? false) : false;
            $active = $current !== null && (string) $current === (string) $key;
        @endphp
        <li @class([
            'ui-stepper-item',
            'ui-stepper-item-active' => $active,
            'ui-stepper-item-done' => $done && ! $active,
        ])>
            <span aria-hidden="true">{{ $done && ! $active ? '✓' : ($index + 1) }}</span>
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
