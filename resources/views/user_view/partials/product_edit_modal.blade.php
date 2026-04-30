@php
    $productEditSurface = $productEditSurface ?? 'modal';
    $productEditPageNative = $productEditPageNative ?? false;
    $productEditHasErrors = ($productEditSurface === 'page')
        ? $errors->any()
        : (old('_open_edit_product_modal') && $errors->any());
    $catalogBrands = $catalogBrands ?? collect();
    $catalogTags = $catalogTags ?? collect();
    $catalogTaxonomyCategories = $catalogTaxonomyCategories ?? collect();
    $workspaceReturnProductId = $workspaceReturnProductId ?? null;
    $additionalDetailKeyErrors = [];
    if ($errors->any()) {
        foreach ($errors->getMessages() as $field => $messages) {
            if (preg_match('/^custom_fields\.(\d+)\.key$/', (string) $field, $m) && $messages !== []) {
                $additionalDetailKeyErrors[(int) $m[1]] = (string) ($messages[0] ?? '');
            }
        }
    }
    $editSectionClass = ($productEditSurface === 'page' && $productEditPageNative)
        ? 'rounded-2xl border border-slate-200/80 bg-white px-5 py-6 sm:px-7 sm:py-7'
        : 'rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7';
@endphp

<div id="editProductModal"
     data-surface="{{ $productEditSurface }}"
     @class([
         'w-full' => $productEditSurface === 'page',
         'fixed inset-0 z-[75] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]' => $productEditSurface !== 'page',
     ])
     data-auto-open="{{ $productEditHasErrors ? 'true' : 'false' }}">
    <div @class([
        'relative flex w-full flex-col overflow-hidden border bg-white',
        'max-h-[94vh] max-w-5xl rounded-3xl border-[#E2E8F0] shadow-2xl' => $productEditSurface !== 'page',
        'max-w-none w-full min-h-0 rounded-2xl border-slate-200/80 shadow-sm' => $productEditSurface === 'page' && $productEditPageNative,
        'max-w-5xl mx-auto min-h-0 rounded-3xl border-slate-200/90 shadow-md ring-1 ring-slate-900/[0.04]' => $productEditSurface === 'page' && ! $productEditPageNative,
    ])>
        @if (! ($productEditSurface === 'page' && $productEditPageNative))
            <div @class([
                'flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-6 py-4',
                'bg-gradient-to-r from-white to-slate-50/80' => $productEditSurface === 'page',
            ])>
                <div class="min-w-0">
                    @if ($productEditSurface === 'page')
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Catalog · Edit workspace</p>
                        <h2 class="mt-1 text-xl font-semibold text-[#0F172A] font-[Poppins] sm:text-2xl">Edit product</h2>
                        <p class="mt-1 text-xs text-[#64748B]">Save applies changes to this product in your active store. Cancel returns without saving.</p>
                    @else
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Product Actions</p>
                        <h2 class="mt-1 text-2xl font-medium text-[#0F172A] font-[Poppins]">Edit Product</h2>
                    @endif
                </div>
                @if ($productEditSurface === 'page' && ! empty($workspaceReturnProductId))
                    <a href="{{ route('products.show', ['product' => $workspaceReturnProductId]) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#475569] shadow-sm transition hover:bg-[#F8FAFC]" aria-label="Back to product workspace without saving">
                        Back
                    </a>
                @else
                    <button type="button" id="closeEditProductModal" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] transition hover:text-[#334155]" aria-label="Close edit product modal">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                @endif
            </div>
        @endif

        <div @class([
            'overflow-y-auto px-6 py-6',
            'sm:px-8 sm:py-8' => $productEditSurface === 'page',
            'border-t border-slate-100/90 bg-slate-50/20' => $productEditSurface === 'page' && $productEditPageNative,
            'bg-slate-50/40' => $productEditSurface === 'page' && ! $productEditPageNative,
        ])>
            @if ($productEditHasErrors)
                <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="editProductForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="_open_edit_product_modal" value="1">
                <input type="hidden" name="_edit_product_id" id="edit_product_id" value="{{ old('_edit_product_id', '') }}">
                @if ($workspaceReturnProductId)
                    <input type="hidden" name="_workspace_return_product_id" value="{{ old('_workspace_return_product_id', (string) $workspaceReturnProductId) }}">
                @endif
                <input type="hidden" name="product_type" id="edit_product_type_value" value="{{ old('product_type', 'physical') }}">
                <input type="hidden" name="custom_product_type" id="edit_product_custom_type_hidden" value="{{ old('custom_product_type', '') }}">
                <input type="hidden" name="inventory_stock_allocation_mode" id="edit_inventory_stock_allocation_mode" value="{{ old('inventory_stock_allocation_mode', 'manual') }}">
                <input type="hidden" name="inventory_apply_same_stock" id="edit_inventory_apply_same_stock" value="{{ old('inventory_apply_same_stock', '') }}">
                <input type="hidden" name="inventory_split_total" id="edit_inventory_split_total" value="{{ old('inventory_split_total', '') }}">

                @if ($productEditSurface === 'page' && $productEditPageNative)
                    <nav id="catalog-editor-section-nav" class="flex flex-wrap gap-2 rounded-2xl border border-slate-200/80 bg-white px-3 py-2.5 text-xs font-semibold text-[#475569] shadow-sm" aria-label="Jump to editor sections">
                        <a href="#catalog-edit-section-basics" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Basics</a>
                        <a href="#catalog-edit-section-media" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Media</a>
                        <a href="#catalog-edit-section-pricing" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Pricing</a>
                        <a href="#catalog-edit-section-organization" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Organization</a>
                        <a href="#catalog-edit-section-additional-details" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Additional details</a>
                        <a href="#catalog-edit-section-option-groups" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Variants</a>
                        <a href="#catalog-edit-section-inventory" class="rounded-lg px-2.5 py-1 hover:bg-[#F1F5F9]">Inventory</a>
                    </nav>
                @endif

                <div class="{{ $editSectionClass }}" @if ($productEditSurface === 'page' && $productEditPageNative) id="catalog-edit-section-basics" @endif>
                    <div @class(['mb-6 border-b border-slate-100 pb-4' => $productEditSurface === 'page' && $productEditPageNative, 'mb-6' => ! ($productEditSurface === 'page' && $productEditPageNative)])>
                        <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl">{{ ($productEditSurface === 'page' && $productEditPageNative) ? 'Product basics' : 'Product details' }}</h3>
                        @if ($productEditSurface === 'page' && $productEditPageNative)
                            <p class="mt-1 text-xs text-[#64748B]">Name, type, identifiers, and pricing defaults for your active store.</p>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Active Store</label>
                            <div class="rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A]">
                                {{ $selectedStore?->name ?? $currentStore?->name ?? 'No active store selected' }}
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">This product can only be edited inside your current active store.</p>
                        </div>
                        <div>
                            <label for="edit_product_type_select" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product behavior (type)</label>
                            <select id="edit_product_type_select" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                @foreach (['physical', 'digital', 'service', 'subscription', 'virtual'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                                <option value="custom">Custom Type</option>
                            </select>
                            <div id="editProductCustomTypeWrap" class="mt-3 hidden">
                                <input id="edit_product_custom_type" type="text" value="{{ old('custom_product_type', '') }}" placeholder="e.g. Home Decor" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">Fulfillment / sales behavior—not the catalog category.</p>
                        </div>
                    </div>
                    @if ($productEditSurface === 'page' && $productEditPageNative)
                        <p id="catalog-edit-section-media" class="mt-6 scroll-mt-28 text-xs font-bold uppercase tracking-wide text-[#94A3B8]">Media</p>
                        <p class="mt-1 text-xs text-[#64748B]">Photos for this listing. Upload here first, then you can assign a photo to each variant row below.</p>
                    @endif
                    <div class="mt-4 grid grid-cols-1 gap-6">
                        <div>
                            <label for="edit_product_image" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">{{ ($productEditSurface === 'page' && $productEditPageNative) ? 'Product images' : 'Product Images' }}</label>
                            <input id="edit_product_image" name="product_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="w-full rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                            <div id="editExistingImageInputs"></div>
                            <div id="editProductImagePreview" class="mt-3 flex flex-wrap gap-2"></div>
                        </div>
                        <div>
                            <label for="edit_product_name" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product Name</label>
                            <input id="edit_product_name" name="name" type="text" value="{{ old('name', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                        </div>
                        <div>
                            <label for="edit_product_description" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Description</label>
                            <textarea id="edit_product_description" name="description" rows="3" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">{{ old('description', '') }}</textarea>
                        </div>
                    </div>
                    <div id="catalog-edit-section-pricing" @class(['mt-6 scroll-mt-28 grid grid-cols-1 gap-6 md:grid-cols-3' => $productEditSurface === 'page' && $productEditPageNative, 'mt-6 grid grid-cols-1 gap-6 md:grid-cols-3' => ! ($productEditSurface === 'page' && $productEditPageNative)])>
                        <div><label for="edit_product_sku" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base SKU</label><input id="edit_product_sku" name="sku" type="text" value="{{ old('sku', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                        <div><label for="edit_product_price" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base Price</label><input id="edit_product_price" name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                        <div><label for="edit_product_stock_alert" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Stock Alert</label><input id="edit_product_stock_alert" name="stock_alert" type="number" min="0" step="1" value="{{ old('stock_alert', '0') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                        @if ($productEditSurface === 'page' && $productEditPageNative)
                            <div id="catalog-edit-section-organization" class="md:col-span-3 mt-2 scroll-mt-28 border-t border-slate-100 pt-6">
                                <p class="text-xs font-bold uppercase tracking-wide text-[#94A3B8]">Organization</p>
                                <p class="mt-1 text-xs text-[#64748B]">Brand, categories, and tags help your team find this product in the catalog.</p>
                            </div>
                        @endif
                        @if ($catalogTaxonomyCategories->isNotEmpty())
                            <div class="md:col-span-3 rounded-xl border border-[#CCFBF1]/80 bg-[#F0FDFA]/40 p-4">
                                <label for="edit_product_category_ids" class="mb-2 block text-sm font-semibold text-[#0F766E] font-[Poppins]">Catalog categories</label>
                                <select id="edit_product_category_ids" name="category_ids[]" multiple size="5" class="w-full rounded-lg border border-[#99F6E4]/60 bg-white px-3 py-2 text-sm text-[#0F172A]">
                                    @foreach ($catalogTaxonomyCategories as $catOption)
                                        <option value="{{ $catOption->id }}" @selected(collect(old('category_ids', []))->contains($catOption->id))>{{ $catOption->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-[#115E59]/90">Main catalog groups—separate from product behavior (type) above.</p>
                            </div>
                        @endif
                        <div class="md:col-span-3">
                            <label for="edit_product_brand_id" class="mb-2 block text-sm font-medium text-[#64748B] font-[Poppins]">Brand <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                            <select id="edit_product_brand_id" name="brand_id" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                <option value="">No brand</option>
                                @foreach ($catalogBrands as $brandOption)
                                    <option value="{{ $brandOption->id }}" @selected((string) old('brand_id', '') === (string) $brandOption->id)>{{ $brandOption->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-[#94A3B8]">Optional vendor or label. Does not change which store owns the product.</p>
                        </div>
                        @if ($catalogTags->isNotEmpty())
                            <div class="md:col-span-3">
                                <label for="edit_product_tag_ids" class="mb-2 block text-sm font-medium text-[#64748B] font-[Poppins]">Tags <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                                <select id="edit_product_tag_ids" name="tag_ids[]" multiple size="4" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                    @foreach ($catalogTags as $tagOption)
                                        <option value="{{ $tagOption->id }}" @selected(collect(old('tag_ids', []))->contains($tagOption->id))>{{ $tagOption->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-[#94A3B8]">Quick labels like Featured or Sale—not your main catalog structure.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="{{ $editSectionClass }}" @if ($productEditSurface === 'page' && $productEditPageNative) id="catalog-edit-section-additional-details" @endif>
                    <input type="hidden" name="_custom_fields_editor" value="1">
                    <div @class(['mb-4 border-b border-slate-100 pb-4' => $productEditSurface === 'page' && $productEditPageNative, 'mb-4' => ! ($productEditSurface === 'page' && $productEditPageNative)])>
                        <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl scroll-mt-28">Additional details</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#64748B]"><span class="font-medium text-[#334155]">Additional details</span> are extra fields you choose and can edit anytime (supplier, material, origin, care notes, ingredients, internal references, and similar). They show under <span class="font-medium text-[#334155]">Additional product details</span> on the product workspace—separate from read-only columns kept from imports. Field names may use letters, numbers, dots, dashes, and underscores.</p>
                    </div>
                    <div id="editAdditionalDetailsBody" class="space-y-3"></div>
                    <button type="button" id="editAddAdditionalDetailRow" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">Add detail</button>
                </div>

                <div class="{{ $editSectionClass }}" @if ($productEditSurface === 'page' && $productEditPageNative) id="catalog-edit-section-option-groups" @endif>
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl scroll-mt-28">Option groups</h3>
                            <p class="mt-1 text-xs text-[#64748B]">Each group (for example Size or Color) lists the values shoppers can pick.</p>
                        </div>
                        <button id="editOpenVariationModal" type="button" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">Add option group</button>
                    </div>
                    <div id="editVariationHiddenInputs"></div>
                    <div id="editNoVariationState" class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">This product has one inventory row. Add option groups only if shoppers choose size, color, pack, or similar variations.</div>
                    <div id="editVariationTypesList" class="hidden space-y-4"></div>
                </div>

                <div class="{{ $editSectionClass }}" @if ($productEditSurface === 'page' && $productEditPageNative) id="catalog-edit-section-inventory" @endif>
                    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl scroll-mt-28">Sellable combinations (variants)</h3>
                            <p class="mt-1 text-xs text-[#64748B]">Each row is one combination of your option groups. Rows are built from option groups above.</p>
                        </div>
                    </div>
                    @if ($productEditSurface === 'page' && $productEditPageNative)
                        <p class="mb-3 text-xs text-[#64748B]">Variant photo: pick the same catalog images you added under <span class="font-medium text-[#334155]">Media</span> so each combination can show the right picture.</p>
                    @endif
                    <div class="mb-5 grid grid-cols-1 gap-3 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 md:grid-cols-4">
                        <div class="md:col-span-2"><p class="text-sm font-semibold text-[#0F172A]">Bulk price &amp; stock tools</p><p class="text-xs text-[#64748B]"><span class="font-medium text-[#334155]">Set each variant’s stock</span> applies the same quantity to every combination row (total on hand = quantity × number of rows). <span class="font-medium text-[#334155]">Split total</span> divides one total across rows (total on hand = the total you enter). Editing a row’s stock directly keeps <span class="font-medium text-[#334155]">manual</span> mode.</p></div>
                        <div><label for="editBulkPrice" class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label><input id="editBulkPrice" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div>
                        <div class="flex items-end gap-2"><div class="flex-1"><label for="editBulkStock" class="mb-1 block text-xs font-semibold text-[#64748B]">Stock (per row)</label><input id="editBulkStock" type="number" min="0" step="1" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><button id="editApplyBulkValues" type="button" class="rounded-lg bg-[#0052CC] px-3 py-2 text-sm font-semibold text-white">Apply to all rows</button></div>
                    </div>
                    <div id="editDistributeStockPanel" class="mb-5 hidden rounded-2xl border border-[#E0E7FF] bg-[#F8FAFF] p-4 md:flex md:flex-wrap md:items-end md:gap-3">
                        <div class="min-w-[12rem] flex-1">
                            <label for="editDistributeTotal" class="mb-1 block text-xs font-semibold text-[#64748B]">Split total inventory</label>
                            <input id="editDistributeTotal" type="number" min="0" step="1" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A]" placeholder="e.g. 90">
                            <p class="mt-1 text-[11px] text-[#64748B]">Divides this total evenly across all combination rows (remainder goes to the first rows).</p>
                        </div>
                        <button type="button" id="editDistributeEqualBtn" class="mt-3 inline-flex items-center justify-center rounded-lg border border-[#BFDBFE] bg-white px-4 py-2 text-sm font-semibold text-[#1D4ED8] hover:bg-[#EFF6FF] md:mt-0">Apply equal split</button>
                    </div>
                    <div id="editVariantRows" class="space-y-4"></div>
                </div>

                <div class="{{ $editSectionClass }}">
                    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between sm:gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0F172A] font-[Poppins] sm:text-xl">Variant combinations preview</h3>
                            <p class="mt-1 text-xs text-[#64748B]">Quick read-only check of SKUs, prices, and stock before you save.</p>
                        </div>
                        <span id="editPreviewCount" class="text-sm text-[#94A3B8]">0 rows</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b border-[#F1F5F9]"><tr><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Combination</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">SKU</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Price</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Stock</th></tr></thead><tbody id="editPreviewTableBody" class="divide-y divide-[#F1F5F9]"></tbody></table></div>
                </div>

                @if (! ($productEditSurface === 'page' && $productEditPageNative))
                    <div class="flex flex-col gap-4 border-t border-slate-200/90 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" id="openDeleteProductWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">Delete Product</button>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            @if ($productEditSurface === 'page' && ! empty($workspaceReturnProductId))
                                <a href="{{ route('products.show', ['product' => $workspaceReturnProductId]) }}" id="dismissEditProductModal" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
                            @else
                                <button type="button" id="dismissEditProductModal" class="rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</button>
                            @endif
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042a3]">{{ $productEditSurface === 'page' ? 'Save and return to workspace' : 'Save Changes' }}</button>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col gap-4 border-t border-slate-200/90 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" id="openDeleteProductWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">Delete Product</button>
                        <a href="{{ route('products.show', ['product' => $workspaceReturnProductId]) }}" id="dismissEditProductModal" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>

<div id="editVariationModal" class="fixed inset-0 z-[76] hidden items-center justify-center bg-[#0F172A]/60 p-4 backdrop-blur-[2px]">
    <div class="w-full max-w-[512px] overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#F1F5F9] px-6 py-4">
            <div><h3 class="text-lg font-semibold text-[#0F172A]">Add Variation Type</h3><p class="mt-0.5 text-xs text-[#64748B]">Define how customers will differentiate your items</p></div>
            <button type="button" id="closeEditVariationModal" class="text-[#94A3B8] hover:text-[#64748B]">X</button>
        </div>
        <div class="space-y-6 p-6">
            <div><label class="mb-2 block text-sm font-semibold text-[#334155]">Variation Name</label><input id="editVariationName" type="text" placeholder="e.g., Size" class="w-full rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm text-[#0F172A]"></div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#334155]">Option Values</label>
                <div class="rounded-lg border border-[#E2E8F0] px-3 py-3">
                    <div id="editVariationOptionChips" class="mb-2 flex flex-wrap gap-2"></div>
                    <input id="editVariationOptionInput" type="text" placeholder="Type a value and press Enter" class="w-full border-0 p-0 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-0">
                </div>
                <textarea id="editVariationOptions" rows="3" placeholder="S, M, L, XL" class="hidden"></textarea>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-[#F1F5F9] bg-[#F8FAFC] px-6 py-4">
            <button type="button" id="cancelEditVariationModal" class="px-4 py-2 text-sm font-semibold text-[#475569]">Cancel</button>
            <button type="button" id="submitEditVariationModal" class="rounded-lg bg-[#0052CC] px-5 py-2 text-sm font-bold text-white">Save Variation</button>
        </div>
    </div>
</div>

<div id="deleteProductWarningModal" class="fixed inset-0 z-[77] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]">
    <div class="w-full max-w-lg overflow-hidden rounded-3xl border border-[#FECACA] bg-white shadow-2xl">
        <div class="bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.18),_transparent_60%)] px-6 pb-4 pt-6">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm">!</div>
            <h3 class="mt-5 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Delete this product?</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">This action permanently removes the product, its variants, and uploaded images.</p>
        </div>
        <div class="px-6 pb-6 pt-2">
            <div class="rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4"><p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]">Warning</p><p class="mt-2 text-sm text-[#7F1D1D]">You are about to delete <span id="deleteProductName" class="font-bold"></span>. This cannot be undone.</p></div>
            <form id="deleteProductForm" method="POST" class="mt-6">
                @csrf
                @method('DELETE')
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelDeleteProduct" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Keep Product</button>
                    <button type="submit" class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.__additionalDetailKeyErrors = @json($additionalDetailKeyErrors);
(() => {
const editModal=document.getElementById('editProductModal'); if(!editModal) return;
const editSurfaceIsPage=editModal.dataset.surface==='page';
const closeEditProductButton=document.getElementById('closeEditProductModal');
const dismissEditProductEl=document.getElementById('dismissEditProductModal');
const closeButtons=[];
if(closeEditProductButton){closeButtons.push(closeEditProductButton);}
if(dismissEditProductEl&&dismissEditProductEl.tagName==='BUTTON'){closeButtons.push(dismissEditProductEl);}
const editButtons=[...document.querySelectorAll('.js-open-edit-product-modal:not([data-open-delete="true"])')];
const deleteButtons=[...document.querySelectorAll('.js-open-delete-product-modal')];
const editForm=document.getElementById('editProductForm'); const deleteForm=document.getElementById('deleteProductForm');
const deleteWarningModal=document.getElementById('deleteProductWarningModal'); const openDeleteWarning=document.getElementById('openDeleteProductWarning'); const cancelDeleteProduct=document.getElementById('cancelDeleteProduct'); const deleteProductName=document.getElementById('deleteProductName');
const editProductId=document.getElementById('edit_product_id'); const editTypeSelect=document.getElementById('edit_product_type_select'); const editTypeValue=document.getElementById('edit_product_type_value'); const editCustomTypeWrap=document.getElementById('editProductCustomTypeWrap'); const editCustomType=document.getElementById('edit_product_custom_type'); const editCustomTypeHidden=document.getElementById('edit_product_custom_type_hidden');
const editName=document.getElementById('edit_product_name'); const editDescription=document.getElementById('edit_product_description'); const editSku=document.getElementById('edit_product_sku'); const editPrice=document.getElementById('edit_product_price'); const editStockAlert=document.getElementById('edit_product_stock_alert'); const editBrandId=document.getElementById('edit_product_brand_id'); const editTagIds=document.getElementById('edit_product_tag_ids'); const editCategoryIds=document.getElementById('edit_product_category_ids'); const editImageInput=document.getElementById('edit_product_image'); const editImagePreview=document.getElementById('editProductImagePreview'); const editExistingImageInputs=document.getElementById('editExistingImageInputs');
const editVariationHiddenInputs=document.getElementById('editVariationHiddenInputs'); const editNoVariationState=document.getElementById('editNoVariationState'); const editVariationTypesList=document.getElementById('editVariationTypesList'); const editAddVariantRow=document.getElementById('editAddVariantRow'); const editVariantRows=document.getElementById('editVariantRows'); const editBulkPrice=document.getElementById('editBulkPrice'); const editBulkStock=document.getElementById('editBulkStock'); const editApplyBulkValues=document.getElementById('editApplyBulkValues'); const editPreviewCount=document.getElementById('editPreviewCount'); const editPreviewTableBody=document.getElementById('editPreviewTableBody');
const editAdditionalDetailsBody=document.getElementById('editAdditionalDetailsBody'); const editAddAdditionalDetailRow=document.getElementById('editAddAdditionalDetailRow');
const editVariationModal=document.getElementById('editVariationModal'); const editOpenVariationModal=document.getElementById('editOpenVariationModal'); const closeEditVariationModal=document.getElementById('closeEditVariationModal'); const cancelEditVariationModal=document.getElementById('cancelEditVariationModal'); const submitEditVariationModal=document.getElementById('submitEditVariationModal'); const editVariationName=document.getElementById('editVariationName'); const editVariationOptions=document.getElementById('editVariationOptions'); const editVariationOptionInput=document.getElementById('editVariationOptionInput'); const editVariationOptionChips=document.getElementById('editVariationOptionChips');
const openVariantPanels = new Set();
const defaultTypes=['physical','digital','service','subscription','virtual']; let editCatalogImages=[]; let currentProduct=null; let editVariationTypes=[]; let editRows=[]; let editingVariationIndex=null; let retainedExistingImages=[]; let selectedEditImages=[]; let editVariationOptionTags=[];
const escapeHtml=(v)=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
const readAdditionalDetailKeyErrors=()=>{try{const raw=window.__additionalDetailKeyErrors;return raw&&typeof raw==='object'?raw:{}}catch(e){return{}}};
const renderAdditionalDetailRows=(rows)=>{if(!editAdditionalDetailsBody)return;const keyErrors=readAdditionalDetailKeyErrors();const data=(Array.isArray(rows)&&rows.length)?rows:[{key:'',type:'text',value:''}];editAdditionalDetailsBody.innerHTML=data.map((row,i)=>{const err=(keyErrors&&keyErrors[i])||(keyErrors&&keyErrors[String(i)])||'';const keyRing=err?' border-rose-300 ring-1 ring-rose-100':' border-[#E2E8F0]';return`<div data-additional-detail-row class="grid gap-3 rounded-xl border border-slate-200/90 bg-slate-50/40 p-4 md:grid-cols-12 md:items-start"><div class="md:col-span-3"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Field name</label><input data-field="key" type="text" name="custom_fields[${i}][key]" value="${escapeHtml(row.key||'')}" class="w-full rounded-lg border bg-white px-3 py-2 text-sm${keyRing}" placeholder="e.g. supplier_code" maxlength="128" autocomplete="off">${err?`<p class="mt-1 text-xs text-rose-600">${escapeHtml(err)}</p>`:''}</div><div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Type</label><select data-field="type" name="custom_fields[${i}][type]" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-2 py-2 text-sm"><option value="text"${(row.type||'text')==='text'?' selected':''}>Text</option><option value="number"${(row.type||'')==='number'?' selected':''}>Number</option><option value="boolean"${(row.type||'')==='boolean'?' selected':''}>Yes / No</option><option value="list"${(row.type||'')==='list'?' selected':''}>List</option></select></div><div class="md:col-span-6"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Value</label><input data-field="value" type="text" name="custom_fields[${i}][value]" value="${escapeHtml(row.value||'')}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm" placeholder="Lists: comma-separated"></div><div class="md:col-span-1 flex items-start justify-end pt-6 md:pt-7"><button type="button" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" data-remove-additional-detail title="Remove this row">Remove</button></div></div>`;}).join('');editAdditionalDetailsBody.querySelectorAll('[data-remove-additional-detail]').forEach((btn)=>btn.addEventListener('click',()=>{btn.closest('[data-additional-detail-row]')?.remove();if(!editAdditionalDetailsBody.querySelector('[data-additional-detail-row]')){renderAdditionalDetailRows([{key:'',type:'text',value:''}]);}}));};
editAddAdditionalDetailRow?.addEventListener('click',()=>{const current=[...editAdditionalDetailsBody.querySelectorAll('[data-additional-detail-row]')].map((row)=>({key:(row.querySelector('[data-field="key"]')?.value||'').trim(),type:(row.querySelector('[data-field="type"]')?.value)||'text',value:row.querySelector('[data-field="value"]')?.value??''}));current.push({key:'',type:'text',value:''});renderAdditionalDetailRows(current);});
const getEditVariantRowLimit=()=>editVariationTypes.reduce((total,variationType)=>total+((variationType.options||[]).length||0),0);
const getEditRowKey=(optionMap)=>Object.entries(optionMap||{}).sort(([left],[right])=>Number(left)-Number(right)).map(([variationIndex,optionIndex])=>`${variationIndex}:${optionIndex}`).join('|');
const buildEditRowsFromVariationTypes=(existingRows=[])=>{if(!editVariationTypes.length||editVariationTypes.some((variationType)=>!(variationType.options||[]).length)){return [];} const existingRowsByKey=new Map(existingRows.map((row)=>[getEditRowKey(row.option_map||{}),row])); const combinations=[]; const walk=(variationIndex,optionMap)=>{if(variationIndex>=editVariationTypes.length){combinations.push({...optionMap}); return;} (editVariationTypes[variationIndex].options||[]).forEach((_,optionIndex)=>{walk(variationIndex+1,{...optionMap,[variationIndex]:optionIndex});});}; walk(0,{}); return combinations.map((optionMap)=>{const existingRow=existingRowsByKey.get(getEditRowKey(optionMap)); return {id:existingRow?.id ?? '',option_map:optionMap,sku:(existingRow?.sku)||'',price:existingRow?.price ?? (editPrice.value||''),compare_at_price:existingRow?.compare_at_price ?? '',stock:existingRow?.stock ?? (editBulkStock.value||''),stock_alert:existingRow?.stock_alert ?? (editStockAlert.value||0),product_image_id:existingRow?.product_image_id ?? ''};});};
const syncType=()=>{const isCustom=editTypeSelect.value==='custom'; editCustomTypeWrap.classList.toggle('hidden',!isCustom); editCustomType.required=isCustom; editTypeValue.value=isCustom?(editCustomType.value.trim()||'custom'):editTypeSelect.value; editCustomTypeHidden.value=isCustom?(editCustomType.value.trim()||''):'';};
const syncSelectedFiles=(input,files)=>{if(!input) return; const transfer=new DataTransfer(); files.forEach((file)=>transfer.items.add(file)); input.files=transfer.files;};
const renderExistingImageInputs=()=>{editExistingImageInputs.innerHTML=retainedExistingImages.map((path)=>`<input type="hidden" name="existing_image_paths[]" value="${escapeHtml(path)}">`).join('');};
const syncEditCatalogImagesFromRetained=()=>{if(!currentProduct)return; const all=Array.isArray(currentProduct.catalog_images)?[...currentProduct.catalog_images]:[]; let next=[]; if(retainedExistingImages.length){next=all.filter((img)=>{const p=img&&img.image_path?String(img.image_path):''; return p!==''&&retainedExistingImages.includes(p);});} editCatalogImages=next; if(editRows&&editRows.length){renderVariantRows();}};
const renderEditImages=()=>{const existing=(currentProduct?.image_urls||[]).map((url,index)=>({kind:'existing',url,path:currentProduct?.image_paths?.[index]||''})).filter((item)=>retainedExistingImages.includes(item.path)); const selected=selectedEditImages.map((file,index)=>({kind:'new',url:URL.createObjectURL(file),name:file.name,index})); const items=[...existing,...selected]; if(!items.length){editImagePreview.innerHTML='<div class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#64748B]">No product images selected.</div>'; renderExistingImageInputs(); syncEditCatalogImagesFromRetained(); return;} editImagePreview.innerHTML=items.map((item,index)=>`<div class="group relative overflow-hidden rounded-2xl border border-[#D9E2EC] bg-white p-2 shadow-sm"><img src="${item.url}" alt="${escapeHtml(item.name||currentProduct?.name||'Product image')}" class="h-16 w-16 rounded-xl object-cover border border-[#E2E8F0]"><button type="button" class="edit-remove-image absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0F172A]/70 text-xs font-bold text-white" data-kind="${item.kind}" data-index="${item.kind==='existing'?index:item.index}" aria-label="Remove image">&times;</button><p class="mt-2 max-w-[64px] truncate text-[11px] text-[#64748B]">${escapeHtml(item.name||(item.path?.split('/').pop()||'Saved image'))}</p></div>`).join(''); renderExistingImageInputs(); document.querySelectorAll('.edit-remove-image').forEach((button)=>button.addEventListener('click',()=>{if(button.dataset.kind==='existing'){const existingIndex=Number(button.dataset.index); const existingItems=(currentProduct?.image_paths||[]).filter((path)=>retainedExistingImages.includes(path)); retainedExistingImages=existingItems.filter((_,idx)=>idx!==existingIndex);}else{selectedEditImages=selectedEditImages.filter((_,idx)=>idx!==Number(button.dataset.index)); syncSelectedFiles(editImageInput,selectedEditImages);} renderEditImages();})); syncEditCatalogImagesFromRetained();};
const syncEditVariationOptions=()=>{editVariationOptions.value=editVariationOptionTags.join(', ');};
const renderEditVariationOptionTags=()=>{editVariationOptionChips.innerHTML=editVariationOptionTags.map((tag,index)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(tag)}<button type="button" class="remove-edit-variation-tag leading-none text-[#94A3B8] hover:text-[#B42318]" data-index="${index}">&times;</button></span>`).join(''); document.querySelectorAll('.remove-edit-variation-tag').forEach((button)=>button.addEventListener('click',()=>{editVariationOptionTags=editVariationOptionTags.filter((_,index)=>index!==Number(button.dataset.index)); syncEditVariationOptions(); renderEditVariationOptionTags();}));};
const addEditVariationOptionTags=(rawValue)=>{const nextTags=String(rawValue||'').split(',').map((value)=>value.trim()).filter(Boolean); if(!nextTags.length) return; editVariationOptionTags=[...editVariationOptionTags,...nextTags]; syncEditVariationOptions(); renderEditVariationOptionTags(); if(editVariationOptionInput) editVariationOptionInput.value='';};
const openVariationEditor=(variationIndex=null)=>{editingVariationIndex=variationIndex; const variation=variationIndex===null?null:editVariationTypes[variationIndex]; editVariationName.value=variation?.name||''; editVariationOptionTags=[...(variation?.options||[])]; syncEditVariationOptions(); renderEditVariationOptionTags(); submitEditVariationModal.textContent=variation?'Update Variation':'Add Variation'; editVariationModal.classList.remove('hidden'); editVariationModal.classList.add('flex');};
const closeVariationEditor=()=>{editingVariationIndex=null; editVariationName.value=''; editVariationOptionTags=[]; syncEditVariationOptions(); renderEditVariationOptionTags(); submitEditVariationModal.textContent='Save Variation'; editVariationModal.classList.add('hidden'); editVariationModal.classList.remove('flex');};
const renderVariationInputs=()=>{editVariationHiddenInputs.innerHTML=editVariationTypes.map((t,i)=>`<input type="hidden" name="variation_types[${i}][name]" value="${escapeHtml(t.name||'')}"><input type="hidden" name="variation_types[${i}][type]" value="${escapeHtml(t.type||'select')}">${(t.options||[]).map((o,j)=>`<input type="hidden" name="variation_types[${i}][options][${j}]" value="${escapeHtml(o)}">`).join('')}`).join('');};
const variantImageOptionLabel=(img,idx)=>{if(img&&img.picker_label)return String(img.picker_label);return 'Catalog photo '+(idx+1);};
const editDistributeStockPanel=document.getElementById('editDistributeStockPanel');const editDistributeTotal=document.getElementById('editDistributeTotal');const editDistributeEqualBtn=document.getElementById('editDistributeEqualBtn');
const editStockAllocMode=document.getElementById('edit_inventory_stock_allocation_mode');const editApplySameHidden=document.getElementById('edit_inventory_apply_same_stock');const editSplitTotalHidden=document.getElementById('edit_inventory_split_total');
const setManualStockAllocMode=()=>{if(editStockAllocMode)editStockAllocMode.value='manual';if(editApplySameHidden)editApplySameHidden.value='';if(editSplitTotalHidden)editSplitTotalHidden.value='';};
const setApplySameStockMode=(qty)=>{const n=Math.max(0,parseInt(String(qty),10)||0);if(editStockAllocMode)editStockAllocMode.value='apply_same_each';if(editApplySameHidden)editApplySameHidden.value=String(n);if(editSplitTotalHidden)editSplitTotalHidden.value='';};
const setSplitTotalMode=(total)=>{const n=Math.max(0,parseInt(String(total),10)||0);if(editStockAllocMode)editStockAllocMode.value='split_total';if(editSplitTotalHidden)editSplitTotalHidden.value=String(n);if(editApplySameHidden)editApplySameHidden.value='';};
const updateDistributePanelVisibility=()=>{if(!editDistributeStockPanel)return;editDistributeStockPanel.classList.toggle('hidden',!(editRows.length>1));};
const renderPreview=()=>{const previewRows=editRows.map((row)=>({label:editVariationTypes.map((v,i)=>{const s=row.option_map?.[i]; return s!==undefined&&s!==''?(v.options?.[s]||''):'';}).filter(Boolean).join(' / ')||'Single inventory row',sku:row.sku||'Auto-generated',price:row.price||editPrice.value||'',stock:row.stock||editBulkStock.value||''})); const rows=previewRows.length?previewRows:[{label:'Add option groups to create combinations',sku:'-',price:'-',stock:'-'}]; editPreviewTableBody.innerHTML=rows.map((r)=>`<tr><td class="px-2 py-4 text-[#0F172A]">${escapeHtml(r.label)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.sku)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.price)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.stock)}</td></tr>`).join(''); editPreviewCount.textContent=previewRows.length?`${previewRows.length} combination(s)`:'0 rows';};
const renderVariantRows=()=>{if(!editRows.length){editVariantRows.innerHTML='';updateDistributePanelVisibility();renderPreview();return;}const missingGalleryNote=(!editCatalogImages.length&&editRows.length)?'<div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" data-variant-gallery-empty-state><p class="font-semibold text-amber-900">Upload product images under Media first</p><p class="mt-1 text-xs text-amber-900/90">Then assign them to variants using each row’s variant photo control.</p></div>':'';editVariantRows.innerHTML=missingGalleryNote+editRows.map((row,rowIndex)=>{const idHidden=row.id?'<input type="hidden" name="variants['+rowIndex+'][id]" value="'+escapeHtml(String(row.id))+'">':'';const selectedOptions=Object.entries(row.option_map||{}).map(([variationIndex,optionIndex])=>'<span class="inline-flex items-center rounded-lg border border-[#DDE7F3] bg-white px-3 py-1.5 text-sm font-medium text-[#0F172A]">'+escapeHtml(editVariationTypes[Number(variationIndex)]?.name||'Option group')+': '+escapeHtml(editVariationTypes[Number(variationIndex)]?.options?.[Number(optionIndex)]||'')+'</span><input type="hidden" name="variants['+rowIndex+'][option_map]['+variationIndex+']" value="'+escapeHtml(optionIndex)+'">').join('');const imgOpts=['<option value="">No variant image</option>',...editCatalogImages.map((img,ix)=>'<option value="'+escapeHtml(String(img.id))+'" '+(String(row.product_image_id??'')===String(img.id)?'selected':'')+'>'+escapeHtml(variantImageOptionLabel(img,ix))+'</option>')].join('');const selImg=editCatalogImages.find((img)=>String(img.id)===String(row.product_image_id??''));const thumbHtml=selImg&&selImg.thumb_url?'<img src="'+escapeHtml(String(selImg.thumb_url))+'" alt="" class="mt-1 h-10 w-10 shrink-0 rounded-lg border border-[#E2E8F0] object-cover" width="40" height="40">':'<span class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-dashed border-[#CBD5E1] bg-white text-[10px] text-[#94A3B8]" title="No image selected">—</span>';const cfs=Array.isArray(row.custom_fields)&&row.custom_fields.length?row.custom_fields:[];const cfHtml=cfs.length?cfs.map((cf,i)=>'<div class="flex items-start gap-2 mb-2"><input type="text" data-cf-field="key" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][key]" value="'+escapeHtml(cf.key||'')+'" placeholder="Key" class="variant-cf-input w-1/3 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><select data-cf-field="type" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][type]" class="variant-cf-input w-1/4 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><option value="text" '+(cf.type==='text'?'selected':'')+'>Text</option><option value="number" '+(cf.type==='number'?'selected':'')+'>Number</option><option value="boolean" '+(cf.type==='boolean'?'selected':'')+'>Boolean</option><option value="list" '+(cf.type==='list'?'selected':'')+'>List</option></select><input type="text" data-cf-field="value" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][value]" value="'+escapeHtml(cf.value||'')+'" placeholder="Value" class="variant-cf-input flex-1 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><button type="button" class="remove-variant-cf text-xs text-rose-600 px-1 py-1 flex items-center justify-center border border-transparent hover:bg-rose-50 hover:border-rose-200 rounded" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" title="Remove field">&times;</button></div>').join(''):'<p class="text-xs text-[#64748B] mb-2">No additional details added.</p>';const isPanelOpen=openVariantPanels.has(String(rowIndex));return '<div class="space-y-4 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">'+idHidden+'<div class="flex flex-wrap gap-2">'+selectedOptions+'</div><div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3"><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">SKU</label><input type="text" name="variants['+rowIndex+'][sku]" value="'+escapeHtml(row.sku||'')+'" data-row-index="'+rowIndex+'" data-row-field="sku" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label><input type="number" min="0" step="0.01" name="variants['+rowIndex+'][price]" value="'+escapeHtml(row.price||'')+'" data-row-index="'+rowIndex+'" data-row-field="price" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Compare-at</label><input type="number" min="0" step="0.01" name="variants['+rowIndex+'][compare_at_price]" value="'+escapeHtml(row.compare_at_price??'')+'" data-row-index="'+rowIndex+'" data-row-field="compare_at_price" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Stock</label><input type="number" min="0" step="1" name="variants['+rowIndex+'][stock]" value="'+escapeHtml(row.stock||'')+'" data-row-index="'+rowIndex+'" data-row-field="stock" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Stock alert</label><input type="number" min="0" step="1" name="variants['+rowIndex+'][stock_alert]" value="'+escapeHtml(row.stock_alert??(editStockAlert.value||0))+'" data-row-index="'+rowIndex+'" data-row-field="stock_alert" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div class="md:col-span-2 lg:col-span-1"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Variant photo</label><div class="flex items-start gap-2">'+thumbHtml+'<select name="variants['+rowIndex+'][product_image_id]" data-row-index="'+rowIndex+'" data-row-field="product_image_id" class="edit-row-select min-w-0 flex-1 rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]">'+imgOpts+'</select></div></div></div><div class="mt-4 border-t border-[#E2E8F0] pt-4"><button type="button" class="toggle-variant-details flex items-center gap-2 text-xs font-semibold text-[#0052CC]" data-row-index="'+rowIndex+'"><span class="flex h-5 w-5 items-center justify-center rounded bg-[#EFF6FF] text-[#1E40AF]">'+(isPanelOpen?'-':'+')+'</span> Additional details</button><div class="mt-4 '+(isPanelOpen?'block':'hidden')+'">'+cfHtml+'<button type="button" class="add-variant-cf text-xs font-semibold text-[#0052CC]" data-row-index="'+rowIndex+'">+ Add field</button></div></div></div>';}).join('');document.querySelectorAll('.edit-row-input,.edit-row-select').forEach((i)=>{const fn=()=>{const r=Number(i.dataset.rowIndex);const f=i.dataset.rowField;editRows[r][f]=i.value;if(f==='stock'||f==='sku'||f==='price'||f==='compare_at_price'||f==='stock_alert'){setManualStockAllocMode();}if(f==='product_image_id'){renderVariantRows();return;}renderPreview();};i.addEventListener('input',fn);i.addEventListener('change',fn);});document.querySelectorAll('.toggle-variant-details').forEach((b)=>b.addEventListener('click',()=>{const idx=String(b.dataset.rowIndex);if(openVariantPanels.has(idx)){openVariantPanels.delete(idx);}else{openVariantPanels.add(idx);}renderVariantRows();}));document.querySelectorAll('.add-variant-cf').forEach((b)=>b.addEventListener('click',()=>{const idx=Number(b.dataset.rowIndex);if(!Array.isArray(editRows[idx].custom_fields))editRows[idx].custom_fields=[];editRows[idx].custom_fields.push({key:'',type:'text',value:''});renderVariantRows();}));document.querySelectorAll('.remove-variant-cf').forEach((b)=>b.addEventListener('click',()=>{const r=Number(b.dataset.rowIndex);const c=Number(b.dataset.cfIndex);editRows[r].custom_fields.splice(c,1);renderVariantRows();}));document.querySelectorAll('.variant-cf-input').forEach((i)=>{const fn=()=>{const r=Number(i.dataset.rowIndex);const c=Number(i.dataset.cfIndex);const f=i.dataset.cfField;editRows[r].custom_fields[c][f]=i.value;};i.addEventListener('input',fn);i.addEventListener('change',fn);});updateDistributePanelVisibility();renderPreview();};
const normalizeRowsAfterVariationChange=()=>{editRows=buildEditRowsFromVariationTypes(editRows);};
const renderVariationCards=()=>{ if(!editVariationTypes.length){editVariationTypesList.classList.add('hidden'); editNoVariationState.classList.remove('hidden'); return;} editVariationTypesList.classList.remove('hidden'); editNoVariationState.classList.add('hidden'); editVariationTypesList.innerHTML=editVariationTypes.map((t,i)=>`<div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5"><div class="mb-3 flex items-center justify-between gap-3"><div><span class="text-base font-medium text-[#0F172A]">Option group ${i+1}: ${escapeHtml(t.name||'Untitled')}</span><div class="mt-1 text-xs uppercase text-[#94A3B8]">Shopper choices · ${escapeHtml(t.type||'select')}</div></div><div class="flex items-center gap-2"><button type="button" class="edit-variation-type text-xs font-semibold text-[#0052CC]" data-variation-index="${i}">Edit</button><button type="button" class="remove-variation-type text-xs font-semibold text-[#B42318]" data-variation-index="${i}">Remove</button></div></div><div class="flex flex-wrap gap-2">${(t.options||[]).map((o,j)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium">${escapeHtml(o)}<button type="button" class="edit-remove-variation-option leading-none text-[#94A3B8] hover:text-[#B42318]" data-variation-index="${i}" data-option-index="${j}">&times;</button></span>`).join('')}</div></div>`).join(''); document.querySelectorAll('.edit-remove-variation-option').forEach((b)=>b.addEventListener('click',()=>{const vi=Number(b.dataset.variationIndex),oi=Number(b.dataset.optionIndex); if(!editVariationTypes[vi]) return; editVariationTypes[vi].options.splice(oi,1); if(!(editVariationTypes[vi].options||[]).length) editVariationTypes.splice(vi,1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows();})); document.querySelectorAll('.edit-variation-type').forEach((b)=>b.addEventListener('click',()=>openVariationEditor(Number(b.dataset.variationIndex)))); document.querySelectorAll('.remove-variation-type').forEach((b)=>b.addEventListener('click',()=>{editVariationTypes.splice(Number(b.dataset.variationIndex),1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows();}));};
const closeAll=()=>{if(editSurfaceIsPage){editVariationModal.classList.add('hidden');editVariationModal.classList.remove('flex');deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');return;}editModal.classList.add('hidden');editModal.classList.remove('flex');editVariationModal.classList.add('hidden');editVariationModal.classList.remove('flex');deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');document.body.classList.remove('overflow-hidden');};
const openEdit=(product)=>{currentProduct=product;const catalogPaths=(Array.isArray(product.catalog_images)?product.catalog_images:[]).map((img)=>img&&img.image_path?String(img.image_path):'').filter((p)=>p!=='');retainedExistingImages=JSON.parse(JSON.stringify(catalogPaths.length?catalogPaths:(product.image_paths||[])));editCatalogImages=Array.isArray(product.catalog_images)?[...product.catalog_images].filter((img)=>{const p=img&&img.image_path?String(img.image_path):'';return p!==''&&retainedExistingImages.includes(p);}):[];selectedEditImages=[];syncSelectedFiles(editImageInput,selectedEditImages);setManualStockAllocMode();editProductId.value=product.id||'';editForm.action=product.update_url;deleteForm.action=product.delete_url;editName.value=product.name||'';editDescription.value=product.description||'';editSku.value=product.sku||'';editPrice.value=product.base_price||'';editStockAlert.value=product.stock_alert||0;if(editBrandId){editBrandId.value=product.brand_id!=null&&product.brand_id!==''?String(product.brand_id):'';}if(editTagIds){const selected=new Set((product.tag_ids||[]).map((id)=>Number(id)));[...editTagIds.options].forEach((opt)=>{opt.selected=selected.has(Number(opt.value));});}if(editCategoryIds){const csel=new Set((product.category_ids||[]).map((id)=>Number(id)));[...editCategoryIds.options].forEach((opt)=>{opt.selected=csel.has(Number(opt.value));});}editBulkPrice.value=product.base_price||'';editBulkStock.value='';if(defaultTypes.includes(product.product_type)){editTypeSelect.value=product.product_type;editCustomType.value='';}else{editTypeSelect.value='custom';editCustomType.value=product.product_type||'';}editVariationTypes=JSON.parse(JSON.stringify(product.variation_types||[]));editRows=JSON.parse(JSON.stringify(product.variants||[]));if(!editRows.length&&editVariationTypes.length){editRows=buildEditRowsFromVariationTypes(editRows);}renderAdditionalDetailRows(Array.isArray(product.custom_fields)&&product.custom_fields.length?product.custom_fields:[{key:'',type:'text',value:''}]);renderEditImages();syncType();renderVariationInputs();renderVariationCards();renderVariantRows();if(editSurfaceIsPage){document.body.classList.remove('overflow-hidden');}else{editModal.classList.remove('hidden');editModal.classList.add('flex');document.body.classList.add('overflow-hidden');}};
const parseProductPayload=(button)=>{try{return button?.dataset?.product?JSON.parse(button.dataset.product):null;}catch(error){return null;}};
window.openProductEditModalFromElement=(button)=>{const product=parseProductPayload(button); if(product){openEdit(product);}};
window.openProductDeleteModalFromElement=(button)=>{const product=parseProductPayload(button); if(!product) return; openEdit(product); openDeleteWarning?.click();};
document.addEventListener('click',(event)=>{const target=event.target; if(!(target instanceof Element)) return; const editButton=target.closest('.js-open-edit-product-modal'); if(editButton instanceof HTMLButtonElement){const product=parseProductPayload(editButton); if(product){openEdit(product);} return;} const deleteButton=target.closest('.js-open-delete-product-modal'); if(deleteButton instanceof HTMLButtonElement){const product=parseProductPayload(deleteButton); if(!product) return; openEdit(product); openDeleteWarning?.click();}});
closeButtons.forEach((b)=>b?.addEventListener('click',closeAll)); editTypeSelect?.addEventListener('change',syncType); editCustomType?.addEventListener('input',syncType); editImageInput?.addEventListener('change',()=>{const incomingFiles=Array.from(editImageInput.files||[]); if(incomingFiles.length){selectedEditImages=[...selectedEditImages,...incomingFiles]; syncSelectedFiles(editImageInput,selectedEditImages);} renderEditImages();});
editVariationOptionInput?.addEventListener('keydown',(event)=>{if(event.key==='Enter'||event.key===','){event.preventDefault(); addEditVariationOptionTags(editVariationOptionInput.value);}});
editVariationOptionInput?.addEventListener('blur',()=>{if(editVariationOptionInput.value.trim()){addEditVariationOptionTags(editVariationOptionInput.value);}});
editOpenVariationModal?.addEventListener('click',()=>openVariationEditor());
[closeEditVariationModal,cancelEditVariationModal].forEach((b)=>b?.addEventListener('click',closeVariationEditor));
submitEditVariationModal?.addEventListener('click',()=>{addEditVariationOptionTags(editVariationOptionInput?.value||''); const name=editVariationName.value.trim(),type='select',options=editVariationOptions.value.split(',').map((v)=>v.trim()).filter(Boolean); if(!name||!options.length){alert('Please enter a variation name and at least one option.'); return;} if(editingVariationIndex===null){editVariationTypes.push({name,type,options});}else{editVariationTypes[editingVariationIndex]={...editVariationTypes[editingVariationIndex],name,type,options};} normalizeRowsAfterVariationChange(); closeVariationEditor(); renderVariationInputs(); renderVariationCards(); renderVariantRows();});
editApplyBulkValues?.addEventListener('click',()=>{editRows=editRows.map((r)=>({...r,price:editBulkPrice.value||r.price}));const q=editBulkStock.value;if(q!==''&&editRows.length){const amt=String(q);editRows=editRows.map((r)=>({...r,stock:amt}));setApplySameStockMode(q);}else{setManualStockAllocMode();}renderVariantRows();});
editDistributeEqualBtn?.addEventListener('click',()=>{const raw=editDistributeTotal?.value||'';const total=parseInt(String(raw),10);if(Number.isNaN(total)||total<0){window.alert('Enter a whole number total to split across rows.');return;}const n=editRows.length;if(n<2){window.alert('Add option groups so there are at least two combination rows.');return;}setSplitTotalMode(total);let rem=total%n;const base=Math.floor(total/n);editRows=editRows.map((r)=>{const add=rem>0?1:0;if(rem>0)rem--;return{...r,stock:String(base+add)};});renderVariantRows();});
openDeleteWarning?.addEventListener('click',()=>{if(!currentProduct) return; deleteProductName.textContent=currentProduct.name||'this product'; deleteWarningModal.classList.remove('hidden'); deleteWarningModal.classList.add('flex');});
cancelDeleteProduct?.addEventListener('click',()=>{deleteWarningModal.classList.add('hidden'); deleteWarningModal.classList.remove('flex');});
renderEditVariationOptionTags();
if(editSurfaceIsPage&&typeof window.__workspaceEditInitialPayload!=='undefined'&&window.__workspaceEditInitialPayload){openEdit(window.__workspaceEditInitialPayload);delete window.__workspaceEditInitialPayload;}
else if(editModal.dataset.autoOpen==='true'&&!editSurfaceIsPage){editModal.classList.remove('hidden');editModal.classList.add('flex');document.body.classList.add('overflow-hidden');syncType();}
})();
</script>
