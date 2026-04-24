@php
    $productModalSelectedStore = $productModalSelectedStore ?? ($selectedStore ?? ($store ?? null));
    $productModalIsOpen = (bool) ($productModalIsOpen ?? false);
    $productModalOpenQuery = $productModalOpenQuery ?? 'openAddProduct';
    $productFormData = old();

    if (empty($productFormData) && isset($draft) && is_array($draft)) {
        $productFormData = $draft;
    }

    $catalogBrands = $catalogBrands ?? collect();
    $catalogTags = $catalogTags ?? collect();
    $catalogTaxonomyCategories = $catalogTaxonomyCategories ?? collect();
    $defaultProductTypes = ['physical', 'digital', 'service', 'subscription', 'virtual'];
    $rawSelectedProductType = (string) ($productFormData['product_type'] ?? 'physical');
    $usesCustomProductType = $rawSelectedProductType !== '' && !in_array($rawSelectedProductType, $defaultProductTypes, true);
    $selectedProductType = $usesCustomProductType ? 'custom' : $rawSelectedProductType;
    $customProductType = $usesCustomProductType ? $rawSelectedProductType : (string) ($productFormData['custom_product_type'] ?? '');
@endphp

<div id="productCreateModal" data-open-query="{{ $productModalOpenQuery }}" class="fixed inset-0 z-[70] {{ $productModalIsOpen ? '' : 'hidden' }}">
    <div id="productCreateModalBackdrop" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]"></div>
    <div class="relative flex min-h-full items-center justify-center px-3 py-3 sm:px-5 sm:py-5">
                <div class="relative w-full max-w-[720px] overflow-hidden rounded-[28px] border border-[#D9E2EC] bg-[#F8FBFF] shadow-[0_30px_80px_rgba(15,23,42,0.28)]">
            <div class="flex items-center justify-between border-b border-[#E2E8F0] bg-white/95 px-5 py-4 sm:px-8">
                <div>
                    <h2 class="text-xl font-semibold text-[#0F172A] font-[Poppins]">Quick add product</h2>
                    <p class="mt-1 text-sm text-[#64748B]">Creates a simple product with one inventory row in your <span class="font-semibold text-[#475569]">active store</span>. After save you will open the full editor for variants, more images, and advanced details.</p>
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
                    <input type="hidden" name="inventory_variant_stock_mode" value="split_total">
                    <input id="bulk-price-hidden" type="hidden" name="bulk_price" value="{{ $productFormData['bulk_price'] ?? '' }}">
                    <input id="bulk-stock-hidden" type="hidden" name="bulk_stock" value="{{ $productFormData['bulk_stock'] ?? '' }}">

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
                                <label for="product-type" class="mb-2 block text-sm font-medium text-[#334155] font-poppins">Product behavior (type)</label>
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
                                <p class="mt-2 text-xs text-[#64748B]">How the product is fulfilled or sold—not the catalog category.</p>
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
                            @if ($catalogTaxonomyCategories->isNotEmpty())
                                <div class="md:col-span-2 rounded-xl border border-[#CCFBF1]/80 bg-[#F0FDFA]/40 p-4">
                                    <label for="product-category-ids" class="mb-2 block text-sm font-semibold text-[#0F766E] font-poppins">Catalog categories</label>
                                    <select id="product-category-ids" name="category_ids[]" multiple size="5" class="w-full rounded-xl border border-[#99F6E4]/60 bg-white px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0D9488]/25">
                                        @foreach ($catalogTaxonomyCategories as $catOption)
                                            <option value="{{ $catOption->id }}" @selected(collect(old('category_ids', $productFormData['category_ids'] ?? []))->contains($catOption->id))>{{ $catOption->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#115E59]/90">Main groups for your catalog (e.g. Clothing, Electronics). Separate from product behavior above.</p>
                                </div>
                            @endif
                            <div class="md:col-span-2">
                                <label for="product-brand-id" class="mb-2 block text-sm font-medium text-[#64748B] font-poppins">Brand <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                                <select id="product-brand-id" name="brand_id" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                    <option value="">No brand</option>
                                    @foreach ($catalogBrands as $brandOption)
                                        <option value="{{ $brandOption->id }}" @selected((string) ($productFormData['brand_id'] ?? '') === (string) $brandOption->id)>{{ $brandOption->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-[#94A3B8]">Optional grouping by label, vendor, or manufacturer.</p>
                            </div>
                            @if ($catalogTags->isNotEmpty())
                                <div class="md:col-span-2">
                                    <label for="product-tag-ids" class="mb-2 block text-sm font-medium text-[#64748B] font-poppins">Tags <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                                    <select id="product-tag-ids" name="tag_ids[]" multiple size="4" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                        @foreach ($catalogTags as $tagOption)
                                            <option value="{{ $tagOption->id }}" @selected(collect(old('tag_ids', $productFormData['tag_ids'] ?? []))->contains($tagOption->id))>{{ $tagOption->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-[#94A3B8]">Light labels like Sale or Featured—does not replace categories.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                        <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Price and inventory</h3>
                        <p class="mt-1 text-xs text-[#64748B]">These apply to your single default inventory row. Totals for multi-variant products are always the sum of each variant row in the full editor.</p>
                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label for="bulk-price" class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label>
                                <input id="bulk-price" type="number" min="0" step="0.01" value="{{ $productFormData['bulk_price'] ?? '' }}" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div>
                                <label for="bulk-stock" class="mb-1 block text-xs font-semibold text-[#64748B]">Stock on hand</label>
                                <input id="bulk-stock" type="number" min="0" step="1" value="{{ $productFormData['bulk_stock'] ?? '' }}" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div>
                                <label for="product-stock-alert" class="mb-1 block text-xs font-semibold text-[#64748B]">Low-stock alert</label>
                                <input id="product-stock-alert" name="stock_alert" type="number" min="0" step="1" value="{{ $productFormData['stock_alert'] ?? 5 }}" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[24px] border border-[#E0E7FF] bg-[#F8FAFF] p-5 shadow-sm sm:p-7">
                        <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Variants and advanced catalog work</h3>
                        <p class="mt-2 text-sm text-[#64748B]">Option groups, sellable combinations, variant photos, per-row pricing, and inventory tools live in the <span class="font-medium text-[#334155]">full product editor</span> so your gallery exists before you assign images to variants.</p>
                        <p class="mt-3 text-sm text-[#334155]">After you save this quick add, you will land in that editor automatically.</p>
                    </div>

                    <div class="sticky bottom-0 flex flex-col gap-3 rounded-[22px] border border-[#DDE7F3] bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-[#64748B]">Save creates the product and opens the full editor for this item.</p>
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

<script>
    (() => {
        const productModal = document.getElementById('productCreateModal');
        if (!productModal) return;

        const productModalBackdrop = document.getElementById('productCreateModalBackdrop');
        const productForm = document.getElementById('product-create-form');
        const openButtons = document.querySelectorAll('[data-open-product-modal]');
        const closeButtons = productModal.querySelectorAll('button[data-close-product-modal]');
        const openQuery = productModal.dataset.openQuery || 'openAddProduct';
        const productTypeSelect = document.getElementById('product-type');
        const productTypeValueInput = document.getElementById('product-type-value');
        const customProductTypeWrapper = document.getElementById('custom-product-type-wrapper');
        const customProductTypeInput = document.getElementById('custom-product-type');
        const productImageInput = document.getElementById('product-image');
        const productImagePreview = document.getElementById('product-image-preview');
        let selectedProductImages = [];

        const bulkPriceInput = document.getElementById('bulk-price');
        const bulkStockInput = document.getElementById('bulk-stock');
        const bulkPriceHiddenInput = document.getElementById('bulk-price-hidden');
        const bulkStockHiddenInput = document.getElementById('bulk-stock-hidden');

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

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
            document.body.classList.remove('overflow-hidden');
            const url = new URL(window.location.href);
            if (url.searchParams.has(openQuery)) {
                url.searchParams.delete(openQuery);
                window.history.replaceState({}, '', url);
            }
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

        bulkPriceInput?.addEventListener('input', () => {
            if (bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || '';
        });

        bulkStockInput?.addEventListener('input', () => {
            if (bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || '';
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
            if (bulkPriceInput && bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || '';
            if (bulkStockInput && bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || '';
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || productModal.classList.contains('hidden')) return;
            closeProductModal();
        });

        syncProductTypeState();
        if (bulkPriceInput && bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || '';
        if (bulkStockInput && bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || '';
        selectedProductImages = Array.from(productImageInput?.files || []);
        renderSelectedImages(productImageInput, productImagePreview);

        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.get(openQuery) === '1' || {{ $productModalIsOpen ? 'true' : 'false' }}) {
            openProductModal();
        }
    })();
</script>
