@php
    $productEditSurface = $productEditSurface ?? 'modal';
    $productEditPageNative = $productEditPageNative ?? false;
    $productCreateMode = $productCreateMode ?? false;
    $productCreateForm = (bool) $productCreateMode;
    $productCreateMode = (bool) ($productWizardMode ?? $productCreateMode);
    $productEditHasErrors = $productCreateMode
        ? false
        : (($productEditSurface === 'page')
            ? $errors->any()
            : (old('_open_edit_product_modal') && $errors->any()));
    $catalogBrands = $catalogBrands ?? collect();
    $catalogTags = $catalogTags ?? collect();
    $catalogTaxonomyCategories = $catalogTaxonomyCategories ?? collect();
    $catalogAttributes = $catalogAttributes ?? collect();
    $canManageCategories = (bool) ($canManageCategories ?? false);
    $canManageBrands = (bool) ($canManageBrands ?? false);
    $canManageTags = (bool) ($canManageTags ?? false);
    $canManageCatalogTools = $canManageCategories || $canManageBrands || $canManageTags;
    $workspaceReturnProductId = $workspaceReturnProductId ?? null;
    $additionalDetailKeyErrors = [];
    if ($errors->any()) {
        foreach ($errors->getMessages() as $field => $messages) {
            if (preg_match('/^custom_fields\.(\d+)\.key$/', (string) $field, $m) && $messages !== []) {
                $additionalDetailKeyErrors[(int) $m[1]] = (string) ($messages[0] ?? '');
            }
        }
    }
    $catalogAttributesForEdit = $catalogAttributes->map(function ($attribute) {
        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'display_type' => $attribute->display_type,
            'is_filterable' => (bool) $attribute->is_filterable,
            'terms' => $attribute->terms->map(fn ($term) => [
                'id' => $term->id,
                'name' => $term->name,
                'swatch_value' => $term->swatch_value,
            ])->values()->all(),
        ];
    })->values()->all();
    $productTypeBehaviorsForEdit = collect(\App\Support\ProductTypeBehavior::types())
        ->mapWithKeys(fn ($type) => [$type => \App\Support\ProductTypeBehavior::behaviorFor($type)])
        ->all();
    $isPageNative = $productEditSurface === 'page' && $productEditPageNative;
    $editSectionClass = $productCreateMode
        ? 'pf-section'
        : ($isPageNative
            ? 'product-edit-form-card rounded-2xl border border-slate-200/80 bg-white px-5 py-6 sm:px-7 sm:py-7'
            : 'rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7');
@endphp

<div id="editProductModal"
     data-surface="{{ $productEditSurface }}"
     @class([
         'w-full' => $productEditSurface === 'page',
         'ui-modal-shell ui-modal-shell--nested hidden' => $productEditSurface !== 'page',
     ])
     data-auto-open="{{ $productEditHasErrors ? 'true' : 'false' }}">
    <div @class([
        'relative flex w-full flex-col',
        'overflow-hidden border bg-white ui-modal-panel ui-modal-panel--2xl' => $productEditSurface !== 'page',
        'max-w-none w-full min-h-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm' => $isPageNative && ! $productCreateMode,
        'ui-modal-panel ui-modal-panel--full mx-auto min-h-0 overflow-hidden border bg-white' => $productEditSurface === 'page' && ! $productEditPageNative,
        'w-full bg-transparent' => $productCreateMode,
    ])>
        @if (! ($productEditSurface === 'page' && $productEditPageNative))
            <div @class([
                'flex items-center justify-between gap-3 border-b border-[#E2E8F0] px-6 py-4',
                'bg-gradient-to-r from-white to-slate-50/80' => $productEditSurface === 'page',
            ])>
                <div class="min-w-0">
                    @if ($productEditSurface === 'page')
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Catalog · Edit workspace</p>
                        <h2 class="mt-1 text-xl font-semibold text-[#0F172A] sm:text-2xl">Edit product</h2>
                        <p class="mt-1 text-xs text-[#64748B]">Save applies changes to this product in your active store. Cancel returns without saving.</p>
                    @else
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Product Actions</p>
                        <h2 class="mt-1 text-2xl font-medium text-[#0F172A]">Edit Product</h2>
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
            'min-h-0 flex-1 overflow-y-auto px-6 py-6' => ! $productCreateMode,
            'sm:px-8 sm:py-8' => $productEditSurface === 'page' && ! $productCreateMode,
            'border-t border-slate-100/90 bg-slate-50/20' => $isPageNative && ! $productCreateMode,
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

            @php
                $editFormAction = null;
                if ($productCreateForm) {
                    $editFormAction = route('product.store');
                } else {
                    $editTargetId = old('_edit_product_id', $workspaceReturnProductId);
                    if (filled($editTargetId)) {
                        $editFormAction = route('product.update', ['productId' => $editTargetId]);
                    }
                }
            @endphp
            <form id="editProductForm" method="POST" enctype="multipart/form-data" class="space-y-6" @if ($editFormAction) action="{{ $editFormAction }}" @endif data-product-store-url="{{ route('product.store') }}" data-product-update-url-template="{{ route('product.update', ['productId' => '__PRODUCT_ID__']) }}">
                @csrf
                @unless ($productCreateForm)
                    @method('PUT')
                    <input type="hidden" name="_open_edit_product_modal" value="1">
                    <input type="hidden" name="_edit_product_id" id="edit_product_id" value="{{ old('_edit_product_id', $workspaceReturnProductId ? (string) $workspaceReturnProductId : '') }}">
                    @if ($workspaceReturnProductId)
                        <input type="hidden" name="_workspace_return_product_id" value="{{ old('_workspace_return_product_id', (string) $workspaceReturnProductId) }}">
                    @endif
                @else
                    <input type="hidden" name="_full_workspace_create" value="1">
                    <input type="hidden" name="_edit_product_id" id="edit_product_id" value="">
                @endunless
                <input type="hidden" name="_save_as_draft" id="edit_save_as_draft" value="">
                <input type="hidden" name="_draft_leave_to" id="edit_draft_leave_to" value="">
                <input type="hidden" name="_custom_fields_editor" value="1">
                <input type="hidden" name="product_type" id="edit_product_type_value" value="{{ old('product_type', 'physical') }}">
                <input type="hidden" name="custom_product_type" id="edit_product_custom_type_hidden" value="{{ old('custom_product_type', '') }}">
                <input type="hidden" name="inventory_stock_allocation_mode" id="edit_inventory_stock_allocation_mode" value="{{ old('inventory_stock_allocation_mode', 'manual') }}">
                <input type="hidden" name="inventory_apply_same_stock" id="edit_inventory_apply_same_stock" value="{{ old('inventory_apply_same_stock', '') }}">
                <input type="hidden" name="inventory_split_total" id="edit_inventory_split_total" value="{{ old('inventory_split_total', '') }}">
                <input type="hidden" name="inventory_variant_stock_mode" value="split_total">
                <input type="hidden" name="bulk_price" id="edit_create_bulk_price" value="{{ old('bulk_price', old('base_price', '')) }}">
                <input type="hidden" name="bulk_stock" id="edit_create_bulk_stock" value="{{ old('bulk_stock', '0') }}">

                <div @class([$editSectionClass, 'pf-basics' => $isPageNative]) @if ($isPageNative) id="catalog-edit-section-basics" data-product-edit-section @endif>
                    <div @class(['mb-6 border-b border-slate-100 pb-4' => $isPageNative, 'mb-6' => ! $isPageNative])>
                        <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">Product details</h3>
                        @if ($isPageNative)
                            <p class="mt-1 text-xs text-[#64748B]">Name, description, and how this product is sold in your store.</p>
                        @endif
                    </div>
                    <div @class(['grid grid-cols-1 gap-6 md:grid-cols-2', 'pf-row-meta' => $isPageNative])>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#334155]">Active Store</label>
                            <div class="rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A]">
                                {{ $selectedStore?->name ?? $currentStore?->name ?? 'No active store selected' }}
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">{{ $productCreateForm ? 'This product will be created in your currently active store. Use the sidebar switcher if you need a different store.' : 'This product can only be edited inside your current active store.' }}</p>
                        </div>
                        <div>
                            <label for="edit_product_type_select" class="mb-2 block text-sm font-medium text-[#334155]">How is this product sold?</label>
                            <select id="edit_product_type_select" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                @foreach (['physical', 'digital', 'service', 'subscription', 'virtual'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                                <option value="__custom__">Other / Custom</option>
                            </select>
                            <div id="edit_custom_product_type_wrap" class="hidden mt-3 space-y-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                                <label for="edit_custom_product_type_input" class="block text-xs font-semibold text-[#334155]">Custom product label</label>
                                <input id="edit_custom_product_type_input" type="text" maxlength="80" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]" placeholder="e.g. Menu item, Warranty, Membership">
                                <div>
                                    <label for="edit_custom_product_type_behavior" class="mb-1 block text-xs font-semibold text-[#334155]">How should this custom type behave?</label>
                                    <select id="edit_custom_product_type_behavior" name="custom_product_type_behavior" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]">
                                        <option value="physical">Ships like a physical product</option>
                                        <option value="digital">Delivered digitally</option>
                                        <option value="service">Sold as a service</option>
                                        <option value="virtual">Virtual / no shipping</option>
                                        <option value="subscription">Subscription</option>
                                    </select>
                                </div>
                            </div>
                            <p id="editProductTypeBehaviorHelp" class="mt-2 text-xs text-[#64748B]">Product behavior controls shipping, inventory, and future fulfillment. Category controls where the item appears in your catalog.</p>
                        </div>
                    </div>
                    @if ($isPageNative)
                        <div class="grid grid-cols-1 gap-6 pf-row-identity">
                            <div>
                                <label for="edit_product_name" class="mb-2 block text-sm font-medium text-[#334155]">
                                    Product Name <span class="pf-req" aria-hidden="true">*</span>
                                </label>
                                <input id="edit_product_name" name="name" type="text" value="{{ old('name', '') }}" placeholder="What are you selling? e.g. Cotton crew neck t-shirt" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                                <p class="mt-1.5 text-xs text-[#64748B]">Shoppers see this name on your website and in search. Be specific.</p>
                            </div>
                            <div>
                                <x-catalog.product-description-field
                                    id="edit_product_description"
                                    name="description"
                                    :value="old('description', '')"
                                    :rows="12"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="{{ $editSectionClass }}" id="catalog-edit-section-media" data-product-edit-section>
                        <div class="mb-6 border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">Images @if ($productCreateMode)<span class="pf-req" aria-hidden="true">*</span>@endif</h3>
                            <p class="mt-1 text-xs text-[#64748B]">Add listing photos, drag to set their order, and click a photo to view it. The first photo is what shoppers see first. Variants can use several of these photos, or you can add more from the variant table.</p>
                        </div>
                    @endif
                    <div @class(['grid grid-cols-1 gap-6' => $isPageNative, 'mt-4 grid grid-cols-1 gap-6' => ! $isPageNative])>
                        <div class="product-image-uploader" data-product-image-uploader>
                            <label for="edit_product_image" class="sr-only">Upload product images</label>
                            <input id="edit_product_image" name="product_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="sr-only">
                            <div
                                data-image-dropzone
                                role="button"
                                tabindex="0"
                                class="product-image-dropzone"
                            >
                                <span class="product-image-dropzone-icon" aria-hidden="true">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                                        <path d="M12 16V4m0 0l-3.5 3.5M12 4l3.5 3.5M4 16.5V18a2 2 0 002 2h12a2 2 0 002-2v-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <p class="product-image-dropzone-title">Drop photos here or click to browse</p>
                                <p class="product-image-dropzone-help">{{ \App\Support\ProductCatalogImageLimits::helpText() }}</p>
                                <span class="product-image-dropzone-action">Choose photos</span>
                            </div>
                            <div id="editExistingImageInputs"></div>
                            <div id="editImageOrderInputs"></div>
                            <div id="editProductImagePreview" class="product-image-gallery" hidden></div>
                            <p data-image-upload-status class="mt-2 text-xs text-[#64748B]">No photos yet. The first photo becomes the main listing image.</p>
                            <p data-image-upload-error class="mt-2 hidden text-xs font-medium text-rose-600" role="alert"></p>
                        </div>
                        @unless ($isPageNative)
                            <div>
                                <label for="edit_product_name" class="mb-2 block text-sm font-medium text-[#334155]">Product Name</label>
                                <input id="edit_product_name" name="name" type="text" value="{{ old('name', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                            </div>
                            <div>
                                <x-catalog.product-description-field
                                    id="edit_product_description"
                                    name="description"
                                    :value="old('description', '')"
                                    :rows="12"
                                />
                            </div>
                        @endunless
                    </div>
                    @if ($isPageNative)
                    </div>

                    <div class="{{ $editSectionClass }}" id="catalog-edit-section-pricing" data-product-edit-section>
                        <div class="mb-6 border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">{{ $productCreateMode ? 'Price & stock' : 'Price & inventory' }}</h3>
                            <p class="mt-1 text-xs text-[#64748B]">{{ $productCreateMode
                                ? 'Choose how this product is sold, then set price and stock. Variants inherit these defaults unless you override a row.'
                                : 'Choose how this product is sold, then set price and stock. Variants inherit these defaults unless you override a row.' }}</p>
                        </div>
                    @endif
                    @if ($isPageNative)
                        <div id="editSellingSetup" class="selling-setup">
                            <p class="selling-setup-kicker">Selling setup</p>
                            <div class="selling-setup-switch" role="group" aria-label="Selling setup">
                                <button type="button" data-selling-setup="single" aria-pressed="true">Single item</button>
                                <button type="button" data-selling-setup="multiple" aria-pressed="false">Multiple variants</button>
                            </div>
                            <p class="selling-setup-help" data-selling-copy="single">This product is sold as one item. Shoppers do not choose a size or color.</p>
                            <p class="selling-setup-help hidden" data-selling-copy="multiple">This product is the catalog listing. Each variant is what you sell and stock.</p>
                        </div>
                    @endif
                    <div @class(['grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3' => $isPageNative, 'mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4' => ! $isPageNative]) @unless ($isPageNative) id="catalog-edit-section-pricing" @endunless>
                        <div>
                            <label for="edit_product_sku" class="mb-2 block text-sm font-medium text-[#334155]">
                                <span data-selling-label="single">Product SKU</span>
                                <span data-selling-label="multiple" class="hidden">Product reference (parent SKU)</span>
                            </label>
                            <input id="edit_product_sku" name="sku" type="text" value="{{ old('sku', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                            <p class="mt-1 text-xs text-[#64748B]" data-selling-help="sku-single">Unique code for this product.</p>
                            <p class="mt-1 hidden text-xs text-[#64748B]" data-selling-help="sku-multiple">Used to generate variant SKUs. Each sellable version also has its own SKU in the table.</p>
                        </div>
                        <div>
                            <label for="edit_product_price" class="mb-2 block text-sm font-medium text-[#334155]">
                                <span data-selling-label="single">Selling price</span>
                                <span data-selling-label="multiple" class="hidden">Default price</span>
                                @if ($productCreateMode)<span class="pf-req" aria-hidden="true">*</span>@endif
                            </label>
                            <input id="edit_product_price" name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                            <p class="mt-1 text-xs text-[#64748B]" data-selling-help="price-single">The price customers pay.</p>
                            <p class="mt-1 hidden text-xs text-[#64748B]" data-selling-help="price-multiple">Variants inherit these defaults unless overridden.</p>
                        </div>
                        <div>
                            <label for="edit_product_compare_at" class="mb-2 block text-sm font-medium text-[#334155]">
                                <span data-selling-label="single">Compare-at price</span>
                                <span data-selling-label="multiple" class="hidden">Default compare-at price</span>
                                <span class="font-normal text-[#94A3B8]">(optional)</span>
                            </label>
                            <input id="edit_product_compare_at" type="number" min="0" step="0.01" value="" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" inputmode="decimal" autocomplete="off">
                            <p class="mt-1 text-xs text-[#64748B]" data-selling-help="compare-single">Shown as a crossed-out price when it is higher than the selling price.</p>
                            <p class="mt-1 hidden text-xs text-[#64748B]" data-selling-help="compare-multiple">Variants inherit this unless you set a different compare-at on a row.</p>
                        </div>
                        <div id="editSimpleStockWrap">
                            <label for="edit_product_stock" class="mb-2 block text-sm font-medium text-[#334155]">Stock quantity @if ($productCreateMode)<span class="pf-req" aria-hidden="true">*</span>@endif</label>
                            <input id="edit_product_stock" type="number" min="0" step="1" value="{{ old('bulk_stock', '0') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" inputmode="numeric" autocomplete="off" data-simple-stock-input="1">
                            <p class="mt-1 text-xs text-[#64748B]">Total units available to sell.</p>
                        </div>
                        <div id="editMultiStockHint" class="hidden rounded-lg border border-[#D8E8E1] bg-[#F4FBF8] px-4 py-3">
                            <p class="text-sm font-medium text-[#0A4335]">Stock is set on each variant</p>
                            <p class="mt-1 text-xs text-[#64748B]">Enter quantity on each sellable row in the table.</p>
                        </div>
                        <div id="editSimpleStockAlertWrap">
                            <label for="edit_product_stock_alert" class="mb-2 block text-sm font-medium text-[#334155]">Low stock alert</label>
                            <input id="edit_product_stock_alert" name="stock_alert" type="number" min="0" step="1" value="{{ old('stock_alert', '0') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" inputmode="numeric" autocomplete="off">
                            <p class="mt-1 text-xs text-[#64748B]">Warn when stock reaches this number. Does not change how many you have.</p>
                        </div>
                        <div id="editMultiStockAlertHint" class="hidden rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                            <p class="text-sm font-medium text-[#0F172A]">Alerts are per variant</p>
                            <p class="mt-1 text-xs text-[#64748B]">Set a low-stock alert on each sellable row.</p>
                        </div>
                        @if ($isPageNative)
                            <div id="editMultiDefaultsSummary" class="hidden rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 lg:col-span-3">
                                <p class="text-sm font-medium text-[#0F172A]">This product is not sold separately</p>
                                <p class="mt-1 text-xs text-[#64748B]">Shoppers buy the variants in the table. Inventory is tracked on each variant. Total stock: <span id="editMultiTotalStockLabel" class="font-semibold tabular-nums text-[#0F172A]">0</span>.</p>
                            </div>
                            <div id="editStandardBaseWrap" class="hidden rounded-xl border border-[#D4E3FF] bg-[#F8FAFF] px-4 py-3 lg:col-span-3">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input id="editIncludeStandardBase" type="checkbox" class="mt-1 rounded border-[#CBD5E1] accent-[#0052CC]">
                                    <span>
                                        <span class="block text-sm font-semibold text-[#0F172A]">Also sell a standard / base version</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-[#64748B]">Adds a Standard / Base row you can stock, using the same inventory as your other variants. Turn this on only if shoppers should also buy a version without a size or color choice. If that item is a different product, create it separately.</span>
                                    </span>
                                </label>
                            </div>
                        @endif
                        @unless ($isPageNative)
                        <div class="md:col-span-3">
                            @include('user_view.partials.product_taxable_control', [
                                'taxSetting' => $taxSetting ?? $selectedStore?->taxSetting,
                                'inputId' => 'edit_product_is_taxable',
                                'checkedOverride' => isset($product) ? (bool) $product->is_taxable : null,
                            ])
                        </div>
                        @php
                            $editFallbackWeight = isset($shippingPreferences['fallback_item_weight']) && is_numeric($shippingPreferences['fallback_item_weight'])
                                ? (float) $shippingPreferences['fallback_item_weight']
                                : null;
                            $editWeightUnit = $shippingPreferences['weight_unit'] ?? 'LB';
                        @endphp
                        <div id="editShippingWeightWrap" class="md:col-span-3 hidden rounded-xl border border-[#E2E8F0] bg-white p-4"
                            data-has-fallback="{{ $editFallbackWeight !== null && $editFallbackWeight > 0 ? '1' : '0' }}"
                            data-fallback-weight="{{ $editFallbackWeight !== null ? number_format($editFallbackWeight, 3, '.', '') : '' }}"
                            data-weight-unit="{{ $editWeightUnit }}">
                            <p class="text-sm font-semibold text-[#0F172A]">Shipping weight</p>
                            <p id="editShippingWeightHelp" class="mt-1 text-xs text-[#64748B]">Used for carrier checkout estimates. This weight applies to every variant. You do not set weight on each size or color.</p>

                            <div id="editShippingWeightOverrideState" class="mt-3 space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Product override — <span id="editShippingWeightOverrideValue">—</span></p>
                                <div class="flex max-w-xs items-center gap-2">
                                    <input id="edit_product_shipping_weight" name="shipping_weight" type="number" min="0.01" max="{{ \App\Services\Delivery\StoreShippingPreferences::MAX_ITEM_WEIGHT }}" step="0.01" value="{{ old('shipping_weight', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" inputmode="decimal" autocomplete="off">
                                    <span id="editShippingWeightUnit" class="text-sm font-semibold text-[#64748B]">{{ $editWeightUnit }}</span>
                                </div>
                                <button type="button" id="editShippingWeightUseFallback" class="{{ $editFallbackWeight !== null && $editFallbackWeight > 0 ? '' : 'hidden ' }}text-xs font-semibold text-brand hover:underline">Use store fallback</button>
                            </div>

                            <div id="editShippingWeightFallbackState" class="mt-3 hidden space-y-2">
                                <p class="text-sm text-[#0F172A]">Using store fallback: <span id="editShippingWeightFallbackLabel" class="font-semibold">{{ $editFallbackWeight !== null ? number_format($editFallbackWeight, 2).' '.$editWeightUnit : '—' }}</span></p>
                                <button type="button" id="editShippingWeightUseDifferent" class="text-xs font-semibold text-brand hover:underline">Use a different weight</button>
                            </div>

                            <div id="editShippingWeightEmptyState" class="mt-3 hidden space-y-2">
                                <p class="text-sm text-[#0F172A]">No shipping estimate configured.</p>
                                <button type="button" id="editShippingWeightAdd" class="text-xs font-semibold text-brand hover:underline">Add a product weight</button>
                                <p class="text-xs text-[#64748B]">You can also set a fallback weight in Delivery.</p>
                            </div>
                        </div>
                            @include('user_view.partials.product_organization_fields', [
                                'catalogBrands' => $catalogBrands,
                                'catalogTags' => $catalogTags,
                                'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
                                'canManageCategories' => $canManageCategories,
                                'canManageBrands' => $canManageBrands,
                                'canManageTags' => $canManageTags,
                                'organizationCompact' => true,
                            ])
                        @endunless
                    </div>
                    @if ($isPageNative)
                    </div>

                    @php
                        $editFallbackWeight = isset($shippingPreferences['fallback_item_weight']) && is_numeric($shippingPreferences['fallback_item_weight'])
                            ? (float) $shippingPreferences['fallback_item_weight']
                            : null;
                        $editWeightUnit = $shippingPreferences['weight_unit'] ?? 'LB';
                    @endphp
                    <div class="{{ $editSectionClass }}" id="catalog-edit-section-tax-shipping" data-product-edit-section>
                        <div class="mb-6 border-b border-slate-100 pb-4">
                            <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">Tax &amp; shipping</h3>
                            <p class="mt-1 text-xs text-[#64748B]">{{ $productCreateMode
                                ? 'These settings do not change the selling price or stock you already entered. You can continue without changing them.'
                                : 'Charge tax at checkout and set a package weight for carrier estimates.' }}</p>
                        </div>
                        <div class="space-y-5">
                            @include('user_view.partials.product_taxable_control', [
                                'taxSetting' => $taxSetting ?? $selectedStore?->taxSetting,
                                'inputId' => 'edit_product_is_taxable',
                                'checkedOverride' => isset($product) ? (bool) $product->is_taxable : null,
                            ])
                            <div id="editShippingWeightWrap" class="hidden rounded-xl border border-[#E2E8F0] bg-white p-4"
                                data-has-fallback="{{ $editFallbackWeight !== null && $editFallbackWeight > 0 ? '1' : '0' }}"
                                data-fallback-weight="{{ $editFallbackWeight !== null ? number_format($editFallbackWeight, 3, '.', '') : '' }}"
                                data-weight-unit="{{ $editWeightUnit }}">
                                <p class="text-sm font-semibold text-[#0F172A]">Shipping weight</p>
                                <p id="editShippingWeightHelp" class="mt-1 text-xs text-[#64748B]">Used for carrier checkout estimates. This weight applies to every variant. You do not set weight on each size or color.</p>

                                <div id="editShippingWeightOverrideState" class="mt-3 space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Product override — <span id="editShippingWeightOverrideValue">—</span></p>
                                    <div class="flex max-w-xs items-center gap-2">
                                        <input id="edit_product_shipping_weight" name="shipping_weight" type="number" min="0.01" max="{{ \App\Services\Delivery\StoreShippingPreferences::MAX_ITEM_WEIGHT }}" step="0.01" value="{{ old('shipping_weight', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]" inputmode="decimal" autocomplete="off">
                                        <span id="editShippingWeightUnit" class="text-sm font-semibold text-[#64748B]">{{ $editWeightUnit }}</span>
                                    </div>
                                    <button type="button" id="editShippingWeightUseFallback" class="{{ $editFallbackWeight !== null && $editFallbackWeight > 0 ? '' : 'hidden ' }}text-xs font-semibold text-brand hover:underline">Use store fallback</button>
                                </div>

                                <div id="editShippingWeightFallbackState" class="mt-3 hidden space-y-2">
                                    <p class="text-sm text-[#0F172A]">Using store fallback: <span id="editShippingWeightFallbackLabel" class="font-semibold">{{ $editFallbackWeight !== null ? number_format($editFallbackWeight, 2).' '.$editWeightUnit : '—' }}</span></p>
                                    <button type="button" id="editShippingWeightUseDifferent" class="text-xs font-semibold text-brand hover:underline">Use a different weight</button>
                                </div>

                                <div id="editShippingWeightEmptyState" class="mt-3 hidden space-y-2">
                                    <p class="text-sm text-[#0F172A]">No shipping estimate configured.</p>
                                    <button type="button" id="editShippingWeightAdd" class="text-xs font-semibold text-brand hover:underline">Add a product weight</button>
                                    <p class="text-xs text-[#64748B]">You can also set a fallback weight in Delivery.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="{{ $editSectionClass }}" id="catalog-edit-section-organization" data-product-edit-section>
                        <div class="mb-6 flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">Organization</h3>
                                <p class="mt-1 text-xs text-[#64748B]">{{ $productCreateMode
                                    ? 'Category, brand, and tags help shoppers find this product. You can continue without them.'
                                    : 'Assign a category so shoppers can find this product. Brand and tags are optional.' }}</p>
                            </div>
                            @if ($canManageCatalogTools)
                                <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#99F6E4] bg-[#F0FDFA] px-3 py-2 text-xs font-semibold text-[#0F766E] transition hover:bg-[#CCFBF1]">
                                    Open catalog tools
                                </button>
                            @endif
                        </div>
                        @if ($canManageCatalogTools && $catalogTaxonomyCategories->isEmpty() && $catalogBrands->isEmpty() && $catalogTags->isEmpty())
                            <div class="mb-5 rounded-xl border border-dashed border-[#99F6E4] bg-[#F0FDFA]/70 px-4 py-3 text-sm text-[#134E4A]">
                                <p class="font-semibold">Create your catalog groups here</p>
                                <p class="mt-1 text-xs leading-relaxed text-[#115E59]/90">You do not need to leave this page. Open catalog tools to add a category, brand, or tag, then pick it below for this product.</p>
                            </div>
                        @endif
                        @include('user_view.partials.product_organization_fields', [
                            'catalogBrands' => $catalogBrands,
                            'catalogTags' => $catalogTags,
                            'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
                            'canManageCategories' => $canManageCategories,
                            'canManageBrands' => $canManageBrands,
                            'canManageTags' => $canManageTags,
                            'organizationCompact' => false,
                        ])
                    </div>
                    @else
                </div>
                    @endif

                @if ($productEditSurface !== 'page')
                    <div class="rounded-2xl border border-[#D4E3FF] bg-[#F8FAFF] p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-[#0F172A]">More product fields</p>
                                <p class="mt-1 text-xs text-[#64748B]">Open specifications, extra details, and size/color options. Or use the full edit page for a clearer layout.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    id="editAdvancedFieldsToggle"
                                    class="inline-flex items-center gap-2 rounded-lg border border-[#BFDBFE] bg-white px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#EFF6FF]"
                                    aria-expanded="false"
                                    aria-controls="editAdvancedFieldsPanel"
                                >
                                    <span id="editAdvancedFieldsToggleLabel">Show all fields</span>
                                    <svg id="editAdvancedFieldsChevron" width="16" height="16" viewBox="0 0 20 20" fill="none" class="transition-transform" aria-hidden="true">
                                        <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <a
                                    id="editFullWorkspaceLink"
                                    href="#"
                                    class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-hover"
                                >
                                    Open full edit workspace
                                    <span aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="editAdvancedFieldsPanel" class="hidden space-y-6">
                @endif

                <div class="{{ $editSectionClass }}" @if ($isPageNative) id="catalog-edit-section-attributes" data-product-edit-section @endif>
                    <div @class(['mb-4 border-b border-slate-100 pb-4' => $isPageNative, 'mb-4' => ! $isPageNative])>
                        <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl scroll-mt-28">Product specifications</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#64748B]">{{ $productCreateMode
                            ? 'Reusable product facts shoppers can filter or compare, such as material or capacity. Size and color option groups are in the Price & stock step.'
                            : 'Specifications are reusable product facts shoppers can filter or compare, such as material, capacity, color family, ingredients, or compatibility. Product options still live under Options.' }}</p>
                    </div>
                    <div id="editProductAttributesBody" class="space-y-4"></div>
                </div>

                <div class="{{ $editSectionClass }}" @if ($isPageNative) id="catalog-edit-section-additional-details" data-product-edit-section @endif>
                    <input type="hidden" name="_custom_fields_editor" value="1">
                    <div @class(['mb-4 border-b border-slate-100 pb-4' => $isPageNative, 'mb-4' => ! $isPageNative])>
                        <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl scroll-mt-28">Additional details</h3>
                        <p class="mt-2 text-sm leading-relaxed text-[#64748B]"><span class="font-medium text-[#334155]">Additional details</span> are extra fields you choose and can edit anytime (supplier, material, origin, care notes, ingredients, internal references, and similar). They show under <span class="font-medium text-[#334155]">Additional product details</span> on the product workspace—separate from read-only columns kept from imports. Field names may use letters, numbers, dots, dashes, and underscores.</p>
                    </div>
                    <div id="editAdditionalDetailsBody" class="space-y-3"></div>
                    <button type="button" id="editAddAdditionalDetailRow" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">Add detail</button>
                </div>

                <div class="{{ $editSectionClass }}" @if ($isPageNative) id="catalog-edit-section-option-groups" data-product-edit-section @if ($productCreateMode) data-selling-hidden="true" @endif @endif>
                    <div class="mb-6 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl scroll-mt-28">{{ $isPageNative ? ($productCreateMode ? 'Option groups' : 'Options') : 'Option groups' }}</h3>
                            @if ($productCreateMode)
                                <p class="mt-1 text-xs text-[#64748B]">Add groups such as Size or Color. Each combination becomes a sellable variant in the table.</p>
                            @else
                                <p class="mt-1 text-xs text-[#64748B]">Add groups like Size or Color only when shoppers choose between versions. Then set SKU, price, and stock for each variant in the table.</p>
                            @endif
                        </div>
                        <button id="editOpenVariationModal" type="button" class="inline-flex shrink-0 items-center gap-2 rounded-full border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">{{ $productCreateMode ? 'Add option group' : 'Add option' }}</button>
                    </div>
                    <div id="editVariationHiddenInputs"></div>
                    <div id="editNoVariationState" class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                        @if ($productCreateMode)
                            <p class="font-semibold text-[#0F172A]">Add an option group to create sellable variants</p>
                            <p class="mt-1">Press <span class="font-medium text-[#334155]">Add option group</span> for Size, Color, or similar. Each combination appears in the table so you can set SKU, price, and stock.</p>
                        @else
                            Add option groups only if shoppers choose size, color, pack, or similar versions.
                        @endif
                    </div>
                    <div id="editVariationTypesList" class="hidden space-y-4"></div>
                </div>

                <div class="{{ $editSectionClass }}" @if ($isPageNative) id="catalog-edit-section-inventory" data-product-edit-section @if ($productCreateMode) data-selling-hidden="true" @endif @endif>
                    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                        <div>
                            <h3 id="editInventorySectionTitle" class="text-lg font-semibold text-[#0F172A] sm:text-xl scroll-mt-28">{{ $productCreateMode ? 'Sellable variants' : 'Inventory' }}</h3>
                            <p id="editInventorySectionLead" class="mt-1 text-xs text-[#64748B]">{{ $productCreateMode
                                ? 'Each row is one sellable version. Enter stock on every row. Blank price uses the default price above. Assign at least one photo to a variant.'
                                : 'Each row is one sellable version. Blank price uses the default price above.' }}</p>
                        </div>
                        <div id="editTotalStockSummary" class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-right shadow-sm">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]">Total stock</p>
                            <p id="editTotalStockDisplay" class="text-xl font-bold tabular-nums text-[#0F172A]">0</p>
                        </div>
                    </div>
                    @if ($isPageNative)
                        <p class="mb-3 text-xs text-[#64748B]">{{ $productCreateMode
                            ? 'Each variant can have several photos. Add them here or choose from listing photos. At least one variant needs a photo before you continue.'
                            : 'Each variant can have several photos. Choose from listing photos under Images, or add more from the variant row.' }}</p>
                    @endif
                    <div id="editInventoryToolsPanel" class="variant-bulk mb-5 hidden">
                        <div class="variant-bulk-head">
                            <p class="variant-bulk-title">Fill option variants at once</p>
                            <p class="variant-bulk-help">Optional. Applies to size/color rows only. Standard / Base keeps its own stock, and its price follows Default price above.</p>
                        </div>
                        <div class="variant-bulk-row">
                            <div class="variant-bulk-field">
                                <label for="editBulkPriceInput">Set price for option variants</label>
                                <input id="editBulkPriceInput" type="number" min="0" step="0.01" inputmode="decimal" autocomplete="off" placeholder="Leave blank">
                            </div>
                            <div class="variant-bulk-field">
                                <label for="editBulkStock">Set stock for option variants</label>
                                <input id="editBulkStock" type="number" min="0" step="1" inputmode="numeric" autocomplete="off" placeholder="Leave blank">
                            </div>
                            <button id="editApplyBulkValues" type="button" class="variant-bulk-apply">Apply</button>
                            <button type="button" id="editResetVariantPrices" class="variant-bulk-link variant-bulk-link-inline">Use default price</button>
                        </div>
                        <p id="editVariantToolsStatus" class="variant-bulk-status" role="status" aria-live="polite" hidden></p>
                    </div>
                    <input type="hidden" id="editBulkPrice" value="">
                    <div id="editVariantRows" class="variant-rows"></div>
                    <input id="editVariantImageFile" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="sr-only">
                    <div id="variantPhotoPicker" class="variant-photo-picker hidden" hidden role="dialog" aria-modal="true" aria-labelledby="variantPhotoPickerTitle">
                        <div class="variant-photo-picker-backdrop" data-close-variant-photos></div>
                        <div class="variant-photo-picker-panel">
                            <div class="variant-photo-picker-head">
                                <div>
                                    <p id="variantPhotoPickerTitle" class="variant-photo-picker-title">Photos for this variant</p>
                                    <p class="variant-photo-picker-help">Click a photo to add or remove it. The first selected photo is the main photo for this variant. New uploads also appear under Photos.</p>
                                    <p id="variantPhotoPickerStatus" class="variant-photo-picker-status"></p>
                                </div>
                                <button type="button" class="variant-photo-picker-close" data-close-variant-photos>Done</button>
                            </div>
                            <div id="variantPhotoPickerGrid" class="variant-photo-picker-grid"></div>
                            <div class="variant-photo-picker-actions">
                                <button type="button" id="variantPhotoPickerUpload" class="variant-photo-picker-add">Add photos</button>
                                <button type="button" id="variantPhotoPickerClear" class="variant-photo-picker-clear">Remove all from this variant</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="editVariantPreviewSection" class="{{ $editSectionClass }} hidden" hidden>
                    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between sm:gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#0F172A] sm:text-xl">{{ $productCreateMode ? 'Variant preview' : 'Variant combinations preview' }}</h3>
                            <p class="mt-1 text-xs text-[#64748B]">{{ $productCreateMode
                                ? 'A list of every variant that will save, with SKU, price, and stock.'
                                : 'Quick read-only check of SKUs, prices, and stock before you save.' }}</p>
                        </div>
                        <span id="editPreviewCount" class="text-sm text-[#94A3B8]">0 rows</span>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b border-[#F1F5F9]"><tr><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Combination</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">SKU</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Price</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Stock</th></tr></thead><tbody id="editPreviewTableBody" class="divide-y divide-[#F1F5F9]"></tbody></table></div>
                </div>

                @if ($productEditSurface !== 'page')
                    </div>
                @endif

                @if (! ($productEditSurface === 'page' && $productEditPageNative))
                    <div class="flex flex-col gap-4 border-t border-slate-200/90 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        @unless ($productCreateForm)
                            <button type="button" id="openDeleteProductWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">Delete Product</button>
                        @else
                            <span></span>
                        @endunless
                        <div class="flex flex-col gap-3 sm:flex-row">
                            @if ($productCreateMode)
                                <a href="{{ route('products') }}" id="dismissEditProductModal" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
                            @elseif ($productEditSurface === 'page' && ! empty($workspaceReturnProductId))
                                <a href="{{ route('products.show', ['product' => $workspaceReturnProductId]) }}" id="dismissEditProductModal" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
                            @else
                                <button type="button" id="dismissEditProductModal" class="rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</button>
                            @endif
                            <button type="submit" class="rounded-lg bg-brand px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover">{{ $productCreateMode ? 'Save product' : ($productEditSurface === 'page' ? 'Save and return to workspace' : 'Save Changes') }}</button>
                        </div>
                    </div>
                @else
                    <div class="product-edit-inline-footer flex flex-col gap-4 border-t border-slate-200/90 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        @unless ($productCreateForm)
                            <button type="button" id="openDeleteProductWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">Delete Product</button>
                        @else
                            <span></span>
                        @endunless
                        <a href="{{ $productCreateMode ? route('products') : route('products.show', ['product' => $workspaceReturnProductId]) }}" id="dismissEditProductModal" class="inline-flex items-center justify-center rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</a>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>

<div id="editVariationModal" class="ui-modal-shell ui-modal-shell--alert hidden">
    <div class="ui-modal-panel ui-modal-panel--md">
        <div class="ui-modal-header">
            <div>
                <h3 id="editVariationModalTitle" class="text-lg font-semibold text-[#0F172A]">{{ $productCreateMode ? 'Add an option group' : 'Add Variation Type' }}</h3>
                <p id="editVariationModalLead" class="mt-0.5 text-xs text-[#64748B]">{{ $productCreateMode
                    ? 'An option group is what shoppers pick, such as Size or Color. Add the values they can choose. Each combination becomes a variant you manage on this step.'
                    : 'Define how customers will differentiate your items' }}</p>
            </div>
            <button type="button" id="closeEditVariationModal" class="ui-modal-close" aria-label="Close">X</button>
        </div>
        <div class="ui-modal-body space-y-6">
            <div><label class="mb-2 block text-sm font-semibold text-[#334155]">{{ $productCreateMode ? 'Option group name' : 'Variation Name' }}</label><input id="editVariationName" type="text" placeholder="e.g., Size" class="w-full rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm text-[#0F172A]"></div>
            <div>
                <label class="mb-2 block text-sm font-semibold text-[#334155]">{{ $productCreateMode ? 'Values in this group' : 'Option Values' }}</label>
                <div class="rounded-lg border border-[#E2E8F0] px-3 py-3">
                    <div id="editVariationOptionChips" class="mb-2 flex flex-wrap gap-2"></div>
                    <input id="editVariationOptionInput" type="text" placeholder="{{ $productCreateMode ? 'Type a value and press Enter, e.g. Small' : 'Type a value and press Enter' }}" class="w-full border-0 p-0 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-0">
                </div>
                <textarea id="editVariationOptions" rows="3" placeholder="S, M, L, XL" class="hidden"></textarea>
            </div>
        </div>
        <div class="ui-modal-footer">
            <button type="button" id="cancelEditVariationModal" class="px-4 py-2 text-sm font-semibold text-[#475569]">Cancel</button>
            <button type="button" id="submitEditVariationModal" class="rounded-lg bg-brand px-5 py-2 text-sm font-bold text-white">{{ $productCreateMode ? 'Add option group' : 'Save Variation' }}</button>
        </div>
    </div>
</div>

<div id="deleteProductWarningModal" class="ui-modal-shell ui-modal-shell--alert hidden">
    <div class="ui-modal-panel ui-modal-panel--md border-[#FECACA]">
        <div class="bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.18),_transparent_60%)] px-6 pb-4 pt-6">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm">!</div>
            <h3 class="mt-5 text-2xl font-semibold text-[#0F172A]">Delete this product?</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">This moves the product to Deleted products. You can undo delete later, or permanently remove it there.</p>
        </div>
        <div class="px-6 pb-6 pt-2">
            <div class="rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4"><p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]">Delete product</p><p class="mt-2 text-sm text-[#7F1D1D]">You are about to delete <span id="deleteProductName" class="font-bold"></span>.</p></div>
            <form id="deleteProductForm" method="POST" class="mt-6">
                @csrf
                @method('DELETE')
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelDeleteProduct" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Keep product</button>
                    <button type="submit" class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="productImageLightbox" class="ui-modal-shell ui-modal-shell--nested hidden" data-product-image-lightbox role="dialog" aria-modal="true" aria-labelledby="productImageLightboxTitle">
    <button type="button" class="ui-modal-backdrop" data-lightbox-close tabindex="-1" aria-label="Close photo preview"></button>
    <div class="product-image-lightbox-frame">
        <div class="product-image-lightbox-toolbar">
            <p id="productImageLightboxTitle" data-lightbox-caption class="text-sm font-semibold">Photo preview</p>
            <button type="button" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/20" data-lightbox-close>Close</button>
        </div>
        <div class="product-image-lightbox-stage">
            <button type="button" class="product-image-lightbox-nav is-prev" data-lightbox-prev aria-label="Previous photo">&lsaquo;</button>
            <img data-lightbox-image src="" alt="">
            <button type="button" class="product-image-lightbox-nav is-next" data-lightbox-next aria-label="Next photo">&rsaquo;</button>
        </div>
    </div>
</div>

<script>
window.__additionalDetailKeyErrors = @json($additionalDetailKeyErrors);
window.__catalogAttributesForEdit = @json($catalogAttributesForEdit);
window.__productTypeBehaviorsForEdit = @json($productTypeBehaviorsForEdit);
(() => {
const editModal=document.getElementById('editProductModal'); if(!editModal) return;
const editSurfaceIsPage=editModal.dataset.surface==='page';
const productCreateMode={{ $productCreateMode ? 'true' : 'false' }};
const closeEditProductButton=document.getElementById('closeEditProductModal');
const dismissEditProductEl=document.getElementById('dismissEditProductModal');
const closeButtons=[];
if(closeEditProductButton){closeButtons.push(closeEditProductButton);}
if(dismissEditProductEl&&dismissEditProductEl.tagName==='BUTTON'){closeButtons.push(dismissEditProductEl);}
const editButtons=[...document.querySelectorAll('.js-open-edit-product-modal:not([data-open-delete="true"])')];
const deleteButtons=[...document.querySelectorAll('.js-open-delete-product-modal')];
const editForm=document.getElementById('editProductForm'); const deleteForm=document.getElementById('deleteProductForm');
const deleteWarningModal=document.getElementById('deleteProductWarningModal'); const openDeleteWarning=document.getElementById('openDeleteProductWarning'); const cancelDeleteProduct=document.getElementById('cancelDeleteProduct'); const deleteProductName=document.getElementById('deleteProductName');
const editProductId=document.getElementById('edit_product_id'); const editTypeSelect=document.getElementById('edit_product_type_select'); const editTypeValue=document.getElementById('edit_product_type_value'); const editCustomTypeHidden=document.getElementById('edit_product_custom_type_hidden'); const editTypeBehaviorHelp=document.getElementById('editProductTypeBehaviorHelp'); const editCustomTypeWrap=document.getElementById('edit_custom_product_type_wrap'); const editCustomTypeInput=document.getElementById('edit_custom_product_type_input'); const editCustomTypeBehavior=document.getElementById('edit_custom_product_type_behavior');
const editName=document.getElementById('edit_product_name'); const editDescription=document.getElementById('edit_product_description'); const editSku=document.getElementById('edit_product_sku'); const editPrice=document.getElementById('edit_product_price'); const editProductCompareAt=document.getElementById('edit_product_compare_at'); const editProductStock=document.getElementById('edit_product_stock'); const editSimpleStockWrap=document.getElementById('editSimpleStockWrap'); const editMultiStockHint=document.getElementById('editMultiStockHint'); const editStockAlert=document.getElementById('edit_product_stock_alert'); const editSimpleStockAlertWrap=document.getElementById('editSimpleStockAlertWrap'); const editMultiStockAlertHint=document.getElementById('editMultiStockAlertHint'); const editProductIsTaxable=document.getElementById('edit_product_is_taxable'); const editShippingWeightWrap=document.getElementById('editShippingWeightWrap'); const editShippingWeight=document.getElementById('edit_product_shipping_weight'); const editBrandId=document.getElementById('edit_product_brand_id'); const editTagIds=document.getElementById('edit_product_tag_ids'); const editCategoryIds=document.getElementById('edit_product_category_ids'); const editImageInput=document.getElementById('edit_product_image'); const editImagePreview=document.getElementById('editProductImagePreview'); const editExistingImageInputs=document.getElementById('editExistingImageInputs');
const editVariationHiddenInputs=document.getElementById('editVariationHiddenInputs'); const editNoVariationState=document.getElementById('editNoVariationState'); const editVariationTypesList=document.getElementById('editVariationTypesList'); const editAddVariantRow=document.getElementById('editAddVariantRow'); const editVariantRows=document.getElementById('editVariantRows'); const editBulkPrice=document.getElementById('editBulkPrice'); const editBulkStock=document.getElementById('editBulkStock'); const editApplyBulkValues=document.getElementById('editApplyBulkValues'); const editApplyBasePriceToVariants=document.getElementById('editApplyBasePriceToVariants'); const editStockCarryNotice=document.getElementById('editStockCarryNotice'); const editStockCarryNoticeText=document.getElementById('editStockCarryNoticeText'); const editStockCarrySplitBtn=document.getElementById('editStockCarrySplitBtn'); const editStockCarryDismissBtn=document.getElementById('editStockCarryDismissBtn'); const editInventoryToolsPanel=document.getElementById('editInventoryToolsPanel'); const editInventorySectionTitle=document.getElementById('editInventorySectionTitle'); const editInventorySectionLead=document.getElementById('editInventorySectionLead'); const editTotalStockDisplay=document.getElementById('editTotalStockDisplay'); const editPreviewCount=document.getElementById('editPreviewCount'); const editPreviewTableBody=document.getElementById('editPreviewTableBody'); const editPreviewSection=document.getElementById('editVariantPreviewSection');
const editAdditionalDetailsBody=document.getElementById('editAdditionalDetailsBody'); const editAddAdditionalDetailRow=document.getElementById('editAddAdditionalDetailRow'); const editProductAttributesBody=document.getElementById('editProductAttributesBody');
const editVariationModal=document.getElementById('editVariationModal'); const editOpenVariationModal=document.getElementById('editOpenVariationModal'); const closeEditVariationModal=document.getElementById('closeEditVariationModal'); const cancelEditVariationModal=document.getElementById('cancelEditVariationModal'); const submitEditVariationModal=document.getElementById('submitEditVariationModal'); const editVariationName=document.getElementById('editVariationName'); const editVariationOptions=document.getElementById('editVariationOptions'); const editVariationOptionInput=document.getElementById('editVariationOptionInput'); const editVariationOptionChips=document.getElementById('editVariationOptionChips');
const editAdvancedFieldsToggle=document.getElementById('editAdvancedFieldsToggle'); const editAdvancedFieldsPanel=document.getElementById('editAdvancedFieldsPanel'); const editAdvancedFieldsToggleLabel=document.getElementById('editAdvancedFieldsToggleLabel'); const editAdvancedFieldsChevron=document.getElementById('editAdvancedFieldsChevron'); const editFullWorkspaceLink=document.getElementById('editFullWorkspaceLink');
const openVariantPanels = new Set();
const defaultTypes=['physical','digital','service','subscription','virtual']; const catalogAttributeDefs=Array.isArray(window.__catalogAttributesForEdit)?window.__catalogAttributesForEdit:[]; const productTypeBehaviors=(window.__productTypeBehaviorsForEdit&&typeof window.__productTypeBehaviorsForEdit==='object')?window.__productTypeBehaviorsForEdit:{}; let editCatalogImages=[]; let currentProduct=null; let editVariationTypes=[]; let editRows=[]; let editingVariationIndex=null; let retainedExistingImages=[]; let selectedEditImages=[]; let editVariationOptionTags=[]; let pendingStockCarryTotal=null; let lastRemovedPendingImageIndex=null; let sellingMode='single'; let includeStandardBase=false; let preservedBaseStock=null;
const escapeHtml=(v)=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
const setAdvancedFieldsOpen=(open)=>{if(!editAdvancedFieldsPanel||!editAdvancedFieldsToggle)return;editAdvancedFieldsPanel.classList.toggle('hidden',!open);editAdvancedFieldsToggle.setAttribute('aria-expanded',open?'true':'false');if(editAdvancedFieldsToggleLabel){editAdvancedFieldsToggleLabel.textContent=open?'Hide extra fields':'Show all fields';}editAdvancedFieldsChevron?.classList.toggle('rotate-180',open);};
const readAdditionalDetailKeyErrors=()=>{try{const raw=window.__additionalDetailKeyErrors;return raw&&typeof raw==='object'?raw:{}}catch(e){return{}}};
const renderAdditionalDetailRows=(rows)=>{if(!editAdditionalDetailsBody)return;const keyErrors=readAdditionalDetailKeyErrors();const data=(Array.isArray(rows)&&rows.length)?rows:[{key:'',type:'text',value:''}];editAdditionalDetailsBody.innerHTML=data.map((row,i)=>{const err=(keyErrors&&keyErrors[i])||(keyErrors&&keyErrors[String(i)])||'';const keyRing=err?' border-rose-300 ring-1 ring-rose-100':' border-[#E2E8F0]';return`<div data-additional-detail-row class="grid gap-3 rounded-xl border border-slate-200/90 bg-slate-50/40 p-4 md:grid-cols-12 md:items-start"><div class="md:col-span-3"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Field name</label><input data-field="key" type="text" name="custom_fields[${i}][key]" value="${escapeHtml(row.key||'')}" class="w-full rounded-lg border bg-white px-3 py-2 text-sm${keyRing}" placeholder="e.g. supplier_code" maxlength="128" autocomplete="off">${err?`<p class="mt-1 text-xs text-rose-600">${escapeHtml(err)}</p>`:''}</div><div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Type</label><select data-field="type" name="custom_fields[${i}][type]" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-2 py-2 text-sm"><option value="text"${(row.type||'text')==='text'?' selected':''}>Text</option><option value="number"${(row.type||'')==='number'?' selected':''}>Number</option><option value="boolean"${(row.type||'')==='boolean'?' selected':''}>Yes / No</option><option value="list"${(row.type||'')==='list'?' selected':''}>List</option></select></div><div class="md:col-span-6"><label class="mb-1 block text-xs font-semibold text-[#64748B]">Value</label><input data-field="value" type="text" name="custom_fields[${i}][value]" value="${escapeHtml(row.value||'')}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm" placeholder="Lists: comma-separated"></div><div class="md:col-span-1 flex items-start justify-end pt-6 md:pt-7"><button type="button" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" data-remove-additional-detail title="Remove this row">Remove</button></div></div>`;}).join('');editAdditionalDetailsBody.querySelectorAll('[data-remove-additional-detail]').forEach((btn)=>btn.addEventListener('click',()=>{btn.closest('[data-additional-detail-row]')?.remove();if(!editAdditionalDetailsBody.querySelector('[data-additional-detail-row]')){renderAdditionalDetailRows([{key:'',type:'text',value:''}]);}}));};
const renderProductAttributeRows=(selectedIds=[])=>{if(!editProductAttributesBody)return;const selected=new Set((Array.isArray(selectedIds)?selectedIds:[]).map((id)=>String(id)));if(!catalogAttributeDefs.length){editProductAttributesBody.innerHTML='<div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-4 text-sm text-[#64748B]"><p class="font-medium text-[#334155]">No specifications have been created for this store yet.</p><p class="mt-1">Create specifications from Catalog tools, then return here to select terms for this product.</p></div>';return;}editProductAttributesBody.innerHTML=catalogAttributeDefs.map((attr)=>{const terms=Array.isArray(attr.terms)?attr.terms:[];const termHtml=terms.length?terms.map((term)=>{const checked=selected.has(String(term.id))?' checked':'';const swatch=term.swatch_value?'<span class="h-3 w-3 rounded-full border border-slate-200" style="background:'+escapeHtml(term.swatch_value)+'"></span>':'';return '<label class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#334155]"><input type="checkbox" name="attribute_terms['+escapeHtml(attr.id)+'][]" value="'+escapeHtml(term.id)+'" class="rounded border-[#CBD5E1] accent-[#0052CC]"'+checked+'>'+swatch+'<span>'+escapeHtml(term.name)+'</span></label>';}).join(''):'<p class="text-sm text-[#94A3B8]">No terms yet. Add terms on the specifications page before assigning this product specification.</p>';return '<div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4"><div class="flex flex-wrap items-center gap-2"><p class="text-sm font-semibold text-[#0F172A]">'+escapeHtml(attr.name)+'</p>'+(attr.is_filterable?'<span class="rounded-full bg-[#EEF4FF] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#0052CC]">Filterable</span>':'')+'</div><div class="mt-3 flex flex-wrap gap-2">'+termHtml+'</div></div>';}).join('');};
editAddAdditionalDetailRow?.addEventListener('click',()=>{const current=[...editAdditionalDetailsBody.querySelectorAll('[data-additional-detail-row]')].map((row)=>({key:(row.querySelector('[data-field="key"]')?.value||'').trim(),type:(row.querySelector('[data-field="type"]')?.value)||'text',value:row.querySelector('[data-field="value"]')?.value??''}));current.push({key:'',type:'text',value:''});renderAdditionalDetailRows(current);});
const getEditVariantRowLimit=()=>editVariationTypes.reduce((total,variationType)=>total+((variationType.options||[]).length||0),0);
const getEditRowKey=(optionMap)=>Object.entries(optionMap||{}).sort(([left],[right])=>Number(left)-Number(right)).map(([variationIndex,optionIndex])=>`${variationIndex}:${optionIndex}`).join('|');
const isEmptyOptionMap=(row)=>Object.keys(row?.option_map||{}).length===0;
const isOptionVariantRow=(row)=>!isEmptyOptionMap(row);
const optionVariantCount=()=>editRows.filter(isOptionVariantRow).length;
const inheritStandardBasePrices=()=>{if(!hasOptionGroups())return;editRows=editRows.map((r)=>isEmptyOptionMap(r)?{...r,price:''}:r);};
const hasOptionGroups=()=>editVariationTypes.length>0;
const editSellingSetup=document.getElementById('editSellingSetup');
const editIncludeStandardBase=document.getElementById('editIncludeStandardBase');
const editStandardBaseWrap=document.getElementById('editStandardBaseWrap');
const editMultiDefaultsSummary=document.getElementById('editMultiDefaultsSummary');
const editOptionGroupsSection=document.getElementById('catalog-edit-section-option-groups');
const editInventorySection=document.getElementById('catalog-edit-section-inventory');
const defaultCompareAtValue=()=>String(editProductCompareAt?.value??'').trim();
const cloneCustomFields=(row)=>Array.isArray(row?.custom_fields)?row.custom_fields.map((cf)=>({key:cf?.key||'',type:cf?.type||'text',value:cf?.value||''})):[];
const makeInventoryRow=(overrides={})=>({id:'',option_map:{},sku:'',price:'',compare_at_price:defaultCompareAtValue(),stock:'0',stock_alert:editStockAlert?.value||0,shipping_weight:'',product_image_id:'',product_image_ids:[],custom_fields:[],...overrides});
const buildEditRowsFromVariationTypes=(existingRows=[])=>{if(!editVariationTypes.length||editVariationTypes.some((variationType)=>!(variationType.options||[]).length)){return [];} const simpleSource=existingRows.find(isEmptyOptionMap)||null; const existingRowsByKey=new Map(existingRows.filter((row)=>!isEmptyOptionMap(row)).map((row)=>[getEditRowKey(row.option_map||{}),row])); const combinations=[]; const walk=(variationIndex,optionMap)=>{if(variationIndex>=editVariationTypes.length){combinations.push({...optionMap}); return;} (editVariationTypes[variationIndex].options||[]).forEach((_,optionIndex)=>{walk(variationIndex+1,{...optionMap,[variationIndex]:optionIndex});});}; walk(0,{}); const combos=combinations.map((optionMap)=>{const existingRow=existingRowsByKey.get(getEditRowKey(optionMap)); return makeInventoryRow({id:existingRow?.id ?? '',option_map:optionMap,sku:(existingRow?.sku)||'',price:existingRow?.price ?? '',compare_at_price:existingRow?.compare_at_price ?? simpleSource?.compare_at_price ?? defaultCompareAtValue(),stock:existingRow?.stock ?? '0',stock_alert:existingRow?.stock_alert ?? simpleSource?.stock_alert ?? (editStockAlert?.value||0),shipping_weight:existingRow?.shipping_weight ?? '',product_image_id:existingRow?.product_image_id ?? '',product_image_ids:Array.isArray(existingRow?.product_image_ids)?existingRow.product_image_ids.slice():(existingRow?.product_image_id?[existingRow.product_image_id]:[]),custom_fields:cloneCustomFields(existingRow)});}); if(!includeStandardBase){return combos;} const std=makeInventoryRow({id:simpleSource?.id ?? '',option_map:{},sku:simpleSource?.sku||'',price:'',compare_at_price:simpleSource?.compare_at_price ?? defaultCompareAtValue(),stock:simpleSource?.stock ?? '0',stock_alert:simpleSource?.stock_alert ?? (editStockAlert?.value||0),shipping_weight:simpleSource?.shipping_weight ?? '',product_image_id:simpleSource?.product_image_id ?? '',product_image_ids:Array.isArray(simpleSource?.product_image_ids)?simpleSource.product_image_ids.slice():(simpleSource?.product_image_id?[simpleSource.product_image_id]:[]),custom_fields:cloneCustomFields(simpleSource)}); return [std,...combos];};
const syncType=()=>{const selected=editTypeSelect.value||'physical';const isCustom=selected==='__custom__';const type=defaultTypes.includes(selected)?selected:'physical';if(!isCustom){editTypeSelect.value=type;}const customBehavior=defaultTypes.includes(editCustomTypeBehavior?.value||'')?(editCustomTypeBehavior?.value||'physical'):'physical';editTypeValue.value=isCustom?customBehavior:type;if(editCustomTypeWrap)editCustomTypeWrap.classList.toggle('hidden',!isCustom);if(editCustomTypeHidden){editCustomTypeHidden.value=isCustom?(editCustomTypeInput?.value||''):'';}if(editTypeBehaviorHelp){const behavior=productTypeBehaviors[editTypeValue.value]||{};editTypeBehaviorHelp.textContent=behavior.description||'Product behavior controls shipping, inventory, and future fulfillment. Category controls where the item appears in your catalog.';}};
const syncSelectedFiles=(input,files)=>{if(!input) return; const transfer=new DataTransfer(); files.forEach((file)=>transfer.items.add(file)); input.files=transfer.files;};
let editGalleryItems=[];
const PRODUCT_IMAGE_MAX_COUNT={{ \App\Support\ProductCatalogImageLimits::MAX }};
const PRODUCT_IMAGE_MAX_BYTES=4096*1024;

const normalizeImageIdList=(ids)=>{const seen=new Set();const out=[];(Array.isArray(ids)?ids:[ids]).forEach((id)=>{const s=String(id??'').trim();if(!s||seen.has(s))return;seen.add(s);out.push(s);});return out;};
const rowImageIds=(row)=>normalizeImageIdList((row&&Array.isArray(row.product_image_ids)&&row.product_image_ids.length)?row.product_image_ids:(row&&row.product_image_id?[row.product_image_id]:[]));
const withRowImageIds=(row,ids)=>{const list=normalizeImageIdList(ids);return {...row,product_image_ids:list,product_image_id:list[0]||''};};
let variantPhotoPickerRow=null;
const variantPhotoPicker=document.getElementById('variantPhotoPicker');
const variantPhotoPickerGrid=document.getElementById('variantPhotoPickerGrid');
const variantPhotoPickerTitle=document.getElementById('variantPhotoPickerTitle');
const editVariantImageFile=document.getElementById('editVariantImageFile');
const variantPhotoPickerHome=variantPhotoPicker?variantPhotoPicker.parentNode:null;
const variantPhotoPickerStatus=document.getElementById('variantPhotoPickerStatus');
const variantRowLabel=(row)=>{if(!row)return 'this variant';if(typeof hasOptionGroups==='function'&&hasOptionGroups()&&isEmptyOptionMap(row))return 'Standard / Base';const parts=(editVariationTypes||[]).map((v,i)=>{const s=row.option_map?.[i];return (s!==undefined&&s!=='')?String(v.options?.[s]||''):'';}).filter(Boolean);return parts.join(' / ')||'this variant';};
const buildVariantPhotoCellHtml=(rowIndex,row)=>{const ids=rowImageIds(row);const hiddenPrimary='<input type="hidden" data-variant-image-hidden="1" data-row-index="'+rowIndex+'" name="variants['+rowIndex+'][product_image_id]" value="'+escapeHtml(String(ids[0]||''))+'">';const hiddenIds=ids.map((id)=>'<input type="hidden" name="variants['+rowIndex+'][product_image_ids][]" value="'+escapeHtml(String(id))+'">').join('');const thumbs=ids.slice(0,3).map((id)=>{const img=editCatalogImages.find((item)=>String(item.id)===String(id));return (img&&img.thumb_url)?'<img src="'+escapeHtml(String(img.thumb_url))+'" alt="" width="32" height="32">':'';}).join('');const extra=ids.length>3?'<span class="variant-photo-more">+'+escapeHtml(String(ids.length-3))+'</span>':'';const stack=ids.length?('<div class="variant-photo-stack">'+thumbs+extra+'</div>'):'<span class="variant-photo-empty">No photos</span>';const label=ids.length?(ids.length+' photo'+(ids.length===1?'':'s')):'Add photos';return '<div class="variant-table-photo" data-variant-photo-cell="'+rowIndex+'">'+hiddenPrimary+hiddenIds+'<button type="button" class="variant-photo-open" data-open-variant-photos="'+rowIndex+'">'+stack+'<span class="variant-photo-open-label">'+escapeHtml(label)+'</span></button></div>';};
const refreshVariantPhotoCell=(rowIndex)=>{if(!editVariantRows||!editRows[rowIndex])return;const cell=editVariantRows.querySelector('[data-variant-photo-cell="'+rowIndex+'"]');if(!cell)return;cell.outerHTML=buildVariantPhotoCellHtml(rowIndex,editRows[rowIndex]);};
const mountVariantPhotoPicker=()=>{if(!variantPhotoPicker)return;if(variantPhotoPicker.parentElement!==document.body){document.body.appendChild(variantPhotoPicker);}};
const unmountVariantPhotoPicker=()=>{if(variantPhotoPicker&&variantPhotoPickerHome&&variantPhotoPicker.parentElement!==variantPhotoPickerHome){variantPhotoPickerHome.appendChild(variantPhotoPicker);}};
const closeVariantPhotoPicker=()=>{variantPhotoPickerRow=null;if(!variantPhotoPicker)return;variantPhotoPicker.classList.add('hidden');variantPhotoPicker.hidden=true;unmountVariantPhotoPicker();};
const renderVariantPhotoPicker=()=>{if(!variantPhotoPicker||!variantPhotoPickerGrid||variantPhotoPickerRow===null||!editRows[variantPhotoPickerRow])return;const row=editRows[variantPhotoPickerRow];const selected=rowImageIds(row);const selectedSet=new Set(selected.map(String));if(variantPhotoPickerTitle){variantPhotoPickerTitle.textContent='Photos for '+variantRowLabel(row);}if(variantPhotoPickerStatus){variantPhotoPickerStatus.textContent=selected.length?(selected.length+' selected. The first one is the main photo for this variant.'):'No photos selected yet. Click a listing photo to add it.';}if(!editCatalogImages.length){variantPhotoPickerGrid.innerHTML='<p class="variant-photo-picker-empty">No listing photos yet. Use Add photos to upload.</p>';return;}const selectedImgs=selected.map((id)=>editCatalogImages.find((img)=>String(img.id)===String(id))).filter(Boolean);const rest=editCatalogImages.filter((img)=>!selectedSet.has(String(img.id)));variantPhotoPickerGrid.innerHTML=selectedImgs.concat(rest).map((img)=>{const id=String(img.id);const galleryIx=Math.max(0,editCatalogImages.findIndex((item)=>String(item.id)===id));const orderIdx=selected.findIndex((item)=>String(item)===id);const on=orderIdx>=0;const badge=on?'<span class="variant-photo-tile-badge'+(orderIdx===0?' is-main':'')+'">'+(orderIdx===0?'Main':String(orderIdx+1))+'</span>':'';const makeMain=(on&&orderIdx>0)?'<span class="variant-photo-tile-main" data-make-variant-main="'+escapeHtml(id)+'">Make main</span>':'';const src=img.thumb_url?escapeHtml(String(img.thumb_url)):'';const imgTag=src?'<img src="'+src+'" alt="">':'<span class="variant-photo-empty">Photo</span>';return '<button type="button" class="variant-photo-tile'+(on?' is-selected':'')+'" data-toggle-variant-photo="'+escapeHtml(id)+'">'+badge+imgTag+makeMain+'<span class="variant-photo-tile-label">Photo '+(galleryIx+1)+'</span></button>';}).join('');};
const openVariantPhotoPicker=(rowIndex)=>{if(!Number.isFinite(rowIndex)||!editRows[rowIndex]||!variantPhotoPicker)return;variantPhotoPickerRow=rowIndex;mountVariantPhotoPicker();variantPhotoPicker.classList.remove('hidden');variantPhotoPicker.hidden=false;renderVariantPhotoPicker();};
const toggleVariantPhoto=(imageId)=>{if(variantPhotoPickerRow===null||!editRows[variantPhotoPickerRow])return;const current=rowImageIds(editRows[variantPhotoPickerRow]);const id=String(imageId);const idx=current.findIndex((item)=>String(item)===id);const next=idx>=0?current.filter((_,i)=>i!==idx):current.concat([id]);editRows[variantPhotoPickerRow]=withRowImageIds(editRows[variantPhotoPickerRow],next);refreshVariantPhotoCell(variantPhotoPickerRow);renderVariantPhotoPicker();};
const promoteVariantPhoto=(imageId)=>{if(variantPhotoPickerRow===null||!editRows[variantPhotoPickerRow])return;const id=String(imageId);const current=rowImageIds(editRows[variantPhotoPickerRow]).filter((item)=>String(item)!==id);editRows[variantPhotoPickerRow]=withRowImageIds(editRows[variantPhotoPickerRow],[id,...current]);refreshVariantPhotoCell(variantPhotoPickerRow);renderVariantPhotoPicker();};
const clearVariantPhotos=()=>{if(variantPhotoPickerRow===null||!editRows[variantPhotoPickerRow])return;editRows[variantPhotoPickerRow]=withRowImageIds(editRows[variantPhotoPickerRow],[]);refreshVariantPhotoCell(variantPhotoPickerRow);renderVariantPhotoPicker();};
const assignUploadedIdsToPickerRow=(ids)=>{if(variantPhotoPickerRow===null||!Array.isArray(ids)||!ids.length||!editRows[variantPhotoPickerRow])return;editRows[variantPhotoPickerRow]=withRowImageIds(editRows[variantPhotoPickerRow],rowImageIds(editRows[variantPhotoPickerRow]).concat(ids));refreshVariantPhotoCell(variantPhotoPickerRow);renderVariantPhotoPicker();};

const editImageStatus=document.querySelector('[data-image-upload-status]');
const editImageError=document.querySelector('[data-image-upload-error]');
const editImageDropzone=document.querySelector('[data-image-dropzone]');
const editImageUploader=document.querySelector('[data-product-image-uploader]');
const editImageOrderInputs=document.getElementById('editImageOrderInputs');
const productImageLightbox=document.getElementById('productImageLightbox');
const productImageLightboxImg=productImageLightbox?productImageLightbox.querySelector('[data-lightbox-image]'):null;
const productImageLightboxCaption=productImageLightbox?productImageLightbox.querySelector('[data-lightbox-caption]'):null;
let productImageLightboxIndex=0;
let galleryDragFromIndex=null;
const revokeGalleryObjectUrl=(item)=>{if(item&&item.kind==='new'&&item.url&&String(item.url).startsWith('blob:')){URL.revokeObjectURL(item.url);}};
const revokeAllGalleryObjectUrls=()=>{editGalleryItems.forEach(revokeGalleryObjectUrl);};
const setImageUploadError=(message)=>{if(!editImageError)return;const text=String(message||'').trim();editImageError.textContent=text;editImageError.classList.toggle('hidden',text==='');};
const galleryItemLabel=(item,index)=>{if(index===0)return 'Main photo'+(item&&item.name?' — '+item.name:'');return 'Photo '+(index+1)+(item&&item.name?' — '+item.name:'');};
const remapVariantPendingByFiles=(previousFiles,nextFiles)=>{const nextIndex=new Map();nextFiles.forEach((file,index)=>nextIndex.set(file,index));const mapOne=(raw)=>{const s=String(raw??'');if(!s.startsWith('new:'))return s;const oldIdx=Number(s.slice(4));if(!Number.isFinite(oldIdx))return '';const file=previousFiles[oldIdx];if(!file)return '';const mapped=nextIndex.get(file);if(mapped===undefined)return '';return 'new:'+mapped;};editRows=editRows.map((row)=>withRowImageIds(row,rowImageIds(row).map(mapOne).filter(Boolean)));};
const persistProductImageGallery=()=>{const previousNew=selectedEditImages.slice();retainedExistingImages=editGalleryItems.filter((item)=>item.kind==='existing').map((item)=>String(item.path||'')).filter(Boolean);selectedEditImages=editGalleryItems.filter((item)=>item.kind==='new').map((item)=>item.file).filter(Boolean);remapVariantPendingByFiles(previousNew,selectedEditImages);syncSelectedFiles(editImageInput,selectedEditImages);if(editExistingImageInputs){editExistingImageInputs.innerHTML=retainedExistingImages.map((path)=>`<input type="hidden" name="existing_image_paths[]" value="${escapeHtml(path)}">`).join('');}if(editImageOrderInputs){let newIndex=0;editImageOrderInputs.innerHTML=editGalleryItems.map((item)=>{if(item.kind==='existing'){return `<input type="hidden" name="image_order[]" value="${escapeHtml('existing:'+item.path)}">`;}const token='new:'+newIndex;newIndex+=1;return `<input type="hidden" name="image_order[]" value="${escapeHtml(token)}">`;}).join('');}};
const renderExistingImageInputs=()=>{persistProductImageGallery();};
const pruneUnavailableVariantImages=()=>{const available=new Set(editCatalogImages.map((img)=>String(img.id)));editRows=editRows.map((row)=>withRowImageIds(row,rowImageIds(row).filter((id)=>available.has(String(id)))));};
const labelVariantImageOption=(img,idx)=>{if(img&&img.picker_label)return String(img.picker_label);return 'Catalog photo '+(idx+1);};
const syncVariantImageHidden=(rowIndex)=>{if(!editVariantRows||!editRows[rowIndex])return;const hidden=editVariantRows.querySelector('input[type="hidden"][data-variant-image-hidden][data-row-index="'+rowIndex+'"]');if(hidden){hidden.value=String(editRows[rowIndex].product_image_id??'');}};
const refreshVariantPhotoOptions=()=>{if(!editVariantRows)return;editRows.forEach((_,rowIndex)=>refreshVariantPhotoCell(rowIndex));};
const syncEditCatalogImagesFromRetained=()=>{if(!currentProduct)return;const catalog=Array.isArray(currentProduct.catalog_images)?currentProduct.catalog_images:[];let newIndex=0;editCatalogImages=editGalleryItems.map((item,galleryIndex)=>{if(item.kind==='existing'){const match=catalog.find((img)=>String(img.image_path||'')===String(item.path||''));const label=galleryItemLabel(item,galleryIndex);if(match){return {...match,thumb_url:match.thumb_url||item.url,picker_label:label};}return {id:item.path,image_path:item.path,thumb_url:item.url,picker_label:label};}const id='new:'+newIndex;newIndex+=1;return {id,image_path:'',thumb_url:item.url,picker_label:'Photo '+(galleryIndex+1)};});pruneUnavailableVariantImages();if(editVariantRows&&editVariantRows.querySelector('[data-variant-photo-cell]')){refreshVariantPhotoOptions();renderVariantPhotoPicker();}else if(editRows&&editRows.length){renderVariantRows();}};
const isAcceptedProductImage=(file)=>{if(!file)return false;const name=String(file.name||'');const type=String(file.type||'');return (/^image\/(jpeg|jpg|png|webp)$/i.test(type)||/\.(jpe?g|png|webp)$/i.test(name));};
const isProductImageWithinSize=(file)=>!!file&&Number(file.size||0)<=PRODUCT_IMAGE_MAX_BYTES;
const hydrateProductImageGalleryFromProduct=(product)=>{revokeAllGalleryObjectUrls();editGalleryItems=[];const paths=Array.isArray(retainedExistingImages)?retainedExistingImages.slice():[];const urls=Array.isArray(product?.image_urls)?product.image_urls:[];const pathList=Array.isArray(product?.image_paths)?product.image_paths:[];const urlByPath={};pathList.forEach((path,index)=>{urlByPath[String(path||'')]=urls[index]||'';});const catalog=Array.isArray(product?.catalog_images)?product.catalog_images:[];paths.forEach((path)=>{const p=String(path||'');if(!p)return;const match=catalog.find((img)=>String(img.image_path||'')===p);editGalleryItems.push({kind:'existing',path:p,file:null,url:(match&&match.thumb_url)?String(match.thumb_url):(urlByPath[p]||''),name:p.split('/').pop()||'Photo'});});selectedEditImages=[];syncSelectedFiles(editImageInput,selectedEditImages);persistProductImageGallery();};
const syncImageUploadStatus=(count)=>{if(!editImageStatus)return;if(!count){editImageStatus.textContent='No photos yet. The first photo becomes the main listing image.';return;}editImageStatus.textContent=count+' of '+PRODUCT_IMAGE_MAX_COUNT+' photo'+(count===1?'':'s')+'. Drag to change order. The first photo is the main listing image.';};
const closeProductImageLightbox=()=>{if(!productImageLightbox)return;productImageLightbox.classList.add('hidden');if(productImageLightboxImg){productImageLightboxImg.removeAttribute('src');}if(editSurfaceIsPage||editModal.classList.contains('hidden')){document.body.classList.remove('overflow-hidden');}};
const isProductImageLightboxOpen=()=>!!(productImageLightbox&&!productImageLightbox.classList.contains('hidden'));
const openProductImageLightbox=(index)=>{if(!productImageLightbox||!editGalleryItems.length)return;const next=Math.max(0,Math.min(editGalleryItems.length-1,Number(index)||0));productImageLightboxIndex=next;const item=editGalleryItems[next];if(!item)return;if(productImageLightboxImg){productImageLightboxImg.src=item.url||'';productImageLightboxImg.alt=galleryItemLabel(item,next);}if(productImageLightboxCaption){productImageLightboxCaption.textContent=galleryItemLabel(item,next)+' · '+(next+1)+' of '+editGalleryItems.length;}productImageLightbox.classList.remove('hidden');document.body.classList.add('overflow-hidden');};
const stepProductImageLightbox=(delta)=>{if(!editGalleryItems.length)return;const next=(productImageLightboxIndex+delta+editGalleryItems.length)%editGalleryItems.length;openProductImageLightbox(next);};
const moveGalleryItem=(fromIndex,toIndex)=>{const from=Number(fromIndex);let to=Number(toIndex);if(!Number.isFinite(from)||!Number.isFinite(to)||from===to)return;if(from<0||from>=editGalleryItems.length)return;to=Math.max(0,Math.min(editGalleryItems.length-1,to));const [moved]=editGalleryItems.splice(from,1);editGalleryItems.splice(to,0,moved);persistProductImageGallery();renderEditImages();};
const removeGalleryItem=(index)=>{const item=editGalleryItems[index];if(!item)return;revokeGalleryObjectUrl(item);editGalleryItems.splice(index,1);if(isProductImageLightboxOpen()){if(!editGalleryItems.length){closeProductImageLightbox();}else{openProductImageLightbox(Math.min(index,editGalleryItems.length-1));}}persistProductImageGallery();renderEditImages();};
const addEditImageFiles=(fileList)=>{const incoming=[...fileList];if(!incoming.length)return [];const remaining=Math.max(0,PRODUCT_IMAGE_MAX_COUNT-editGalleryItems.length);if(!remaining){setImageUploadError('You can attach up to '+PRODUCT_IMAGE_MAX_COUNT+' photos per product.');return [];}const accepted=[];const rejected=[];incoming.forEach((file)=>{if(!isAcceptedProductImage(file)){rejected.push('Use JPG, PNG, or WebP photos.');return;}if(!isProductImageWithinSize(file)){rejected.push('Each photo must be 4 MB or smaller.');return;}accepted.push(file);});const usable=accepted.slice(0,remaining);if(accepted.length>remaining){rejected.push('Only the first '+(remaining)+' photo'+(remaining===1?' was':'s were')+' added because this product already has '+PRODUCT_IMAGE_MAX_COUNT+' photos.');}const before=editGalleryItems.length;usable.forEach((file)=>{editGalleryItems.push({kind:'new',path:'',file,url:URL.createObjectURL(file),name:file.name||'New photo'});});if(usable.length){setImageUploadError(rejected[0]||'');persistProductImageGallery();renderEditImages();const addedIds=[];let newIndex=0;editGalleryItems.forEach((item,idx)=>{if(item.kind!=='new')return;const id='new:'+newIndex;if(idx>=before)addedIds.push(id);newIndex+=1;});return addedIds;}if(rejected.length){setImageUploadError(rejected[0]);}return [];};
const renderEditImages=()=>{persistProductImageGallery();syncImageUploadStatus(editGalleryItems.length);if(editImageUploader){editImageUploader.classList.toggle('has-photos',editGalleryItems.length>0);}if(!editImagePreview){syncEditCatalogImagesFromRetained();return;}if(!editGalleryItems.length){editImagePreview.innerHTML='';editImagePreview.hidden=true;syncEditCatalogImagesFromRetained();return;}editImagePreview.hidden=false;editImagePreview.innerHTML=editGalleryItems.map((item,index)=>{const name=escapeHtml(item.name||currentProduct?.name||'Product image');const mainClass=index===0?' is-main':'';const badges=(index===0?'<span class="product-image-badge-main">Main</span>':'<span class="product-image-badge-pos">'+(index+1)+'</span>')+(item.kind==='new'?'<span class="product-image-badge-new">New</span>':'');const makeMain=index===0?'':'<button type="button" class="product-image-tile-action" data-gallery-action="main" data-gallery-index="'+index+'" aria-label="Set as main photo">Main</button>';return '<article class="product-image-tile'+mainClass+'" draggable="true" data-gallery-index="'+index+'"><span class="product-image-tile-handle" data-image-drag-handle aria-label="Drag to change photo order">::</span><button type="button" class="product-image-tile-preview" data-gallery-action="view" data-gallery-index="'+index+'" aria-label="View '+name+'"><img src="'+escapeHtml(item.url||'')+'" alt="'+name+'"></button><div class="product-image-tile-badges">'+badges+'</div><div class="product-image-tile-actions">'+makeMain+'<button type="button" class="product-image-tile-action" data-gallery-action="view" data-gallery-index="'+index+'" aria-label="View photo">View</button><button type="button" class="product-image-tile-action is-danger" data-gallery-action="remove" data-gallery-index="'+index+'" aria-label="Remove photo">&times;</button></div></article>';}).join('');syncEditCatalogImagesFromRetained();};
const handleGalleryAction=(action,index)=>{if(action==='view'){openProductImageLightbox(index);return;}if(action==='remove'){removeGalleryItem(index);return;}if(action==='main'){moveGalleryItem(index,0);}};
if(editImagePreview&&!editImagePreview.dataset.galleryBound){editImagePreview.dataset.galleryBound='1';editImagePreview.addEventListener('click',(event)=>{const button=event.target.closest('[data-gallery-action]');if(!button||!editImagePreview.contains(button))return;event.preventDefault();event.stopPropagation();handleGalleryAction(String(button.dataset.galleryAction||''),Number(button.dataset.galleryIndex));});editImagePreview.addEventListener('dragstart',(event)=>{if(event.target.closest('[data-gallery-action]')){event.preventDefault();return;}const tile=event.target.closest('[data-gallery-index]');if(!tile||!editImagePreview.contains(tile))return;galleryDragFromIndex=Number(tile.dataset.galleryIndex);tile.classList.add('is-dragging');if(event.dataTransfer){event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',String(galleryDragFromIndex));}});editImagePreview.addEventListener('dragover',(event)=>{const types=event.dataTransfer?Array.from(event.dataTransfer.types||[]):[];const files=types.includes('Files');const tile=event.target.closest('[data-gallery-index]');if(files){event.preventDefault();if(event.dataTransfer){event.dataTransfer.dropEffect='copy';}return;}if(!tile||galleryDragFromIndex===null)return;event.preventDefault();tile.classList.add('is-drop-target');if(event.dataTransfer){event.dataTransfer.dropEffect='move';}});editImagePreview.addEventListener('dragleave',(event)=>{const tile=event.target.closest('[data-gallery-index]');if(tile){tile.classList.remove('is-drop-target');}});editImagePreview.addEventListener('drop',(event)=>{event.preventDefault();editImagePreview.querySelectorAll('.is-drop-target').forEach((node)=>node.classList.remove('is-drop-target'));const incoming=event.dataTransfer?.files||[];if(incoming.length&&galleryDragFromIndex===null){addEditImageFiles(incoming);return;}const tile=event.target.closest('[data-gallery-index]');if(!tile||galleryDragFromIndex===null)return;moveGalleryItem(galleryDragFromIndex,Number(tile.dataset.galleryIndex));galleryDragFromIndex=null;});editImagePreview.addEventListener('dragend',()=>{galleryDragFromIndex=null;editImagePreview.querySelectorAll('.is-dragging,.is-drop-target').forEach((node)=>node.classList.remove('is-dragging','is-drop-target'));});}
if(productImageLightbox&&!productImageLightbox.dataset.bound){productImageLightbox.dataset.bound='1';productImageLightbox.addEventListener('click',(event)=>{const target=event.target;if(!(target instanceof Element))return;if(target.closest('[data-lightbox-close]')){event.preventDefault();closeProductImageLightbox();return;}if(target.closest('[data-lightbox-prev]')){event.preventDefault();stepProductImageLightbox(-1);return;}if(target.closest('[data-lightbox-next]')){event.preventDefault();stepProductImageLightbox(1);}});}
document.addEventListener('keydown',(event)=>{if(!isProductImageLightboxOpen())return;if(event.key==='ArrowLeft'){event.preventDefault();stepProductImageLightbox(-1);}if(event.key==='ArrowRight'){event.preventDefault();stepProductImageLightbox(1);}});
const syncEditVariationOptions=()=>{editVariationOptions.value=editVariationOptionTags.join(', ');};
const renderEditVariationOptionTags=()=>{editVariationOptionChips.innerHTML=editVariationOptionTags.map((tag,index)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(tag)}<button type="button" class="remove-edit-variation-tag leading-none text-[#94A3B8] hover:text-[#B42318]" data-index="${index}">&times;</button></span>`).join(''); document.querySelectorAll('.remove-edit-variation-tag').forEach((button)=>button.addEventListener('click',()=>{editVariationOptionTags=editVariationOptionTags.filter((_,index)=>index!==Number(button.dataset.index)); syncEditVariationOptions(); renderEditVariationOptionTags();}));};
const addEditVariationOptionTags=(rawValue)=>{const nextTags=String(rawValue||'').split(',').map((value)=>value.trim()).filter(Boolean); if(!nextTags.length) return; editVariationOptionTags=[...editVariationOptionTags,...nextTags]; syncEditVariationOptions(); renderEditVariationOptionTags(); if(editVariationOptionInput) editVariationOptionInput.value='';};
const openVariationEditor=(variationIndex=null)=>{editingVariationIndex=variationIndex; const variation=variationIndex===null?null:editVariationTypes[variationIndex]; editVariationName.value=variation?.name||''; editVariationOptionTags=[...(variation?.options||[])]; syncEditVariationOptions(); renderEditVariationOptionTags(); const modalTitle=document.getElementById('editVariationModalTitle'); const modalLead=document.getElementById('editVariationModalLead'); if(productCreateMode){if(modalTitle){modalTitle.textContent=variation?'Edit option group':'Add an option group';}if(modalLead){modalLead.textContent=variation?'Change the group name or values. Variants below update to match.':'An option group is what shoppers pick, such as Size or Color. Add the values they can choose. Each combination becomes a variant you manage on this step.';}submitEditVariationModal.textContent=variation?'Save option group':'Add option group';}else{if(modalTitle){modalTitle.textContent=variation?'Update Variation':'Add Variation Type';}submitEditVariationModal.textContent=variation?'Update Variation':'Add Variation';} editVariationModal.classList.remove('hidden'); editVariationModal.classList.add('flex');};
const closeVariationEditor=()=>{editingVariationIndex=null; editVariationName.value=''; editVariationOptionTags=[]; syncEditVariationOptions(); renderEditVariationOptionTags(); submitEditVariationModal.textContent=productCreateMode?'Add option group':'Save Variation'; editVariationModal.classList.add('hidden'); editVariationModal.classList.remove('flex');};
const renderVariationInputs=()=>{editVariationTypes=sanitizeEditVariationTypes(editVariationTypes);editVariationHiddenInputs.innerHTML=editVariationTypes.map((t,i)=>`<input type="hidden" name="variation_types[${i}][name]" value="${escapeHtml(t.name||'')}"><input type="hidden" name="variation_types[${i}][type]" value="${escapeHtml(t.type||'select')}">${(t.options||[]).map((o,j)=>`<input type="hidden" name="variation_types[${i}][options][${j}]" value="${escapeHtml(o)}">`).join('')}`).join('');};
const variantImageOptionLabel=(img,idx)=>{if(img&&img.picker_label)return String(img.picker_label);return 'Catalog photo '+(idx+1);};
const editDistributeStockPanel=document.getElementById('editDistributeStockPanel');const editDistributeTotal=document.getElementById('editDistributeTotal');const editDistributeEqualBtn=document.getElementById('editDistributeEqualBtn');const editBulkPriceInput=document.getElementById('editBulkPriceInput');const editResetVariantPrices=document.getElementById('editResetVariantPrices');const editVariantToolsStatus=document.getElementById('editVariantToolsStatus');const editDistributeCount=document.getElementById('editDistributeCount');
const editStockAllocMode=document.getElementById('edit_inventory_stock_allocation_mode');const editApplySameHidden=document.getElementById('edit_inventory_apply_same_stock');const editSplitTotalHidden=document.getElementById('edit_inventory_split_total');
const setManualStockAllocMode=()=>{if(editStockAllocMode)editStockAllocMode.value='manual';if(editApplySameHidden)editApplySameHidden.value='';if(editSplitTotalHidden)editSplitTotalHidden.value='';};
const setApplySameStockMode=(qty)=>{const n=Math.max(0,parseInt(String(qty),10)||0);if(editStockAllocMode)editStockAllocMode.value='apply_same_each';if(editApplySameHidden)editApplySameHidden.value=String(n);if(editSplitTotalHidden)editSplitTotalHidden.value='';};
const setSplitTotalMode=(total)=>{const n=Math.max(0,parseInt(String(total),10)||0);if(editStockAllocMode)editStockAllocMode.value='split_total';if(editSplitTotalHidden)editSplitTotalHidden.value=String(n);if(editApplySameHidden)editApplySameHidden.value='';};
const syncSellingLabels=(mode)=>{document.querySelectorAll('[data-selling-label]').forEach((el)=>{el.classList.toggle('hidden', el.getAttribute('data-selling-label')!==mode);});document.querySelectorAll('[data-selling-help]').forEach((el)=>{const key=el.getAttribute('data-selling-help')||'';const show=mode==='multiple'?(key.endsWith('-multiple')||key==='multiple'):(key.endsWith('-single')||key==='single');el.classList.toggle('hidden', !show);});document.querySelectorAll('[data-selling-copy]').forEach((el)=>{el.classList.toggle('hidden', el.getAttribute('data-selling-copy')!==mode);});};
const merchantWantsMultiple=()=>editSellingSetup?sellingMode==='multiple':hasOptionGroups();
const applySellingModeFromProduct=()=>{includeStandardBase=hasOptionGroups()&&editRows.some(isEmptyOptionMap)&&editRows.some((row)=>!isEmptyOptionMap(row));sellingMode=hasOptionGroups()?'multiple':'single';if(editIncludeStandardBase){editIncludeStandardBase.checked=includeStandardBase;}if(editProductCompareAt){if(!hasOptionGroups()&&editRows.length){editProductCompareAt.value=editRows[0].compare_at_price||'';}else if(editRows.length){const values=[...new Set(editRows.map((r)=>String(r.compare_at_price??'')))];editProductCompareAt.value=values.length===1?values[0]:'';}}};
const updateSellingModeUi=()=>{const mode=merchantWantsMultiple()?'multiple':'single';sellingMode=mode;if(editSellingSetup){editSellingSetup.querySelectorAll('[data-selling-setup]').forEach((btn)=>{btn.setAttribute('aria-pressed', btn.getAttribute('data-selling-setup')===mode?'true':'false');});}syncSellingLabels(mode);const showGroups=mode==='multiple';const showMatrix=mode==='multiple'&&hasOptionGroups();if(editSellingSetup){if(editOptionGroupsSection){if(showGroups){editOptionGroupsSection.removeAttribute('data-selling-hidden');}else{editOptionGroupsSection.setAttribute('data-selling-hidden','true');}}if(editInventorySection){if(showMatrix){editInventorySection.removeAttribute('data-selling-hidden');}else{editInventorySection.setAttribute('data-selling-hidden','true');}}}if(editStandardBaseWrap){editStandardBaseWrap.classList.toggle('hidden', !showMatrix);}if(editMultiDefaultsSummary){editMultiDefaultsSummary.classList.toggle('hidden', !showMatrix);}const totalLabel=document.getElementById('editMultiTotalStockLabel');if(totalLabel){totalLabel.textContent=String(editRows.reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??''),10)||0),0));}if(editIncludeStandardBase){editIncludeStandardBase.checked=!!includeStandardBase;}};
const setSellingMode=(mode)=>{if(mode==='single'&&hasOptionGroups()){if(!window.confirm('Switching to Single item removes sizes, colors, and variant rows. Stock is combined into one amount.')){updateSellingModeUi();return;}syncEditRowsFromDom();const total=getEditRowsStockTotal(editRows);const emptyRow=editRows.find(isEmptyOptionMap);const first=emptyRow||editRows[0]||{};includeStandardBase=false;sellingMode='single';editVariationTypes=[];editRows=[makeInventoryRow({id:emptyRow?.id??'',sku:first.sku||'',price:'',compare_at_price:first.compare_at_price||defaultCompareAtValue(),stock:String(total),stock_alert:first.stock_alert??editStockAlert?.value??0,shipping_weight:first.shipping_weight||'',product_image_id:first.product_image_id||'',product_image_ids:Array.isArray(first.product_image_ids)?first.product_image_ids.slice():(first.product_image_id?[first.product_image_id]:[]),custom_fields:cloneCustomFields(first)})];if(editProductStock){editProductStock.value=String(total);}renderVariationInputs();renderVariationCards();renderVariantRows({skipDomSync:true});return;}sellingMode=mode;if(mode==='multiple'){renderVariationCards();updateInventoryToolsVisibility();return;}updateSellingModeUi();};
const hasMultipleVariants=()=>hasOptionGroups();const stockDisplayValue=(value)=>String(value ?? '');const syncSimpleStockFromRows=()=>{if(!editProductStock)return;if(isSimpleInventoryProduct()&&editRows.length){editProductStock.value=stockDisplayValue(editRows[0].stock ?? 0);}else if(!hasMultipleVariants()){editProductStock.value=stockDisplayValue(editProductStock.value ?? 0);}};const syncRowsFromSimpleStock=()=>{if(!editProductStock||!isSimpleInventoryProduct()||!editRows.length)return;const qty=String(Math.max(0,parseInt(String(editProductStock.value??'0'),10)||0));editRows[0]={...editRows[0],stock:qty};};const syncRowsFromSimpleAlert=()=>{if(!editStockAlert||!isSimpleInventoryProduct()||!editRows.length)return;const alertQty=String(Math.max(0,parseInt(String(editStockAlert.value??'0'),10)||0));editRows[0]={...editRows[0],stock_alert:alertQty};};const syncWorkspaceInventorySummary=()=>{const el=document.getElementById('workspaceEditInventorySummary');if(!el)return;const total=editRows.reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??''),10)||0),0);el.textContent=total.toLocaleString();};const updateTotalStockDisplay=()=>{const total=editRows.reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??''),10)||0),0);if(editTotalStockDisplay){editTotalStockDisplay.textContent=String(total);}const multiTotalLabel=document.getElementById('editMultiTotalStockLabel');if(multiTotalLabel){multiTotalLabel.textContent=String(total);}if(editBulkPrice&&editPrice){editBulkPrice.value=editPrice.value||'';}syncSimpleStockFromRows();syncWorkspaceInventorySummary();};const updateInventoryToolsVisibility=()=>{syncSimpleRowPriceFromBase();const multi=hasMultipleVariants();if(editInventoryToolsPanel){editInventoryToolsPanel.classList.toggle('hidden',!multi);}if(editDistributeCount){editDistributeCount.textContent=editRows.length===1?'1 variant':editRows.length+' variants';}if(multi&&editBulkPriceInput&&editBulkPriceInput.value===''&&editPrice&&editPrice.value!==''){editBulkPriceInput.placeholder=editPrice.value;}if(editSimpleStockWrap){editSimpleStockWrap.classList.toggle('hidden',multi);}if(editMultiStockHint){editMultiStockHint.classList.toggle('hidden',!multi);}if(editSimpleStockAlertWrap){editSimpleStockAlertWrap.classList.toggle('hidden',multi);}if(editMultiStockAlertHint){editMultiStockAlertHint.classList.toggle('hidden',!multi);}if(!multi){clearStockCarryNotice();}else if(pendingStockCarryTotal&&editStockCarryNotice){editStockCarryNotice.classList.remove('hidden');}if(editInventorySectionTitle){editInventorySectionTitle.textContent=multi?'Sellable variants':(productCreateMode?'Sellable variants':'Inventory details');}if(editInventorySectionLead){editInventorySectionLead.textContent=multi?(productCreateMode?'Each row is one sellable version. Enter stock on every row. Blank price uses the default price. Assign at least one photo to a variant.':'Each row is one sellable version. Blank price uses the default price. Total stock is the sum of all rows.'):'Price and stock come from the fields above.';}if(editPreviewSection){editPreviewSection.classList.add('hidden');}updateTotalStockDisplay();updateSellingModeUi();};
const renderPreview=()=>{const previewRows=editRows.map((row)=>({label:editVariationTypes.map((v,i)=>{const s=row.option_map?.[i]; return s!==undefined&&s!==''?(v.options?.[s]||''):'';}).filter(Boolean).join(' / ')||'Single inventory row',sku:row.sku||'Auto-generated',price:row.price||editPrice.value||'',stock:stockDisplayValue(row.stock ?? editBulkStock.value ?? '')})); const rows=previewRows.length?previewRows:[{label:'Add option groups to create variants',sku:'-',price:'-',stock:'-'}]; editPreviewTableBody.innerHTML=rows.map((r)=>`<tr><td class="px-2 py-4 text-[#0F172A]">${escapeHtml(r.label)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.sku)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.price)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.stock)}</td></tr>`).join(''); editPreviewCount.textContent=previewRows.length?`${previewRows.length} combination(s)`:'0 rows';};
const isSimpleInventoryProduct=()=>!hasOptionGroups();const syncSimpleRowPriceFromBase=()=>{if(!isSimpleInventoryProduct()||!editRows.length)return;const price=editPrice?.value||editRows[0].price||'';editRows[0]={...editRows[0],price};};const syncEditRowsFromDom=()=>{if(!editVariantRows)return;const mapsByRow=new Map();editVariantRows.querySelectorAll('input[name*="[option_map]"]').forEach((el)=>{const match=String(el.name||'').match(/variants\[(\d+)\]\[option_map\]\[(\d+)\]/);if(!match)return;const r=Number(match[1]);const current=mapsByRow.get(r)||{};current[Number(match[2])]=el.value;mapsByRow.set(r,current);});editVariantRows.querySelectorAll('.edit-row-input,.edit-row-select').forEach((el)=>{const r=Number(el.dataset.rowIndex);const f=el.dataset.rowField;if(!Number.isFinite(r)||!f||!editRows[r])return;const memoryKey=getEditRowKey(editRows[r].option_map||{});const domKey=getEditRowKey(mapsByRow.get(r)||{});if(memoryKey!==domKey)return;editRows[r][f]=el.value;});};const updateVariantPhotoThumb=(rowIndex)=>{refreshVariantPhotoCell(rowIndex);};
const renderVariantRows=(opts={})=>{if(!opts.skipDomSync){syncEditRowsFromDom();}if(!editRows.length){editVariantRows.innerHTML='';updateInventoryToolsVisibility();renderPreview();return;}const missingGalleryNote=(!editCatalogImages.length&&editRows.length)?(productCreateMode?'<div class="mb-3 rounded-lg border border-[#D8E8E1] bg-[#F4FBF8] px-3 py-2 text-xs text-[#0A4335]" data-variant-gallery-empty-state>No listing photos yet. Add photos here or under Images, then assign them to variants.</div>':'<div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950" data-variant-gallery-empty-state>Upload listing photos under Images, then assign one or more to each variant.</div>'):'';const fieldClass='edit-row-input w-full';const rowsHtml=editRows.map((row,rowIndex)=>{const idHidden=row.id?'<input type="hidden" name="variants['+rowIndex+'][id]" value="'+escapeHtml(String(row.id))+'">':'';const optionHiddens=Object.entries(row.option_map||{}).map(([variationIndex,optionIndex])=>'<input type="hidden" name="variants['+rowIndex+'][option_map]['+variationIndex+']" value="'+escapeHtml(optionIndex)+'">').join('');const isStdRow=hasOptionGroups()&&isEmptyOptionMap(row);const combinationLabel=isStdRow?'Standard / Base':(editVariationTypes.map((v,i)=>{const s=row.option_map?.[i];return (s!==undefined&&s!=='')?(v.options?.[s]||''):'';}).filter(Boolean).join(' / ')||'Default');const photoHtml=buildVariantPhotoCellHtml(rowIndex,row);const cfs=Array.isArray(row.custom_fields)&&row.custom_fields.length?row.custom_fields:[];const cfHtml=cfs.length?cfs.map((cf,i)=>'<div class="mb-2 flex items-start gap-2"><input type="text" data-cf-field="key" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][key]" value="'+escapeHtml(cf.key||'')+'" placeholder="Key" class="variant-cf-input w-1/3 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><select data-cf-field="type" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][type]" class="variant-cf-input w-1/4 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><option value="text" '+(cf.type==='text'?'selected':'')+'>Text</option><option value="number" '+(cf.type==='number'?'selected':'')+'>Number</option><option value="boolean" '+(cf.type==='boolean'?'selected':'')+'>Boolean</option><option value="list" '+(cf.type==='list'?'selected':'')+'>List</option></select><input type="text" data-cf-field="value" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" name="variants['+rowIndex+'][custom_fields]['+i+'][value]" value="'+escapeHtml(cf.value||'')+'" placeholder="Value" class="variant-cf-input flex-1 rounded-lg border border-[#E2E8F0] px-2 py-1.5 text-xs"><button type="button" class="remove-variant-cf rounded border border-transparent px-1 py-1 text-xs text-rose-600 hover:border-rose-200 hover:bg-rose-50" data-row-index="'+rowIndex+'" data-cf-index="'+i+'" title="Remove field">&times;</button></div>').join(''):'<p class="mb-2 text-xs text-[#64748B]">No additional details added.</p>';const isPanelOpen=openVariantPanels.has(String(rowIndex));const stockAlertValue=escapeHtml(stockDisplayValue(row.stock_alert ?? editStockAlert?.value ?? 0));const extraHtml='<div class="mt-3"><p class="mb-2 text-xs font-semibold text-[#64748B]">Additional details</p>'+cfHtml+'<button type="button" class="add-variant-cf text-xs font-semibold text-[#0052CC]" data-row-index="'+rowIndex+'">+ Add field</button></div>';if(isSimpleInventoryProduct()){const hiddenFields=idHidden+optionHiddens+'<input type="hidden" name="variants['+rowIndex+'][sku]" value="'+escapeHtml(row.sku||'')+'" data-row-index="'+rowIndex+'" data-row-field="sku" class="edit-row-input"><input type="hidden" name="variants['+rowIndex+'][compare_at_price]" value="'+escapeHtml(row.compare_at_price??'')+'" data-row-index="'+rowIndex+'" data-row-field="compare_at_price" class="edit-row-input"><input type="hidden" name="variants['+rowIndex+'][price]" value=""><input type="hidden" name="variants['+rowIndex+'][stock]" value="'+escapeHtml(stockDisplayValue(row.stock))+'" data-row-index="'+rowIndex+'" data-row-field="stock" class="edit-row-input"><input type="hidden" name="variants['+rowIndex+'][stock_alert]" value="'+stockAlertValue+'" data-row-index="'+rowIndex+'" data-row-field="stock_alert" class="edit-row-input"><input type="hidden" data-variant-image-hidden="1" data-row-index="'+rowIndex+'" name="variants['+rowIndex+'][product_image_id]" value="'+escapeHtml(String(row.product_image_id??''))+'">';if(editSellingSetup){return '<div class="hidden">'+hiddenFields+'</div>';}return '<div class="variant-simple-row">'+idHidden+optionHiddens+'<div><label>SKU</label><input type="text" name="variants['+rowIndex+'][sku]" value="'+escapeHtml(row.sku||'')+'" data-row-index="'+rowIndex+'" data-row-field="sku" class="'+fieldClass+'"></div><div><label>Compare-at</label><input type="number" min="0" step="0.01" name="variants['+rowIndex+'][compare_at_price]" value="'+escapeHtml(row.compare_at_price??'')+'" data-row-index="'+rowIndex+'" data-row-field="compare_at_price" class="'+fieldClass+'"></div><input type="hidden" name="variants['+rowIndex+'][price]" value=""><input type="hidden" name="variants['+rowIndex+'][stock]" value="'+escapeHtml(stockDisplayValue(row.stock))+'" data-row-index="'+rowIndex+'" data-row-field="stock" class="edit-row-input"><input type="hidden" name="variants['+rowIndex+'][stock_alert]" value="'+stockAlertValue+'" data-row-index="'+rowIndex+'" data-row-field="stock_alert" class="edit-row-input"><div><label>Photo</label>'+photoHtml+'</div><p class="variant-simple-note">Price and stock come from the fields above.</p></div>';}const extraRow=isPanelOpen?'<tr class="variant-table-extra"><td colspan="8">'+extraHtml+'</td></tr>':'';return '<tr><td class="variant-table-name">'+idHidden+escapeHtml(combinationLabel)+(isStdRow?'<span class="variant-base-badge">Base</span>':'')+optionHiddens+'</td><td><input type="text" name="variants['+rowIndex+'][sku]" value="'+escapeHtml(row.sku||'')+'" data-row-index="'+rowIndex+'" data-row-field="sku" class="'+fieldClass+'"></td><td>'+(isStdRow?('<input type="hidden" name="variants['+rowIndex+'][price]" value=""><input type="number" min="0" step="0.01" value="" placeholder="'+escapeHtml(editPrice?.value||'')+'" class="'+fieldClass+' opacity-60 cursor-not-allowed" disabled title="Uses Default price" aria-label="Uses default price">'):('<input type="number" min="0" step="0.01" name="variants['+rowIndex+'][price]" value="'+escapeHtml(row.price||'')+'" placeholder="'+escapeHtml(editPrice?.value||'')+'" data-row-index="'+rowIndex+'" data-row-field="price" class="'+fieldClass+'">'))+'</td><td><input type="number" min="0" step="0.01" name="variants['+rowIndex+'][compare_at_price]" value="'+escapeHtml(row.compare_at_price??'')+'" data-row-index="'+rowIndex+'" data-row-field="compare_at_price" class="'+fieldClass+'"></td><td><input type="number" min="0" step="1" name="variants['+rowIndex+'][stock]" value="'+escapeHtml(stockDisplayValue(row.stock))+'" data-row-index="'+rowIndex+'" data-row-field="stock" class="'+fieldClass+'"></td><td><input type="number" min="0" step="1" name="variants['+rowIndex+'][stock_alert]" value="'+stockAlertValue+'" data-row-index="'+rowIndex+'" data-row-field="stock_alert" class="'+fieldClass+'"></td><td>'+photoHtml+'</td><td><button type="button" class="toggle-variant-details variant-table-more-btn" data-row-index="'+rowIndex+'">'+(isPanelOpen?'Hide':'More')+'</button></td></tr>'+extraRow;}).join('');if(isSimpleInventoryProduct()){editVariantRows.innerHTML=missingGalleryNote+rowsHtml;}else{editVariantRows.innerHTML=missingGalleryNote+'<div class="variant-table-wrap"><table class="variant-table"><thead><tr><th>Variant</th><th>SKU</th><th>Price'+(productCreateMode?' <span class="pf-req" aria-hidden="true">*</span>':'')+'</th><th>Compare-at</th><th>Stock'+(productCreateMode?' <span class="pf-req" aria-hidden="true">*</span>':'')+'</th><th>Alert</th><th>Photo'+(productCreateMode?' <span class="pf-req" aria-hidden="true">*</span>':'')+'</th><th><span class="sr-only">More</span></th></tr></thead><tbody>'+rowsHtml+'</tbody></table></div>';}document.querySelectorAll('.edit-row-input,.edit-row-select').forEach((i)=>{const fn=(event)=>{const r=Number(i.dataset.rowIndex);const f=i.dataset.rowField;if(!Number.isFinite(r)||!f||!editRows[r])return;editRows[r][f]=i.value;if(f==='stock'||f==='sku'||f==='price'||f==='compare_at_price'||f==='stock_alert'){setManualStockAllocMode();}if(f==='stock'||f==='stock_alert'){updateTotalStockDisplay();}if(f==='price'){refreshInheritedPriceHints();}if(f==='product_image_id'){if(event&&event.type==='input'){return;}syncVariantImageHidden(r);updateVariantPhotoThumb(r);renderPreview();return;}renderPreview();};i.addEventListener('input',fn);i.addEventListener('change',fn);});document.querySelectorAll('.toggle-variant-details').forEach((b)=>b.addEventListener('click',()=>{const idx=String(b.dataset.rowIndex);if(openVariantPanels.has(idx)){openVariantPanels.delete(idx);}else{openVariantPanels.add(idx);}renderVariantRows({skipDomSync:true});}));document.querySelectorAll('.add-variant-cf').forEach((b)=>b.addEventListener('click',()=>{const idx=Number(b.dataset.rowIndex);if(!Array.isArray(editRows[idx].custom_fields))editRows[idx].custom_fields=[];editRows[idx].custom_fields.push({key:'',type:'text',value:''});renderVariantRows({skipDomSync:true});}));document.querySelectorAll('.remove-variant-cf').forEach((b)=>b.addEventListener('click',()=>{const r=Number(b.dataset.rowIndex);const c=Number(b.dataset.cfIndex);editRows[r].custom_fields.splice(c,1);renderVariantRows({skipDomSync:true});}));document.querySelectorAll('.variant-cf-input').forEach((i)=>{const fn=()=>{const r=Number(i.dataset.rowIndex);const c=Number(i.dataset.cfIndex);const f=i.dataset.cfField;editRows[r].custom_fields[c][f]=i.value;};i.addEventListener('input',fn);i.addEventListener('change',fn);});updateInventoryToolsVisibility();renderPreview();refreshInheritedPriceHints();};
const getEditRowsStockTotal=(rows)=>(Array.isArray(rows)?rows:[]).reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??''),10)||0),0);const refreshInheritedPriceHints=()=>{if(!editVariantRows)return;const base=editPrice?editPrice.value:'';const ph=base!==''?('Default '+base):'Default';editVariantRows.querySelectorAll('.edit-row-input[data-row-field="price"], input[aria-label="Uses default price"]').forEach((input)=>{input.placeholder=ph;const hint=input.parentElement?input.parentElement.querySelector('p'):null;if(hint){hint.textContent=(input.disabled||input.value==='')?'Inherits the default price':'Custom price for this variant';}});};const setVariantToolsStatus=(message)=>{if(!editVariantToolsStatus)return;editVariantToolsStatus.textContent=message||'';editVariantToolsStatus.hidden=!message;};const clearStockCarryNotice=()=>{pendingStockCarryTotal=null;setVariantToolsStatus('');};const normalizeRowsAfterVariationChange=()=>{syncEditRowsFromDom();if(editRows.length===1&&isEmptyOptionMap(editRows[0])&&editProductStock){editRows[0]={...editRows[0],stock:String(Math.max(0,parseInt(String(editProductStock.value??'0'),10)||0))};}const previousRows=editRows.slice();const previousTotal=getEditRowsStockTotal(previousRows);const hadSimpleRow=previousRows.length===1&&isEmptyOptionMap(previousRows[0]);if(!hasOptionGroups()){includeStandardBase=false;preservedBaseStock=null;const emptyRow=previousRows.find(isEmptyOptionMap);const first=emptyRow||previousRows[0]||{};editRows=[makeInventoryRow({id:emptyRow?.id??'',sku:first.sku||'',price:'',compare_at_price:first.compare_at_price||defaultCompareAtValue(),stock:String(previousTotal||Math.max(0,parseInt(String(editProductStock?.value??'0'),10)||0)),stock_alert:first.stock_alert??editStockAlert?.value??0,shipping_weight:first.shipping_weight||'',product_image_id:first.product_image_id||'',product_image_ids:Array.isArray(first.product_image_ids)?first.product_image_ids.slice():(first.product_image_id?[first.product_image_id]:[]),custom_fields:cloneCustomFields(first)})];return;}if(editSellingSetup){sellingMode='multiple';}editRows=buildEditRowsFromVariationTypes(previousRows);if(hadSimpleRow){preservedBaseStock=String(previousRows[0]?.stock??previousTotal??'0');if(editBulkStock){editBulkStock.value='';}}inheritStandardBasePrices();const newTotal=getEditRowsStockTotal(editRows);if(newTotal>0){clearStockCarryNotice();}};
const renderVariationCards=()=>{ if(!editVariationTypes.length){editVariationTypesList.classList.add('hidden'); editNoVariationState.classList.remove('hidden'); return;} editVariationTypesList.classList.remove('hidden'); editNoVariationState.classList.add('hidden'); editVariationTypesList.innerHTML=editVariationTypes.map((t,i)=>`<div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3"><div class="mb-2 flex items-center justify-between gap-3"><div><span class="text-sm font-semibold text-[#0F172A]">${escapeHtml(t.name||'Untitled')}</span><div class="mt-0.5 text-[11px] text-[#94A3B8]">Option group ${i+1}</div></div><div class="flex items-center gap-2"><button type="button" class="edit-variation-type text-xs font-semibold text-[#0052CC]" data-variation-index="${i}">Edit</button><button type="button" class="remove-variation-type text-xs font-semibold text-[#B42318]" data-variation-index="${i}">Remove</button></div></div><div class="flex flex-wrap gap-2">${(t.options||[]).map((o,j)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium">${escapeHtml(o)}<button type="button" class="edit-remove-variation-option leading-none text-[#94A3B8] hover:text-[#B42318]" data-variation-index="${i}" data-option-index="${j}">&times;</button></span>`).join('')}</div></div>`).join(''); document.querySelectorAll('.edit-remove-variation-option').forEach((b)=>b.addEventListener('click',()=>{const vi=Number(b.dataset.variationIndex),oi=Number(b.dataset.optionIndex); if(!editVariationTypes[vi]) return; editVariationTypes[vi].options.splice(oi,1); if(!(editVariationTypes[vi].options||[]).length) editVariationTypes.splice(vi,1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows({skipDomSync:true});})); document.querySelectorAll('.edit-variation-type').forEach((b)=>b.addEventListener('click',()=>openVariationEditor(Number(b.dataset.variationIndex)))); document.querySelectorAll('.remove-variation-type').forEach((b)=>b.addEventListener('click',()=>{editVariationTypes.splice(Number(b.dataset.variationIndex),1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows({skipDomSync:true});}));};
const closeAll=()=>{closeVariantPhotoPicker();closeProductImageLightbox();if(editSurfaceIsPage){editVariationModal.classList.add('hidden');editVariationModal.classList.remove('flex');deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');return;}editModal.classList.add('hidden');editModal.classList.remove('flex');editVariationModal.classList.add('hidden');editVariationModal.classList.remove('flex');deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');document.body.classList.remove('overflow-hidden');};
const syncEditShippingVisibility=()=>{const type=String((editTypeValue&&editTypeValue.value)||'physical').toLowerCase();const show=type===''||type==='physical';if(editShippingWeightWrap){editShippingWeightWrap.classList.toggle('hidden',!show);}};const sanitizeEditVariationTypes=(types)=>(Array.isArray(types)?types:[]).map((t)=>({name:String(t?.name||'').trim(),type:String(t?.type||'select'),options:(Array.isArray(t?.options)?t.options:[]).map((o)=>String(o||'').trim()).filter(Boolean)})).filter((t)=>t.name!==''&&t.options.length>0);const openEdit=(product)=>{currentProduct=product;const isCreate=!!product.is_create||!product.id;const catalogPaths=(Array.isArray(product.catalog_images)?product.catalog_images:[]).map((img)=>img&&img.image_path?String(img.image_path):'').filter((p)=>p!=='');retainedExistingImages=JSON.parse(JSON.stringify(catalogPaths.length?catalogPaths:(product.image_paths||[])));hydrateProductImageGalleryFromProduct(product);setManualStockAllocMode();clearStockCarryNotice();if(editProductId){editProductId.value=product.id||'';}if(editForm){const updateUrl=product.update_url||(product.id?String(editForm.dataset.productUpdateUrlTemplate||'').replace('__PRODUCT_ID__',String(product.id)):'');const storeUrl=product.store_url||editForm.dataset.productStoreUrl||'';editForm.action=isCreate?(storeUrl||editForm.action):(updateUrl||editForm.action);}if(deleteForm&&product.delete_url){deleteForm.action=product.delete_url;}editName.value=product.name||'';editDescription.value=product.description||'';editSku.value=product.sku||'';editPrice.value=product.base_price||'';editStockAlert.value=product.stock_alert||0;if(editProductStock){const liveVals=(typeof window.readLiveProductValues==='function'&&product.id!=null)?window.readLiveProductValues(product.id):null;if(liveVals&&Array.isArray(liveVals.variants)&&liveVals.variants.length&&Array.isArray(product.variants)&&product.variants.length>1){const byId=new Map(liveVals.variants.map((row)=>[String(row.id),row]));product.variants=product.variants.map((row)=>{const match=byId.get(String(row.id));return match?{...row,stock:String(Math.max(0,parseInt(String(match.stock??0),10)||0)),stock_alert:match.stock_alert!=null?Math.max(0,parseInt(String(match.stock_alert),10)||0):row.stock_alert}:row;});}const liveStockRaw=liveVals&&(liveVals.inventory!=null?liveVals.inventory:(liveVals.stock!=null?liveVals.stock:null));const variantStockTotal=Array.isArray(product.variants)&&product.variants.length?product.variants.reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??0),10)||0),0):null;const liveDefault=liveStockRaw!=null&&liveStockRaw!==''?Math.max(0,parseInt(String(liveStockRaw),10)||0):(product.default_stock!=null&&product.default_stock!==''?Math.max(0,parseInt(String(product.default_stock),10)||0):null);const initialStock=liveDefault!=null&&(variantStockTotal===null||!Array.isArray(product.variants)||product.variants.length<=1||(liveVals&&Array.isArray(liveVals.variants)&&liveVals.variants.length))?liveDefault:(variantStockTotal!=null?variantStockTotal:(product.default_stock??0));editProductStock.value=stockDisplayValue(initialStock);if(Array.isArray(product.variants)&&product.variants.length===1){product.variants[0].stock=stockDisplayValue(initialStock);}product.default_stock=Number(initialStock);}if(editProductIsTaxable){editProductIsTaxable.checked=product.is_taxable!==false;}if(editShippingWeight){editShippingWeight.value=(product.shipping_weight!=null&&product.shipping_weight!=='')?String(product.shipping_weight):'';}if(typeof syncEditShippingVisibility==='function'){syncEditShippingVisibility();}if(editBrandId){editBrandId.value=product.brand_id!=null&&product.brand_id!==''?String(product.brand_id):'';}if(editTagIds){const selected=new Set((product.tag_ids||[]).map((id)=>Number(id)));[...editTagIds.options].forEach((opt)=>{opt.selected=selected.has(Number(opt.value));});}if(editCategoryIds){const csel=new Set((product.category_ids||[]).map((id)=>Number(id)));[...editCategoryIds.options].forEach((opt)=>{opt.selected=csel.has(Number(opt.value));});}editBulkPrice.value=product.base_price||'';editBulkStock.value='';if(editBulkPriceInput){editBulkPriceInput.value='';}if(editDistributeTotal){editDistributeTotal.value='';}setVariantToolsStatus('');const productBehaviorType=defaultTypes.includes(product.product_type)?product.product_type:'physical';const customType=(typeof product.custom_product_type==='string'?product.custom_product_type:'').trim();if(editCustomTypeBehavior){editCustomTypeBehavior.value=productBehaviorType;}if(customType!==''&&editCustomTypeInput){editCustomTypeInput.value=customType;editTypeSelect.value='__custom__';}else{if(editCustomTypeInput)editCustomTypeInput.value='';editTypeSelect.value=productBehaviorType;}editVariationTypes=sanitizeEditVariationTypes(JSON.parse(JSON.stringify(product.variation_types||[])));editRows=JSON.parse(JSON.stringify(product.variants||[]));if(!editRows.length&&editVariationTypes.length){editRows=buildEditRowsFromVariationTypes(editRows);}inheritStandardBasePrices();if(!editRows.length&&isCreate){editRows=[{id:'',option_map:{},sku:'',price:product.base_price||'',compare_at_price:'',stock:stockDisplayValue(editProductStock?.value||product.default_stock||0),stock_alert:product.stock_alert||5,shipping_weight:'',product_image_id:'',product_image_ids:[],custom_fields:[]}];}applySellingModeFromProduct();renderAdditionalDetailRows(Array.isArray(product.custom_fields)&&product.custom_fields.length?product.custom_fields:[{key:'',type:'text',value:''}]);renderProductAttributeRows(product.attribute_term_ids||[]);renderEditImages();syncType();renderVariationInputs();renderVariationCards();renderVariantRows({skipDomSync:true});if(product&&product.id!=null&&typeof window.readLiveProductValues==='function'){const live=window.readLiveProductValues(product.id);if(live){if(live.stock!=null&&live.stock!==''&&isSimpleInventoryProduct()&&editRows.length){const qty=String(Math.max(0,parseInt(String(live.stock),10)||0));editRows[0]={...editRows[0],stock:qty};if(editProductStock){editProductStock.value=qty;}renderVariantRows({skipDomSync:true});}else if(Array.isArray(live.variants)&&live.variants.length&&editRows.length>1){const byId=new Map(live.variants.map((row)=>[String(row.id),row]));editRows=editRows.map((row)=>{const match=byId.get(String(row.id));return match?{...row,stock:String(Math.max(0,parseInt(String(match.stock??0),10)||0)),stock_alert:match.stock_alert!=null?String(Math.max(0,parseInt(String(match.stock_alert),10)||0)):row.stock_alert}:row;});const liveTotal=editRows.reduce((sum,row)=>sum+Math.max(0,parseInt(String(row.stock??0),10)||0),0);if(editProductStock){editProductStock.value=stockDisplayValue(liveTotal);}renderVariantRows({skipDomSync:true});syncWorkspaceInventorySummary();}if(live.price!=null&&live.price!==''&&editPrice){editPrice.value=String(live.price);if(isSimpleInventoryProduct()){syncSimpleRowPriceFromBase();renderVariantRows({skipDomSync:true});}}}}if(editSurfaceIsPage){document.body.classList.remove('overflow-hidden');}else{editModal.classList.remove('hidden');editModal.classList.add('flex');document.body.classList.add('overflow-hidden');}};
const parseProductPayload=(button)=>{if(typeof window.resolveProductEditPayloadFromButton==='function'){const resolved=window.resolveProductEditPayloadFromButton(button);if(resolved){return resolved;}}try{const raw=button?.getAttribute?.('data-product')||button?.dataset?.product||'';return raw?JSON.parse(raw):null;}catch(error){return null;}};
window.openProductEditModalFromElement=(button)=>{const product=parseProductPayload(button); if(product){openEdit(product);}};
window.openProductDeleteModalFromElement=(button)=>{const product=parseProductPayload(button); if(!product) return; openEdit(product); openDeleteWarning?.click();};
if(productCreateMode){window.__productCreateGate={listingPhotoCount:()=>editGalleryItems.length,sellingMode:()=>sellingMode,optionGroupCount:()=>sanitizeEditVariationTypes(editVariationTypes).length,snapshotVariants:()=>{syncEditRowsFromDom();return editRows.map((row)=>({stock:String(row.stock??''),price:String(row.price??''),imageCount:rowImageIds(row).length,isBase:isEmptyOptionMap(row),isOption:isOptionVariantRow(row)}));}};}
document.addEventListener('click',(event)=>{const target=event.target; if(!(target instanceof Element)) return; const editButton=target.closest('.js-open-edit-product-modal'); if(editButton instanceof HTMLButtonElement){const product=parseProductPayload(editButton); if(product){openEdit(product);} return;} const deleteButton=target.closest('.js-open-delete-product-modal'); if(deleteButton instanceof HTMLButtonElement){const product=parseProductPayload(deleteButton); if(!product) return; openEdit(product); openDeleteWarning?.click();}});
closeButtons.forEach((b)=>b?.addEventListener('click',closeAll)); const syncRowsFromSimpleCompareAt=()=>{if(!editProductCompareAt||!isSimpleInventoryProduct()||!editRows.length)return;editRows[0]={...editRows[0],compare_at_price:editProductCompareAt.value};};editSellingSetup?.querySelectorAll('[data-selling-setup]').forEach((btn)=>btn.addEventListener('click',()=>setSellingMode(btn.getAttribute('data-selling-setup')||'single')));editIncludeStandardBase?.addEventListener('change',()=>{includeStandardBase=!!editIncludeStandardBase.checked;if(!hasOptionGroups()){includeStandardBase=false;editIncludeStandardBase.checked=false;return;}syncEditRowsFromDom();if(!includeStandardBase){const leaving=editRows.find(isEmptyOptionMap);if(leaving){preservedBaseStock=String(leaving.stock??'0');}}editRows=buildEditRowsFromVariationTypes(editRows.slice());if(includeStandardBase&&preservedBaseStock!=null){editRows=editRows.map((r)=>isEmptyOptionMap(r)?{...r,stock:preservedBaseStock}:r);}inheritStandardBasePrices();renderVariantRows({skipDomSync:true});});editProductCompareAt?.addEventListener('input',()=>{syncRowsFromSimpleCompareAt();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editProductCompareAt?.addEventListener('change',()=>{syncRowsFromSimpleCompareAt();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editPrice?.addEventListener('input',()=>{syncSimpleRowPriceFromBase();refreshInheritedPriceHints();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editPrice?.addEventListener('change',()=>{syncSimpleRowPriceFromBase();refreshInheritedPriceHints();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editProductStock?.addEventListener('input',()=>{syncRowsFromSimpleStock();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}else{updateTotalStockDisplay();}});editProductStock?.addEventListener('change',()=>{syncRowsFromSimpleStock();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editStockAlert?.addEventListener('input',()=>{syncRowsFromSimpleAlert();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editStockAlert?.addEventListener('change',()=>{syncRowsFromSimpleAlert();if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}});editTypeSelect?.addEventListener('change',syncType); editCustomTypeInput?.addEventListener('input',syncType); editCustomTypeBehavior?.addEventListener('change',syncType); editImageInput?.addEventListener('change',()=>{const incomingFiles=Array.from(editImageInput.files||[]); if(incomingFiles.length){addEditImageFiles(incomingFiles);} syncSelectedFiles(editImageInput,selectedEditImages);});

if(editVariantRows&&!editVariantRows.dataset.photoPickerBound){editVariantRows.dataset.photoPickerBound='1';editVariantRows.addEventListener('click',(event)=>{const btn=event.target.closest('[data-open-variant-photos]');if(!btn||!editVariantRows.contains(btn))return;event.preventDefault();openVariantPhotoPicker(Number(btn.getAttribute('data-open-variant-photos')));});}
variantPhotoPicker?.addEventListener('click',(event)=>{if(event.target.closest('[data-close-variant-photos]')){closeVariantPhotoPicker();return;}const makeMain=event.target.closest('[data-make-variant-main]');if(makeMain){event.preventDefault();event.stopPropagation();promoteVariantPhoto(makeMain.getAttribute('data-make-variant-main'));return;}const tile=event.target.closest('[data-toggle-variant-photo]');if(tile){toggleVariantPhoto(tile.getAttribute('data-toggle-variant-photo'));}});
document.getElementById('variantPhotoPickerUpload')?.addEventListener('click',()=>editVariantImageFile?.click());
document.getElementById('variantPhotoPickerClear')?.addEventListener('click',clearVariantPhotos);
editVariantImageFile?.addEventListener('change',()=>{const ids=addEditImageFiles(editVariantImageFile.files||[]);assignUploadedIdsToPickerRow(ids);editVariantImageFile.value='';});
document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&variantPhotoPickerRow!==null){event.preventDefault();closeVariantPhotoPicker();}});
if(editImageDropzone&&editImageInput){const openImagePicker=()=>editImageInput.click();editImageDropzone.addEventListener('click',(event)=>{event.preventDefault();openImagePicker();});editImageDropzone.addEventListener('keydown',(event)=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();openImagePicker();}});['dragenter','dragover'].forEach((type)=>editImageDropzone.addEventListener(type,(event)=>{event.preventDefault();editImageDropzone.classList.add('is-dragover');}));['dragleave','dragend'].forEach((type)=>editImageDropzone.addEventListener(type,()=>editImageDropzone.classList.remove('is-dragover')));editImageDropzone.addEventListener('drop',(event)=>{event.preventDefault();editImageDropzone.classList.remove('is-dragover');addEditImageFiles(event.dataTransfer?.files||[]);});}

editVariationOptionInput?.addEventListener('keydown',(event)=>{if(event.key==='Enter'||event.key===','){event.preventDefault(); addEditVariationOptionTags(editVariationOptionInput.value);}});
editVariationOptionInput?.addEventListener('blur',()=>{if(editVariationOptionInput.value.trim()){addEditVariationOptionTags(editVariationOptionInput.value);}});
editOpenVariationModal?.addEventListener('click',()=>openVariationEditor());
[closeEditVariationModal,cancelEditVariationModal].forEach((b)=>b?.addEventListener('click',closeVariationEditor));
editVariationModal?.addEventListener('click',(event)=>{if(event.target===editVariationModal){closeVariationEditor();}});
submitEditVariationModal?.addEventListener('click',()=>{addEditVariationOptionTags(editVariationOptionInput?.value||''); const name=editVariationName.value.trim(),type='select',options=editVariationOptions.value.split(',').map((v)=>v.trim()).filter(Boolean); if(!name||!options.length){alert(productCreateMode?'Enter an option group name and at least one value, such as Size with S, M, and L.':'Please enter a variation name and at least one option.'); return;} if(editingVariationIndex===null){editVariationTypes.push({name,type,options});}else{editVariationTypes[editingVariationIndex]={...editVariationTypes[editingVariationIndex],name,type,options};} if(editSellingSetup){sellingMode='multiple';} normalizeRowsAfterVariationChange(); closeVariationEditor(); renderVariationInputs(); renderVariationCards(); renderVariantRows({skipDomSync:true});});
editApplyBulkValues?.addEventListener('click',()=>{if(!hasMultipleVariants()){return;}syncEditRowsFromDom();const rawStock=editBulkStock?editBulkStock.value:'';const rawPrice=editBulkPriceInput?editBulkPriceInput.value:'';if(rawStock===''&&rawPrice===''){setVariantToolsStatus('Enter a price, a stock number, or both — then press Apply.');return;}const optionCount=optionVariantCount();if(optionCount<1){setVariantToolsStatus('Add size or color variants before applying bulk values.');return;}const done=[];if(rawPrice!==''){const price=String(Math.max(0,parseFloat(String(rawPrice))||0));editRows=editRows.map((r)=>isOptionVariantRow(r)?{...r,price}:r);done.push('price');}if(rawStock!==''){const amt=String(Math.max(0,parseInt(String(rawStock),10)||0));editRows=editRows.map((r)=>isOptionVariantRow(r)?{...r,stock:amt}:r);setManualStockAllocMode();pendingStockCarryTotal=null;done.push('stock');}inheritStandardBasePrices();renderVariantRows({skipDomSync:true});setVariantToolsStatus(done.join(' and ')+' applied to '+optionCount+' option variant'+(optionCount===1?'':'s')+'. Standard / Base was left unchanged.');});
editResetVariantPrices?.addEventListener('click',()=>{if(!hasMultipleVariants()){return;}syncEditRowsFromDom();editRows=editRows.map((r)=>isOptionVariantRow(r)?{...r,price:''}:r);inheritStandardBasePrices();if(editBulkPriceInput){editBulkPriceInput.value='';}renderVariantRows({skipDomSync:true});setVariantToolsStatus('Option variants now follow the default price'+(editPrice&&editPrice.value!==''?' ('+editPrice.value+').':'.'));});

openDeleteWarning?.addEventListener('click',()=>{if(!currentProduct) return; deleteProductName.textContent=currentProduct.name||'this product'; deleteWarningModal.classList.remove('hidden'); deleteWarningModal.classList.add('flex');});
cancelDeleteProduct?.addEventListener('click',()=>{deleteWarningModal.classList.add('hidden'); deleteWarningModal.classList.remove('flex');});
deleteWarningModal?.addEventListener('click',(event)=>{if(event.target===deleteWarningModal){deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');}});
if(!editSurfaceIsPage){editModal.addEventListener('click',(event)=>{if(event.target===editModal){closeAll();}});}
document.addEventListener('keydown',(event)=>{if(event.key!=='Escape')return;if(isProductImageLightboxOpen()){closeProductImageLightbox();return;}if(!deleteWarningModal.classList.contains('hidden')){deleteWarningModal.classList.add('hidden');deleteWarningModal.classList.remove('flex');return;}if(!editVariationModal.classList.contains('hidden')){closeVariationEditor();return;}if(!editSurfaceIsPage&&!editModal.classList.contains('hidden')){closeAll();}});
renderEditVariationOptionTags();
editAdvancedFieldsToggle?.addEventListener('click',()=>{setAdvancedFieldsOpen(editAdvancedFieldsToggle.getAttribute('aria-expanded')!=='true');});
editFullWorkspaceLink?.addEventListener('click',(event)=>{event.preventDefault();const url=currentProduct?.edit_workspace_url||'';if(url){window.location.assign(url);}});
document.addEventListener('click',(event)=>{const target=event.target;if(target instanceof Element&&target.closest('.js-open-edit-product-modal')){setAdvancedFieldsOpen(false);}});
editForm?.addEventListener('submit',()=>{persistProductImageGallery();syncEditRowsFromDom();inheritStandardBasePrices();syncRowsFromSimpleStock();syncRowsFromSimpleAlert();syncRowsFromSimpleCompareAt();if(isSimpleInventoryProduct()&&editRows.length&&!(String(editRows[0].sku||'').trim())&&editSku){editRows[0]={...editRows[0],sku:editSku.value||''};}if(isSimpleInventoryProduct()){renderVariantRows({skipDomSync:true});}editRows.forEach((_,idx)=>syncVariantImageHidden(idx));editVariationTypes=sanitizeEditVariationTypes(editVariationTypes);renderVariationInputs();const bulkPriceMirror=document.getElementById('edit_create_bulk_price');const bulkStockMirror=document.getElementById('edit_create_bulk_stock');if(bulkPriceMirror){bulkPriceMirror.value=editPrice?.value||'';}if(bulkStockMirror){const stocks=editRows.map((row)=>Math.max(0,parseInt(String(row.stock??''),10)||0));bulkStockMirror.value=String(stocks.length?stocks.reduce((sum,n)=>sum+n,0):(Math.max(0,parseInt(String(editProductStock?.value??'0'),10)||0)));}});
if(editSurfaceIsPage&&typeof window.__workspaceEditInitialPayload!=='undefined'&&window.__workspaceEditInitialPayload){openEdit(window.__workspaceEditInitialPayload);delete window.__workspaceEditInitialPayload;}
else if(editModal.dataset.autoOpen==='true'&&!editSurfaceIsPage){const pendingId=(editProductId&&editProductId.value)||'';let restored=null;document.querySelectorAll('.js-open-edit-product-modal').forEach((btn)=>{const parsed=parseProductPayload(btn);if(parsed&&String(parsed.id)===String(pendingId)){restored=parsed;}});if(restored){restored.name=editName?.value||restored.name;restored.description=editDescription?.value||restored.description;restored.sku=editSku?.value||restored.sku;restored.base_price=editPrice?.value||restored.base_price;restored.stock_alert=editStockAlert?.value||restored.stock_alert;openEdit(restored);setAdvancedFieldsOpen(true);}else if(editForm&&pendingId){editForm.action=String(editForm.dataset.productUpdateUrlTemplate||'').replace('__PRODUCT_ID__',String(pendingId));editModal.classList.remove('hidden');editModal.classList.add('flex');document.body.classList.add('overflow-hidden');syncType();}}
})();
</script>
<script>
(() => {
    const wrap = document.getElementById('editShippingWeightWrap');
    if (!wrap) return;
    const unit = wrap.dataset.weightUnit || 'LB';
    const fallback = Number(wrap.dataset.fallbackWeight || 0);
    const hasFallback = wrap.dataset.hasFallback === '1' && fallback > 0;
    const input = document.getElementById('edit_product_shipping_weight');
    const overrideState = document.getElementById('editShippingWeightOverrideState');
    const fallbackState = document.getElementById('editShippingWeightFallbackState');
    const emptyState = document.getElementById('editShippingWeightEmptyState');
    const overrideValue = document.getElementById('editShippingWeightOverrideValue');
    const fallbackLabel = document.getElementById('editShippingWeightFallbackLabel');
    const useFallbackBtn = document.getElementById('editShippingWeightUseFallback');
    const useOverrideBtn = document.getElementById('editShippingWeightUseDifferent');
    const addWeightBtn = document.getElementById('editShippingWeightAdd');

    let mode = 'empty';

    const parsedOverride = () => {
        const raw = String(input?.value ?? '').trim();
        if (raw === '') {
            return null;
        }
        const n = Number(raw);
        if (!Number.isFinite(n) || n <= 0) {
            return null;
        }

        return n;
    };

    const show = (el, on) => {
        if (!el) return;
        el.classList.toggle('hidden', !on);
    };

    const applyMode = () => {
        show(overrideState, mode === 'override');
        show(fallbackState, mode === 'fallback');
        show(emptyState, mode === 'empty');
        const n = parsedOverride();
        if (overrideValue) {
            overrideValue.textContent = n !== null ? `${n.toFixed(2)} ${unit}` : '—';
        }
        if (hasFallback && fallbackLabel) {
            fallbackLabel.textContent = `${fallback.toFixed(2)} ${unit}`;
        }
    };

    const inferMode = () => {
        if (parsedOverride() !== null) {
            mode = 'override';
        } else if (hasFallback) {
            mode = 'fallback';
        } else {
            mode = 'empty';
        }
        applyMode();
    };

    const enterOverride = () => {
        mode = 'override';
        applyMode();
        input?.focus();
    };

    useFallbackBtn?.addEventListener('click', () => {
        if (input) input.value = '';
        mode = hasFallback ? 'fallback' : 'empty';
        applyMode();
    });
    useOverrideBtn?.addEventListener('click', () => {
        if (input) input.value = '';
        enterOverride();
    });
    addWeightBtn?.addEventListener('click', () => {
        if (input) input.value = '';
        enterOverride();
    });
    input?.addEventListener('input', () => {
        mode = 'override';
        applyMode();
    });
    input?.addEventListener('blur', inferMode);

    const hookRefresh = () => window.setTimeout(inferMode, 0);
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (target.closest('.js-open-edit-product-modal')) {
            hookRefresh();
        }
    });
    document.getElementById('edit_product_type')?.addEventListener('change', hookRefresh);
    document.getElementById('edit_custom_type_behavior')?.addEventListener('change', hookRefresh);
    hookRefresh();
})();
</script>
