@extends('layouts.user.user-sidebar')

@section('title', 'Edit — '.$product->name.' — Product workspace')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('topbar')
    <x-ui.merchant-topbar title="Edit Product" lead="Update catalog details, variants, and inventory." class="!border-b-0 !shadow-none">
        <x-slot:actions>
            <a href="{{ route('products.show', $product) }}" class="hidden sm:inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                Back
            </a>
            <button type="submit" form="editProductForm" class="hidden sm:inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-bold text-white shadow-sm transition hover:opacity-90">
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
                    <p class="product-edit-eyebrow">Catalog <span aria-hidden="true">/</span> {{ $product->name }}</p>
                    <h2>{{ $product->name }}</h2>
                    <p class="mt-1 text-sm text-[#454652]">Store: <span class="font-semibold text-[#1A1B22]">{{ $selectedStore?->name }}</span></p>
                    <p class="mt-1 text-sm italic text-[#454652]">
                         Use <span class="font-semibold not-italic text-[#3F51B5]">Save and return to workspace</span> when you are done.
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
                            <a href="#catalog-edit-section-basics" class="product-edit-section-link is-active" data-product-edit-tab>Basics</a>
                            <a href="#catalog-edit-section-media" class="product-edit-section-link" data-product-edit-tab>Media</a>
                            <a href="#catalog-edit-section-pricing" class="product-edit-section-link" data-product-edit-tab>Pricing</a>
                            <a href="#catalog-edit-section-organization" class="product-edit-section-link" data-product-edit-tab>Organization</a>
                            <a href="#catalog-edit-section-attributes" class="product-edit-section-link" data-product-edit-tab>Product specifications</a>
                            <a href="#catalog-edit-section-additional-details" class="product-edit-section-link" data-product-edit-tab>Additional details</a>
                            <a href="#catalog-edit-section-option-groups" class="product-edit-section-link" data-product-edit-tab>Variants</a>
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
                                <p class="text-[10px] font-bold uppercase tracking-wide text-[#3F51B5]">Inventory summary</p>
                                <p class="mt-1 text-4xl font-bold leading-none text-[#24389C] tabular-nums sm:text-5xl">{{ number_format($sumStock) }}</p>
                                <p class="mt-2 text-[11px] text-[#454652]">Total units across all variant rows</p>
                                <p class="mt-2 text-[9px] font-bold uppercase italic tracking-wide text-[#454652]/70">Updated {{ optional($product->updated_at)->diffForHumans() }}</p>
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
                .filter(Boolean);
            const scrollRoot = workspace.closest('.merchant-app') || document.scrollingElement || document.documentElement;
            let lockedId = null;
            let lockTimer = null;
            let ticking = false;

            const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const stickyOffset = () => {
                const navHeight = nav ? nav.getBoundingClientRect().height : 0;
                return navHeight + 16;
            };

            const syncNavPlacement = () => {
                if (!nav || !navShell) return;

                const headerBottom = mainHeader?.getBoundingClientRect().bottom
                    ?? scrollRoot.getBoundingClientRect().top;
                const shellTop = navShell.getBoundingClientRect().top;
                const shouldStick = shellTop <= headerBottom;

                nav.classList.toggle('is-stuck', shouldStick);

                if (shouldStick) {
                    const shellRect = navShell.getBoundingClientRect();
                    nav.style.setProperty('--product-edit-nav-top', `${headerBottom - 1}px`);
                    nav.style.setProperty('--product-edit-nav-left', `${shellRect.left}px`);
                    nav.style.setProperty('--product-edit-nav-width', `${shellRect.width}px`);
                } else {
                    nav.style.removeProperty('--product-edit-nav-top');
                    nav.style.removeProperty('--product-edit-nav-left');
                    nav.style.removeProperty('--product-edit-nav-width');
                }
            };

            let lastActiveId = null;
            const selectTab = (id) => {
                if (id === lastActiveId) return;
                lastActiveId = id;
                tabs.forEach((tab) => {
                    const active = tab.getAttribute('href') === '#' + id;
                    tab.classList.toggle('is-active', active);
                    if (active) {
                        tab.setAttribute('aria-current', 'location');
                        tab.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
                    } else {
                        tab.removeAttribute('aria-current');
                    }
                });
            };

            const scrollTopOf = (el) => {
                if (scrollRoot === document.scrollingElement || scrollRoot === document.documentElement || scrollRoot === document.body) {
                    return el.getBoundingClientRect().top + window.scrollY;
                }
                const rootRect = scrollRoot.getBoundingClientRect();
                return scrollRoot.scrollTop + (el.getBoundingClientRect().top - rootRect.top);
            };

            const updateActiveFromScroll = () => {
                if (!sections.length) return;
                if (lockedId) {
                    selectTab(lockedId);
                    return;
                }

                const isWindowScroll = scrollRoot === document.scrollingElement
                    || scrollRoot === document.documentElement
                    || scrollRoot === document.body;
                const maxScroll = Math.max(
                    0,
                    (isWindowScroll ? document.documentElement.scrollHeight : scrollRoot.scrollHeight)
                    - (isWindowScroll ? window.innerHeight : scrollRoot.clientHeight)
                );
                const current = isWindowScroll ? window.scrollY : scrollRoot.scrollTop;

                if (maxScroll > 0 && current >= maxScroll - 2) {
                    selectTab(sections[sections.length - 1].id);
                    return;
                }

                const line = current + stickyOffset();
                let active = sections[0];
                for (const section of sections) {
                    if (scrollTopOf(section) <= line) {
                        active = section;
                    } else {
                        break;
                    }
                }
                selectTab(active.id);
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

                lockedId = target.id;
                selectTab(target.id);
                if (lockTimer) window.clearTimeout(lockTimer);
                lockTimer = window.setTimeout(() => {
                    lockedId = null;
                    updateActiveFromScroll();
                }, 700);

                const top = Math.max(0, scrollTopOf(target) - stickyOffset());
                const behavior = prefersReducedMotion() ? 'auto' : 'smooth';
                if (scrollRoot === document.scrollingElement || scrollRoot === document.documentElement || scrollRoot === document.body) {
                    window.scrollTo({ top, behavior });
                } else {
                    scrollRoot.scrollTo({ top, behavior });
                }
            }));

            const bindScroll = scrollRoot === document.scrollingElement || scrollRoot === document.documentElement || scrollRoot === document.body
                ? window
                : scrollRoot;
            bindScroll.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll);
            syncNavPlacement();
            updateActiveFromScroll();

            if (window.location.hash) {
                const hashTarget = document.querySelector(window.location.hash);
                if (hashTarget && sections.includes(hashTarget)) {
                    selectTab(hashTarget.id);
                }
            }

            document.getElementById('workspaceOpenDeleteProduct')?.addEventListener('click', () => {
                document.getElementById('openDeleteProductWarning')?.click();
            });
        })();
    </script>
@endsection
