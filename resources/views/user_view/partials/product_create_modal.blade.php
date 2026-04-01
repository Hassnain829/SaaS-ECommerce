@php
    $productModalSelectedStore = $productModalSelectedStore ?? ($selectedStore ?? ($store ?? null));
    $productModalIsOpen = (bool) ($productModalIsOpen ?? false);
    $productModalOpenQuery = $productModalOpenQuery ?? 'openAddProduct';
    $productFormData = old();

    if (empty($productFormData) && isset($draft) && is_array($draft)) {
        $productFormData = $draft;
    }

    $productVariationTypes = $productFormData['variation_types'] ?? [];
    $productCustomVariants = $productFormData['variants'] ?? [];
    $productPreviewRows = [];

    if (!empty($productCustomVariants)) {
        foreach ($productCustomVariants as $variantRow) {
            if (!is_array($variantRow)) {
                continue;
            }

            $labelParts = [];
            foreach ($productVariationTypes as $variationIndex => $variationType) {
                $selectedIndex = $variantRow['option_map'][$variationIndex] ?? null;
                if ($selectedIndex !== null && $selectedIndex !== '' && isset($variationType['options'][$selectedIndex])) {
                    $labelParts[] = $variationType['options'][$selectedIndex];
                }
            }

            $productPreviewRows[] = [
                'label' => !empty($labelParts) ? implode(' / ', $labelParts) : 'Default Variant',
                'sku' => $variantRow['sku'] ?? '',
                'price' => $variantRow['price'] ?? ($productFormData['bulk_price'] ?? '0'),
                'stock' => $variantRow['stock'] ?? ($productFormData['bulk_stock'] ?? 50),
            ];
        }
    }

    if (empty($productPreviewRows)) {
        $productPreviewRows[] = [
            'label' => 'No variants added yet',
            'sku' => '-',
            'price' => '-',
            'stock' => '-',
        ];
    }

    $defaultProductTypes = ['physical', 'digital', 'service', 'subscription', 'virtual'];
    $rawSelectedProductType = (string) ($productFormData['product_type'] ?? 'physical');
    $usesCustomProductType = $rawSelectedProductType !== '' && !in_array($rawSelectedProductType, $defaultProductTypes, true);
    $selectedProductType = $usesCustomProductType ? 'custom' : $rawSelectedProductType;
    $customProductType = $usesCustomProductType ? $rawSelectedProductType : (string) ($productFormData['custom_product_type'] ?? '');
@endphp

<div id="productCreateModal" data-open-query="{{ $productModalOpenQuery }}" class="fixed inset-0 z-[70] {{ $productModalIsOpen ? '' : 'hidden' }}">
    <div id="productCreateModalBackdrop" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]"></div>
    <div class="relative flex min-h-full items-center justify-center px-3 py-3 sm:px-5 sm:py-5">
        <div class="relative w-full max-w-[980px] overflow-hidden rounded-[28px] border border-[#D9E2EC] bg-[#F8FBFF] shadow-[0_30px_80px_rgba(15,23,42,0.28)]">
            <div class="flex items-center justify-between border-b border-[#E2E8F0] bg-white/95 px-5 py-4 sm:px-8">
                <div>
                    <h2 class="text-xl font-semibold text-[#0F172A] font-[Poppins]">Create Product</h2>
                    <p class="mt-1 text-sm text-[#64748B]">Add a product to your catalog without leaving the products page.</p>
                </div>
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-[#D9E2EC] bg-white text-[#64748B] transition hover:border-[#0052CC] hover:text-[#0052CC]" data-close-product-modal aria-label="Close create product modal">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="max-h-[calc(100vh-8rem)] overflow-y-auto px-4 py-5 sm:px-7 sm:py-6">
                @if ($errors->any() && old('_open_add_product_modal'))
                    <div class="mb-6 rounded-xl border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                        <ul class="ml-5 list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="product-create-form" action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <input type="hidden" name="_open_add_product_modal" value="1">
                    <input id="bulk-price-hidden" type="hidden" name="bulk_price" value="{{ $productFormData['bulk_price'] ?? '' }}">
                    <input id="bulk-stock-hidden" type="hidden" name="bulk_stock" value="{{ $productFormData['bulk_stock'] ?? '' }}">
                    <input type="hidden" name="stock_alert" value="{{ $productFormData['stock_alert'] ?? 5 }}">

                    <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                        <div class="mb-6">
                            <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Details</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Active Store</label>
                                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A]">
                                    {{ $productModalSelectedStore?->name ?? 'No active store selected' }}
                                </div>
                                <p class="mt-2 text-xs text-[#64748B]">This product will be created in your currently active store. Use the sidebar switcher if you need a different store.</p>
                            </div>

                            <div>
                                <label for="product-type" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Product Type</label>
                                <input type="hidden" id="product-type-value" name="product_type" value="{{ $selectedProductType === 'custom' ? $customProductType : $selectedProductType }}">
                                <div class="relative">
                                    <select id="product-type" class="w-full appearance-none rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 pr-10 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                        @foreach ($defaultProductTypes as $productType)
                                            <option value="{{ $productType }}" @selected($selectedProductType === $productType)>{{ ucfirst($productType) }}</option>
                                        @endforeach
                                        <option value="custom" @selected($selectedProductType === 'custom')>Custom Type</option>
                                    </select>
                                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" width="12" height="12" viewBox="0 0 14 14" fill="none">
                                        <path d="M7 9L3 5H11L7 9Z" fill="#64748B"/>
                                    </svg>
                                </div>
                                <div id="custom-product-type-wrapper" class="mt-3 {{ $selectedProductType === 'custom' ? '' : 'hidden' }}">
                                    <input id="custom-product-type" name="custom_product_type" type="text" value="{{ $customProductType }}" placeholder="e.g. Home Decor" class="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                    <p class="mt-2 text-xs text-[#64748B]">Create your own product type if it is not in the list.</p>
                                </div>
                                <p class="mt-2 text-xs text-[#64748B]">This is the product type, not the store category.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-1 gap-6">
                            <div>
                                <label for="product-image" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Product Image</label>
                                <input id="product-image" name="product_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                                <p class="mt-2 text-xs text-[#64748B]">You can upload multiple images. Files are stored in your project storage and referenced from there.</p>
                                <div id="product-image-preview" class="mt-3 flex flex-wrap gap-3"></div>
                            </div>
                            <div>
                                <label for="product-name" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Product Name</label>
                                <input id="product-name" name="name" type="text" value="{{ $productFormData['name'] ?? '' }}" placeholder="e.g. Premium Cotton T-Shirt" class="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div>
                                <label for="product-description" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Description</label>
                                <textarea id="product-description" name="description" rows="3" placeholder="Describe your product's key features and benefits..." class="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ $productFormData['description'] ?? '' }}</textarea>
                            </div>
                            <div>
                                <label for="product-sku" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Base SKU (optional)</label>
                                <input id="product-sku" name="sku" type="text" value="{{ $productFormData['sku'] ?? '' }}" class="w-full rounded-xl border border-[#E2E8F0] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                        <div class="mb-6 flex items-center justify-between gap-3">
                            <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h3>
                            <button id="openVariationModal" type="button" class="inline-flex items-center gap-2 rounded-full border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                    <path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="currentColor"/>
                                </svg>
                                Add Variation Type
                            </button>
                        </div>

                        <div id="variation-hidden-inputs">
                            @foreach ($productVariationTypes as $variationIndex => $variationType)
                                <input type="hidden" name="variation_types[{{ $variationIndex }}][name]" value="{{ $variationType['name'] ?? '' }}">
                                <input type="hidden" name="variation_types[{{ $variationIndex }}][type]" value="{{ $variationType['type'] ?? 'select' }}">
                                @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                                    <input type="hidden" name="variation_types[{{ $variationIndex }}][options][{{ $optionIndex }}]" value="{{ $option }}">
                                @endforeach
                            @endforeach
                        </div>

                        <div id="no-variation-state" class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B] {{ empty($productVariationTypes) ? '' : 'hidden' }}">
                            No variation type added yet. Click <span class="font-semibold text-[#0052CC]">Add Variation Type</span> to start.
                        </div>

                        <div id="variation-types-list" class="space-y-4 {{ empty($productVariationTypes) ? 'hidden' : '' }}">
                            @foreach ($productVariationTypes as $index => $variationType)
                                <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="text-base text-[#0F172A] font-poppins">Variation {{ $index + 1 }}: {{ $variationType['name'] ?? 'Variation' }}</span>
                                        <span class="text-xs uppercase text-[#94A3B8]">{{ $variationType['type'] ?? 'select' }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                                            <span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium">
                                                {{ $option }}
                                                <button type="button" class="remove-variation-option leading-none text-[#94A3B8] hover:text-[#B42318]" data-variation-index="{{ $index }}" data-option-index="{{ $optionIndex }}" aria-label="Remove option">&times;</button>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants</h3>
                            <span class="text-sm font-medium text-[#64748B]">Rows are created automatically from variation options</span>
                        </div>

                        <div class="mb-5 grid grid-cols-1 gap-3 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 md:grid-cols-4">
                            <div class="md:col-span-2">
                                <p class="text-sm font-semibold text-[#0F172A]">Bulk Set Price & Stock</p>
                                <p class="text-xs text-[#64748B]">Apply one value to all variant rows.</p>
                            </div>
                            <div>
                                <label for="bulk-price" class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label>
                                <input id="bulk-price" type="number" min="0" step="0.01" value="{{ $productFormData['bulk_price'] ?? '' }}" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label for="bulk-stock" class="mb-1 block text-xs font-semibold text-[#64748B]">Stock</label>
                                    <input id="bulk-stock" type="number" min="0" step="1" value="{{ $productFormData['bulk_stock'] ?? '' }}" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                </div>
                                <button id="apply-bulk-values" type="button" class="rounded-lg bg-[#0052CC] px-3 py-2 text-sm font-semibold text-white">Apply</button>
                            </div>
                        </div>

                        <div id="variant-rows" class="space-y-4"></div>
                        <p class="mt-3 text-xs text-[#64748B]">Each row is created from your variation options automatically.</p>
                    </div>

                    <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h3>
                            <span id="preview-count" class="text-sm text-[#94A3B8]">{{ count($productPreviewRows) }} variant row(s)</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b border-[#F1F5F9]">
                                    <tr>
                                        <th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Variant</th>
                                        <th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">SKU</th>
                                        <th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Price ($)</th>
                                        <th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Stock</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-table-body" class="divide-y divide-[#F1F5F9]">
                                    @foreach ($productPreviewRows as $row)
                                        <tr>
                                            <td class="px-2 py-4 font-medium text-[#0F172A]">{{ $row['label'] }}</td>
                                            <td class="px-2 py-4 text-[#475569]">{{ $row['sku'] ?: 'Auto-generated' }}</td>
                                            <td class="px-2 py-4 text-[#475569]">{{ $row['price'] }}</td>
                                            <td class="px-2 py-4 text-[#475569]">{{ $row['stock'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sticky bottom-0 flex flex-col gap-3 rounded-[22px] border border-[#DDE7F3] bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-[#64748B]">Saving here creates the product only and keeps you on the products page.</p>
                        <div class="flex items-center gap-3">
                            <button type="button" class="rounded-lg px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-close-product-modal>Cancel</button>
                            <button type="submit" id="submit-product-modal" class="rounded-xl bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0047B3]" @disabled(!$productModalSelectedStore)>Save Product</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="variationModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-[#0F172A]/60 p-4 backdrop-blur-[2px]">
    <div class="w-full max-w-[512px] overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#F1F5F9] px-6 py-4">
            <div>
                <h3 class="text-lg font-semibold text-[#0F172A]">Add Variation Type</h3>
                <p class="mt-0.5 text-xs text-[#64748B]">Define how customers will differentiate your items</p>
            </div>
            <button type="button" id="closeVariationModal" class="text-[#94A3B8] hover:text-[#64748B]">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M1.4 14L0 12.6L5.6 7L0 1.4L1.4 0L7 5.6L12.6 0L14 1.4L8.4 7L14 12.6L12.6 14L7 8.4L1.4 14Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <form id="variationForm" class="space-y-6 p-6">
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#334155]">Variation Name</label>
                <input id="variationName" type="text" placeholder="e.g., Size" class="w-full rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-[#334155]">Option Values</label>
                <div class="rounded-lg border border-[#E2E8F0] px-3 py-3 focus-within:ring-2 focus-within:ring-[#0052CC]/20">
                    <div id="variationOptionChips" class="mb-2 flex flex-wrap gap-2"></div>
                    <input id="variationOptionInput" type="text" placeholder="Type a value and press Enter" class="w-full border-0 p-0 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-0">
                </div>
                <textarea id="variationOptions" rows="3" placeholder="S, M, L, XL" class="hidden"></textarea>
            </div>
        </form>

        <div class="flex items-center justify-end gap-3 border-t border-[#F1F5F9] bg-[#F8FAFC] px-6 py-4">
            <button type="button" id="cancelVariationModal" class="px-4 py-2 text-sm font-semibold text-[#475569] hover:text-[#1E293B]">Cancel</button>
            <button type="button" id="submitVariationModal" class="flex items-center gap-2 rounded-lg bg-[#0052CC] px-5 py-2 text-sm font-bold text-white shadow-md shadow-[#0052CC]/20 transition hover:bg-[#0047B3]">
                <span>Add Variation</span>
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                    <path d="M4 5.33333H0V4H4V0H5.33333V4H9.33333V5.33333H5.33333V9.33333H4V5.33333Z" fill="white"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
    (() => {
        const productModal = document.getElementById('productCreateModal');
        if (!productModal) return;

        const variationModal = document.getElementById('variationModal');
        const productModalBackdrop = document.getElementById('productCreateModalBackdrop');
        const productForm = document.getElementById('product-create-form');
        const openButtons = document.querySelectorAll('[data-open-product-modal]');
        const closeButtons = productModal.querySelectorAll('button[data-close-product-modal]');
        const openQuery = productModal.dataset.openQuery || 'openAddProduct';
        const submitButton = document.getElementById('submit-product-modal');
        const productTypeSelect = document.getElementById('product-type');
        const productTypeValueInput = document.getElementById('product-type-value');
        const customProductTypeWrapper = document.getElementById('custom-product-type-wrapper');
        const customProductTypeInput = document.getElementById('custom-product-type');
        const productImageInput = document.getElementById('product-image');
        const productImagePreview = document.getElementById('product-image-preview');
        let selectedProductImages = [];

        let variationTypes = @json(array_values($productVariationTypes));
        let rows = @json(array_values($productCustomVariants));
        let defaultPrice = @json((string) ($productFormData['bulk_price'] ?? ''));
        let defaultStock = @json((string) ($productFormData['bulk_stock'] ?? ''));
        const defaultStockAlert = @json((int) ($productFormData['stock_alert'] ?? 5));

        const bulkPriceInput = document.getElementById('bulk-price');
        const bulkStockInput = document.getElementById('bulk-stock');
        const applyBulkButton = document.getElementById('apply-bulk-values');
        const rowsContainer = document.getElementById('variant-rows');
        const previewCount = document.getElementById('preview-count');
        const previewTableBody = document.getElementById('preview-table-body');
        const variationTypesList = document.getElementById('variation-types-list');
        const noVariationState = document.getElementById('no-variation-state');
        const variationHiddenInputs = document.getElementById('variation-hidden-inputs');
        const bulkPriceHiddenInput = document.getElementById('bulk-price-hidden');
        const bulkStockHiddenInput = document.getElementById('bulk-stock-hidden');
        const openVariationModal = document.getElementById('openVariationModal');
        const variationOptionInput = document.getElementById('variationOptionInput');
        const variationOptionChips = document.getElementById('variationOptionChips');
        const variationOptionsTextarea = document.getElementById('variationOptions');
        let variationOptionTags = [];

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
        const getRowKey = (optionMap) => Object.entries(optionMap || {}).sort(([left], [right]) => Number(left) - Number(right)).map(([variationIndex, optionIndex]) => `${variationIndex}:${optionIndex}`).join('|');
        const buildRowsFromVariationTypes = (existingRows = []) => {
            if (!variationTypes.length || variationTypes.some((variationType) => !(variationType.options || []).length)) {
                return [];
            }

            const existingRowsByKey = new Map(existingRows.map((row) => [getRowKey(row.option_map || {}), row]));
            const combinations = [];

            const walk = (variationIndex, optionMap) => {
                if (variationIndex >= variationTypes.length) {
                    combinations.push({ ...optionMap });
                    return;
                }

                (variationTypes[variationIndex].options || []).forEach((_, optionIndex) => {
                    walk(variationIndex + 1, {
                        ...optionMap,
                        [variationIndex]: optionIndex,
                    });
                });
            };

            walk(0, {});

            return combinations.map((optionMap) => {
                const existingRow = existingRowsByKey.get(getRowKey(optionMap));

                return {
                    option_map: optionMap,
                    sku: existingRow?.sku || '',
                    price: existingRow?.price ?? defaultPrice,
                    stock: existingRow?.stock ?? defaultStock,
                    stock_alert: existingRow?.stock_alert ?? defaultStockAlert,
                };
            });
        };

        const syncSelectedFiles = (input, files) => {
            if (!input) return;
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        };

        const renderSelectedImages = (input, preview) => {
            if (!preview || !input) return;
            const files = selectedProductImages;
            if (!files.length) {
                preview.innerHTML = '';
                return;
            }

            preview.innerHTML = files.map((file, index) => {
                const objectUrl = URL.createObjectURL(file);
                return `<div class="group relative overflow-hidden rounded-2xl border border-[#D9E2EC] bg-white p-2 shadow-sm"><img src="${objectUrl}" alt="${escapeHtml(file.name)}" class="h-20 w-20 rounded-xl object-cover"><button type="button" class="remove-selected-image absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0F172A]/70 text-xs font-bold text-white" data-index="${index}" aria-label="Remove image">&times;</button><p class="mt-2 max-w-[80px] truncate text-[11px] text-[#64748B]">${escapeHtml(file.name)}</p></div>`;
            }).join('');

            preview.querySelectorAll('.remove-selected-image').forEach((button) => {
                button.addEventListener('click', () => {
                    const nextFiles = files.filter((_, index) => index !== Number(button.dataset.index));
                    selectedProductImages = nextFiles;
                    syncSelectedFiles(input, selectedProductImages);
                    renderSelectedImages(input, preview);
                });
            });
        };

        const syncVariationOptionTextarea = () => {
            if (variationOptionsTextarea) {
                variationOptionsTextarea.value = variationOptionTags.join(', ');
            }
        };

        const renderVariationOptionTags = () => {
            if (!variationOptionChips) return;
            variationOptionChips.innerHTML = variationOptionTags.map((tag, index) => `<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(tag)}<button type="button" class="remove-variation-tag leading-none text-[#94A3B8] hover:text-[#B42318]" data-index="${index}">&times;</button></span>`).join('');
            variationOptionChips.querySelectorAll('.remove-variation-tag').forEach((button) => {
                button.addEventListener('click', () => {
                    variationOptionTags = variationOptionTags.filter((_, index) => index !== Number(button.dataset.index));
                    syncVariationOptionTextarea();
                    renderVariationOptionTags();
                });
            });
        };

        const addVariationOptionTags = (rawValue) => {
            const nextTags = String(rawValue || '').split(',').map((value) => value.trim()).filter(Boolean);
            if (!nextTags.length) return;
            variationOptionTags = [...variationOptionTags, ...nextTags];
            syncVariationOptionTextarea();
            renderVariationOptionTags();
            if (variationOptionInput) variationOptionInput.value = '';
        };

        const syncProductTypeState = () => {
            const isCustomType = productTypeSelect?.value === 'custom';
            customProductTypeWrapper?.classList.toggle('hidden', !isCustomType);
            if (customProductTypeInput) {
                customProductTypeInput.required = Boolean(isCustomType);
            }
            if (productTypeValueInput) {
                productTypeValueInput.value = isCustomType
                    ? (customProductTypeInput?.value.trim() || 'custom')
                    : (productTypeSelect?.value || 'physical');
            }
        };

        const openProductModal = () => {
            productModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };

        const closeProductModal = () => {
            productModal.classList.add('hidden');
            variationModal?.classList.add('hidden');
            variationModal?.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            const url = new URL(window.location.href);
            if (url.searchParams.has(openQuery)) {
                url.searchParams.delete(openQuery);
                window.history.replaceState({}, '', url);
            }
        };

        const openVariationDialog = () => {
            variationModal?.classList.remove('hidden');
            variationModal?.classList.add('flex');
            document.getElementById('variationName')?.focus();
        };

        const closeVariationDialog = () => {
            variationModal?.classList.add('hidden');
            variationModal?.classList.remove('flex');
            const name = document.getElementById('variationName');
            if (name) name.value = '';
            variationOptionTags = [];
            syncVariationOptionTextarea();
            renderVariationOptionTags();
        };

        const normalizeRow = (row) => ({
            option_map: row?.option_map || {},
            sku: row?.sku || '',
            price: row?.price ?? defaultPrice,
            stock: row?.stock ?? defaultStock,
            stock_alert: row?.stock_alert ?? defaultStockAlert,
        });

        rows = rows.map(normalizeRow);

        const renderVariationHiddenInputs = () => {
            if (!variationHiddenInputs) return;
            variationHiddenInputs.innerHTML = variationTypes.map((variationType, variationIndex) => {
                const optionInputs = (variationType.options || []).map((option, optionIndex) => `<input type="hidden" name="variation_types[${variationIndex}][options][${optionIndex}]" value="${escapeHtml(option)}">`).join('');
                return `<input type="hidden" name="variation_types[${variationIndex}][name]" value="${escapeHtml(variationType.name || '')}"><input type="hidden" name="variation_types[${variationIndex}][type]" value="${escapeHtml(variationType.type || 'select')}">${optionInputs}`;
            }).join('');
        };

        const attachVariationOptionRemoveListeners = () => {
            document.querySelectorAll('.remove-variation-option').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const variationIndex = Number(button.dataset.variationIndex);
                    const optionIndex = Number(button.dataset.optionIndex);
                    if (!variationTypes[variationIndex]) return;
                    variationTypes[variationIndex].options.splice(optionIndex, 1);
                    if (!(variationTypes[variationIndex].options || []).length) variationTypes.splice(variationIndex, 1);
                    rows = buildRowsFromVariationTypes(rows);
                    renderVariationHiddenInputs();
                    renderVariationTypeCards();
                    renderVariantRows();
                });
            });
        };

        const renderVariationTypeCards = () => {
            if (!variationTypes.length) {
                variationTypesList?.classList.add('hidden');
                noVariationState?.classList.remove('hidden');
                return;
            }

            noVariationState?.classList.add('hidden');
            variationTypesList?.classList.remove('hidden');

            variationTypesList.innerHTML = variationTypes.map((variationType, variationIndex) => {
                const chips = (variationType.options || []).map((option, optionIndex) => `<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium">${escapeHtml(option)}<button type="button" class="remove-variation-option leading-none text-[#94A3B8] hover:text-[#B42318]" data-variation-index="${variationIndex}" data-option-index="${optionIndex}" aria-label="Remove option">&times;</button></span>`).join('');
                return `<div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5"><div class="mb-3 flex items-center justify-between"><span class="text-base font-medium text-[#0F172A]">Variation ${variationIndex + 1}: ${escapeHtml(variationType.name || 'Variation')}</span><span class="text-xs uppercase text-[#94A3B8]">${escapeHtml(variationType.type || 'select')}</span></div><div class="flex flex-wrap gap-2">${chips}</div></div>`;
            }).join('');
            attachVariationOptionRemoveListeners();
        };

        const updatePreview = () => {
            if (!previewCount || !previewTableBody) return;
            const previewRows = rows.map((row) => {
                const labelParts = variationTypes.map((variation, variationIndex) => {
                    const selectedIndex = row.option_map?.[variationIndex];
                    return selectedIndex !== undefined && selectedIndex !== null && selectedIndex !== '' ? (variation.options?.[selectedIndex] || '') : '';
                }).filter(Boolean);
                return {
                    label: labelParts.length ? labelParts.join(' / ') : 'Default Variant',
                    sku: row.sku || 'Auto-generated',
                    price: row.price || defaultPrice,
                    stock: row.stock || defaultStock,
                };
            });

            const sourceRows = previewRows.length ? previewRows : [{ label: 'No variants added yet', sku: '-', price: '-', stock: '-' }];
            previewTableBody.innerHTML = sourceRows.map((row) => `<tr><td class="px-2 py-4 font-medium text-[#0F172A]">${escapeHtml(row.label)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(row.sku)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(row.price)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(row.stock)}</td></tr>`).join('');
            previewCount.textContent = `${previewRows.length} variant row(s)`;
        };

        const renderVariantRows = () => {
            if (!rowsContainer) return;
            if (!rows.length) {
                rowsContainer.innerHTML = '';
                updatePreview();
                return;
            }

            rowsContainer.innerHTML = rows.map((row, rowIndex) => {
                const selectedOptions = Object.entries(row.option_map || {}).map(([variationIndex, optionIndex]) => {
                    const variationType = variationTypes[Number(variationIndex)];
                    const optionValue = variationType?.options?.[Number(optionIndex)] || '';
                    return `<span class="inline-flex items-center rounded-lg border border-[#DDE7F3] bg-white px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(variationType?.name || 'Variation')}: ${escapeHtml(optionValue)}</span><input type="hidden" name="variants[${rowIndex}][option_map][${variationIndex}]" value="${escapeHtml(optionIndex)}">`;
                }).join('');
                return `<div class="space-y-4 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5"><div class="flex flex-wrap gap-2">${selectedOptions}</div><div class="grid grid-cols-1 gap-3 md:grid-cols-3"><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">SKU (optional)</label><input type="text" name="variants[${rowIndex}][sku]" value="${escapeHtml(row.sku || '')}" data-row-index="${rowIndex}" data-row-field="sku" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Price ($)</label><input type="number" min="0" step="0.01" name="variants[${rowIndex}][price]" value="${escapeHtml(row.price || '')}" data-row-index="${rowIndex}" data-row-field="price" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Stock</label><input type="number" min="0" step="1" name="variants[${rowIndex}][stock]" value="${escapeHtml(row.stock || '')}" data-row-index="${rowIndex}" data-row-field="stock" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"></div></div><input type="hidden" name="variants[${rowIndex}][stock_alert]" value="${escapeHtml(row.stock_alert ?? defaultStockAlert)}"></div>`;
            }).join('');
            document.querySelectorAll('input[data-row-field]').forEach((input) => {
                input.addEventListener('input', () => {
                    const rowIndex = Number(input.dataset.rowIndex);
                    rows[rowIndex][input.dataset.rowField] = input.value;
                    updatePreview();
                });
            });
            updatePreview();
        };

        openButtons.forEach((button) => button.addEventListener('click', (event) => {
            event.preventDefault();
            openProductModal();
        }));

        closeButtons.forEach((button) => button.addEventListener('click', (event) => {
            event.preventDefault();
            closeProductModal();
        }));

        productModalBackdrop?.addEventListener('click', (event) => {
            if (event.target === productModalBackdrop) {
                closeProductModal();
            }
        });

        productTypeSelect?.addEventListener('change', syncProductTypeState);
        customProductTypeInput?.addEventListener('input', syncProductTypeState);
        productImageInput?.addEventListener('change', () => {
            const incomingFiles = Array.from(productImageInput.files || []);
            if (incomingFiles.length) {
                selectedProductImages = [...selectedProductImages, ...incomingFiles];
                syncSelectedFiles(productImageInput, selectedProductImages);
            }
            renderSelectedImages(productImageInput, productImagePreview);
        });
        variationOptionInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addVariationOptionTags(variationOptionInput.value);
            }
        });
        variationOptionInput?.addEventListener('blur', () => {
            if (variationOptionInput.value.trim()) {
                addVariationOptionTags(variationOptionInput.value);
            }
        });
        openVariationModal?.addEventListener('click', (event) => {
            event.preventDefault();
            openVariationDialog();
        });

        document.getElementById('closeVariationModal')?.addEventListener('click', closeVariationDialog);
        document.getElementById('cancelVariationModal')?.addEventListener('click', closeVariationDialog);
        document.getElementById('submitVariationModal')?.addEventListener('click', () => {
            const variationName = document.getElementById('variationName')?.value.trim();
            const variationType = 'select';
            addVariationOptionTags(variationOptionInput?.value || '');
            const optionsStr = document.getElementById('variationOptions')?.value.trim();
            if (!variationName || !optionsStr) {
                alert('Please add a variation name and at least one option.');
                return;
            }
            const options = optionsStr.split(',').map((option) => option.trim()).filter(Boolean);
            if (!options.length) {
                alert('Please enter valid options separated by commas.');
                return;
            }
            variationTypes.push({ name: variationName, type: variationType, options });
            rows = buildRowsFromVariationTypes(rows);
            renderVariationHiddenInputs();
            renderVariationTypeCards();
            renderVariantRows();
            closeVariationDialog();
        });

        variationModal?.addEventListener('click', (event) => {
            if (event.target === variationModal) closeVariationDialog();
        });

        if (applyBulkButton) {
            applyBulkButton.addEventListener('click', (event) => {
                event.preventDefault();
                const price = bulkPriceInput?.value || defaultPrice;
                const stock = bulkStockInput?.value || defaultStock;
                rows = rows.map((row) => ({ ...row, price, stock }));
                renderVariantRows();
            });
        }

        bulkPriceInput?.addEventListener('input', () => {
            defaultPrice = bulkPriceInput.value;
            if (bulkPriceHiddenInput) bulkPriceHiddenInput.value = defaultPrice;
            updatePreview();
        });

        bulkStockInput?.addEventListener('input', () => {
            defaultStock = bulkStockInput.value;
            if (bulkStockHiddenInput) bulkStockHiddenInput.value = defaultStock;
            updatePreview();
        });

        productForm?.addEventListener('submit', (event) => {
            if (!{{ $productModalSelectedStore ? 'true' : 'false' }}) {
                event.preventDefault();
                alert('Please switch to an active store before saving the product.');
                return;
            }
            if (productTypeSelect?.value === 'custom' && !customProductTypeInput?.value.trim()) {
                event.preventDefault();
                alert('Please enter a custom product type.');
                customProductTypeInput?.focus();
                return;
            }
            syncProductTypeState();
            if (bulkPriceInput && bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || defaultPrice;
            if (bulkStockInput && bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || defaultStock;
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || productModal.classList.contains('hidden')) return;
            if (variationModal && !variationModal.classList.contains('hidden')) {
                closeVariationDialog();
                return;
            }
            closeProductModal();
        });

        syncProductTypeState();
        if (!rows.length && variationTypes.length) {
            rows = buildRowsFromVariationTypes(rows);
        }
        renderVariationHiddenInputs();
        renderVariationTypeCards();
        renderVariantRows();
        selectedProductImages = Array.from(productImageInput?.files || []);
        renderSelectedImages(productImageInput, productImagePreview);
        renderVariationOptionTags();

        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.get(openQuery) === '1' || {{ $productModalIsOpen ? 'true' : 'false' }}) {
            openProductModal();
        }
    })();
</script>
