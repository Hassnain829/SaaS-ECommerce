@php
    $managementBrands = $managementBrands ?? collect();
    $brands = $managementBrands->isNotEmpty() ? $managementBrands : ($brands ?? collect());
    $canManageBrands = $canManageBrands ?? false;
    $embedCatalogHubs = (bool) ($embedCatalogHubs ?? false);
    $reopenAdd = $errors->any() && (old('_open_brand_add_modal') == '1' || old('_open_brand_add_modal') === 1 || old('_open_brand_add_modal') === true);
    $reopenEdit = $errors->any() && old('_editing_brand_id');
    $editingBrand = $reopenEdit ? $brands->firstWhere('id', (int) old('_editing_brand_id')) : null;
    $reopenEdit = $reopenEdit && $editingBrand instanceof \App\Models\Brand;
    $openBrandHub = (bool) ($openBrandHub ?? false) || $reopenAdd;
    $statusBadgeClasses = [
        'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'inactive' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'draft' => 'bg-amber-50 text-amber-900 ring-amber-200',
    ];
@endphp

@if ($canManageBrands)
<div id="brandHubModal" class="@if ($embedCatalogHubs) flex min-h-0 w-full flex-1 flex-col overflow-hidden @else fixed inset-0 z-[74] {{ $openBrandHub ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6 @endif">
    @unless ($embedCatalogHubs)
        <button type="button" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]" data-brand-hub-backdrop aria-label="Close"></button>
    @endunless
    <div class="relative flex w-full flex-col overflow-hidden rounded-xl bg-white @if ($embedCatalogHubs) max-h-full min-h-0 flex-1 border border-[#E2E8F0] shadow-sm @else max-h-[min(88vh,560px)] max-w-xl border border-[#E2E8F0] shadow-md @endif">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 py-3.5 sm:px-5">
            <div class="min-w-0">
                <h2 class="text-base font-semibold tracking-tight text-[#0F172A] font-[Poppins]">Brands</h2>
                @unless ($embedCatalogHubs)
                    <p class="mt-0.5 text-xs text-[#64748B]">Manage brand records for this store.</p>
                @endunless
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" id="brand-hub-open-add" class="inline-flex items-center gap-1.5 rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#0047B3] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/35">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M5 6.66667H0V5H5V0H6.66667V5H11.6667V6.66667H6.66667V11.6667H5V6.66667Z" fill="currentColor"/></svg>
                    Add brand
                </button>
                @unless ($embedCatalogHubs)
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B] transition hover:border-[#0052CC] hover:text-[#0052CC]" data-brand-hub-close aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                @endunless
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3 sm:px-5 sm:py-3">
            <table class="w-full min-w-[360px] text-left text-sm text-[#334155]">
                <thead>
                    <tr class="border-b border-[#E2E8F0] bg-[#F8FAFC]">
                        <th class="py-3 pl-1 pr-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B] sm:pl-2">Brand</th>
                        <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">Status</th>
                        <th class="w-14 px-2 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">#</th>
                        <th class="py-3 pl-3 pr-1 text-right text-[11px] font-semibold uppercase tracking-wide text-[#64748B] sm:pr-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F1F5F9]">
                    @forelse ($brands as $brand)
                        @php
                            $sc = $statusBadgeClasses[$brand->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                            $n = (int) $brand->products_count;
                            $brandPayload = [
                                'id' => $brand->id,
                                'name' => $brand->name,
                                'status' => $brand->status,
                                'sort_order' => (int) $brand->sort_order,
                                'products_count' => $n,
                                'short_description' => $brand->short_description,
                                'slug' => $brand->slug,
                                'description' => $brand->description ?? '',
                                'featured' => (bool) $brand->featured,
                                'seo_title' => $brand->seo_title,
                                'seo_description' => $brand->seo_description,
                                'update_url' => route('brands.update', $brand),
                            ];
                        @endphp
                        <tr class="align-middle transition-colors hover:bg-[#F8FAFC]/90">
                            <td class="max-w-[9rem] py-3.5 pl-1 pr-3 font-medium text-[#0F172A] sm:max-w-none sm:pl-2">
                                <span class="block truncate">{{ $brand->name }}</span>
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $sc }}">{{ ucfirst($brand->status) }}</span>
                            </td>
                            <td class="px-2 py-3.5 text-center">
                                <span class="inline-flex min-w-[1.75rem] justify-center rounded-md bg-[#F1F5F9] px-2 py-0.5 text-xs font-semibold tabular-nums text-[#475569]">{{ $n }}</span>
                            </td>
                            <td class="py-3.5 pl-3 pr-1 text-right sm:pr-2">
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    @if ($n > 0)
                                        <a href="{{ route('products', ['brand' => $brand->id]) }}" class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#0052CC] hover:bg-[#EEF4FF]" data-brand-hub-close-link>View</a>
                                    @endif
                                    <button type="button" class="js-brand-edit-open inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#475569] hover:bg-[#F1F5F9] hover:text-[#0F172A]" data-brand='@json($brandPayload)'>Edit</button>
                                    <button type="button" class="js-brand-delete-open inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-[#B42318] hover:bg-[#FEF2F2]" data-delete-url="{{ route('brands.destroy', $brand) }}" data-brand-name="{{ e($brand->name) }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-2 py-10 text-center sm:px-4">
                                <p class="text-sm font-medium text-[#475569]">No brands yet</p>
                                <p class="mt-1 text-xs text-[#94A3B8]">Create a brand to assign to products.</p>
                                <button type="button" id="brand-hub-empty-add" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-[#0052CC] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#0047B3]">Add brand</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <details id="brand-hub-add-details" class="mt-5 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 shadow-sm sm:px-4 sm:py-3.5" @if ($reopenAdd) open @endif>
                <summary class="cursor-pointer list-none text-sm font-semibold text-[#0F172A] [&::-webkit-details-marker]:hidden">Add new brand</summary>
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
                    <form method="POST" action="{{ route('brands.store') }}" class="space-y-3" id="brandHubAddForm">
                        @csrf
                        <input type="hidden" name="_open_brand_add_modal" value="1">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 sm:items-end">
                            <div class="sm:col-span-5">
                                <label class="mb-0.5 block text-[11px] font-semibold text-[#64748B]">Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] shadow-sm placeholder:text-[#94A3B8] focus:border-[#0052CC]/40 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" placeholder="Brand name">
                                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-3">
                                <label class="mb-0.5 block text-[11px] font-semibold text-[#64748B]">Status</label>
                                <select name="status" class="w-full appearance-none rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] shadow-sm focus:border-[#0052CC]/40 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                    @foreach (['active', 'inactive', 'draft'] as $st)
                                        <option value="{{ $st }}" @selected(old('status', 'active') === $st)>{{ ucfirst($st) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="mb-0.5 block text-[11px] font-semibold text-[#64748B]">Note <span class="font-normal text-[#94A3B8]">(opt.)</span></label>
                                <input type="text" name="short_description" value="{{ old('short_description') }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] shadow-sm placeholder:text-[#94A3B8] focus:border-[#0052CC]/40 focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" placeholder="Short note (optional)">
                            </div>
                        </div>
                        <details class="rounded-md border border-[#E2E8F0] bg-white px-2.5 py-1.5">
                            <summary class="cursor-pointer list-none text-[11px] font-semibold text-[#64748B] [&::-webkit-details-marker]:hidden">More</summary>
                            <div class="mt-2 space-y-2 border-t border-[#E2E8F0] pt-2">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">Slug</label>
                                        <input type="text" name="slug" value="{{ old('slug') }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm" placeholder="Auto from name if empty">
                                        @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">Sort order</label>
                                        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">Long description</label>
                                        <textarea name="description" rows="2" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">{{ old('description') }}</textarea>
                                    </div>
                                    <div class="flex items-center gap-2 sm:col-span-2">
                                        <input type="hidden" name="featured" value="0">
                                        <input type="checkbox" name="featured" value="1" id="brand-hub-add-featured" class="rounded border-[#CBD5E1]" @checked(old('featured') === '1')>
                                        <label for="brand-hub-add-featured" class="text-sm text-[#0F172A]">Featured</label>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">SEO title</label>
                                        <input type="text" name="seo_title" value="{{ old('seo_title') }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">SEO description</label>
                                        <input type="text" name="seo_description" value="{{ old('seo_description') }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                                    </div>
                                </div>
                            </div>
                        </details>
                        <div class="flex justify-end pt-1">
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0047B3] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/35">Save brand</button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
</div>

<div id="brandEditModal" class="fixed inset-0 z-[76] {{ $reopenEdit ? 'flex' : 'hidden' }} items-center justify-center px-4 py-6">
    <button type="button" class="absolute inset-0 bg-[#0F172A]/70 backdrop-blur-[3px]" data-brand-edit-backdrop aria-label="Close"></button>
    <div class="relative w-full max-w-md overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-md">
        <div class="flex items-start justify-between gap-2 border-b border-[#E2E8F0] bg-white px-3 py-2.5 sm:px-4">
            <h2 class="text-sm font-semibold text-[#0F172A] font-[Poppins]">Edit <span id="brandEditTitleName" class="text-[#475569]">{{ $reopenEdit ? $editingBrand->name : '…' }}</span></h2>
            <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#D9E2EC] bg-white text-[#64748B] hover:border-[#0052CC] hover:text-[#0052CC]" data-brand-edit-close aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3L13 13M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="max-h-[calc(100vh-6rem)] overflow-y-auto px-3 py-3 sm:px-4">
            @if ($reopenEdit)
                <div class="mb-3 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-3 py-2 text-xs text-[#B42318]">
                    <ul class="ml-5 list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ $reopenEdit ? route('brands.update', $editingBrand) : '#' }}" id="brandEditForm" class="space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="_open_brand_edit_modal" value="1">
                <input type="hidden" name="_editing_brand_id" id="brand_edit_brand_id" value="{{ $reopenEdit ? old('_editing_brand_id', $editingBrand->id) : '' }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:items-end">
                    <div class="sm:col-span-4">
                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">Name</label>
                        <input type="text" name="name" id="brand_edit_name" value="{{ $reopenEdit ? old('name', $editingBrand->name) : '' }}" required class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-semibold text-[#64748B]">Status</label>
                        <select name="status" id="brand_edit_status" class="w-full appearance-none rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            @foreach (['active', 'inactive', 'draft'] as $st)
                                <option value="{{ $st }}" @selected($reopenEdit ? (old('status', $editingBrand->status) === $st) : ($st === 'active'))>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-5">
                        <label class="mb-1 block text-[11px] font-semibold text-[#64748B]">Note <span class="font-normal text-[#94A3B8]">(opt.)</span></label>
                        <input type="text" name="short_description" id="brand_edit_short_description" value="{{ $reopenEdit ? old('short_description', $editingBrand->short_description) : '' }}" class="w-full rounded-md border border-[#E2E8F0] px-2.5 py-1.5 text-sm">
                    </div>
                </div>
                <p class="text-[11px] text-[#64748B]" id="brand_edit_product_count_wrap" style="display:none;"><span id="brand_edit_product_count" class="font-semibold text-[#475569]">—</span> products</p>
                <details class="rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-2.5 py-1.5" id="brand_edit_more_details" @if($reopenEdit && $errors->has('slug')) open @endif>
                    <summary class="cursor-pointer list-none text-[11px] font-semibold text-[#64748B] [&::-webkit-details-marker]:hidden">More</summary>
                    <div class="mt-2 space-y-2 border-t border-[#E2E8F0] pt-2">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[#64748B]">Slug</label>
                                <input type="text" name="slug" id="brand_edit_slug" value="{{ $reopenEdit ? old('slug', $editingBrand->slug) : '' }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                                @error('slug')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[#64748B]">Sort order</label>
                                <input type="number" name="sort_order" id="brand_edit_sort_order" min="0" value="{{ $reopenEdit ? old('sort_order', $editingBrand->sort_order) : '' }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-[#64748B]">Long description</label>
                                <textarea name="description" id="brand_edit_description" rows="2" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">{{ $reopenEdit ? old('description', $editingBrand->description) : '' }}</textarea>
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <input type="hidden" name="featured" value="0">
                                <input type="checkbox" name="featured" value="1" id="brand_edit_featured" class="rounded border-[#CBD5E1]" @checked($reopenEdit ? (old('featured', $editingBrand->featured ? '1' : '0') === '1') : false)>
                                <label for="brand_edit_featured" class="text-sm text-[#0F172A]">Featured</label>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[#64748B]">SEO title</label>
                                <input type="text" name="seo_title" id="brand_edit_seo_title" value="{{ $reopenEdit ? old('seo_title', $editingBrand->seo_title) : '' }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[#64748B]">SEO description</label>
                                <input type="text" name="seo_description" id="brand_edit_seo_description" value="{{ $reopenEdit ? old('seo_description', $editingBrand->seo_description) : '' }}" class="w-full rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>
                </details>
                <div class="flex flex-col-reverse gap-2 border-t border-[#F1F5F9] pt-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-md border border-[#E2E8F0] px-3 py-2 text-xs font-semibold text-[#475569] hover:bg-[#F8FAFC]" data-brand-edit-close>Cancel</button>
                    <button type="submit" class="rounded-md bg-[#0052CC] px-4 py-2 text-xs font-bold text-white hover:bg-[#0047B3]">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="brandDeleteWarningModal" class="fixed inset-0 z-[78] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]">
    <div class="w-full max-w-sm overflow-hidden rounded-xl border border-[#FECACA] bg-white shadow-md">
        <div class="px-4 pb-3 pt-4">
            <h3 class="text-sm font-semibold text-[#0F172A] font-[Poppins]">Delete <span id="deleteBrandName" class="text-[#475569]"></span>?</h3>
            <p class="mt-1.5 text-xs text-[#64748B]">Detach from products first if assigned.</p>
        </div>
        <div class="px-4 pb-4 pt-0">
            <form id="deleteBrandForm" method="POST" class="mt-2">
                @csrf
                @method('DELETE')
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelDeleteBrand" class="rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#DC2626] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#B91C1C]">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const hubModal = document.getElementById('brandHubModal');
    const editModal = document.getElementById('brandEditModal');
    const deleteModal = document.getElementById('brandDeleteWarningModal');
    const editForm = document.getElementById('brandEditForm');
    const deleteForm = document.getElementById('deleteBrandForm');
    const deleteName = document.getElementById('deleteBrandName');
    const editTitleName = document.getElementById('brandEditTitleName');
    const addDetails = document.getElementById('brand-hub-add-details');
    const editProductCountWrap = document.getElementById('brand_edit_product_count_wrap');
    const editProductCount = document.getElementById('brand_edit_product_count');
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
            window.__openCatalogToolsTab?.('brands');
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

    document.querySelectorAll('[data-open-brand-hub]').forEach((btn) => btn.addEventListener('click', openHub));
    hubModal.querySelectorAll('[data-brand-hub-close], [data-brand-hub-backdrop]').forEach((el) => el.addEventListener('click', closeHub));
    document.querySelectorAll('[data-brand-hub-close-link]').forEach((a) => a.addEventListener('click', () => closeHub()));

    document.getElementById('brand-hub-open-add')?.addEventListener('click', (e) => {
        e.preventDefault();
        openAddSection();
    });
    document.getElementById('brand-hub-empty-add')?.addEventListener('click', (e) => {
        e.preventDefault();
        openAddSection();
    });

    editModal.querySelectorAll('[data-brand-edit-close], [data-brand-edit-backdrop]').forEach((el) => el.addEventListener('click', closeEdit));

    document.getElementById('cancelDeleteBrand')?.addEventListener('click', closeDelete);
    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
            closeDelete();
        }
    });

    const fillEditFromPayload = (data) => {
        editForm.action = data.update_url || '#';
        document.getElementById('brand_edit_brand_id').value = data.id ?? '';
        document.getElementById('brand_edit_name').value = data.name ?? '';
        document.getElementById('brand_edit_status').value = data.status ?? 'active';
        document.getElementById('brand_edit_sort_order').value = data.sort_order ?? 0;
        document.getElementById('brand_edit_short_description').value = data.short_description ?? '';
        document.getElementById('brand_edit_slug').value = data.slug ?? '';
        document.getElementById('brand_edit_description').value = data.description ?? '';
        document.getElementById('brand_edit_seo_title').value = data.seo_title ?? '';
        document.getElementById('brand_edit_seo_description').value = data.seo_description ?? '';
        const feat = document.getElementById('brand_edit_featured');
        if (feat) feat.checked = !!data.featured;
        if (editTitleName) editTitleName.textContent = data.name || '…';
        if (editProductCountWrap && editProductCount) {
            const n = data.products_count != null ? Number(data.products_count) : NaN;
            if (!Number.isNaN(n) && n > 0) {
                editProductCount.textContent = String(n);
                editProductCountWrap.style.display = '';
            } else {
                editProductCountWrap.style.display = 'none';
            }
        }
        const more = document.getElementById('brand_edit_more_details');
        const serverReopenEdit = @json((bool) $reopenEdit);
        if (more && !serverReopenEdit) {
            more.removeAttribute('open');
        }
    };

    hubModal.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.js-brand-edit-open');
        if (editBtn instanceof HTMLButtonElement) {
            e.preventDefault();
            let data = null;
            try {
                const raw = editBtn.getAttribute('data-brand');
                data = raw ? JSON.parse(raw) : null;
            } catch (err) {
                return;
            }
            if (!data || !data.update_url) return;
            fillEditFromPayload(data);
            openEdit();
            return;
        }
        const delBtn = e.target.closest('.js-brand-delete-open');
        if (delBtn instanceof HTMLButtonElement) {
            e.preventDefault();
            const url = delBtn.getAttribute('data-delete-url');
            const name = delBtn.getAttribute('data-brand-name') || 'this brand';
            if (!url) return;
            deleteForm.action = url;
            if (deleteName) deleteName.textContent = name;
            openDelete();
        }
    });

    @if ($openBrandHub && ! $reopenEdit)
    syncBodyLock();
    @endif
    @if ($reopenEdit)
    syncBodyLock();
    @endif
})();
</script>
@endif
