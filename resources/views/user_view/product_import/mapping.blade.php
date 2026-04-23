@extends('layouts.user.user-sidebar')

@section('title', 'Map columns - Import')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('content')
    <div class="flex-1 overflow-y-auto p-4 lg:p-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Step 2 of 4</p>
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

            @php
                $existingCustom = old('custom_field_mappings', $existingCustomMappings ?? []);
                if (! is_array($existingCustom)) {
                    $existingCustom = [];
                }
            @endphp

            <form method="post" action="{{ route('products.import.mapping.save', ['productImportId' => $import->id]) }}" class="rounded-3xl border border-[#E2E8F0] bg-white p-6 shadow-sm sm:p-8">
                @csrf
                <div class="mb-8 space-y-3 text-sm text-[#64748B]">
                    <p><span class="font-semibold text-[#0F172A]">1. Catalog fields</span> map spreadsheet columns into real product columns (name, SKU, price, stock, taxonomy, and so on).</p>
                    <p><span class="font-semibold text-[#0F172A]">2. Custom fields</span> (optional) attach extra columns to the product or variant record in a structured way you can use later in your theme or integrations.</p>
                    <p><span class="font-semibold text-[#0F172A]">3. Leftover columns</span> are still stored with the product so nothing is dropped, unless you map them above. Each spreadsheet column can only be selected once across sections 1 and 2.</p>
                </div>

                <h2 class="text-sm font-semibold uppercase tracking-wide text-[#64748B]">Catalog fields</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($fieldLabels as $field => $label)
                        <div class="grid gap-2 sm:grid-cols-[220px_1fr] sm:items-center">
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

                <div class="mt-10 border-t border-[#E2E8F0] pt-8">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-[#64748B]">Custom meta fields</h2>
                            <p class="mt-2 text-sm text-[#64748B]">Destination keys: letters, numbers, <span class="font-mono">_</span>, <span class="font-mono">.</span>, <span class="font-mono">-</span> (1–128 chars). Cannot match a built-in import field name such as <span class="font-mono">sku</span>.</p>
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
