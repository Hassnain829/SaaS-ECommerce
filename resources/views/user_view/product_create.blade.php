@extends('layouts.user.user-sidebar')

@php
    $storeCurrency = strtoupper((string) ($selectedStore->currency ?? 'USD'));

    // Photos sit second so merchants can assign them to variants on Price & stock.
    // Tax and shipping remain the second-last step.
    $productSteps = [
        [
            'id' => 'essentials',
            'label' => 'Essentials',
            'hint' => 'Name and description',
            'flag' => 'required',
            'note' => 'Start with the name shoppers will see. Only the product name is required here.',
        ],
        [
            'id' => 'media',
            'label' => 'Photos',
            'hint' => 'Listing images',
            'flag' => null,
            'note' => 'Add photos, drag to set their order, and click a photo to preview it. The first photo is the main listing image. You can assign a photo to each variant on Price & stock after you add option groups.',
        ],
        [
            'id' => 'pricing',
            'label' => 'Price & stock',
            'hint' => 'Price, stock, groups, and variants',
            'flag' => 'required',
            'note' => 'Set the selling price and how many you have. If shoppers pick Size or Color, add option groups on this same step, then manage each variant\'s SKU, price, and stock below.',
        ],
        [
            'id' => 'organization',
            'label' => 'Organization',
            'hint' => 'Category, brand, tags',
            'flag' => 'optional',
            'note' => 'Create a category here if you do not have one yet, then assign this product to it. Brand and tags are optional.',
        ],
        [
            'id' => 'tax-shipping',
            'label' => 'Tax & shipping',
            'hint' => 'Tax and package weight',
            'flag' => 'optional',
            'note' => 'Optional. Turn tax on or off for this product, and set a shipping weight if carriers need it for checkout estimates.',
        ],
        [
            'id' => 'details',
            'label' => 'More details',
            'hint' => 'Specs and extra fields',
            'flag' => 'optional',
            'note' => 'Extra facts such as material, supplier, or warranty. Safe to skip for now.',
        ],
    ];
    $canManageBrands = $canManageBrands ?? false;
    $canManageTags = $canManageTags ?? false;
    $canManageCategories = $canManageCategories ?? false;
    $canManageCatalogTools = $canManageBrands || $canManageTags || $canManageCategories;
    $catalogToolsReopen = $errors->any() && (
        old('_open_brand_add_modal') == '1' || old('_open_brand_add_modal') === true ||
        old('_editing_brand_id') ||
        old('_open_tag_add_modal') == '1' || old('_open_tag_add_modal') === true ||
        old('_editing_tag_id') ||
        old('_open_category_add_modal') == '1' || old('_open_category_add_modal') === true ||
        old('_editing_category_id')
    );
    $catalogToolsDefaultTab = 'categories';
    if ($errors->any()) {
        if (old('_editing_brand_id') || old('_open_brand_add_modal') == '1' || old('_open_brand_add_modal') === true) {
            $catalogToolsDefaultTab = 'brands';
        } elseif (old('_editing_tag_id') || old('_open_tag_add_modal') == '1' || old('_open_tag_add_modal') === true) {
            $catalogToolsDefaultTab = 'tags';
        }
    }
    $openCatalogToolsShell = $catalogToolsReopen || request()->boolean('openCatalogTools');
    $requestedStep = (string) request()->query('step', '');
    if ($openCatalogToolsShell && $requestedStep === '') {
        $requestedStep = 'organization';
    }
@endphp

@section('title', 'Add product — Product workspace')
@section('sidebar_brand_title', config('app.name'))
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'Your store')

@section('topbar')
    <x-ui.merchant-topbar title="Add product" lead="Create a product for {{ $selectedStore?->name }}.">
        <x-slot:actions>
            <a href="{{ route('products') }}" data-product-create-allow-leave data-turbo="false" class="hidden sm:inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">
                Cancel
            </a>
            @if ($canManageCatalogTools)
                <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="hidden sm:inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">
                    Catalog tools
                </button>
            @endif
            <button type="submit" form="editProductForm" class="inline-flex h-9 items-center rounded-md bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Save product
            </button>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="pf-shell" data-pf-shell data-product-create-guard>
        @include('user_view.partials.flash_success')

        @if ($errors->has('brand'))
            <div class="pf-alert" role="alert">
                <p class="font-semibold">Cannot remove this brand</p>
                <p>{{ $errors->first('brand') }}</p>
            </div>
        @endif

        @if ($errors->has('category'))
            <div class="pf-alert" role="alert">
                <p class="font-semibold">Cannot remove this category</p>
                <p>{{ $errors->first('category') }}</p>
            </div>
        @endif

        @if ($errors->any() && ! $errors->has('brand') && ! $errors->has('category') && ! $catalogToolsReopen)
            <div class="pf-alert" data-pf-server-errors>
                <p class="font-semibold">Your product was not saved yet</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Live summary of what will be saved --}}
        <div class="pf-topline">
            <div class="pf-topline-title">
                <p class="pf-topline-name is-empty" data-pf-name>Untitled product</p>
                <p class="pf-topline-sub">Draft in {{ $selectedStore?->name }} · nothing is saved until you press Save product</p>
            </div>

            <div class="pf-stats">
                <div>
                    <p class="pf-stat-label">Price</p>
                    <p class="pf-stat-value is-empty" data-pf-price>Not set</p>
                </div>
                <div>
                    <p class="pf-stat-label">Stock</p>
                    <p class="pf-stat-value" data-pf-stock>0</p>
                </div>
                <div>
                    <p class="pf-stat-label">Photos</p>
                    <p class="pf-stat-value is-empty" data-pf-photos>None</p>
                </div>
            </div>

            <span class="pf-ready" data-pf-ready>
                <span class="pf-ready-dot" aria-hidden="true"></span>
                <span data-pf-ready-text>Add a name and price</span>
            </span>
        </div>

        <div class="pf-body">
            <nav class="pf-rail" aria-label="Product setup steps">
                <p class="pf-rail-title">Steps</p>

                @foreach ($productSteps as $index => $step)
                    <button type="button" @class(['pf-step', 'is-active' => $index === 0]) data-pf-step="{{ $step['id'] }}">
                        <span class="pf-step-num" aria-hidden="true">
                            <span class="pf-num">{{ $index + 1 }}</span>
                            <span class="pf-check"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.2 7.2a1 1 0 01-1.4 0L3.3 9.1a1 1 0 011.4-1.4l4.1 4.1 6.5-6.5a1 1 0 011.4 0z" clip-rule="evenodd"/></svg></span>
                        </span>
                        <span class="pf-step-text">
                            <span class="pf-step-label">
                                {{ $step['label'] }}
                                @if ($step['flag'] === 'required')
                                    <span class="pf-flag pf-flag-required">Required</span>
                                @elseif ($step['flag'] === 'optional')
                                    <span class="pf-flag pf-flag-optional">Optional</span>
                                @endif
                            </span>
                            <span class="pf-step-hint">{{ $step['hint'] }}</span>
                        </span>
                    </button>
                @endforeach

                <div class="pf-rail-foot">
                    <p class="pf-rail-note">
                        <strong>Name</strong> and <strong>price</strong> are all you need to save. Add option groups in Price&stock when shoppers choose Size, Color, or similar, then manage each variant on that same step.
                    </p>
                </div>
            </nav>

            <div class="pf-panel" data-pf-panel>
                <div class="pf-panel-inner">
                    <p class="pf-note" data-pf-note hidden></p>

                    <script>
                        window.__workspaceEditInitialPayload = @json($createProductPayload);
                    </script>

                    @include('user_view.partials.product_edit_modal', [
                        'productEditSurface' => 'page',
                        'productEditPageNative' => true,
                        'productCreateMode' => true,
                        'selectedStore' => $selectedStore,
                        'catalogBrands' => $catalogBrands,
                        'catalogTags' => $catalogTags,
                        'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
                        'catalogAttributes' => $catalogAttributes,
                        'canManageBrands' => $canManageBrands,
                        'canManageTags' => $canManageTags,
                        'canManageCategories' => $canManageCategories,
                        'workspaceReturnProductId' => null,
                        'shippingPreferences' => $shippingPreferences ?? [],
                    ])
                </div>
            </div>
        </div>

        <div class="pf-footer">
            <button type="button" class="pf-btn pf-btn-secondary" data-pf-back disabled>← Back</button>
            <span class="pf-footer-count" data-pf-count>Step 1 of {{ count($productSteps) }}</span>
            <span class="pf-toast" data-pf-toast role="status" aria-live="polite"></span>
            <span class="pf-footer-spacer"></span>
            <a href="{{ route('products') }}" class="pf-btn pf-btn-secondary" data-product-create-allow-leave data-turbo="false">Cancel</a>
            <button type="submit" form="editProductForm" class="pf-btn pf-btn-save">Save product</button>
            <button type="button" class="pf-btn pf-btn-primary" data-pf-next>Continue →</button>
        </div>
    </div>

    <script>
        (() => {
            const shell = document.querySelector('[data-pf-shell]');
            if (!shell) return;

            // Section ids come from the shared product form partial. Grouping them
            // here is what turns one very long form into short, ordered steps.
            const STEPS = [
                {
                    id: 'essentials',
                    sections: ['catalog-edit-section-basics'],
                    required: [{ id: 'edit_product_name', message: 'Add a product name before you continue.' }],
                },
                { id: 'media', sections: ['catalog-edit-section-media'] },
                {
                    id: 'pricing',
                    sections: [
                        'catalog-edit-section-pricing',
                        'catalog-edit-section-option-groups',
                        'catalog-edit-section-inventory',
                        'editVariantPreviewSection',
                    ],
                    required: [{ id: 'edit_product_price', message: 'Set a price before you continue.' }],
                },
                { id: 'organization', sections: ['catalog-edit-section-organization'] },
                { id: 'tax-shipping', sections: ['catalog-edit-section-tax-shipping'] },
                {
                    id: 'details',
                    sections: ['catalog-edit-section-attributes', 'catalog-edit-section-additional-details'],
                },
            ];

            const NOTES = @json(collect($productSteps)->pluck('note', 'id'));

            const panel = shell.querySelector('[data-pf-panel]');
            const note = shell.querySelector('[data-pf-note]');
            const tabs = [...shell.querySelectorAll('[data-pf-step]')];
            const backBtn = shell.querySelector('[data-pf-back]');
            const nextBtn = shell.querySelector('[data-pf-next]');
            const countLabel = shell.querySelector('[data-pf-count]');
            const toast = shell.querySelector('[data-pf-toast]');

            const nameInput = document.getElementById('edit_product_name');
            const priceInput = document.getElementById('edit_product_price');
            const stockInput = document.getElementById('edit_product_stock');
            const totalStock = document.getElementById('editTotalStockDisplay');
            const imagePreview = document.getElementById('editProductImagePreview');
            const form = document.getElementById('editProductForm');

            const allSections = new Map();
            STEPS.forEach((step) => step.sections.forEach((id) => {
                const el = document.getElementById(id);
                if (el) allSections.set(id, el);
            }));

            const visited = new Set(['essentials']);
            let index = 0;
            const requestedStep = @json($requestedStep);
            if (requestedStep) {
                const requestedIndex = STEPS.findIndex((step) => step.id === requestedStep);
                if (requestedIndex >= 0) {
                    index = requestedIndex;
                    STEPS.slice(0, requestedIndex + 1).forEach((step) => visited.add(step.id));
                }
            }
            let toastTimer = null;

            const flash = (message) => {
                if (!toast) return;
                toast.textContent = message || '';
                if (toastTimer) window.clearTimeout(toastTimer);
                if (message) toastTimer = window.setTimeout(() => { toast.textContent = ''; }, 4000);
            };

            const clearFieldError = (field) => {
                field.classList.remove('pf-invalid');
                field.parentElement?.querySelector('.pf-field-error')?.remove();
            };

            const markFieldError = (field, message) => {
                clearFieldError(field);
                field.classList.add('pf-invalid');
                const hint = document.createElement('p');
                hint.className = 'pf-field-error';
                hint.textContent = message;
                field.parentElement?.appendChild(hint);
            };

            const hasValue = (field) => !!field && String(field.value ?? '').trim() !== '';

            const syncRequiredFlags = () => {
                tabs.forEach((tab, i) => {
                    tab.classList.toggle('is-filled', stepIsComplete(STEPS[i]));
                });
                document.querySelectorAll('[data-required-flag-for]').forEach((flag) => {
                    const field = document.getElementById(flag.getAttribute('data-required-flag-for') || '');
                    flag.classList.toggle('is-met', hasValue(field));
                });
            };

            const stepIsComplete = (step) => {
                if (step.required?.length) {
                    return step.required.every((rule) => hasValue(document.getElementById(rule.id)));
                }
                return visited.has(step.id);
            };

            const validateStep = (step, { focus = true } = {}) => {
                for (const rule of step.required ?? []) {
                    const field = document.getElementById(rule.id);
                    if (!field) continue;
                    if (!hasValue(field)) {
                        markFieldError(field, rule.message);
                        if (focus) field.focus();
                        flash(rule.message);
                        return false;
                    }
                    clearFieldError(field);
                }
                return true;
            };

            const render = () => {
                const step = STEPS[index];
                visited.add(step.id);

                allSections.forEach((el, id) => {
                    if (step.sections.includes(id)) {
                        el.removeAttribute('data-step-hidden');
                    } else {
                        el.setAttribute('data-step-hidden', 'true');
                    }
                });

                tabs.forEach((tab, i) => {
                    tab.classList.toggle('is-active', i === index);
                    tab.classList.toggle('is-done', stepIsComplete(STEPS[i]) && i !== index);
                });
                syncRequiredFlags();

                if (note) {
                    const text = NOTES[step.id] || '';
                    note.textContent = text;
                    note.hidden = text === '';
                }

                if (countLabel) countLabel.textContent = `Step ${index + 1} of ${STEPS.length}`;
                if (backBtn) backBtn.disabled = index === 0;
                if (nextBtn) {
                    const last = index === STEPS.length - 1;
                    nextBtn.hidden = last;
                    nextBtn.textContent = 'Continue →';
                }
                if (panel) panel.scrollTop = 0;
                const scroller = shell.closest('.merchant-app');
                if (scroller) scroller.scrollTop = 0;
            };

            const goTo = (target, { validate = true } = {}) => {
                if (validate && target > index && !validateStep(STEPS[index])) return;
                index = Math.min(Math.max(target, 0), STEPS.length - 1);
                flash('');
                render();
            };

            tabs.forEach((tab, i) => tab.addEventListener('click', () => goTo(i, { validate: i > index })));
            backBtn?.addEventListener('click', () => goTo(index - 1, { validate: false }));
            nextBtn?.addEventListener('click', () => goTo(index + 1));

            /* Live summary */
            const readStock = () => {
                const fromTotal = totalStock ? parseInt(totalStock.textContent.replace(/[^\d-]/g, ''), 10) : NaN;
                if (Number.isFinite(fromTotal) && fromTotal > 0) return fromTotal;
                const simple = stockInput ? parseInt(stockInput.value, 10) : NaN;
                return Number.isFinite(simple) ? simple : 0;
            };

            const setStat = (selector, value, empty) => {
                const el = shell.querySelector(selector);
                if (!el) return;
                el.textContent = value === null ? empty : value;
                el.classList.toggle('is-empty', value === null);
            };

            const refreshSummary = () => {
                const nameEl = shell.querySelector('[data-pf-name]');
                if (nameEl) {
                    const value = nameInput ? nameInput.value.trim() : '';
                    nameEl.textContent = value || 'Untitled product';
                    nameEl.classList.toggle('is-empty', value === '');
                }

                const price = priceInput && priceInput.value !== '' ? Number(priceInput.value) : null;
                setStat('[data-pf-price]', price === null || Number.isNaN(price)
                    ? null
                    : @json($storeCurrency) + ' ' + price.toFixed(2), 'Not set');

                setStat('[data-pf-stock]', String(readStock()), '0');

                const photos = imagePreview ? imagePreview.querySelectorAll('img').length : 0;
                setStat('[data-pf-photos]', photos > 0 ? String(photos) : null, 'None');

                const ready = hasValue(nameInput) && hasValue(priceInput);
                const readyEl = shell.querySelector('[data-pf-ready]');
                const readyText = shell.querySelector('[data-pf-ready-text]');
                readyEl?.classList.toggle('is-ready', ready);
                if (readyText) readyText.textContent = ready ? 'Ready to save' : 'Add a name and price';

                tabs.forEach((tab, i) => {
                    if (i !== index) tab.classList.toggle('is-done', stepIsComplete(STEPS[i]));
                });
                syncRequiredFlags();
            };

            ['input', 'change'].forEach((evt) => {
                nameInput?.addEventListener(evt, () => { clearFieldError(nameInput); refreshSummary(); });
                priceInput?.addEventListener(evt, () => { clearFieldError(priceInput); refreshSummary(); });
                stockInput?.addEventListener(evt, refreshSummary);
            });

            // The shared partial owns stock totals and image previews, so watch
            // its output rather than trying to recompute it here.
            if (totalStock) new MutationObserver(refreshSummary).observe(totalStock, { childList: true, characterData: true, subtree: true });
            if (imagePreview) new MutationObserver(refreshSummary).observe(imagePreview, { childList: true, subtree: true });

            /* Saving: land the merchant on the step that still needs work */
            form?.addEventListener('submit', (event) => {
                for (let i = 0; i < STEPS.length; i += 1) {
                    if (!(STEPS[i].required?.length)) continue;
                    if (!stepIsComplete(STEPS[i])) {
                        event.preventDefault();
                        index = i;
                        render();
                        validateStep(STEPS[i]);
                        return;
                    }
                }
                window.__releaseProductCreateGuard?.();
            });

            const stepIndexById = (id) => {
                const found = STEPS.findIndex((step) => step.id === id);
                return found >= 0 ? found : 0;
            };

            // A rejected save comes back with errors: open the step that owns the
            // first failing field instead of dropping the merchant at the top.
            const serverErrors = shell.querySelector('[data-pf-server-errors]');
            if (serverErrors) {
                const text = serverErrors.textContent.toLowerCase();
                if (text.includes('weight') || (text.includes('tax') && ! text.includes('name'))) {
                    index = stepIndexById('tax-shipping');
                } else if (text.includes('sku') || text.includes('variant') || text.includes('option') || text.includes('price')) {
                    index = stepIndexById('pricing');
                } else if (text.includes('photo') || text.includes('image')) {
                    index = stepIndexById('media');
                } else if (text.includes('name')) {
                    index = stepIndexById('essentials');
                } else {
                    index = 0;
                }
            }

            document.addEventListener('catalog-tools:saved', (event) => {
                const message = event.detail && event.detail.message ? String(event.detail.message) : '';
                if (message) flash(message);
            });

            render();
            refreshSummary();
        })();
    </script>

    @if ($canManageCatalogTools)
        @include('user_view.partials.catalog_tools_modal', [
            'managementBrands' => $managementBrands ?? collect(),
            'managementTags' => $managementTags ?? collect(),
            'managementCategories' => $managementCategories ?? collect(),
            'canManageBrands' => $canManageBrands,
            'canManageTags' => $canManageTags,
            'canManageCategories' => $canManageCategories,
            'openCatalogToolsShell' => $openCatalogToolsShell,
            'catalogToolsDefaultTab' => $catalogToolsDefaultTab,
            'catalogToolsStayOnPage' => true,
            'catalogToolsReturn' => 'products.create',
        ])
    @endif
@endsection

@push('overlays')
<div id="productCreateLeaveModal" class="ui-modal-shell ui-modal-shell--alert hidden" role="dialog" aria-modal="true" aria-labelledby="productCreateLeaveTitle" data-product-create-leave-modal>
    <div class="ui-modal-panel ui-modal-panel--md border-[#FDE68A]">
        <div class="bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.18),_transparent_60%)] px-6 pb-4 pt-6">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFFBEB] text-[#D97706] shadow-sm" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 9V13M12 17H12.01M10.29 3.86L1.82 18C1.64 18.3 1.55 18.65 1.55 19C1.55 19.35 1.64 19.7 1.81 20C1.99 20.31 2.24 20.56 2.54 20.74C2.85 20.92 3.19 21.02 3.54 21.02H20.46C20.81 21.02 21.15 20.92 21.46 20.74C21.76 20.56 22.01 20.31 22.19 20C22.36 19.7 22.45 19.35 22.45 19C22.45 18.65 22.36 18.3 22.18 18L13.71 3.86C13.53 3.56 13.28 3.32 12.97 3.15C12.67 2.98 12.33 2.89 11.98 2.89C11.64 2.89 11.3 2.98 10.99 3.15C10.69 3.32 10.44 3.57 10.26 3.86L10.29 3.86Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 id="productCreateLeaveTitle" class="mt-5 text-2xl font-semibold text-[#0F172A]">Finish this product first</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">You are still adding a product. Save it, or cancel, before you go to another page.</p>
        </div>
        <div class="px-6 pb-6 pt-2">
            <div class="rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#92400E]">Unsaved product</p>
                <p class="mt-2 text-sm text-[#78350F]">Nothing is saved until you press Save product. Leave without saving discards what you entered.</p>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]" data-product-create-leave>Leave without saving</button>
                <button type="button" class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover" data-product-create-stay>Keep adding</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const guard = document.querySelector('[data-product-create-guard]');
    const modal = document.getElementById('productCreateLeaveModal');
    if (!guard || !modal) return;

    const createPath = new URL(@json(route('products.create')), window.location.origin).pathname.replace(/\/+$/, '') || '/products/create';
    const stayButtons = [...modal.querySelectorAll('[data-product-create-stay]')];
    const leaveBtn = modal.querySelector('[data-product-create-leave]');

    let allowingLeave = false;
    let pending = null;

    const isCreateUrl = (href) => {
        try {
            const url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin) return false;
            const path = url.pathname.replace(/\/+$/, '') || '/';
            return path === createPath;
        } catch (e) {
            return false;
        }
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        modal.querySelector('button[data-product-create-stay]')?.focus();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        pending = null;
    };

    const release = () => {
        allowingLeave = true;
        window.removeEventListener('beforeunload', onBeforeUnload);
    };

    window.__releaseProductCreateGuard = release;

    const onBeforeUnload = (event) => {
        if (allowingLeave) return;
        event.preventDefault();
        event.returnValue = '';
    };

    const leave = () => {
        const action = pending;
        release();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');

        if (action?.type === 'form' && action.form instanceof HTMLFormElement) {
            action.form.submit();
            return;
        }
        if (action?.type === 'back') {
            window.history.go(-2);
            return;
        }
        if (action?.href) {
            window.location.href = action.href;
        }
    };

    const intercept = (next) => {
        if (allowingLeave) return false;
        pending = next;
        openModal();
        return true;
    };

    stayButtons.forEach((btn) => btn.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    }));
    leaveBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        leave();
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('hidden')) return;
        event.preventDefault();
        closeModal();
    });

    document.addEventListener('click', (event) => {
        if (allowingLeave) return;
        if (!(event.target instanceof Element)) return;
        const link = event.target.closest('a[href]');
        if (!link) return;
        if (link.closest('#productCreateLeaveModal')) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') return;
        if (link.hasAttribute('data-product-create-allow-leave')) {
            release();
            return;
        }
        const href = link.getAttribute('href') || '';
        if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (isCreateUrl(link.href)) return;
        event.preventDefault();
        event.stopPropagation();
        intercept({ type: 'href', href: link.href });
    }, true);

    document.addEventListener('submit', (event) => {
        if (allowingLeave) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.id === 'editProductForm') return;
        if (form.hasAttribute('data-catalog-kind')) return;
        if (form.hasAttribute('data-product-create-allow-leave')) return;
        if (form.id === 'sidebar-store-switch-form') return;
        event.preventDefault();
        event.stopPropagation();
        intercept({ type: 'form', form });
    }, true);

    document.addEventListener('turbo:before-visit', (event) => {
        if (allowingLeave) return;
        const url = event.detail && event.detail.url ? String(event.detail.url) : '';
        if (url === '' || isCreateUrl(url)) return;
        event.preventDefault();
        intercept({ type: 'href', href: url });
    });

    window.addEventListener('beforeunload', onBeforeUnload);

    if (!window.history.state || !window.history.state.productCreateGuard) {
        window.history.pushState({ productCreateGuard: true }, '', window.location.href);
    }
    window.addEventListener('popstate', () => {
        if (allowingLeave) return;
        if (!window.history.state || !window.history.state.productCreateGuard) {
            window.history.pushState({ productCreateGuard: true }, '', window.location.href);
        }
        intercept({ type: 'back' });
    });
})();
</script>
@endpush

