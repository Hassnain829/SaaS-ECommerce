@php
    $canManageCatalogTools = ($canManageBrands ?? false) || ($canManageTags ?? false) || ($canManageCategories ?? false);
    if (!isset($catalogToolsDefaultTab) || $catalogToolsDefaultTab === null || $catalogToolsDefaultTab === '') {
        if ($canManageCategories ?? false) {
            $catalogToolsDefaultTab = 'categories';
        } elseif ($canManageBrands ?? false) {
            $catalogToolsDefaultTab = 'brands';
        } else {
            $catalogToolsDefaultTab = 'tags';
        }
    }
    $catalogToolsAllowedTabs = array_values(array_filter([
        ($canManageCategories ?? false) ? 'categories' : null,
        ($canManageBrands ?? false) ? 'brands' : null,
        ($canManageTags ?? false) ? 'tags' : null,
    ]));
    if ($catalogToolsAllowedTabs !== [] && !in_array($catalogToolsDefaultTab, $catalogToolsAllowedTabs, true)) {
        $catalogToolsDefaultTab = $catalogToolsAllowedTabs[0];
    }
@endphp

@if ($canManageCatalogTools)
<script>
(() => {
    window.__openCatalogToolsTab = window.__openCatalogToolsTab || function (tab) {
        const shell = document.getElementById('catalogToolsShellModal');
        if (!shell) return;
        shell.classList.remove('hidden');
        shell.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        const tabButtons = [...shell.querySelectorAll('[data-catalog-tab]')];
        const present = new Set(tabButtons.map((btn) => btn.getAttribute('data-catalog-tab')).filter(Boolean));
        const order = ['categories', 'brands', 'tags'];
        let tabKey = tab === 'tags' || tab === 'categories' || tab === 'brands' ? tab : null;
        if (!tabKey || !present.has(tabKey)) {
            tabKey = order.find((t) => present.has(t)) || tabButtons[0]?.getAttribute('data-catalog-tab') || 'categories';
        }

        shell.querySelectorAll('[data-catalog-tab]').forEach((btn) => {
            const active = btn.getAttribute('data-catalog-tab') === tabKey;
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        shell.querySelectorAll('[data-catalog-tab-panel]').forEach((panel) => {
            const match = panel.getAttribute('data-catalog-tab-panel') === tabKey;
            panel.classList.toggle('hidden', !match);
            panel.classList.toggle('flex', match);
        });
    };

    window.__closeCatalogToolsShell = window.__closeCatalogToolsShell || function () {
        const shell = document.getElementById('catalogToolsShellModal');
        if (!shell) return;
        shell.classList.add('hidden');
        shell.classList.remove('flex');
        const brandEdit = document.getElementById('brandEditModal');
        const brandDel = document.getElementById('brandDeleteWarningModal');
        const tagEdit = document.getElementById('tagEditModal');
        const tagDel = document.getElementById('tagDeleteWarningModal');
        const catEdit = document.getElementById('categoryEditModal');
        const catDel = document.getElementById('categoryDeleteWarningModal');
        [brandEdit, brandDel, tagEdit, tagDel, catEdit, catDel].forEach((el) => {
            if (!el) return;
            el.classList.add('hidden');
            el.classList.remove('flex');
        });
        document.body.classList.remove('overflow-hidden');
    };
})();
</script>

<div id="catalogToolsShellModal"
    class="fixed inset-0 z-[68] {{ ($openCatalogToolsShell ?? false) ? 'flex' : 'hidden' }} min-h-0 items-center justify-center px-4 py-4 sm:px-5 sm:py-6"
    data-catalog-tools-shell>
    <button type="button" class="absolute inset-0 bg-[#0F172A]/60 backdrop-blur-[2px] transition-opacity" data-catalog-tools-backdrop aria-label="Close catalog tools"></button>
    <div class="relative flex max-h-[min(92dvh,700px)] w-full max-w-2xl min-h-0 flex-col overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-lg shadow-slate-900/10">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 py-3 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-base font-semibold tracking-tight text-[#0F172A] font-[Poppins]">Catalog tools</h2>
                <p class="mt-0.5 max-w-md text-[11px] leading-snug text-[#64748B]"><span class="font-medium text-[#0F766E]">Categories</span> organize the storefront catalog; brand and tags are optional.</p>
            </div>
            <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] transition hover:border-[#0052CC] hover:text-[#0052CC]" data-catalog-tools-close aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="flex shrink-0 flex-wrap gap-0.5 border-b border-[#E2E8F0] bg-[#F8FAFC] px-2 py-2 sm:px-3" role="tablist" aria-label="Catalog organization">
            @if ($canManageCategories)
                <button type="button" role="tab" data-catalog-tab="categories"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-[#64748B] transition-all hover:bg-white hover:text-[#0F172A] aria-selected:bg-white aria-selected:font-semibold aria-selected:text-[#0F766E] aria-selected:shadow-sm aria-selected:ring-1 aria-selected:ring-[#0D9488]/25"
                    aria-selected="{{ $catalogToolsDefaultTab === 'categories' ? 'true' : 'false' }}">Categories</button>
            @endif
            @if ($canManageBrands)
                <button type="button" role="tab" data-catalog-tab="brands"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-[#64748B] transition-all hover:bg-white hover:text-[#0F172A] aria-selected:bg-white aria-selected:font-semibold aria-selected:text-[#0052CC] aria-selected:shadow-sm aria-selected:ring-1 aria-selected:ring-[#0052CC]/20"
                    aria-selected="{{ $catalogToolsDefaultTab === 'brands' ? 'true' : 'false' }}">Brands</button>
            @endif
            @if ($canManageTags)
                <button type="button" role="tab" data-catalog-tab="tags"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-[#64748B] transition-all hover:bg-white hover:text-[#0F172A] aria-selected:bg-white aria-selected:font-normal aria-selected:text-[#475569] aria-selected:shadow-sm aria-selected:ring-1 aria-selected:ring-[#E2E8F0]"
                    aria-selected="{{ $catalogToolsDefaultTab === 'tags' ? 'true' : 'false' }}">Tags</button>
            @endif
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-[#FAFBFC]">
            @if ($canManageCategories)
                <div data-catalog-tab-panel="categories"
                    class="{{ $catalogToolsDefaultTab === 'categories' ? 'flex' : 'hidden' }} min-h-0 flex-1 flex-col overflow-hidden p-2 sm:p-2.5">
                    @include('user_view.partials.category_modals', [
                        'managementCategories' => $managementCategories ?? collect(),
                        'canManageCategories' => true,
                        'embedCatalogHubs' => true,
                    ])
                </div>
            @endif
            @if ($canManageBrands)
                <div data-catalog-tab-panel="brands"
                    class="{{ $catalogToolsDefaultTab === 'brands' ? 'flex' : 'hidden' }} min-h-0 flex-1 flex-col overflow-hidden p-2 sm:p-2.5">
                    @include('user_view.partials.brand_modals', [
                        'managementBrands' => $managementBrands ?? collect(),
                        'canManageBrands' => true,
                        'embedCatalogHubs' => true,
                    ])
                </div>
            @endif
            @if ($canManageTags)
                <div data-catalog-tab-panel="tags"
                    class="{{ $catalogToolsDefaultTab === 'tags' ? 'flex' : 'hidden' }} min-h-0 flex-1 flex-col overflow-hidden p-2 sm:p-2.5">
                    @include('user_view.partials.tag_modals', [
                        'managementTags' => $managementTags ?? collect(),
                        'canManageTags' => true,
                        'embedCatalogHubs' => true,
                    ])
                </div>
            @endif
        </div>
    </div>
</div>

<script>
(() => {
    const shell = document.getElementById('catalogToolsShellModal');
    if (!shell) return;

    const closeShell = () => window.__closeCatalogToolsShell?.();

    document.querySelectorAll('[data-open-catalog-tools]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-catalog-tools-tab');
            window.__openCatalogToolsTab?.(tab);
        });
    });

    shell.querySelectorAll('[data-catalog-tools-close], [data-catalog-tools-backdrop]').forEach((el) => {
        el.addEventListener('click', closeShell);
    });

    shell.querySelectorAll('[data-catalog-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-catalog-tab');
            if (tab) window.__openCatalogToolsTab?.(tab);
        });
    });
})();
</script>
@endif
