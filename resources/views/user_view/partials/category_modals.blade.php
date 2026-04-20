@php
    $managementCategories = $managementCategories ?? collect();
    $categories = $managementCategories->isNotEmpty() ? $managementCategories : ($categories ?? collect());
    $canManageCategories = $canManageCategories ?? false;
    $embedCatalogHubs = (bool) ($embedCatalogHubs ?? false);
    $reopenAdd = $errors->any() && (old('_open_category_add_modal') == '1' || old('_open_category_add_modal') === 1 || old('_open_category_add_modal') === true);
    $reopenEdit = $errors->any() && old('_editing_category_id');
    $editingCategory = $reopenEdit ? $categories->firstWhere('id', (int) old('_editing_category_id')) : null;
    $reopenEdit = $reopenEdit && $editingCategory instanceof \App\Models\Category;
    $openCategoryHub = (bool) ($openCategoryHub ?? false) || $reopenAdd;
    $statusBadgeClasses = [
        'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200',
    ];
@endphp

@if ($canManageCategories)
<div id="categoryHubModal" class="@if ($embedCatalogHubs) flex min-h-0 w-full flex-1 flex-col overflow-hidden @else fixed inset-0 z-[71] {{ $openCategoryHub ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6 @endif">
    @unless ($embedCatalogHubs)
        <button type="button" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]" data-category-hub-backdrop aria-label="Close"></button>
    @endunless
    <div class="relative flex w-full flex-col overflow-hidden rounded-xl bg-white @if ($embedCatalogHubs) max-h-full min-h-0 flex-1 border border-[#E2E8F0] shadow-sm @else max-h-[min(88vh,520px)] max-w-lg border border-[#E2E8F0] shadow-md @endif">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 py-3 sm:px-5">
            <div class="min-w-0">
                @if ($embedCatalogHubs)
                    <h2 class="text-sm font-semibold tracking-tight text-[#0F766E] font-[Poppins]">Your catalog structure</h2>
                    <p class="mt-0.5 text-[11px] text-[#64748B]">Groups products for browsing—subcategories nest under a parent.</p>
                @else
                    <h2 class="text-base font-semibold tracking-tight text-[#0F172A] font-[Poppins]">Categories</h2>
                    <p class="mt-0.5 text-xs text-[#64748B]">Browse groups like Clothing, Electronics, or Accessories.</p>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" id="category-hub-open-add" class="inline-flex items-center gap-1.5 rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#0047B3] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/35">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="currentColor"/></svg>
                    Add category
                </button>
                @unless ($embedCatalogHubs)
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B] transition hover:border-[#0052CC] hover:text-[#0052CC]" data-category-hub-close aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                @endunless
            </div>
        </div>
        @if ($embedCatalogHubs)
            <p class="shrink-0 border-b border-[#E2E8F0] bg-[#FAFBFC] px-4 py-1.5 text-[10px] leading-snug text-[#64748B] sm:px-5">Tip: category = storefront grouping · type = how the product behaves (shipping, digital delivery, etc.).</p>
        @endif
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3 sm:px-5 sm:py-3">
            <table class="w-full min-w-[320px] text-left text-sm text-[#334155]">
                <thead>
                    <tr class="border-b border-[#E2E8F0] bg-[#F8FAFC]">
                        <th class="py-3 pl-1 pr-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B] sm:pl-2">@if ($embedCatalogHubs)Group @else Category @endif</th>
                        <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Status</th>
                        <th class="w-14 px-2 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">#</th>
                        <th class="py-3 pl-3 pr-1 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B] sm:pr-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F1F5F9]">
                    @forelse ($categories as $cat)
                        @php
                            $sc = $statusBadgeClasses[$cat->status ?? 'active'] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                            $n = (int) $cat->products_count;
                            $catPayload = [
                                'id' => $cat->id,
                                'name' => $cat->name,
                                'slug' => $cat->slug,
                                'parent_id' => $cat->parent_id,
                                'status' => $cat->status ?? 'active',
                                'sort_order' => (int) $cat->sort_order,
                                'products_count' => $n,
                                'update_url' => route('categories.update', $cat),
                            ];
                        @endphp
                        <tr class="align-middle transition-colors hover:bg-[#F8FAFC]/90">
                            <td class="max-w-[10rem] py-3 pl-1 pr-3 font-medium text-[#0F172A] sm:max-w-none sm:pl-2">
                                @if ($cat->parent)
                                    <div class="border-l-2 border-[#99F6E4] pl-2">
                                        <span class="block truncate text-sm">{{ $cat->name }}</span>
                                        <span class="mt-0.5 block truncate text-[11px] font-normal text-[#64748B]">in {{ $cat->parent->name }}</span>
                                    </div>
                                @else
                                    <span class="block truncate text-sm">{{ $cat->name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $sc }}">{{ ucfirst($cat->status ?? 'active') }}</span>
                            </td>
                            <td class="px-2 py-3.5 text-center">
                                <span class="inline-flex min-w-[1.75rem] justify-center rounded-md bg-[#F1F5F9] px-2 py-0.5 text-xs font-semibold tabular-nums text-[#475569]">{{ $n }}</span>
                            </td>
                            <td class="py-3.5 pl-3 pr-1 text-right sm:pr-2">
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    @if ($n > 0)
                                        <a href="{{ route('products', ['category' => $cat->id]) }}" class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#0052CC] hover:bg-[#EEF4FF]" data-category-hub-close-link>View</a>
                                    @endif
                                    <button type="button" class="js-category-edit-open inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#475569] hover:bg-[#F1F5F9] hover:text-[#0F172A]" data-category='@json($catPayload)'>Edit</button>
                                    <button type="button" class="js-category-delete-open inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#B42318] hover:bg-[#FEF2F2]" data-delete-url="{{ route('categories.destroy', $cat) }}" data-category-name="{{ e($cat->name) }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-2 py-10 text-center sm:px-4">
                                <p class="text-sm font-medium text-[#475569]">No categories yet</p>
                                <p class="mt-1 text-xs text-[#94A3B8]">Create groups to organize products in filters.</p>
                                <button type="button" id="category-hub-empty-add" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-[#0052CC] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0047B3]">Add category</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <details id="category-hub-add-details" class="mt-5 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 shadow-sm sm:px-4 sm:py-3.5" @if ($reopenAdd) open @endif>
                <summary class="cursor-pointer list-none text-sm font-semibold text-[#0F172A] [&::-webkit-details-marker]:hidden">Add new category</summary>
                <div class="mt-4 border-t border-[#F1F5F9] pt-4">
                    @if ($reopenAdd)
                        <div class="mb-3 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-3 py-2 text-xs text-[#B42318]">
                            <ul class="ml-5 list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('categories.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="_open_category_add_modal" value="1">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-[#64748B]">Name</label>
                            <input type="text" name="name" value="{{ old('name', '') }}" required maxlength="120" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm shadow-sm placeholder:text-[#94A3B8] focus:border-[#0052CC]/40 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" placeholder="Category name">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-[#64748B]">Status</label>
                            <select name="status" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm shadow-sm focus:border-[#0052CC]/40 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                @foreach (['active', 'inactive'] as $st)
                                    <option value="{{ $st }}" @selected(old('status', 'active') === $st)>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <details class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5" @if ($reopenAdd && ($errors->has('slug') || $errors->has('parent_id') || $errors->has('sort_order'))) open @endif>
                            <summary class="cursor-pointer text-xs font-semibold text-[#475569] [&::-webkit-details-marker]:hidden">More options</summary>
                            <div class="mt-3 space-y-3 border-t border-[#E2E8F0] pt-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-[#64748B]">Slug <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                                    <input type="text" name="slug" value="{{ old('slug', '') }}" maxlength="160" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-[#0052CC]/20">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-[#64748B]">Parent <span class="font-normal text-[#94A3B8]">(optional)</span></label>
                                    <select name="parent_id" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-[#0052CC]/20">
                                        <option value="">— None —</option>
                                        @foreach ($categories as $p)
                                            <option value="{{ $p->id }}" @selected((string) old('parent_id', '') === (string) $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-[#64748B]">Sort order</label>
                                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', '0') }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-[#0052CC]/20">
                                </div>
                            </div>
                        </details>
                        <div class="flex justify-end pt-1">
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0047B3] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/35">Save category</button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
</div>

<div id="categoryEditModal" class="fixed inset-0 z-[70] {{ $reopenEdit ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6">
    <button type="button" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]" data-category-edit-backdrop aria-label="Close"></button>
    <div class="relative w-full max-w-lg overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-md">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-4 py-2.5">
            <h2 class="text-sm font-semibold text-[#0F172A] font-[Poppins]">Edit <span id="categoryEditTitleName" class="text-[#475569]">{{ $reopenEdit ? $editingCategory->name : '…' }}</span></h2>
            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-[#D9E2EC] text-[#64748B]" data-category-edit-close aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="max-h-[min(70vh,480px)] overflow-y-auto px-4 py-3">
            @if ($reopenEdit)
                <div class="mb-3 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-3 py-2 text-xs text-[#B42318]">
                    <ul class="ml-5 list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ $reopenEdit ? route('categories.update', $editingCategory) : '#' }}" id="categoryEditForm" class="space-y-2">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_editing_category_id" id="category_edit_category_id" value="{{ $reopenEdit ? old('_editing_category_id', $editingCategory->id) : '' }}">
                <div>
                    <label class="mb-0.5 block text-[11px] font-semibold text-[#64748B]">Name</label>
                    <input type="text" name="name" id="category_edit_name" value="{{ $reopenEdit ? old('name', $editingCategory->name) : '' }}" required maxlength="120" class="w-full rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm">
                </div>
                <div>
                    <label class="mb-0.5 block text-[11px] font-semibold text-[#64748B]">Status</label>
                    <select name="status" id="category_edit_status" class="w-full rounded-lg border border-[#E2E8F0] px-2.5 py-1.5 text-sm">
                        @foreach (['active', 'inactive'] as $st)
                            <option value="{{ $st }}" @selected($reopenEdit ? (old('status', $editingCategory->status ?? 'active') === $st) : ($st === 'active'))>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <details class="rounded border border-[#E2E8F0] bg-[#F8FAFC] px-2 py-1.5" id="category_edit_more_details" @if ($reopenEdit && ($errors->has('slug') || $errors->has('parent_id'))) open @endif>
                    <summary class="cursor-pointer text-[11px] font-semibold text-[#475569]">More options</summary>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label class="mb-0.5 block text-[10px] text-[#64748B]">Slug</label>
                            <input type="text" name="slug" id="category_edit_slug" value="{{ $reopenEdit ? old('slug', $editingCategory->slug) : '' }}" maxlength="160" class="w-full rounded border border-[#E2E8F0] bg-white px-2 py-1 text-sm">
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] text-[#64748B]">Parent (optional)</label>
                            <select name="parent_id" id="category_edit_parent_id" class="w-full rounded border border-[#E2E8F0] bg-white px-2 py-1 text-sm">
                                <option value="">— None —</option>
                                @foreach ($categories as $p)
                                    @if (! $reopenEdit || $p->id !== $editingCategory->id)
                                        <option value="{{ $p->id }}" @selected($reopenEdit ? (string) old('parent_id', $editingCategory->parent_id ?? '') === (string) $p->id : false)>{{ $p->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-0.5 block text-[10px] text-[#64748B]">Sort order</label>
                            <input type="number" name="sort_order" id="category_edit_sort_order" min="0" value="{{ $reopenEdit ? old('sort_order', $editingCategory->sort_order) : '' }}" class="w-full rounded border border-[#E2E8F0] bg-white px-2 py-1 text-sm">
                        </div>
                    </div>
                </details>
                <p id="category_edit_product_count_wrap" class="hidden text-[11px] text-[#64748B]">Used on <span id="category_edit_product_count" class="font-semibold text-[#0F172A]"></span> product(s).</p>
                <button type="submit" class="w-full rounded-lg bg-[#0052CC] py-2 text-[11px] font-bold text-white hover:bg-[#0047B3]">Save changes</button>
            </form>
        </div>
    </div>
</div>

<div id="categoryDeleteWarningModal" class="fixed inset-0 z-[78] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]">
    <div class="w-full max-w-sm overflow-hidden rounded-xl border border-[#FECACA] bg-white shadow-md">
        <div class="px-4 pb-3 pt-4">
            <h3 class="text-sm font-semibold text-[#0F172A] font-[Poppins]">Remove <span id="deleteCategoryName" class="text-[#475569]"></span>?</h3>
            <p class="mt-1.5 text-xs text-[#64748B]">You can only delete a category after it is removed from all products.</p>
        </div>
        <div class="px-4 pb-4 pt-0">
            <form id="deleteCategoryForm" method="POST" class="mt-2">
                @csrf
                @method('DELETE')
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelDeleteCategory" class="rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#DC2626] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#B91C1C]">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const hubModal = document.getElementById('categoryHubModal');
    const editModal = document.getElementById('categoryEditModal');
    const deleteModal = document.getElementById('categoryDeleteWarningModal');
    const editForm = document.getElementById('categoryEditForm');
    const deleteForm = document.getElementById('deleteCategoryForm');
    const deleteName = document.getElementById('deleteCategoryName');
    const editTitleName = document.getElementById('categoryEditTitleName');
    const addDetails = document.getElementById('category-hub-add-details');
    const editProductCountWrap = document.getElementById('category_edit_product_count_wrap');
    const editProductCount = document.getElementById('category_edit_product_count');
    if (!hubModal || !editModal || !deleteModal || !editForm || !deleteForm) return;

    const embedCatalogHubs = @json($embedCatalogHubs);
    const catalogToolsShell = () => document.getElementById('catalogToolsShellModal');
    const isFlex = (el) => el && el.classList.contains('flex');
    const syncBodyLock = () => {
        if (embedCatalogHubs) {
            if (isFlex(editModal) || isFlex(deleteModal)) {
                document.body.classList.add('overflow-hidden');
            } else if (catalogToolsShell() && catalogToolsShell().classList.contains('flex')) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
            return;
        }
        if (isFlex(hubModal) || isFlex(editModal) || isFlex(deleteModal)) {
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    };

    const openHub = () => {
        if (embedCatalogHubs) {
            window.__openCatalogToolsTab?.('categories');
            syncBodyLock();
            return;
        }
        hubModal.classList.remove('hidden');
        hubModal.classList.add('flex');
        syncBodyLock();
    };

    const openAddSection = () => {
        openHub();
        if (addDetails) {
            addDetails.open = true;
            addDetails.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    const openEdit = () => {
        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
        syncBodyLock();
    };
    const closeEdit = () => {
        editModal.classList.add('hidden');
        editModal.classList.remove('flex');
        syncBodyLock();
    };

    const openDelete = () => {
        deleteModal.classList.remove('hidden');
        deleteModal.classList.add('flex');
        syncBodyLock();
    };
    const closeDelete = () => {
        deleteModal.classList.add('hidden');
        deleteModal.classList.remove('flex');
        syncBodyLock();
    };

    const closeHub = () => {
        if (embedCatalogHubs) {
            if (isFlex(editModal) || isFlex(deleteModal)) {
                editModal.classList.add('hidden');
                editModal.classList.remove('flex');
                deleteModal.classList.add('hidden');
                deleteModal.classList.remove('flex');
                syncBodyLock();
                return;
            }
            window.__closeCatalogToolsShell?.();
            syncBodyLock();
            return;
        }
        hubModal.classList.add('hidden');
        hubModal.classList.remove('flex');
        syncBodyLock();
    };

    document.querySelectorAll('[data-open-category-hub]').forEach((btn) => btn.addEventListener('click', openHub));
    hubModal.querySelectorAll('[data-category-hub-close], [data-category-hub-backdrop]').forEach((el) => el.addEventListener('click', closeHub));
    document.querySelectorAll('[data-category-hub-close-link]').forEach((a) => a.addEventListener('click', () => closeHub()));

    document.getElementById('category-hub-open-add')?.addEventListener('click', (e) => {
        e.preventDefault();
        openAddSection();
    });
    document.getElementById('category-hub-empty-add')?.addEventListener('click', (e) => {
        e.preventDefault();
        openAddSection();
    });

    editModal.querySelectorAll('[data-category-edit-close], [data-category-edit-backdrop]').forEach((el) => el.addEventListener('click', closeEdit));

    document.getElementById('cancelDeleteCategory')?.addEventListener('click', closeDelete);
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            closeDelete();
        }
    });

    const fillEditFromPayload = (data) => {
        editForm.action = data.update_url || '#';
        document.getElementById('category_edit_category_id').value = data.id ?? '';
        document.getElementById('category_edit_name').value = data.name ?? '';
        document.getElementById('category_edit_status').value = data.status ?? 'active';
        document.getElementById('category_edit_sort_order').value = data.sort_order ?? 0;
        document.getElementById('category_edit_slug').value = data.slug ?? '';
        const parentSelect = document.getElementById('category_edit_parent_id');
        if (parentSelect) {
            const selfId = String(data.id ?? '');
            [...parentSelect.options].forEach((opt) => {
                opt.hidden = opt.value && opt.value === selfId;
            });
            parentSelect.value = data.parent_id != null && String(data.parent_id) !== '' ? String(data.parent_id) : '';
        }
        if (editTitleName) editTitleName.textContent = data.name || '…';
        if (editProductCountWrap && editProductCount) {
            const n = data.products_count != null ? Number(data.products_count) : NaN;
            if (!Number.isNaN(n) && n > 0) {
                editProductCount.textContent = String(n);
                editProductCountWrap.classList.remove('hidden');
            } else {
                editProductCountWrap.classList.add('hidden');
            }
        }
        const more = document.getElementById('category_edit_more_details');
        const serverReopenEdit = @json((bool) $reopenEdit);
        if (more && !serverReopenEdit) {
            more.removeAttribute('open');
        }
    };

    hubModal.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.js-category-edit-open');
        if (editBtn instanceof HTMLButtonElement) {
            e.preventDefault();
            let data = null;
            try {
                const raw = editBtn.getAttribute('data-category');
                data = raw ? JSON.parse(raw) : null;
            } catch (err) {
                return;
            }
            if (!data || !data.update_url) return;
            fillEditFromPayload(data);
            openEdit();
            return;
        }
        const delBtn = e.target.closest('.js-category-delete-open');
        if (delBtn instanceof HTMLButtonElement) {
            e.preventDefault();
            const url = delBtn.getAttribute('data-delete-url');
            const name = delBtn.getAttribute('data-category-name') || 'this category';
            if (!url) return;
            deleteForm.action = url;
            if (deleteName) deleteName.textContent = name;
            openDelete();
        }
    });

    @if ($openCategoryHub && ! $reopenEdit)
    syncBodyLock();
    @endif
    @if ($reopenEdit)
    syncBodyLock();
    @endif
})();
</script>
@endif
