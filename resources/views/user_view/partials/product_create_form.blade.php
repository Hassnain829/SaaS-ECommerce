@php
    $productModalSelectedStore = $productModalSelectedStore ?? ($selectedStore ?? ($store ?? null));
    $productFormData = old();

    if (empty($productFormData) && isset($draft) && is_array($draft)) {
        $productFormData = $draft;
    }

    $catalogBrands = $catalogBrands ?? collect();
    $catalogTags = $catalogTags ?? collect();
    $catalogTaxonomyCategories = $catalogTaxonomyCategories ?? collect();
    $taxSetting = $taxSetting ?? ($productModalSelectedStore?->taxSetting ?? null);
    $defaultProductTaxable = (bool) ($taxSetting?->default_product_taxable ?? true);
    $isTaxableChecked = old('is_taxable', $defaultProductTaxable ? '1' : '0') === '1' || old('is_taxable') === true;
    $defaultProductTypes = \App\Support\ProductTypeBehavior::types();
    $rawSelectedProductType = \App\Support\ProductTypeBehavior::normalize((string) ($productFormData['product_type'] ?? 'physical'));
    $selectedProductType = in_array($rawSelectedProductType, $defaultProductTypes, true) ? $rawSelectedProductType : 'physical';
    $selectedCustomProductType = trim((string) ($productFormData['custom_product_type'] ?? ''));
    $selectedProductTypeSelector = trim((string) ($productFormData['product_type_selector'] ?? ''));
    $selectedCustomProductBehavior = \App\Support\ProductTypeBehavior::normalize((string) ($productFormData['custom_product_type_behavior'] ?? $selectedProductType));
    if (! in_array($selectedCustomProductBehavior, $defaultProductTypes, true)) {
        $selectedCustomProductBehavior = 'physical';
    }
    $selectedCustomMode = $selectedCustomProductType !== '' || $selectedProductTypeSelector === '__custom__';
    $showProductCreateErrors = $errors->any() && (old('_from_product_create_page') || old('_open_add_product_modal'));
    $createSectionClass = 'rounded-2xl border border-slate-200/80 bg-white px-5 py-6 sm:px-7 sm:py-7';
@endphp

@if ($showProductCreateErrors)
    <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
        <ul class="ml-5 list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="product-create-form" action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf
    <input type="hidden" name="_from_product_create_page" value="1">
    <input type="hidden" name="inventory_variant_stock_mode" value="split_total">
    <input id="bulk-price-hidden" type="hidden" name="bulk_price" value="{{ $productFormData['bulk_price'] ?? '' }}">
    <input id="bulk-stock-hidden" type="hidden" name="bulk_stock" value="{{ $productFormData['bulk_stock'] ?? '' }}">

    <nav id="catalog-create-section-nav" class="flex flex-wrap gap-2 rounded-2xl border border-slate-200/80 bg-white px-3 py-2.5 text-xs font-semibold text-[#475569] shadow-sm" aria-label="Jump to editor sections">
        <a href="#catalog-create-section-basics" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Basics</a>
        <a href="#catalog-create-section-media" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Media</a>
        <a href="#catalog-create-section-pricing" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Pricing</a>
        <a href="#catalog-create-section-organization" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Organization</a>
    </nav>

    <div class="{{ $createSectionClass }}" id="catalog-create-section-basics">
        <div class="mb-6 border-b border-slate-100 pb-4">
            <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl">Product basics</h3>
            <p class="mt-1 text-xs text-[#64748B]">Name, type, identifiers, and pricing defaults for your active store.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Active Store</label>
                <div class="rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A]">
                    {{ $productModalSelectedStore?->name ?? 'No active store selected' }}
                </div>
                <p class="mt-2 text-xs text-[#64748B]">This product will be created in your currently active store. Use the sidebar switcher if you need a different store.</p>
            </div>

            <div>
                <label for="product-type" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">How is this product sold?</label>
                <input type="hidden" id="product-type-value" name="product_type" value="{{ $selectedProductType }}">
                <div class="relative">
                    <select id="product-type" name="product_type_selector" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        @foreach ($defaultProductTypes as $productType)
                            <option value="{{ $productType }}" @selected($selectedProductType === $productType)>{{ ucfirst($productType) }}</option>
                        @endforeach
                        <option value="__custom__" @selected($selectedCustomMode)>Other / Custom</option>
                    </select>
                </div>
                <div id="custom-product-type-wrap" class="{{ $selectedCustomMode ? '' : 'hidden' }} mt-3 space-y-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <label for="custom-product-type" class="block text-xs font-semibold text-[#334155]">Custom product label</label>
                    <input id="custom-product-type" name="custom_product_type" type="text" maxlength="80" value="{{ $selectedCustomProductType }}" placeholder="e.g. Menu item, Warranty, Membership" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                    <div>
                        <label for="custom-product-type-behavior" class="mb-1 block text-xs font-semibold text-[#334155]">How should this custom type behave?</label>
                        <select id="custom-product-type-behavior" name="custom_product_type_behavior" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                            <option value="physical" @selected($selectedCustomProductBehavior === 'physical')>Ships like a physical product</option>
                            <option value="digital" @selected($selectedCustomProductBehavior === 'digital')>Delivered digitally</option>
                            <option value="service" @selected($selectedCustomProductBehavior === 'service')>Sold as a service</option>
                            <option value="virtual" @selected($selectedCustomProductBehavior === 'virtual')>Virtual / no shipping</option>
                            <option value="subscription" @selected($selectedCustomProductBehavior === 'subscription')>Subscription</option>
                        </select>
                    </div>
                    <p class="text-xs text-[#64748B]">Use this when your product type is not listed. This label appears in your catalog while system behavior stays safely mapped.</p>
                </div>
                <p id="createProductTypeBehaviorHelp" class="mt-2 text-xs text-[#64748B]">Product behavior controls shipping, inventory, and future fulfillment. Category controls where the item appears in your catalog.</p>
            </div>
        </div>

        <p id="catalog-create-section-media" class="mt-6 scroll-mt-28 text-xs font-bold uppercase tracking-wide text-[#94A3B8]">Media</p>
        <p class="mt-1 text-xs text-[#64748B]">Photos for this listing. Upload here first, then you can assign a photo to each variant row in the full editor.</p>

        <div class="mt-4 grid grid-cols-1 gap-6">
            <div>
                <label for="product-image" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product images</label>
                <input id="product-image" name="product_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                <div id="product-image-preview" class="mt-3 flex flex-wrap gap-2"></div>
            </div>
            <div>
                <label for="product-name" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product Name</label>
                <input id="product-name" name="name" type="text" value="{{ $productFormData['name'] ?? '' }}" placeholder="e.g. Premium Cotton T-Shirt" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
            <div>
                <label for="product-description" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Description</label>
                <textarea id="product-description" name="description" rows="3" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" placeholder="Optional short description">{{ $productFormData['description'] ?? '' }}</textarea>
            </div>
        </div>

        <div id="catalog-create-section-pricing" class="mt-6 scroll-mt-28 grid grid-cols-1 gap-6 md:grid-cols-3">
            <div>
                <label for="product-sku" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base SKU</label>
                <input id="product-sku" name="sku" type="text" value="{{ $productFormData['sku'] ?? '' }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
            <div>
                <label for="bulk-price" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base Price</label>
                <input id="bulk-price" type="number" min="0" step="0.01" value="{{ $productFormData['bulk_price'] ?? '' }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
            <div>
                <label for="product-stock-alert" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Stock Alert</label>
                <input id="product-stock-alert" name="stock_alert" type="number" min="0" step="1" value="{{ $productFormData['stock_alert'] ?? 5 }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
            <div class="md:col-span-3">
                <label for="bulk-stock" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Stock on hand</label>
                <input id="bulk-stock" type="number" min="0" step="1" value="{{ $productFormData['bulk_stock'] ?? '' }}" class="w-full max-w-md rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                <p class="mt-1.5 text-xs text-[#64748B]">Applies to your single default inventory row until you add option groups in the full editor.</p>
            </div>
            <div class="md:col-span-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <input type="hidden" name="is_taxable" value="0">
                <label class="flex items-start gap-3">
                    <input id="product-is-taxable" name="is_taxable" type="checkbox" value="1" class="mt-0.5 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]" @checked($isTaxableChecked) @error('is_taxable') aria-invalid="true" @enderror>
                    <span>
                        <span class="block text-sm font-semibold text-[#0F172A]">Charge tax on this product</span>
                        <span class="mt-1 block text-xs text-[#64748B]">Store default: New products are {{ $defaultProductTaxable ? 'taxable' : 'not taxable' }}.</span>
                    </span>
                </label>
                @error('is_taxable')
                    <p class="mt-2 text-xs text-[#B42318]">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div id="catalog-create-section-organization" class="mt-6 scroll-mt-28 border-t border-slate-100 pt-6">
            <p class="text-xs font-bold uppercase tracking-wide text-[#94A3B8]">Organization</p>
            <p class="mt-1 text-xs text-[#64748B]">Brand, categories, and tags help your team find this product in the catalog.</p>

            @if ($catalogTaxonomyCategories->isNotEmpty())
                <div class="mt-4 rounded-xl border border-[#CCFBF1]/80 bg-[#F0FDFA]/40 p-4">
                    <label for="product-category-ids" class="mb-2 block text-sm font-semibold text-[#0F766E] font-[Poppins]">Catalog categories</label>
                    <select id="product-category-ids" name="category_ids[]" multiple size="5" class="w-full rounded-lg border border-[#99F6E4]/60 bg-white px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0D9488]/25">
                        @foreach ($catalogTaxonomyCategories as $catOption)
                            <option value="{{ $catOption->id }}" @selected(collect(old('category_ids', $productFormData['category_ids'] ?? []))->contains($catOption->id))>{{ $catOption->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-[#115E59]/90">Main catalog groups—separate from product behavior (type) above.</p>
                </div>
            @endif

            @if ($catalogBrands->isNotEmpty())
                <div class="mt-4">
                    <label for="product-brand-id" class="mb-2 block text-sm font-medium text-[#64748B] font-[Poppins]">Brand <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                    <select id="product-brand-id" name="brand_id" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                        <option value="">No brand</option>
                        @foreach ($catalogBrands as $brandOption)
                            <option value="{{ $brandOption->id }}" @selected((string) old('brand_id', $productFormData['brand_id'] ?? '') === (string) $brandOption->id)>{{ $brandOption->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-[#94A3B8]">Optional vendor or label. Does not change which store owns the product.</p>
                </div>
            @endif

            @if ($catalogTags->isNotEmpty())
                <div class="mt-4">
                    <label for="product-tag-ids" class="mb-2 block text-sm font-medium text-[#64748B] font-[Poppins]">Tags <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                    <select id="product-tag-ids" name="tag_ids[]" multiple size="4" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                        @foreach ($catalogTags as $tagOption)
                            <option value="{{ $tagOption->id }}" @selected(collect(old('tag_ids', $productFormData['tag_ids'] ?? []))->contains($tagOption->id))>{{ $tagOption->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-[#94A3B8]">Quick labels like Featured or Sale—not your main catalog structure.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-4 border-t border-slate-200/90 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-[#64748B]">Use the sidebar to save. You can also cancel here if you want to leave without creating a product.</p>
        <a href="{{ $productCreateCancelUrl ?? route('products') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
    </div>
</form>

<script>
    (() => {
        const productForm = document.getElementById('product-create-form');
        if (!productForm) return;

        const productTypeSelect = document.getElementById('product-type');
        const productTypeValueInput = document.getElementById('product-type-value');
        const customProductTypeWrap = document.getElementById('custom-product-type-wrap');
        const customProductTypeInput = document.getElementById('custom-product-type');
        const customProductTypeBehaviorInput = document.getElementById('custom-product-type-behavior');
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
            if (!productTypeValueInput) return;
            const selected = productTypeSelect?.value || 'physical';
            const isCustom = selected === '__custom__';
            if (customProductTypeWrap) customProductTypeWrap.classList.toggle('hidden', !isCustom);
            if (isCustom) {
                const selectedBehavior = customProductTypeBehaviorInput?.value || 'physical';
                productTypeValueInput.value = selectedBehavior;
            } else {
                productTypeValueInput.value = selected;
                if (customProductTypeInput) customProductTypeInput.value = '';
            }
        };

        productTypeSelect?.addEventListener('change', syncProductTypeState);
        customProductTypeBehaviorInput?.addEventListener('change', syncProductTypeState);
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

        productForm.addEventListener('submit', (event) => {
            if (!{{ $productModalSelectedStore ? 'true' : 'false' }}) {
                event.preventDefault();
                alert('Please switch to an active store before saving the product.');
                return;
            }
            syncProductTypeState();
            if (bulkPriceInput && bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || '';
            if (bulkStockInput && bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || '';
        });

        syncProductTypeState();
        if (bulkPriceInput && bulkPriceHiddenInput) bulkPriceHiddenInput.value = bulkPriceInput.value || '';
        if (bulkStockInput && bulkStockHiddenInput) bulkStockHiddenInput.value = bulkStockInput.value || '';
        selectedProductImages = Array.from(productImageInput?.files || []);
        renderSelectedImages(productImageInput, productImagePreview);
    })();
</script>
