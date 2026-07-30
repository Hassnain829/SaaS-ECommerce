@php
    $steps = collect($progress ?? [])->map(function (array $item, int $index) {
        return [
            'key' => $item['key'] ?? (string) $index,
            'label' => $item['label'] ?? 'Step',
            'done' => ($item['status'] ?? '') === 'complete',
        ];
    })->values()->all();
    $current = collect($progress ?? [])->firstWhere('status', 'current')['key']
        ?? collect($progress ?? [])->firstWhere('status', 'current')['label']
        ?? null;
@endphp
<nav aria-label="USPS setup progress" class="ui-panel !py-3">
    <x-ui.stepper :steps="$steps" :current="$current" />
</nav>
