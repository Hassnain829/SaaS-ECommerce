@php
    $canManageCatalogTools = ($canManageBrands ?? false) || ($canManageTags ?? false) || ($canManageCategories ?? false);
    $catalogToolsStayOnPage = (bool) ($catalogToolsStayOnPage ?? false);
    $catalogToolsReturn = $catalogToolsReturn ?? null;
    $catalogToolsReturnProductId = $catalogToolsReturnProductId ?? null;
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
    class="ui-modal-shell ui-modal-shell--clear {{ ($openCatalogToolsShell ?? false) ? 'flex' : 'hidden' }} min-h-0"
    data-catalog-tools-shell
    @if ($catalogToolsStayOnPage) data-catalog-tools-ajax="true" @endif
    @if ($catalogToolsReturn) data-catalog-return="{{ $catalogToolsReturn }}" @endif>
    <button type="button" class="ui-modal-backdrop" data-catalog-tools-backdrop aria-label="Close catalog tools"></button>
    <div class="ui-modal-panel ui-modal-panel--2xl">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 py-3 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-base font-semibold tracking-tight text-[#0F172A]">Catalog tools</h2>
                <p class="mt-0.5 max-w-md text-[11px] leading-snug text-[#64748B]"><span class="font-medium text-[#0F766E]">Categories</span> organize the storefront catalog; brand and tags are optional.</p>
            </div>
            <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#64748B] transition hover:border-[#0052CC] hover:text-[#0052CC]" data-catalog-tools-close aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>
        <p data-catalog-tools-status hidden class="shrink-0 border-b border-[#CCFBF1] bg-[#F0FDFA] px-4 py-2 text-xs font-medium text-[#115E59] sm:px-5" role="status"></p>
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

    document.addEventListener('click', (event) => {
        const btn = event.target instanceof Element ? event.target.closest('[data-open-catalog-tools]') : null;
        if (!btn) return;
        window.__openCatalogToolsTab?.(btn.getAttribute('data-catalog-tools-tab'));
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

    if (shell.getAttribute('data-catalog-tools-ajax') !== 'true') {
        return;
    }

    const statusEl = shell.querySelector('[data-catalog-tools-status]');
    const setStatus = (message, isError = false) => {
        if (!statusEl) return;
        if (!message) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('border-[#FECACA]', isError);
        statusEl.classList.toggle('bg-[#FEF2F2]', isError);
        statusEl.classList.toggle('text-[#B91C1C]', isError);
        statusEl.classList.toggle('border-[#CCFBF1]', !isError);
        statusEl.classList.toggle('bg-[#F0FDFA]', !isError);
        statusEl.classList.toggle('text-[#115E59]', !isError);
    };

    const fieldKind = (kind) => {
        if (kind === 'category') return 'categories';
        if (kind === 'brand') return 'brands';
        if (kind === 'tag') return 'tags';
        return kind;
    };

    const upsertOption = (select, item, { selectIt, multiple }) => {
        if (!select || !item || item.id == null) return;
        const value = String(item.id);
        const label = String(item.label || item.name || '');
        let option = [...select.options].find((opt) => opt.value === value);
        if (!option) {
            option = document.createElement('option');
            option.value = value;
            select.appendChild(option);
        }
        option.textContent = label;
        if (selectIt && item.assignable !== false) {
            if (multiple) {
                option.selected = true;
            } else {
                select.value = value;
            }
        }
    };

    const removeOption = (select, id) => {
        if (!select || id == null) return;
        const value = String(id);
        [...select.options].filter((opt) => opt.value === value).forEach((opt) => opt.remove());
        if (select.id === 'edit_product_brand_id' && select.value === value) {
            select.value = '';
        }
    };

    const syncOrgVisibility = (kind) => {
        const key = fieldKind(kind);
        const wrap = document.querySelector(`[data-org-field="${key}"]`);
        const select = document.querySelector(`[data-org-select="${key}"]`);
        const empty = document.querySelector(`[data-org-empty="${key}"]`);
        if (!select) return;
        const hasChoices = key === 'brands'
            ? [...select.options].some((opt) => opt.value !== '')
            : select.options.length > 0;
        empty?.classList.toggle('hidden', hasChoices);
        if (key !== 'brands') {
            select.classList.toggle('hidden', !hasChoices);
        }
        wrap?.classList.remove('hidden');
    };

    const escapeHubText = (v) => String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
    const statusBadgeClass = (status) => {
        if (status === 'active') return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
        if (status === 'draft') return 'bg-amber-50 text-amber-900 ring-amber-200';
        return 'bg-slate-100 text-slate-700 ring-slate-200';
    };
    const emptyHubCopy = {
        categories: { title: 'No categories yet', help: 'Create groups to organize products in filters.', action: 'Add category', id: 'category-hub-empty-add' },
        brands: { title: 'No brands yet', help: 'Create a brand to assign to products.', action: 'Add brand', id: 'brand-hub-empty-add' },
        tags: { title: 'No tags yet', help: 'Use tags for quick merchandising labels.', action: 'Add tag', id: 'tag-hub-empty-add' },
    };
    const hubRowTableId = {
        brand: 'catalog-hub-brands-rows',
        tag: 'catalog-hub-tags-rows',
        category: 'catalog-hub-categories-rows',
    };
    const hubTbody = (kind) => {
        const key = fieldKind(kind);
        const byId = document.getElementById(hubRowTableId[kind] || '');
        if (byId) return byId;
        return shell.querySelector(`[data-catalog-hub-rows="${key}"]`)
            || document.querySelector(`[data-catalog-hub-rows="${key}"]`);
    };
    const clearEmptyHubRows = (tbody) => {
        tbody.querySelectorAll('[data-catalog-hub-empty]').forEach((row) => row.remove());
        [...tbody.querySelectorAll('tr')].forEach((row) => {
            if (row.querySelector('td[colspan]')) row.remove();
        });
    };
    const insertEmptyHubRow = (key, tbody) => {
        const copy = emptyHubCopy[key];
        if (!copy || !tbody) return;
        tbody.innerHTML = `<tr data-catalog-hub-empty="${key}"><td colspan="4" class="px-2 py-10 text-center sm:px-4"><p class="text-sm font-medium text-[#475569]">${copy.title}</p><p class="mt-1 text-xs text-[#94A3B8]">${copy.help}</p><button type="button" id="${copy.id}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-brand-hover">${copy.action}</button></td></tr>`;
    };
    const buildHubRow = (kind, item) => {
        const id = String(item.id);
        const name = escapeHubText(item.name || item.label || '');
        const status = String(item.status || 'active');
        const count = Number(item.products_count || 0);
        const tr = document.createElement('tr');
        tr.className = 'align-middle transition-colors hover:bg-[#F8FAFC]/90';
        tr.dataset.catalogRow = kind;
        tr.dataset.catalogRowId = id;
        let nameHtml = `<span class="block truncate">${name}</span>`;
        if (kind === 'category' && item.parent_name) {
            nameHtml = `<div class="border-l-2 border-[#99F6E4] pl-2"><span class="block truncate text-sm">${name}</span><span class="mt-0.5 block truncate text-[11px] font-normal text-[#64748B]">in ${escapeHubText(item.parent_name)}</span></div>`;
        }
        if (kind === 'tag' && item.color) {
            nameHtml = `<span class="flex items-center gap-2 truncate"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-[#E2E8F0]" style="background-color: ${escapeHubText(item.color)}"></span><span class="truncate">${name}</span></span>`;
        }
        const editAttr = kind === 'brand' ? 'data-brand' : (kind === 'tag' ? 'data-tag' : 'data-category');
        const editClass = kind === 'brand' ? 'js-brand-edit-open' : (kind === 'tag' ? 'js-tag-edit-open' : 'js-category-edit-open');
        const deleteClass = kind === 'brand' ? 'js-brand-delete-open' : (kind === 'tag' ? 'js-tag-delete-open' : 'js-category-delete-open');
        const deleteNameAttr = kind === 'brand' ? 'data-brand-name' : (kind === 'tag' ? 'data-tag-name' : 'data-category-name');
        tr.innerHTML = `<td class="max-w-[9rem] py-3.5 pl-1 pr-3 font-medium text-[#0F172A] sm:max-w-none sm:pl-2">${nameHtml}</td><td class="px-3 py-3.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ${statusBadgeClass(status)}">${escapeHubText(status.charAt(0).toUpperCase() + status.slice(1))}</span></td><td class="px-2 py-3.5 text-center"><span class="inline-flex min-w-[1.75rem] justify-center rounded-md bg-[#F1F5F9] px-2 py-0.5 text-xs font-semibold tabular-nums text-[#475569]">${count}</span></td><td class="py-3.5 pl-3 pr-1 text-right sm:pr-2"><div class="flex flex-wrap items-center justify-end gap-1"><button type="button" class="${editClass} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#475569] hover:bg-[#F1F5F9] hover:text-[#0F172A]">Edit</button><button type="button" class="${deleteClass} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#B42318] hover:bg-[#FEF2F2]" data-delete-url="${escapeHubText(item.destroy_url || '')}" ${deleteNameAttr}="${name}">Delete</button></div></td>`;
        const editBtn = tr.querySelector('.' + editClass);
        if (editBtn) editBtn.setAttribute(editAttr, JSON.stringify(item));
        return tr;
    };
    const upsertHubRow = (kind, item, action) => {
        const tbody = hubTbody(kind);
        if (!tbody || item == null || item.id == null) return;
        const id = String(item.id);
        if (action === 'deleted') {
            tbody.querySelector(`[data-catalog-row-id="${CSS.escape(id)}"]`)?.remove();
            if (!tbody.querySelector('[data-catalog-row]')) insertEmptyHubRow(fieldKind(kind), tbody);
            return;
        }
        clearEmptyHubRows(tbody);
        const next = buildHubRow(kind, item);
        const existing = tbody.querySelector(`[data-catalog-row-id="${CSS.escape(id)}"]`);
        if (existing) existing.replaceWith(next);
        else tbody.prepend(next);
        next.scrollIntoView({ block: 'nearest' });
    };

    const applyCatalogItem = (payload) => {
        const kind = payload?.kind;
        const action = payload?.action;
        const item = payload?.item || {};
        const selectIt = action === 'created';
        if (kind === 'category') {
            const select = document.getElementById('edit_product_category_ids');
            if (action === 'deleted') {
                removeOption(select, item.id);
            } else if (item.assignable === false) {
                removeOption(select, item.id);
            } else {
                upsertOption(select, item, { selectIt, multiple: true });
            }
            const parentSelects = document.querySelectorAll('select[name="parent_id"], #category_edit_parent_id');
            parentSelects.forEach((el) => {
                if (!(el instanceof HTMLSelectElement)) return;
                if (action === 'deleted') {
                    removeOption(el, item.id);
                    return;
                }
                upsertOption(el, { id: item.id, label: item.name, assignable: true }, { selectIt: false, multiple: false });
            });
        }
        if (kind === 'brand') {
            const select = document.getElementById('edit_product_brand_id');
            if (action === 'deleted') {
                removeOption(select, item.id);
            } else {
                upsertOption(select, item, { selectIt, multiple: false });
            }
        }
        if (kind === 'tag') {
            const select = document.getElementById('edit_product_tag_ids');
            if (action === 'deleted') {
                removeOption(select, item.id);
            } else {
                upsertOption(select, item, { selectIt, multiple: true });
            }
        }
        if (kind) {
            if (action === 'created' || action === 'updated') {
                window.__openCatalogToolsTab?.(fieldKind(kind));
            }
            upsertHubRow(kind, item, action);
            syncOrgVisibility(kind);
        }
    };

    const resetCreateForm = (form) => {
        if (!(form instanceof HTMLFormElement)) return;
        if (form.querySelector('input[name="_method"]')) return;
        form.reset();
        const details = form.closest('details');
        if (details) details.open = false;
    };

    const firstErrorMessage = (data) => {
        if (data && typeof data.message === 'string' && data.message !== '') return data.message;
        const errors = data && data.errors && typeof data.errors === 'object' ? data.errors : null;
        if (!errors) return 'That could not be saved. Check the form and try again.';
        const first = Object.values(errors).flat().find((msg) => typeof msg === 'string' && msg !== '');
        return first || 'That could not be saved. Check the form and try again.';
    };

    document.querySelectorAll('form[data-catalog-kind]').forEach((form) => {
        form.setAttribute('data-turbo', 'false');
    });

    const emptyAddOpeners = {
        'brand-hub-empty-add': 'brand-hub-open-add',
        'tag-hub-empty-add': 'tag-hub-open-add',
        'category-hub-empty-add': 'category-hub-open-add',
    };
    document.addEventListener('click', (event) => {
        const emptyAdd = event.target instanceof Element ? event.target.closest('#brand-hub-empty-add, #tag-hub-empty-add, #category-hub-empty-add') : null;
        if (!emptyAdd) return;
        const openerId = emptyAddOpeners[emptyAdd.id];
        if (!openerId) return;
        event.preventDefault();
        document.getElementById(openerId)?.click();
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.hasAttribute('data-catalog-kind')) return;
        const actionAttr = form.getAttribute('action') || '';
        if (actionAttr === '' || actionAttr === '#') return;

        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        setStatus('');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                setStatus(firstErrorMessage(data), true);
                return;
            }
            applyCatalogItem(data);
            setStatus(data.message || 'Catalog updated.');
            if (data.action === 'created') {
                resetCreateForm(form);
            }
            if (data.action === 'updated' || data.action === 'deleted') {
                [document.getElementById('categoryEditModal'), document.getElementById('brandEditModal'), document.getElementById('tagEditModal')].forEach((el) => {
                    if (!el) return;
                    el.classList.add('hidden');
                    el.classList.remove('flex');
                });
                [document.getElementById('categoryDeleteWarningModal'), document.getElementById('brandDeleteWarningModal'), document.getElementById('tagDeleteWarningModal')].forEach((el) => {
                    if (!el) return;
                    el.classList.add('hidden');
                    el.classList.remove('flex');
                });
            }
            document.dispatchEvent(new CustomEvent('catalog-tools:saved', { detail: data }));
        } catch (error) {
            setStatus('Could not update the catalog. Try again.', true);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }, true);
})();
</script>
@endif
