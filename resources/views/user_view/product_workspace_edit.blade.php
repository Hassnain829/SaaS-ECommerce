@extends('layouts.user.user-sidebar')

@section('title', 'Edit — '.$product->name.' — Product workspace')
@section('sidebar_brand_title', config('app.name'))
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'Catalog')

@section('topbar')
    <x-ui.merchant-topbar title="Edit product" lead="Update catalog details, variants, and inventory.">
        <x-slot:actions>
            <a href="{{ route('products.show', $product) }}" class="hidden sm:inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">
                Back
            </a>
            <button type="submit" form="editProductForm" class="hidden sm:inline-flex h-9 items-center rounded-md bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover">
                Save and exit
            </button>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @php
        $product->loadMissing('variants');
        $sumStock = (int) $product->variants->sum('stock');
    @endphp

    <div id="product-edit-workspace" class="product-edit-workspace -m-4 flex min-h-full flex-col lg:-m-8">
        <div class="product-edit-scroll w-full">
            <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
            @include('user_view.partials.flash_success')

            <section class="product-edit-hero">
                <div class="min-w-0">
                    <p class="product-edit-eyebrow">Editing product</p>
                    <h2>{{ $product->name }}</h2>
                    <p class="mt-1 text-sm text-[#454652]">
                        Update name, price, stock, images, and options for
                        <span class="font-semibold text-[#1A1B22]">{{ $selectedStore?->name }}</span>.
                        Use the section bar to jump, then save when you are done.
                    </p>
                </div>
            </section>

            <script>
                window.__workspaceEditInitialPayload = @json($editProductPayload);
            </script>

            <div id="catalog-editor-workspace-layout" class="mt-6 grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                <div class="min-w-0 space-y-4 lg:col-span-8">
                    <div class="product-edit-section-nav-shell" data-product-edit-nav-shell>
                        <nav id="catalog-editor-section-nav" class="product-edit-section-nav" aria-label="Jump to editor sections">
                            <a href="#catalog-edit-section-basics" class="product-edit-section-link is-active" data-product-edit-tab>Product details</a>
                            <a href="#catalog-edit-section-media" class="product-edit-section-link" data-product-edit-tab>Images</a>
                            <a href="#catalog-edit-section-pricing" class="product-edit-section-link" data-product-edit-tab>Price &amp; inventory</a>
                            <a href="#catalog-edit-section-organization" class="product-edit-section-link" data-product-edit-tab>Organization</a>
                            <a href="#catalog-edit-section-attributes" class="product-edit-section-link" data-product-edit-tab>Specifications</a>
                            <a href="#catalog-edit-section-additional-details" class="product-edit-section-link" data-product-edit-tab>Additional details</a>
                            <a href="#catalog-edit-section-option-groups" class="product-edit-section-link" data-product-edit-tab>Options</a>
                            <a href="#catalog-edit-section-inventory" class="product-edit-section-link" data-product-edit-tab>Inventory</a>
                        </nav>
                    </div>

                    @include('user_view.partials.product_edit_modal', [
                        'productEditSurface' => 'page',
                        'productEditPageNative' => true,
                        'selectedStore' => $selectedStore,
                        'catalogBrands' => $catalogBrands,
                        'catalogTags' => $catalogTags,
                        'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
                        'catalogAttributes' => $catalogAttributes,
                        'workspaceReturnProductId' => $workspaceReturnProductId,
                    ])
                </div>

                <aside class="space-y-6 lg:col-span-4">
                    <div class="product-edit-rail space-y-6 lg:sticky">
                        <section class="product-edit-card p-5 sm:p-6">
                            <p class="product-edit-eyebrow">Editor status</p>
                            <div class="mt-4 flex items-center gap-2">
                                <span @class(['h-2 w-2 rounded-full', 'bg-emerald-500' => $product->status, 'bg-amber-500' => ! $product->status])></span>
                                <p class="text-sm font-bold text-[#1A1B22]">{{ $product->status ? 'Published' : 'Draft' }}</p>
                            </div>
                            <div class="mt-2 border-l-2 border-[#C5C5D4]/40 py-1 pl-4">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#454652]">Active store</p>
                                <p class="text-sm text-[#1A1B22]">{{ $selectedStore?->name }}</p>
                            </div>
                            <div class="mt-6 rounded-xl border border-[#3F51B5]/10 bg-[#3F51B5]/5 p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#3F51B5]">Units available</p>
                                <p id="workspaceEditInventorySummary" class="mt-1 text-4xl font-bold leading-none text-[#24389C] tabular-nums sm:text-5xl">{{ number_format($sumStock) }}</p>
                                <p class="mt-2 text-[11px] text-[#454652]">Total stock for this product</p>
                                <p class="mt-2 text-[9px] font-bold uppercase italic tracking-wide text-[#454652]/70">Last saved {{ optional($product->updated_at)->diffForHumans() }}</p>
                            </div>
                            <div class="mt-6 space-y-3">
                                <button type="submit" form="editProductForm" class="product-edit-primary-action w-full">Save and return to workspace</button>
                                <a href="{{ route('products.show', $product) }}" class="product-edit-secondary-action w-full">View workspace only</a>
                                <a href="{{ route('products') }}" class="inline-flex w-full items-center justify-center py-2 text-[11px] font-bold uppercase tracking-wide text-[#3F51B5] hover:underline">Back to product list</a>
                            </div>
                        </section>

                        <section class="product-edit-card overflow-hidden">
                            <div class="border-b border-[#C5C5D4]/30 bg-[#F4F2FC] px-5 py-3 sm:px-6">
                                <p class="product-edit-eyebrow">Additional details</p>
                            </div>
                            <div class="space-y-3 p-5 text-xs leading-relaxed text-[#454652] sm:p-6">
                                <p><span class="font-semibold text-[#1A1B22]">Additional details</span> are editable product information such as supplier, material, origin, care notes, or internal references.</p>
                                <p><span class="font-semibold text-[#1A1B22]">Advanced imported data</span> remains safely preserved on the product workspace until you make it editable.</p>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    </div>

    <script>
        (() => {
            const workspace = document.getElementById('product-edit-workspace');
            if (!workspace) return;

            const nav = workspace.querySelector('#catalog-editor-section-nav');
            const navShell = workspace.querySelector('[data-product-edit-nav-shell]');
            const mainHeader = document.querySelector('body > main > header') || document.querySelector('.merchant-topbar');
            const tabs = [...workspace.querySelectorAll('[data-product-edit-tab]')];
            const sections = tabs
                .map((tab) => document.querySelector(tab.getAttribute('href')))
                .filter(Boolean)
                .sort((a, b) => a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
            const isWindowScrollRoot = (el) => el === document.scrollingElement
                || el === document.documentElement
                || el === document.body;
            const scrollRoot = workspace.closest('.merchant-app') || document.scrollingElement || document.documentElement;
            let lockedId = null;
            let lockTimer = null;
            let ticking = false;
            let lastActiveId = null;

            const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const headerBottom = () => mainHeader?.getBoundingClientRect().bottom
                ?? (isWindowScrollRoot(scrollRoot) ? 0 : scrollRoot.getBoundingClientRect().top);

            const stickyOffset = () => {
                const navHeight = nav ? nav.getBoundingClientRect().height : 0;
                return navHeight + 12;
            };

            const keepTabInView = (tab) => {
                if (!nav || !tab) return;
                const navRect = nav.getBoundingClientRect();
                const tabRect = tab.getBoundingClientRect();
                const pad = 12;
                if (tabRect.left < navRect.left + pad) {
                    nav.scrollLeft -= (navRect.left + pad) - tabRect.left;
                } else if (tabRect.right > navRect.right - pad) {
                    nav.scrollLeft += tabRect.right - (navRect.right - pad);
                }
            };

            const syncNavPlacement = () => {
                if (!nav || !navShell) return;

                const topEdge = headerBottom();
                const shouldStick = navShell.getBoundingClientRect().top <= topEdge + 1;
                nav.classList.toggle('is-stuck', shouldStick);

                if (shouldStick) {
                    const shellRect = navShell.getBoundingClientRect();
                    nav.style.setProperty('--product-edit-nav-top', `${Math.round(topEdge)}px`);
                    nav.style.setProperty('--product-edit-nav-left', `${Math.round(shellRect.left)}px`);
                    nav.style.setProperty('--product-edit-nav-width', `${Math.round(shellRect.width)}px`);
                } else {
                    nav.style.removeProperty('--product-edit-nav-top');
                    nav.style.removeProperty('--product-edit-nav-left');
                    nav.style.removeProperty('--product-edit-nav-width');
                }
            };

            const selectTab = (id, { syncHash = false } = {}) => {
                if (id === lastActiveId) return;
                lastActiveId = id;
                tabs.forEach((tab) => {
                    const active = tab.getAttribute('href') === '#' + id;
                    tab.classList.toggle('is-active', active);
                    if (active) {
                        tab.setAttribute('aria-current', 'location');
                        keepTabInView(tab);
                    } else {
                        tab.removeAttribute('aria-current');
                    }
                });
                if (syncHash && id && history.replaceState) {
                    history.replaceState(null, '', '#' + id);
                }
            };

            const scrollTopOf = (el) => {
                if (isWindowScrollRoot(scrollRoot)) {
                    return el.getBoundingClientRect().top + window.scrollY;
                }
                const rootRect = scrollRoot.getBoundingClientRect();
                return scrollRoot.scrollTop + (el.getBoundingClientRect().top - rootRect.top);
            };

            const currentScroll = () => (isWindowScrollRoot(scrollRoot) ? window.scrollY : scrollRoot.scrollTop);

            const maxScroll = () => {
                if (isWindowScrollRoot(scrollRoot)) {
                    return Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
                }
                return Math.max(0, scrollRoot.scrollHeight - scrollRoot.clientHeight);
            };

            const updateActiveFromScroll = () => {
                if (!sections.length) return;
                if (lockedId) {
                    selectTab(lockedId);
                    return;
                }

                if (maxScroll() > 0 && currentScroll() >= maxScroll() - 4) {
                    selectTab(sections[sections.length - 1].id);
                    return;
                }

                const line = headerBottom() + stickyOffset() + 8;
                let active = sections[0];
                for (const section of sections) {
                    if (section.getBoundingClientRect().top <= line) {
                        active = section;
                    }
                }
                selectTab(active.id);
            };

            const scrollToSection = (target) => {
                const top = Math.max(0, scrollTopOf(target) - stickyOffset());
                const behavior = prefersReducedMotion() ? 'auto' : 'smooth';
                if (isWindowScrollRoot(scrollRoot)) {
                    window.scrollTo({ top, behavior });
                } else {
                    scrollRoot.scrollTo({ top, behavior });
                }
            };

            const lockSection = (id) => {
                lockedId = id;
                selectTab(id, { syncHash: true });
                if (lockTimer) window.clearTimeout(lockTimer);
                lockTimer = window.setTimeout(() => {
                    lockedId = null;
                    updateActiveFromScroll();
                }, 900);
            };

            const onScroll = () => {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(() => {
                    syncNavPlacement();
                    updateActiveFromScroll();
                    ticking = false;
                });
            };

            tabs.forEach((tab) => tab.addEventListener('click', (event) => {
                const target = document.querySelector(tab.getAttribute('href'));
                if (!target) return;
                event.preventDefault();
                lockSection(target.id);
                scrollToSection(target);
            }));

            const bindScroll = isWindowScrollRoot(scrollRoot) ? window : scrollRoot;
            bindScroll.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll);
            syncNavPlacement();
            updateActiveFromScroll();

            if (window.location.hash) {
                const hashTarget = document.querySelector(window.location.hash);
                if (hashTarget && sections.includes(hashTarget)) {
                    lockSection(hashTarget.id);
                    window.requestAnimationFrame(() => {
                        syncNavPlacement();
                        scrollToSection(hashTarget);
                    });
                }
            }

            document.getElementById('workspaceOpenDeleteProduct')?.addEventListener('click', () => {
                document.getElementById('openDeleteProductWarning')?.click();
            });
        })();
    </script>
@endsection
