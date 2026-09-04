@php
    $catalogBrands = $catalogBrands ?? collect();
    $catalogTags = $catalogTags ?? collect();
    $catalogTaxonomyCategories = $catalogTaxonomyCategories ?? collect();
    $canManageCategories = (bool) ($canManageCategories ?? false);
    $canManageBrands = (bool) ($canManageBrands ?? false);
    $canManageTags = (bool) ($canManageTags ?? false);
    $organizationCompact = (bool) ($organizationCompact ?? false);
    $selectedCategoryIds = collect(old('category_ids', []));
    $selectedTagIds = collect(old('tag_ids', []));
    $categoriesEmpty = $catalogTaxonomyCategories->isEmpty();
    $brandsEmpty = $catalogBrands->isEmpty();
    $tagsEmpty = $catalogTags->isEmpty();
@endphp

<div @class(['grid grid-cols-1 gap-5', 'md:col-span-2 lg:col-span-4' => $organizationCompact]) data-product-organization>
    <div data-org-field="categories" @class(['rounded-xl border border-[#CCFBF1]/80 bg-[#F0FDFA]/40 p-4', 'md:col-span-3' => $organizationCompact])>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label for="edit_product_category_ids" class="block text-sm font-semibold text-[#0F766E]">Categories</label>
            @if ($canManageCategories)
                <button type="button" data-open-catalog-tools data-catalog-tools-tab="categories" class="inline-flex items-center gap-1 text-xs font-semibold text-[#0F766E] hover:underline">
                    <span aria-hidden="true">+</span> Create category
                </button>
            @endif
        </div>
        <div data-org-empty="categories" @class(['rounded-lg border border-dashed border-[#99F6E4] bg-white/80 px-3 py-3', 'hidden' => ! $categoriesEmpty])>
            <p class="text-sm font-medium text-[#134E4A]">No categories yet</p>
            <p class="mt-0.5 text-xs text-[#115E59]/80">Create a group such as Clothing or Electronics, then assign this product to it.</p>
        </div>
        <select id="edit_product_category_ids" name="category_ids[]" multiple size="5" data-org-select="categories"
            @class(['w-full rounded-lg border border-[#99F6E4]/60 bg-white px-3 py-2 text-sm text-[#0F172A]', 'hidden' => $categoriesEmpty])>
            @foreach ($catalogTaxonomyCategories as $catOption)
                <option value="{{ $catOption->id }}" @selected($selectedCategoryIds->contains($catOption->id))>{{ $catOption->name }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-xs text-[#115E59]/90">Main catalog groups — separate from product type above. Hold Ctrl or Cmd to pick more than one.</p>
    </div>

    <div data-org-field="brands" @class(['' => ! $organizationCompact, 'md:col-span-3' => $organizationCompact])>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label for="edit_product_brand_id" class="block text-sm font-medium text-[#64748B]">Brand <span class="font-normal text-[#94A3B8]">(optional)</span></label>
            @if ($canManageBrands)
                <button type="button" data-open-catalog-tools data-catalog-tools-tab="brands" class="inline-flex items-center gap-1 text-xs font-semibold text-brand hover:underline">
                    <span aria-hidden="true">+</span> Create brand
                </button>
            @endif
        </div>
        <div data-org-empty="brands" @class(['mb-2 rounded-lg border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3', 'hidden' => ! $brandsEmpty])>
            <p class="text-sm font-medium text-[#334155]">No brands yet</p>
            <p class="mt-0.5 text-xs text-[#64748B]">Add a vendor or label if you sell more than one brand.</p>
        </div>
        <select id="edit_product_brand_id" name="brand_id" data-org-select="brands" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
            <option value="">No brand</option>
            @foreach ($catalogBrands as $brandOption)
                <option value="{{ $brandOption->id }}" @selected((string) old('brand_id', '') === (string) $brandOption->id)>{{ $brandOption->name }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-[#94A3B8]">Optional vendor or label. Does not change which store owns the product.</p>
    </div>

    <div data-org-field="tags" @class(['' => ! $organizationCompact, 'md:col-span-3' => $organizationCompact])>
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label for="edit_product_tag_ids" class="block text-sm font-medium text-[#64748B]">Tags <span class="font-normal text-[#94A3B8]">(optional)</span></label>
            @if ($canManageTags)
                <button type="button" data-open-catalog-tools data-catalog-tools-tab="tags" class="inline-flex items-center gap-1 text-xs font-semibold text-brand hover:underline">
                    <span aria-hidden="true">+</span> Create tag
                </button>
            @endif
        </div>
        <div data-org-empty="tags" @class(['rounded-lg border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3', 'hidden' => ! $tagsEmpty])>
            <p class="text-sm font-medium text-[#334155]">No tags yet</p>
            <p class="mt-0.5 text-xs text-[#64748B]">Tags are quick labels such as Featured or Sale — not your main catalog structure.</p>
        </div>
        <select id="edit_product_tag_ids" name="tag_ids[]" multiple size="4" data-org-select="tags"
            @class(['w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm text-[#0F172A]', 'hidden' => $tagsEmpty])>
            @foreach ($catalogTags as $tagOption)
                <option value="{{ $tagOption->id }}" @selected($selectedTagIds->contains($tagOption->id))>{{ $tagOption->name }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-[#94A3B8]">Quick labels like Featured or Sale. Hold Ctrl or Cmd to pick more than one.</p>
    </div>
</div>
