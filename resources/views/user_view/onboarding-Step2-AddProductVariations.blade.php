<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding - Add Product | BaaS Platform</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[#F5F7F8] antialiased text-[#0F172A] min-h-screen flex flex-col overflow-x-hidden font-[Inter]">
    <div class="w-full bg-[#F5F7F8] flex flex-col">
        <header
            class="flex justify-between items-center px-4 sm:px-6 lg:px-16 py-3 bg-white border-b border-[#E2E8F0] w-full">
            <div class="flex items-center gap-4">
                <div class="w-6 h-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2H15.3333V8.6667H8.6667V15.3333H2V22H22V2Z" fill="#0052CC" />
                    </svg>
                </div>
                <span class="text-lg font-bold text-[#0F172A]">BaaS Platform</span>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden md:flex items-center gap-4 lg:gap-8 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-[#475569] font-inter font-medium">Dashboard</a>
                    <a href="{{ route('products') }}" class="text-[#0052CC] font-semibold">Products</a>
                    <a href="{{ route('orders') }}" class="text-[#475569] font-inter font-medium">Orders</a>
                    <a href="{{ route('generalSettings') }}" class="text-[#475569] font-inter font-medium">Settings</a>
                </nav>
                <div class="flex items-center gap-3 sm:gap-4">
                    <button form="product-onboarding-form" type="submit"
                        class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Save
                        & Continue</button>
                    <div class="w-10 h-10 rounded-full border border-[#E2E8F0] overflow-hidden bg-[#E2E8F0]"></div>
                </div>
            </div>
        </header>

        <main class="px-6 md:px-10 py-8 max-w-[1024px] w-full mx-auto">
            <nav class="flex items-center gap-2 text-sm font-inter font-medium mb-6">
                <a href="{{ route('onboarding-StoreDetails-1') }}"
                    class="text-[#0052CC] opacity-70 hover:opacity-100">Onboarding</a>
                <svg width="5" height="7" viewBox="0 0 5 7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z"
                        fill="#94A3B8" />
                </svg>
                <span class="text-[#0F172A]">Add Product</span>
            </nav>

            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-inter font-medium text-[#64748B] uppercase tracking-wider">Step 2 of 3</span>
                    <span class="text-xs text-[#64748B]">Setup Progress: 55% Complete</span>
                </div>
                <div class="w-full h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                    <div class="h-2 w-[55%] bg-[#0052CC] rounded-full"></div>
                </div>
                <div class="flex justify-end mt-1">
                    <span class="text-xs text-[#0052CC] font-inter font-medium">Next: Launch</span>
                </div>
            </div>

            <div class="flex justify-between items-start mb-8">
                <div>
                    @if ($store->products->count() > 0 || request()->filled('store_id'))
                        <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Add Product to {{ $store->name }}</h1>
                        <p class="text-base text-[#64748B] mt-1">Expand your store catalog. Define product basics, add variation types, then add variant rows by selecting options.</p>
                    @else
                        <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Add Product</h1>
                        <p class="text-base text-[#64748B] mt-1">Define product basics, add variation types, then add
                            variant rows by selecting options.</p>
                    @endif
                </div>
                <button
                    class="bg-[#E2E8F0] text-[#64748B] text-sm font-inter font-medium px-4 py-2 rounded border border-[#E2E8F0]"
                    type="button">Upload CSV</button>
            </div>

            @if (session('success'))
                <div class="mb-6 rounded-lg border border-[#CBE8D1] bg-[#ECFDF3] px-4 py-3 text-sm text-[#05603A]">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $step2Data = old();
                if (empty($step2Data)) {
                    $step2Data = $draft ?? [];
                }

                $variationTypes = $step2Data['variation_types'] ?? ($draft['variation_types'] ?? []);
                $customVariants = $step2Data['variants'] ?? ($draft['variants'] ?? []);

                $previewRows = [];

                if (!empty($customVariants)) {
                    foreach ($customVariants as $variantRow) {
                        if (!is_array($variantRow)) {
                            continue;
                        }

                        $labelParts = [];
                        foreach ($variationTypes as $variationIndex => $variationType) {
                            $selectedIndex = $variantRow['option_map'][$variationIndex] ?? null;
                            if ($selectedIndex !== null && $selectedIndex !== '' && isset($variationType['options'][$selectedIndex])) {
                                $labelParts[] = $variationType['options'][$selectedIndex];
                            }
                        }

                        $previewRows[] = [
                            'label' => !empty($labelParts) ? implode(' / ', $labelParts) : 'Default Variant',
                            'sku' => $variantRow['sku'] ?? '',
                            'price' => $variantRow['price'] ?? ($step2Data['base_price'] ?? '0'),
                            'stock' => $variantRow['stock'] ?? ($step2Data['default_stock'] ?? 50),
                        ];
                    }
                }

                if (empty($previewRows)) {
                    $previewRows[] = [
                        'label' => 'No variants added yet',
                        'sku' => '-',
                        'price' => '-',
                        'stock' => '-',
                    ];
                }
            @endphp

            <form id="product-onboarding-form" action="{{ route('onboarding-Step2-AddProductVariations.store') }}"
                method="POST">
                @csrf
                {{-- Determine if this is a new product creation or editing --}}
                <input type="hidden" name="mode" value="{{ !empty($draft) && isset($draft['name']) ? 'edit' : 'create' }}">
                <input id="base-price-hidden" type="hidden" name="base_price"
                    value="{{ $step2Data['base_price'] ?? '' }}">
                <input id="default-stock-hidden" type="hidden" name="default_stock"
                    value="{{ $step2Data['default_stock'] ?? '' }}">
                <input type="hidden" name="stock_alert" value="{{ $step2Data['stock_alert'] ?? 5 }}">
                <input type="hidden" name="product_type" value="{{ $step2Data['product_type'] ?? 'physical' }}">

                <div id="variation-hidden-inputs">
                    @foreach ($variationTypes as $variationIndex => $variationType)
                        <input type="hidden" name="variation_types[{{ $variationIndex }}][name]"
                            value="{{ $variationType['name'] ?? '' }}">
                        <input type="hidden" name="variation_types[{{ $variationIndex }}][type]"
                            value="{{ $variationType['type'] ?? 'select' }}">
                        @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                            <input type="hidden" name="variation_types[{{ $variationIndex }}][options][{{ $optionIndex }}]"
                                value="{{ $option }}">
                        @endforeach
                    @endforeach
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex items-center gap-2 mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Basic Information</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-6 mb-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product
                                Name</label>
                            <input id="name" name="name" type="text" placeholder="e.g. Premium Cotton T-Shirt"
                                value="{{ $step2Data['name'] ?? '' }}"
                                class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                    </div>

                    <div>
                        <label for="description"
                            class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Description</label>
                        <textarea id="description" name="description" rows="3"
                            placeholder="Describe your product's key features and benefits..."
                            class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ $step2Data['description'] ?? '' }}</textarea>
                    </div>

                    <div class="mt-4">
                        <label for="sku" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Base SKU
                            (optional)</label>
                        <input id="sku" name="sku" type="text" value="{{ $step2Data['sku'] ?? '' }}"
                            class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h2>
                        <button id="openVariationModal" type="button"
                            class="flex items-center gap-2 text-[#0052CC] text-sm font-medium">Add Variation
                            Type</button>
                    </div>

                    <div id="no-variation-state"
                        class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B] {{ empty($variationTypes) ? '' : 'hidden' }}">
                        No variation type added yet. Click <span class="font-semibold text-[#0052CC]">Add Variation
                            Type</span> to start.
                    </div>
                    <div id="variation-types-list" class="space-y-4 {{ empty($variationTypes) ? 'hidden' : '' }}">
                        @foreach ($variationTypes as $index => $variationType)
                            <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-base text-[#0F172A] font-poppins">Variation {{ $index + 1 }}:
                                        {{ $variationType['name'] ?? 'Variation' }}</span>
                                    <span
                                        class="text-xs text-[#94A3B8] uppercase">{{ $variationType['type'] ?? 'select' }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                                        <span
                                            class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-medium inline-flex items-center gap-2">
                                            {{ $option }}
                                            <button type="button"
                                                class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none"
                                                data-variation-index="{{ $index }}" data-option-index="{{ $optionIndex }}"
                                                aria-label="Remove option {{ $option }}">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants</h2>
                        <button id="add-variant-row" type="button"
                            class="px-3 py-2 rounded-lg border border-[#0052CC] text-[#0052CC] text-sm font-semibold {{ empty($variationTypes) ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ empty($variationTypes) ? 'disabled' : '' }}>Add Variant</button>
                    </div>

                    <div
                        class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4 p-4 border border-[#E2E8F0] rounded-xl bg-[#F8FAFC]">
                        <div class="md:col-span-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Bulk Set Price & Stock</p>
                            <p class="text-xs text-[#64748B]">Apply one value to all variant rows.</p>
                        </div>
                        <div>
                            <label for="bulk-price"
                                class="block text-xs font-semibold text-[#64748B] mb-1">Price</label>
                            <input id="bulk-price" type="number" min="0" step="0.01"
                                value="{{ $step2Data['base_price'] ?? '' }}"
                                class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                        <div class="flex gap-2 items-end">
                            <div class="flex-1">
                                <label for="bulk-stock"
                                    class="block text-xs font-semibold text-[#64748B] mb-1">Stock</label>
                                <input id="bulk-stock" type="number" min="0" step="1"
                                    value="{{ $step2Data['default_stock'] ?? '' }}"
                                    class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <button id="apply-bulk-values" type="button"
                                class="px-3 py-2 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Apply</button>
                        </div>
                    </div>

                    <div id="variant-rows" class="space-y-4"></div>

                    <p class="mt-3 text-xs text-[#64748B]">Each row is one variant. Select one option from each
                        variation type.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h2>
                        <span id="preview-count" class="text-sm text-[#94A3B8]">{{ count($previewRows) }} variant
                            row(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-[#F1F5F9]">
                                <tr>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Variant
                                    </th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">SKU</th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Price ($)
                                    </th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Stock
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="preview-table-body" class="divide-y divide-[#F1F5F9]">
                                @foreach ($previewRows as $row)
                                    <tr>
                                        <td class="py-4 px-2 font-medium text-[#0F172A]">{{ $row['label'] }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['sku'] ?: 'Auto-generated' }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['price'] }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['stock'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-6 border-t border-[#E2E8F0]">
                    <a href="{{ route('onboarding-StoreDetails-1') }}" class="text-[#475569] font-bold">Back to Basic
                        Setup</a>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('onboarding_StoreReady') }}" class="text-[#475569] font-bold px-6 py-2">Skip
                            for Now</a>
                        <button type="submit"
                            class="bg-[#0052CC] text-white font-bold px-8 py-3 rounded-lg shadow-lg shadow-[#0052CC]/20">Save
                            & Continue</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <div id="variationModal" class="fixed inset-0 z-50 hidden">
        <iframe id="variationModalFrame" src="{{ route('onboarding_AddProduct_VariationsPopup') }}"
            class="h-full w-full border-0 bg-transparent" title="Add Variation Type"></iframe>
    </div>

    <script>
        (() => {
            let variationTypes = @json(array_values($variationTypes));
            const existingVariants = @json(array_values($customVariants));
            let defaultPrice = @json((string) ($step2Data['base_price'] ?? ''));
            let defaultStock = @json((string) ($step2Data['default_stock'] ?? ''));
            const defaultStockAlert = @json((int) ($step2Data['stock_alert'] ?? 5));
            // sessionStorage form element references added
            const step2StorageKey = 'onboarding_step2_unsaved_state';
            const productForm = document.getElementById('product-onboarding-form');
            const nameInput = document.getElementById('name');
            const descriptionInput = document.getElementById('description');
            const skuInput = document.getElementById('sku');

            const rowsContainer = document.getElementById('variant-rows');
            const addRowButton = document.getElementById('add-variant-row');
            const bulkPriceInput = document.getElementById('bulk-price');
            const bulkStockInput = document.getElementById('bulk-stock');
            const applyBulkButton = document.getElementById('apply-bulk-values');
            const previewCount = document.getElementById('preview-count');
            const previewTableBody = document.getElementById('preview-table-body');
            const variationTypesList = document.getElementById('variation-types-list');
            const noVariationState = document.getElementById('no-variation-state');
            const variationHiddenInputs = document.getElementById('variation-hidden-inputs');
            const variationModal = document.getElementById('variationModal');
            const openVariationModal = document.getElementById('openVariationModal');
            const basePriceHiddenInput = document.getElementById('base-price-hidden');
            const defaultStockHiddenInput = document.getElementById('default-stock-hidden');

            if (!rowsContainer) {
                return;
            }

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const renderVariationHiddenInputs = () => {
                if (!variationHiddenInputs) {
                    return;
                }

                variationHiddenInputs.innerHTML = variationTypes.map((variationType, variationIndex) => {
                    const optionInputs = (variationType.options || []).map((option, optionIndex) => (
                        `<input type="hidden" name="variation_types[${variationIndex}][options][${optionIndex}]" value="${escapeHtml(option)}">`
                    )).join('');

                    return `
                        <input type="hidden" name="variation_types[${variationIndex}][name]" value="${escapeHtml(variationType.name || '')}">
                        <input type="hidden" name="variation_types[${variationIndex}][type]" value="${escapeHtml(variationType.type || 'select')}">
                        ${optionInputs}
                    `;
                }).join('');
            };

            const saveStep2StateToSession = () => {
                const payload = {
                    name: nameInput ? nameInput.value : '',
                    description: descriptionInput ? descriptionInput.value : '',
                    sku: skuInput ? skuInput.value : '',
                    base_price: bulkPriceInput ? bulkPriceInput.value : '',
                    default_stock: bulkStockInput ? bulkStockInput.value : '',
                    rows: rows.map((row) => ({
                        option_map: { ...(row.option_map || {}) },
                        sku: row.sku ?? '',
                        price: row.price ?? '',
                        stock: row.stock ?? '',
                        stock_alert: row.stock_alert ?? defaultStockAlert,
                    })),
                };

                sessionStorage.setItem(step2StorageKey, JSON.stringify(payload));
            };

            const getSavedStep2State = () => {
                try {
                    const raw = sessionStorage.getItem(step2StorageKey);
                    return raw ? JSON.parse(raw) : null;
                } catch (error) {
                    return null;
                }
            };

            const restoreStep2StateFromSession = () => {
                const savedState = getSavedStep2State();
                if (!savedState) {
                    return;
                }

                if (nameInput && typeof savedState.name === 'string') {
                    nameInput.value = savedState.name;
                }

                if (descriptionInput && typeof savedState.description === 'string') {
                    descriptionInput.value = savedState.description;
                }

                if (skuInput && typeof savedState.sku === 'string') {
                    skuInput.value = savedState.sku;
                }

                if (bulkPriceInput && typeof savedState.base_price === 'string') {
                    bulkPriceInput.value = savedState.base_price;
                    defaultPrice = savedState.base_price;
                }

                if (bulkStockInput && typeof savedState.default_stock === 'string') {
                    bulkStockInput.value = savedState.default_stock;
                    defaultStock = savedState.default_stock;
                }

                if (basePriceHiddenInput) {
                    basePriceHiddenInput.value = bulkPriceInput ? bulkPriceInput.value : defaultPrice;
                }

                if (defaultStockHiddenInput) {
                    defaultStockHiddenInput.value = bulkStockInput ? bulkStockInput.value : defaultStock;
                }

                if (Array.isArray(savedState.rows)) {
                    rows = savedState.rows.map((row) => ({
                        option_map: { ...(row.option_map || {}) },
                        sku: row.sku ?? '',
                        price: row.price ?? '',
                        stock: row.stock ?? '',
                        stock_alert: row.stock_alert ?? defaultStockAlert,
                    }));
                }
            };

            const clearSavedStep2State = () => {
                sessionStorage.removeItem(step2StorageKey);
            };

            const buildOptionLabel = (rowData) => {
                if (!variationTypes.length) {
                    return 'Default Variant';
                }

                const parts = variationTypes.map((variation, variationIndex) => {
                    const selectedIndex = rowData.option_map?.[variationIndex];
                    if (selectedIndex === undefined || selectedIndex === null || selectedIndex === '') {
                        return variation.name;
                    }
                    return variation.options?.[selectedIndex] ?? variation.name;
                });

                return parts.join(' / ');
            };

            const renderVariationTypeCards = () => {
                if (!variationTypesList || !noVariationState) {
                    return;
                }

                if (!variationTypes.length) {
                    variationTypesList.classList.add('hidden');
                    noVariationState.classList.remove('hidden');
                    return;
                }

                noVariationState.classList.add('hidden');
                variationTypesList.classList.remove('hidden');
                variationTypesList.innerHTML = variationTypes.map((variationType, variationIndex) => {
                    const chips = (variationType.options || []).map((option, optionIndex) => (
                        `<span class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-medium inline-flex items-center gap-2">
                            ${escapeHtml(option)}
                            <button
                                type="button"
                                class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none"
                                data-variation-index="${variationIndex}"
                                data-option-index="${optionIndex}"
                                aria-label="Remove option ${escapeHtml(option)}"
                            >×</button>
                        </span>`
                    )).join('');

                    return `
                        <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-base text-[#0F172A] font-poppins">Variation ${variationIndex + 1}: ${escapeHtml(variationType.name || 'Variation')}</span>
                                <span class="text-xs text-[#94A3B8] uppercase">${escapeHtml(variationType.type || 'select')}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">${chips}</div>
                        </div>
                    `;
                }).join('');
            };

            const syncAddVariantButtonState = () => {
                if (!addRowButton) {
                    return;
                }

                if (!variationTypes.length) {
                    addRowButton.disabled = true;
                    addRowButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    addRowButton.disabled = false;
                    addRowButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            };

            const createSelect = (rowIndex, variationIndex, variation, selectedValue) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'flex-1 min-w-[160px]';

                const label = document.createElement('label');
                label.className = 'block text-xs font-semibold text-[#64748B] mb-1';
                label.textContent = variation.name;

                const select = document.createElement('select');
                select.name = `variants[${rowIndex}][option_map][${variationIndex}]`;
                select.className = 'w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20';

                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Select option';
                select.appendChild(emptyOption);

                (variation.options || []).forEach((optionValue, optionIndex) => {
                    const option = document.createElement('option');
                    option.value = String(optionIndex);
                    option.textContent = optionValue;
                    if (String(selectedValue ?? '') === String(optionIndex)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                wrapper.appendChild(label);
                wrapper.appendChild(select);

                return wrapper;
            };

            const renderRows = (rows) => {
                rowsContainer.innerHTML = '';

                rows.forEach((rowData, rowIndex) => {
                    const card = document.createElement('div');
                    card.className = 'border border-[#E2E8F0] rounded-xl p-4 bg-[#F8FAFC]';

                    const top = document.createElement('div');
                    top.className = 'flex items-center justify-between mb-3';

                    const title = document.createElement('p');
                    title.className = 'text-sm font-semibold text-[#0F172A]';
                    title.textContent = `Variant ${rowIndex + 1}: ${buildOptionLabel(rowData)}`;

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'text-xs font-semibold text-[#B42318]';
                    removeBtn.textContent = 'Remove';
                    removeBtn.addEventListener('click', () => {
                        rows.splice(rowIndex, 1);
                        renderRows(rows);
                    });

                    top.appendChild(title);
                    top.appendChild(removeBtn);
                    card.appendChild(top);

                    if (variationTypes.length) {
                        const selectsWrap = document.createElement('div');
                        selectsWrap.className = 'flex flex-wrap gap-3 mb-3';
                        variationTypes.forEach((variation, variationIndex) => {
                            selectsWrap.appendChild(createSelect(rowIndex, variationIndex, variation, rowData.option_map?.[variationIndex]));
                        });
                        card.appendChild(selectsWrap);
                    }

                    const inputsWrap = document.createElement('div');
                    inputsWrap.className = 'grid grid-cols-1 md:grid-cols-4 gap-3';

                    const fields = [
                        { key: 'sku', label: 'SKU (optional)', type: 'text', value: rowData.sku ?? '' },
                        { key: 'price', label: 'Price', type: 'number', step: '0.01', min: '0', value: rowData.price ?? defaultPrice },
                        { key: 'stock', label: 'Stock', type: 'number', step: '1', min: '0', value: rowData.stock ?? defaultStock },
                        { key: 'stock_alert', label: 'Stock Alert', type: 'number', step: '1', min: '0', value: rowData.stock_alert ?? defaultStockAlert },
                    ];

                    fields.forEach((field) => {
                        const block = document.createElement('div');

                        const label = document.createElement('label');
                        label.className = 'block text-xs font-semibold text-[#64748B] mb-1';
                        label.textContent = field.label;

                        const input = document.createElement('input');
                        input.type = field.type;
                        input.name = `variants[${rowIndex}][${field.key}]`;
                        input.value = field.value;
                        if (field.step) input.step = field.step;
                        if (field.min) input.min = field.min;
                        input.className = 'w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20';

                        block.appendChild(label);
                        block.appendChild(input);
                        inputsWrap.appendChild(block);
                    });

                    card.appendChild(inputsWrap);
                    rowsContainer.appendChild(card);
                });

                renderPreview(rows);
            };

            const renderPreview = (rows) => {
                if (!previewTableBody || !previewCount) {
                    return;
                }

                let previewRows = rows.map((rowData) => ({
                    label: buildOptionLabel(rowData),
                    sku: rowData.sku || '',
                    price: rowData.price ?? defaultPrice,
                    stock: rowData.stock ?? defaultStock,
                }));

                if (!previewRows.length) {
                    previewRows = [{
                        label: 'No variants added yet',
                        sku: '-',
                        price: '-',
                        stock: '-',
                    }];
                }

                previewCount.textContent = `${previewRows.length} variant row(s)`;
                previewTableBody.innerHTML = previewRows.map((row) => `
                    <tr>
                        <td class="py-4 px-2 font-medium text-[#0F172A]">${row.label}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.sku || '-'}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.price}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.stock}</td>
                    </tr>
                `).join('');
            };
            // rows restore on page reload with sessionStorage
            let rows = existingVariants.length ? existingVariants : [];

            restoreStep2StateFromSession();

            renderRows(rows);
            renderVariationHiddenInputs();
            renderVariationTypeCards();
            syncAddVariantButtonState();

            if (addRowButton) {
                addRowButton.addEventListener('click', () => {
                    if (!variationTypes.length) {
                        return;
                    }

                    rows.push({ option_map: {}, sku: '', price: '', stock: '', stock_alert: defaultStockAlert });
                    renderRows(rows);
                    saveStep2StateToSession();
                });
            }

            if (applyBulkButton) {
                applyBulkButton.addEventListener('click', () => {
                    const bulkPrice = bulkPriceInput ? bulkPriceInput.value : '';
                    const bulkStock = bulkStockInput ? bulkStockInput.value : '';

                    rows.forEach((row) => {
                        if (bulkPrice !== '') {
                            row.price = bulkPrice;
                        }
                        if (bulkStock !== '') {
                            row.stock = bulkStock;
                        }
                    });

                    if (bulkPrice !== '') {
                        defaultPrice = bulkPrice;
                        if (basePriceHiddenInput) {
                            basePriceHiddenInput.value = bulkPrice;
                        }
                    }
                    if (bulkStock !== '') {
                        defaultStock = bulkStock;
                        if (defaultStockHiddenInput) {
                            defaultStockHiddenInput.value = bulkStock;
                        }
                    }

                    renderRows(rows);
                    saveStep2StateToSession();
                });
            }

            if (bulkPriceInput && basePriceHiddenInput) {
                bulkPriceInput.addEventListener('input', () => {
                    basePriceHiddenInput.value = bulkPriceInput.value !== '' ? bulkPriceInput.value : defaultPrice;
                });
            }

            if (bulkStockInput && defaultStockHiddenInput) {
                bulkStockInput.addEventListener('input', () => {
                    defaultStockHiddenInput.value = bulkStockInput.value !== '' ? bulkStockInput.value : defaultStock;
                });
            }

            rowsContainer.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                const match = target.name.match(/^variants\[(\d+)]\[(sku|price|stock|stock_alert)]$/);
                if (!match) {
                    return;
                }

                const rowIndex = Number(match[1]);
                const key = match[2];

                if (!rows[rowIndex]) {
                    return;
                }

                rows[rowIndex][key] = target.value;
                renderPreview(rows);
                saveStep2StateToSession();
            });

            rowsContainer.addEventListener('change', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLSelectElement)) {
                    return;
                }
                const match = target.name.match(/^variants\[(\d+)]\[option_map]\[(\d+)]$/);
                if (!match) {
                    return;
                }

                const rowIndex = Number(match[1]);
                const variationIndex = Number(match[2]);
                if (!rows[rowIndex]) {
                    return;
                }

                rows[rowIndex].option_map = rows[rowIndex].option_map || {};
                rows[rowIndex].option_map[variationIndex] = target.value;

                renderRows(rows);
                saveStep2StateToSession();
            });

            if (variationTypesList) {
                variationTypesList.addEventListener('click', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLButtonElement) || !target.classList.contains('remove-variation-option')) {
                        return;
                    }

                    const variationIndex = Number(target.dataset.variationIndex);
                    const optionIndex = Number(target.dataset.optionIndex);

                    if (!Number.isInteger(variationIndex) || !Number.isInteger(optionIndex) || !variationTypes[variationIndex]) {
                        return;
                    }

                    variationTypes[variationIndex].options.splice(optionIndex, 1);
                    if ((variationTypes[variationIndex].options || []).length === 0) {
                        variationTypes.splice(variationIndex, 1);
                    }
                    renderVariationHiddenInputs();
                    renderVariationTypeCards();
                    syncAddVariantButtonState();

                    if (!variationTypes.length) {
                        rows = [];
                    } else {
                        rows = rows.map((row) => ({
                            ...row,
                            option_map: Object.fromEntries(
                                Object.entries(row.option_map || {}).filter(([key]) => Number(key) < variationTypes.length)
                            ),
                        }));
                    }

                    renderRows(rows);
                    saveStep2StateToSession();
                });
            }

            const showVariationModal = () => {
                if (!variationModal) {
                    return;
                }
                variationModal.classList.remove('hidden');
                variationModal.classList.add('flex');
            };

            const hideVariationModal = () => {
                if (!variationModal) {
                    return;
                }
                variationModal.classList.add('hidden');
                variationModal.classList.remove('flex');
            };

            if (openVariationModal) {
                openVariationModal.addEventListener('click', () => {
                    saveStep2StateToSession();
                    showVariationModal();
                });
            }

            [nameInput, descriptionInput, skuInput, bulkPriceInput, bulkStockInput].forEach((field) => {
                if (!field) {
                    return;
                }

                field.addEventListener('input', () => {
                    saveStep2StateToSession();
                });
            });
            if (variationModal) {
                variationModal.addEventListener('click', (event) => {
                    if (event.target === variationModal) {
                        hideVariationModal();
                    }
                });
            }
            if (productForm) {
                productForm.addEventListener('submit', () => {
                    clearSavedStep2State();
                });
            }
        })();
    </script>
</body>

</html>