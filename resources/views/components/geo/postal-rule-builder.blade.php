@props([
    'inputName' => 'postal_rules_json',
    'inputId' => 'postal-rules-json',
    'containerId' => 'postal-rule-builder',
    'rules' => [],
    'label' => 'ZIP / postal coverage rules (optional)',
    'help' => 'Add exact codes or prefixes. Merchants never type wildcard characters — the system stores prefix rules safely.',
])

@php
    $rules = collect(is_array($rules) ? $rules : [])
        ->map(fn ($rule): array => [
            'type' => in_array($rule['type'] ?? '', ['prefix', 'starts_with'], true) ? 'prefix' : 'exact',
            'value' => (string) ($rule['value'] ?? ''),
        ])
        ->filter(fn (array $rule): bool => $rule['value'] !== '')
        ->values()
        ->all();
@endphp

<div id="{{ $containerId }}" class="space-y-2" data-role="postal-rule-builder">
    <div>
        <span class="text-xs font-semibold text-[#64748B]">{{ $label }}</span>
        <p class="mt-1 text-[11px] text-[#94A3B8]">{{ $help }}</p>
    </div>

    <div class="space-y-2" data-postal-rules-list>
        @forelse ($rules as $index => $rule)
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2" data-postal-rule-row>
                <select class="h-9 rounded-lg border border-[#CBD5E1] bg-white px-2 text-xs font-semibold text-[#475569]" data-postal-rule-type>
                    <option value="exact" @selected(($rule['type'] ?? 'exact') === 'exact')>Exact postal code</option>
                    <option value="prefix" @selected(($rule['type'] ?? '') === 'prefix')>Starts with</option>
                </select>
                <input type="text" value="{{ $rule['value'] }}" placeholder="75002 or 606" class="h-9 min-w-[8rem] flex-1 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" data-postal-rule-value>
                <button type="button" class="rounded-lg border border-[#FECACA] bg-white px-2 py-1 text-xs font-semibold text-[#991B1B]" data-postal-rule-remove aria-label="Remove rule">Remove</button>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>
        @endforelse
    </div>

    <button type="button" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]" data-postal-rule-add>Add postal rule</button>
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ e(json_encode($rules)) }}">
</div>
