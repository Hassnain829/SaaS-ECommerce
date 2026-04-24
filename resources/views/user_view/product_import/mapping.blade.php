@extends('layouts.user.user-sidebar')

@section('title', 'Map columns - Import')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Adjust mapping (when needed)</p>
                    <h1 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Map columns</h1>
                    <p class="mt-2 text-sm text-[#64748B]">File: <span class="font-medium text-[#0F172A]">{{ $import->original_filename }}</span></p>
                </div>
                <a href="{{ route('products.import.create') }}" class="text-sm font-semibold text-[#0052CC]">Start over</a>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('import_notice'))
                <div class="mb-6 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E40AF]">
                    {{ session('import_notice') }}
                </div>
            @endif

            @php
                $existingCustom = old('custom_field_mappings', $existingCustomMappings ?? []);
                if (! is_array($existingCustom)) {
                    $existingCustom = [];
                }
                $headers = $headers ?? [];
                $headerHints = \App\Support\ImportExtraColumnHints::mappingHeaderSignals($headers);
            @endphp

            <form method="post" action="{{ route('products.import.mapping.save', ['productImportId' => $import->id]) }}" class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                @csrf
                <div class="mb-8 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                    <p class="font-semibold text-[#0F172A]">How this step works</p>
                    <p class="mt-1">We may have pre-selected matches from your header row—confirm them below. Map each spreadsheet column to one catalog target. <span class="font-medium text-[#334155]">Additional details</span> (optional) save extra columns you want to edit later on the product or variant. Any column you leave unmapped is still kept as read-only reference after import.</p>
                    <p class="mt-2 text-xs text-[#64748B]">Each spreadsheet column can only be chosen once across catalog targets and additional details.</p>
                </div>

                @if ($headerHints['variant'] || $headerHints['image'] || $headerHints['category_like'])
                    <div class="mb-6 space-y-2 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                        <p class="font-semibold text-[#78350F]">We noticed possible columns in your file</p>
                        <ul class="ml-5 list-disc space-y-1">
                            @if ($headerHints['variant'])
                                <li>Headers look like they may include <span class="font-medium">variants or options</span>—open <span class="font-medium">Variants</span> when you map those.</li>
                            @endif
                            @if ($headerHints['image'])
                                <li>Headers may include <span class="font-medium">image URLs or photos</span>—open <span class="font-medium">Images</span> when you are ready.</li>
                            @endif
                            @if ($headerHints['category_like'])
                                <li>Some columns look like <span class="font-medium">categories</span>. Map them to <span class="font-medium">Categories</span> under Product information, or leave them unmapped and promote them later from the product workspace.</li>
                            @endif
                        </ul>
                    </div>
                @endif

                @foreach (\App\Catalog\ProductImportField::mappingUiSections() as $section)
                    @php
                        $sid = $section['id'] ?? '';
                        $shouldOpen = (bool) ($section['default_open'] ?? false);
                        if ($sid === 'pricing_inventory') {
                            foreach ($headers as $hc) {
                                if (! is_string($hc) || $hc === '') {
                                    continue;
                                }
                                if (preg_match('/price|cost|compare|stock|inventory|weight|length|width|height|dimension/i', $hc) === 1) {
                                    $shouldOpen = true;
                                    break;
                                }
                            }
                        }
                        if ($sid === 'variants' && $headerHints['variant']) {
                            $shouldOpen = true;
                        }
                        if ($sid === 'images' && $headerHints['image']) {
                            $shouldOpen = true;
                        }
                    @endphp
                    <details id="import-mapping-section-{{ $section['id'] }}" class="group mb-4 rounded-2xl border border-[#E2E8F0] bg-[#FAFAFA]/80 open:bg-white" @if ($shouldOpen) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl px-4 py-3 text-left font-[Poppins] text-base font-semibold text-[#0F172A] hover:bg-white [&::-webkit-details-marker]:hidden">
                            <span>{{ $section['title'] }}</span>
                            <span class="text-xs font-normal text-[#94A3B8] group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
                        </summary>
                        <div class="border-t border-[#F1F5F9] px-4 pb-4 pt-3">
                            <p class="text-sm text-[#64748B]">{{ $section['intro'] }}</p>
                            <div class="mt-4 space-y-4">
                                @foreach ($section['fields'] as $field)
                                    @php
                                        $label = $fieldLabels[$field] ?? null;
                                    @endphp
                                    @if ($label === null)
                                        @continue
                                    @endif
                                    <div class="grid gap-2 sm:grid-cols-[minmax(0,14rem)_1fr] sm:items-center">
                                        <label for="map_{{ $field }}" class="text-sm font-medium text-[#334155]">
                                            {{ $label }}
                                            @if (in_array($field, \App\Catalog\ProductImportField::requiredForImport(), true))
                                                <span class="text-[#B42318]">*</span>
                                            @endif
                                        </label>
                                        <select id="map_{{ $field }}" name="column_mapping[{{ $field }}]" class="w-full rounded-xl border border-[#CBD5E1] bg-white px-4 py-2.5 text-sm text-[#0F172A]">
                                            <option value="">— Ignore —</option>
                                            @foreach ($headers as $h)
                                                @if ($h === '')
                                                    @continue
                                                @endif
                                                <option value="{{ $h }}" @selected(old('column_mapping.'.$field, $existingMapping[$field] ?? '') === $h)>{{ $h }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endforeach

                <details id="import-mapping-section-additional_details" class="group mt-8 border-t border-[#E2E8F0] pt-8">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl border border-[#E2E8F0] bg-[#FAFAFA]/80 px-4 py-3 text-left font-[Poppins] text-base font-semibold text-[#0F172A] hover:bg-white [&::-webkit-details-marker]:hidden">
                        <span>Additional details</span>
                        <span class="text-xs font-normal text-[#94A3B8] group-open:rotate-180 transition-transform" aria-hidden="true">▼</span>
                    </summary>
                    <div class="mt-4 px-1 pb-2">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-[#0F172A] font-[Poppins]">Map optional editable fields</p>
                            <p class="mt-2 text-sm text-[#64748B]">Optional: map extra columns into <span class="font-medium text-[#334155]">editable additional details</span> on the product or variant (for example supplier code or material). Use short internal names: letters, numbers, underscores, dots, or dashes (1–128 characters). Names cannot match a built-in catalog field such as <span class="font-mono text-xs">sku</span>.</p>
                            <p class="mt-2 text-xs text-[#64748B]">Columns you do not map here still appear later under <span class="font-medium text-[#334155]">Advanced imported data</span> as read-only reference.</p>
                        </div>
                        <button type="button" id="add-custom-field" class="inline-flex items-center justify-center rounded-xl border border-[#CBD5E1] bg-white px-4 py-2.5 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">
                            Add custom field
                        </button>
                    </div>

                    <div id="custom-field-rows" class="mt-4 space-y-3">
                        @foreach ($existingCustom as $i => $cm)
                            @if (! is_array($cm))
                                @continue
                            @endif
                            <div class="custom-field-row grid gap-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 sm:grid-cols-[1fr_1fr_140px_auto] sm:items-end">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Source column</label>
                                    <select name="custom_field_mappings[{{ $i }}][source]" class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                        <option value="">— Select —</option>
                                        @foreach ($headers as $h)
                                            @if ($h === '')
                                                @continue
                                            @endif
                                            <option value="{{ $h }}" @selected(old('custom_field_mappings.'.$i.'.source', $cm['source'] ?? '') === $h)>{{ $h }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Destination key</label>
                                    <input type="text" name="custom_field_mappings[{{ $i }}][key]" value="{{ old('custom_field_mappings.'.$i.'.key', $cm['key'] ?? '') }}" class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]" placeholder="e.g. supplier_code" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Scope</label>
                                    @php $scopeVal = old('custom_field_mappings.'.$i.'.scope', $cm['scope'] ?? 'product'); @endphp
                                    <select name="custom_field_mappings[{{ $i }}][scope]" class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                        <option value="product" @selected($scopeVal === 'product')>Product</option>
                                        <option value="variant" @selected($scopeVal === 'variant')>Variant</option>
                                    </select>
                                </div>
                                <div class="flex sm:justify-end">
                                    <button type="button" class="remove-custom-field text-sm font-semibold text-[#B42318] hover:underline">Remove</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    </div>
                </details>

                <template id="custom-field-row-template">
                    <div class="custom-field-row grid gap-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 sm:grid-cols-[1fr_1fr_140px_auto] sm:items-end">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Source column</label>
                            <select data-name-source class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                <option value="">— Select —</option>
                                @foreach ($headers as $h)
                                    @if ($h === '')
                                        @continue
                                    @endif
                                    <option value="{{ $h }}">{{ $h }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Destination key</label>
                            <input type="text" data-name-key class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]" placeholder="e.g. supplier_code" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Scope</label>
                            <select data-name-scope class="mt-1 w-full rounded-xl border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                <option value="product">Product</option>
                                <option value="variant">Variant</option>
                            </select>
                        </div>
                        <div class="flex sm:justify-end">
                            <button type="button" class="remove-custom-field text-sm font-semibold text-[#B42318] hover:underline">Remove</button>
                        </div>
                    </div>
                </template>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-[#0047B3]">
                        Build preview
                    </button>
                    <a href="{{ route('products') }}" class="inline-flex items-center justify-center rounded-xl border border-[#E2E8F0] px-6 py-3 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const container = document.getElementById('custom-field-rows');
            const tpl = document.getElementById('custom-field-row-template');
            const addBtn = document.getElementById('add-custom-field');
            if (!container || !tpl || !addBtn) {
                return;
            }
            let idx = {{ count($existingCustom) }};

            function wireRow(row) {
                row.querySelector('.remove-custom-field')?.addEventListener('click', function () {
                    row.remove();
                });
            }

            container.querySelectorAll('.custom-field-row').forEach(wireRow);

            addBtn.addEventListener('click', function () {
                const frag = document.importNode(tpl.content, true);
                const node = frag.querySelector('.custom-field-row');
                if (!node) {
                    return;
                }
                const i = idx++;
                node.querySelector('[data-name-source]')?.setAttribute('name', 'custom_field_mappings[' + i + '][source]');
                node.querySelector('[data-name-key]')?.setAttribute('name', 'custom_field_mappings[' + i + '][key]');
                node.querySelector('[data-name-scope]')?.setAttribute('name', 'custom_field_mappings[' + i + '][scope]');
                container.appendChild(node);
                wireRow(node);
            });
        })();
    </script>
@endsection
