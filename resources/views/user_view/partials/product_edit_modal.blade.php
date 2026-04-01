@php
    $productEditHasErrors = old('_open_edit_product_modal') && $errors->any();
@endphp

<div id="editProductModal" class="fixed inset-0 z-[75] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]" data-auto-open="{{ $productEditHasErrors ? 'true' : 'false' }}">
    <div class="relative flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-[#E2E8F0] bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Product Actions</p>
                <h2 class="mt-1 text-2xl font-medium text-[#0F172A] font-[Poppins]">Edit Product</h2>
            </div>
            <button type="button" id="closeEditProductModal" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] transition hover:text-[#334155]" aria-label="Close edit product modal">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </div>

        <div class="overflow-y-auto px-6 py-6">
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
                <input type="hidden" name="product_type" id="edit_product_type_value" value="{{ old('product_type', 'physical') }}">
                <input type="hidden" name="custom_product_type" id="edit_product_custom_type_hidden" value="{{ old('custom_product_type', '') }}">

                <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6"><h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Details</h3></div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Active Store</label>
                            <div class="rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A]">
                                {{ $selectedStore?->name ?? $currentStore?->name ?? 'No active store selected' }}
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">This product can only be edited inside your current active store.</p>
                        </div>
                        <div>
                            <label for="edit_product_type_select" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product Type</label>
                            <select id="edit_product_type_select" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                @foreach (['physical', 'digital', 'service', 'subscription', 'virtual'] as $type)
                                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                                @endforeach
                                <option value="custom">Custom Type</option>
                            </select>
                            <div id="editProductCustomTypeWrap" class="mt-3 hidden">
                                <input id="edit_product_custom_type" type="text" value="{{ old('custom_product_type', '') }}" placeholder="e.g. Home Decor" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]">
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 grid grid-cols-1 gap-6">
                        <div>
                            <label for="edit_product_image" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Product Images</label>
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
                    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div><label for="edit_product_sku" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base SKU</label><input id="edit_product_sku" name="sku" type="text" value="{{ old('sku', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                        <div><label for="edit_product_price" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Base Price</label><input id="edit_product_price" name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                        <div><label for="edit_product_stock_alert" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Stock Alert</label><input id="edit_product_stock_alert" name="stock_alert" type="number" min="0" step="1" value="{{ old('stock_alert', '0') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A]"></div>
                    </div>
                </div>

                <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6 flex items-center justify-between gap-3">
                        <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h3>
                        <button id="editOpenVariationModal" type="button" class="inline-flex items-center gap-2 rounded-full border border-[#D4E3FF] bg-[#EEF4FF] px-4 py-2 text-sm font-semibold text-[#0052CC] transition hover:bg-[#E4EEFF]">Add Variation Type</button>
                    </div>
                    <div id="editVariationHiddenInputs"></div>
                    <div id="editNoVariationState" class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">No variation type added yet.</div>
                    <div id="editVariationTypesList" class="hidden space-y-4"></div>
                </div>

                <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants</h3>
                        <span class="text-sm font-medium text-[#64748B]">Rows are created automatically from variation options</span>
                    </div>
                    <div class="mb-5 grid grid-cols-1 gap-3 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 md:grid-cols-4">
                        <div class="md:col-span-2"><p class="text-sm font-semibold text-[#0F172A]">Bulk Set Price & Stock</p><p class="text-xs text-[#64748B]">Apply one value to all variant rows.</p></div>
                        <div><label for="editBulkPrice" class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label><input id="editBulkPrice" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div>
                        <div class="flex items-end gap-2"><div class="flex-1"><label for="editBulkStock" class="mb-1 block text-xs font-semibold text-[#64748B]">Stock</label><input id="editBulkStock" type="number" min="0" step="1" class="w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><button id="editApplyBulkValues" type="button" class="rounded-lg bg-[#0052CC] px-3 py-2 text-sm font-semibold text-white">Apply</button></div>
                    </div>
                    <div id="editVariantRows" class="space-y-4"></div>
                </div>

                <div class="rounded-[24px] border border-[#DDE7F3] bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6 flex items-center justify-between gap-3"><h3 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h3><span id="editPreviewCount" class="text-sm text-[#94A3B8]">0 variant row(s)</span></div>
                    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="border-b border-[#F1F5F9]"><tr><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Variant</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">SKU</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Price</th><th class="px-2 py-3 text-left text-xs font-bold uppercase text-[#94A3B8]">Stock</th></tr></thead><tbody id="editPreviewTableBody" class="divide-y divide-[#F1F5F9]"></tbody></table></div>
                </div>

                <div class="flex flex-col gap-4 border-t border-[#E2E8F0] pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" id="openDeleteProductWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">Delete Product</button>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" id="dismissEditProductModal" class="rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</button>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042a3]">Save Changes</button>
                    </div>
                </div>
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
(() => {
const editModal=document.getElementById('editProductModal'); if(!editModal) return;
const closeButtons=[document.getElementById('closeEditProductModal'),document.getElementById('dismissEditProductModal')];
const editButtons=[...document.querySelectorAll('.js-open-edit-product-modal:not([data-open-delete="true"])')];
const deleteButtons=[...document.querySelectorAll('.js-open-delete-product-modal')];
const editForm=document.getElementById('editProductForm'); const deleteForm=document.getElementById('deleteProductForm');
const deleteWarningModal=document.getElementById('deleteProductWarningModal'); const openDeleteWarning=document.getElementById('openDeleteProductWarning'); const cancelDeleteProduct=document.getElementById('cancelDeleteProduct'); const deleteProductName=document.getElementById('deleteProductName');
const editProductId=document.getElementById('edit_product_id'); const editTypeSelect=document.getElementById('edit_product_type_select'); const editTypeValue=document.getElementById('edit_product_type_value'); const editCustomTypeWrap=document.getElementById('editProductCustomTypeWrap'); const editCustomType=document.getElementById('edit_product_custom_type'); const editCustomTypeHidden=document.getElementById('edit_product_custom_type_hidden');
const editName=document.getElementById('edit_product_name'); const editDescription=document.getElementById('edit_product_description'); const editSku=document.getElementById('edit_product_sku'); const editPrice=document.getElementById('edit_product_price'); const editStockAlert=document.getElementById('edit_product_stock_alert'); const editImageInput=document.getElementById('edit_product_image'); const editImagePreview=document.getElementById('editProductImagePreview'); const editExistingImageInputs=document.getElementById('editExistingImageInputs');
const editVariationHiddenInputs=document.getElementById('editVariationHiddenInputs'); const editNoVariationState=document.getElementById('editNoVariationState'); const editVariationTypesList=document.getElementById('editVariationTypesList'); const editAddVariantRow=document.getElementById('editAddVariantRow'); const editVariantRows=document.getElementById('editVariantRows'); const editBulkPrice=document.getElementById('editBulkPrice'); const editBulkStock=document.getElementById('editBulkStock'); const editApplyBulkValues=document.getElementById('editApplyBulkValues'); const editPreviewCount=document.getElementById('editPreviewCount'); const editPreviewTableBody=document.getElementById('editPreviewTableBody');
const editVariationModal=document.getElementById('editVariationModal'); const editOpenVariationModal=document.getElementById('editOpenVariationModal'); const closeEditVariationModal=document.getElementById('closeEditVariationModal'); const cancelEditVariationModal=document.getElementById('cancelEditVariationModal'); const submitEditVariationModal=document.getElementById('submitEditVariationModal'); const editVariationName=document.getElementById('editVariationName'); const editVariationOptions=document.getElementById('editVariationOptions'); const editVariationOptionInput=document.getElementById('editVariationOptionInput'); const editVariationOptionChips=document.getElementById('editVariationOptionChips');
const defaultTypes=['physical','digital','service','subscription','virtual']; let currentProduct=null; let editVariationTypes=[]; let editRows=[]; let editingVariationIndex=null; let retainedExistingImages=[]; let selectedEditImages=[]; let editVariationOptionTags=[];
const escapeHtml=(v)=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;");
const getEditVariantRowLimit=()=>editVariationTypes.reduce((total,variationType)=>total+((variationType.options||[]).length||0),0);
const getEditRowKey=(optionMap)=>Object.entries(optionMap||{}).sort(([left],[right])=>Number(left)-Number(right)).map(([variationIndex,optionIndex])=>`${variationIndex}:${optionIndex}`).join('|');
const buildEditRowsFromVariationTypes=(existingRows=[])=>{if(!editVariationTypes.length||editVariationTypes.some((variationType)=>!(variationType.options||[]).length)){return [];} const existingRowsByKey=new Map(existingRows.map((row)=>[getEditRowKey(row.option_map||{}),row])); const combinations=[]; const walk=(variationIndex,optionMap)=>{if(variationIndex>=editVariationTypes.length){combinations.push({...optionMap}); return;} (editVariationTypes[variationIndex].options||[]).forEach((_,optionIndex)=>{walk(variationIndex+1,{...optionMap,[variationIndex]:optionIndex});});}; walk(0,{}); return combinations.map((optionMap)=>{const existingRow=existingRowsByKey.get(getEditRowKey(optionMap)); return {option_map:optionMap,sku:(existingRow?.sku)||'',price:existingRow?.price ?? (editPrice.value || ''),stock:existingRow?.stock ?? (editBulkStock.value || ''),stock_alert:existingRow?.stock_alert ?? (editStockAlert.value || 0)};});};
const syncType=()=>{const isCustom=editTypeSelect.value==='custom'; editCustomTypeWrap.classList.toggle('hidden',!isCustom); editCustomType.required=isCustom; editTypeValue.value=isCustom?(editCustomType.value.trim()||'custom'):editTypeSelect.value; editCustomTypeHidden.value=isCustom?(editCustomType.value.trim()||''):'';};
const syncSelectedFiles=(input,files)=>{if(!input) return; const transfer=new DataTransfer(); files.forEach((file)=>transfer.items.add(file)); input.files=transfer.files;};
const renderExistingImageInputs=()=>{editExistingImageInputs.innerHTML=retainedExistingImages.map((path)=>`<input type="hidden" name="existing_image_paths[]" value="${escapeHtml(path)}">`).join('');};
const renderEditImages=()=>{const existing=(currentProduct?.image_urls||[]).map((url,index)=>({kind:'existing',url,path:currentProduct?.image_paths?.[index]||''})).filter((item)=>retainedExistingImages.includes(item.path)); const selected=selectedEditImages.map((file,index)=>({kind:'new',url:URL.createObjectURL(file),name:file.name,index})); const items=[...existing,...selected]; if(!items.length){editImagePreview.innerHTML='<div class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#64748B]">No product images selected.</div>'; renderExistingImageInputs(); return;} editImagePreview.innerHTML=items.map((item,index)=>`<div class="group relative overflow-hidden rounded-2xl border border-[#D9E2EC] bg-white p-2 shadow-sm"><img src="${item.url}" alt="${escapeHtml(item.name||currentProduct?.name||'Product image')}" class="h-16 w-16 rounded-xl object-cover border border-[#E2E8F0]"><button type="button" class="edit-remove-image absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0F172A]/70 text-xs font-bold text-white" data-kind="${item.kind}" data-index="${item.kind==='existing'?index:item.index}" aria-label="Remove image">&times;</button><p class="mt-2 max-w-[64px] truncate text-[11px] text-[#64748B]">${escapeHtml(item.name||(item.path?.split('/').pop()||'Saved image'))}</p></div>`).join(''); renderExistingImageInputs(); document.querySelectorAll('.edit-remove-image').forEach((button)=>button.addEventListener('click',()=>{if(button.dataset.kind==='existing'){const existingIndex=Number(button.dataset.index); const existingItems=(currentProduct?.image_paths||[]).filter((path)=>retainedExistingImages.includes(path)); retainedExistingImages=existingItems.filter((_,idx)=>idx!==existingIndex);}else{selectedEditImages=selectedEditImages.filter((_,idx)=>idx!==Number(button.dataset.index)); syncSelectedFiles(editImageInput,selectedEditImages);} renderEditImages();}));};
const syncEditVariationOptions=()=>{editVariationOptions.value=editVariationOptionTags.join(', ');};
const renderEditVariationOptionTags=()=>{editVariationOptionChips.innerHTML=editVariationOptionTags.map((tag,index)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(tag)}<button type="button" class="remove-edit-variation-tag leading-none text-[#94A3B8] hover:text-[#B42318]" data-index="${index}">&times;</button></span>`).join(''); document.querySelectorAll('.remove-edit-variation-tag').forEach((button)=>button.addEventListener('click',()=>{editVariationOptionTags=editVariationOptionTags.filter((_,index)=>index!==Number(button.dataset.index)); syncEditVariationOptions(); renderEditVariationOptionTags();}));};
const addEditVariationOptionTags=(rawValue)=>{const nextTags=String(rawValue||'').split(',').map((value)=>value.trim()).filter(Boolean); if(!nextTags.length) return; editVariationOptionTags=[...editVariationOptionTags,...nextTags]; syncEditVariationOptions(); renderEditVariationOptionTags(); if(editVariationOptionInput) editVariationOptionInput.value='';};
const openVariationEditor=(variationIndex=null)=>{editingVariationIndex=variationIndex; const variation=variationIndex===null?null:editVariationTypes[variationIndex]; editVariationName.value=variation?.name||''; editVariationOptionTags=[...(variation?.options||[])]; syncEditVariationOptions(); renderEditVariationOptionTags(); submitEditVariationModal.textContent=variation?'Update Variation':'Add Variation'; editVariationModal.classList.remove('hidden'); editVariationModal.classList.add('flex');};
const closeVariationEditor=()=>{editingVariationIndex=null; editVariationName.value=''; editVariationOptionTags=[]; syncEditVariationOptions(); renderEditVariationOptionTags(); submitEditVariationModal.textContent='Save Variation'; editVariationModal.classList.add('hidden'); editVariationModal.classList.remove('flex');};
const renderVariationInputs=()=>{editVariationHiddenInputs.innerHTML=editVariationTypes.map((t,i)=>`<input type="hidden" name="variation_types[${i}][name]" value="${escapeHtml(t.name||'')}"><input type="hidden" name="variation_types[${i}][type]" value="${escapeHtml(t.type||'select')}">${(t.options||[]).map((o,j)=>`<input type="hidden" name="variation_types[${i}][options][${j}]" value="${escapeHtml(o)}">`).join('')}`).join('');};
const renderPreview=()=>{const previewRows=editRows.map((row)=>({label:editVariationTypes.map((v,i)=>{const s=row.option_map?.[i]; return s!==undefined&&s!==''?(v.options?.[s]||''):'';}).filter(Boolean).join(' / ')||'Default Variant',sku:row.sku||'Auto-generated',price:row.price||editPrice.value||'',stock:row.stock||editBulkStock.value||''})); const rows=previewRows.length?previewRows:[{label:'No variants added yet',sku:'-',price:'-',stock:'-'}]; editPreviewTableBody.innerHTML=rows.map((r)=>`<tr><td class="px-2 py-4 text-[#0F172A]">${escapeHtml(r.label)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.sku)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.price)}</td><td class="px-2 py-4 text-[#475569]">${escapeHtml(r.stock)}</td></tr>`).join(''); editPreviewCount.textContent=`${previewRows.length} variant row(s)`;};
const renderVariantRows=()=>{ if(!editRows.length){editVariantRows.innerHTML=''; renderPreview(); return;} editVariantRows.innerHTML=editRows.map((row,rowIndex)=>`<div class="space-y-4 rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5"><div class="flex flex-wrap gap-2">${Object.entries(row.option_map||{}).map(([variationIndex,optionIndex])=>`<span class="inline-flex items-center rounded-lg border border-[#DDE7F3] bg-white px-3 py-1.5 text-sm font-medium text-[#0F172A]">${escapeHtml(editVariationTypes[Number(variationIndex)]?.name||'Variation')}: ${escapeHtml(editVariationTypes[Number(variationIndex)]?.options?.[Number(optionIndex)]||'')}</span><input type="hidden" name="variants[${rowIndex}][option_map][${variationIndex}]" value="${escapeHtml(optionIndex)}">`).join('')}</div><div class="grid grid-cols-1 gap-3 md:grid-cols-3"><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">SKU</label><input type="text" name="variants[${rowIndex}][sku]" value="${escapeHtml(row.sku||'')}" data-row-index="${rowIndex}" data-row-field="sku" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Price</label><input type="number" min="0" step="0.01" name="variants[${rowIndex}][price]" value="${escapeHtml(row.price||'')}" data-row-index="${rowIndex}" data-row-field="price" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div><div><label class="mb-1 block text-xs font-semibold text-[#64748B]">Stock</label><input type="number" min="0" step="1" name="variants[${rowIndex}][stock]" value="${escapeHtml(row.stock||'')}" data-row-index="${rowIndex}" data-row-field="stock" class="edit-row-input w-full rounded-lg border border-[#E2E8F0] px-3 py-2 text-sm text-[#0F172A]"></div></div><input type="hidden" name="variants[${rowIndex}][stock_alert]" value="${escapeHtml(editStockAlert.value||row.stock_alert||0)}"></div>`).join(''); document.querySelectorAll('.edit-row-input').forEach((i)=>i.addEventListener('input',()=>{const r=Number(i.dataset.rowIndex); editRows[r][i.dataset.rowField]=i.value; renderPreview();})); renderPreview();};
const normalizeRowsAfterVariationChange=()=>{editRows=buildEditRowsFromVariationTypes(editRows);};
const renderVariationCards=()=>{ if(!editVariationTypes.length){editVariationTypesList.classList.add('hidden'); editNoVariationState.classList.remove('hidden'); return;} editVariationTypesList.classList.remove('hidden'); editNoVariationState.classList.add('hidden'); editVariationTypesList.innerHTML=editVariationTypes.map((t,i)=>`<div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5"><div class="mb-3 flex items-center justify-between gap-3"><div><span class="text-base font-medium text-[#0F172A]">Variation ${i+1}: ${escapeHtml(t.name||'Variation')}</span><div class="mt-1 text-xs uppercase text-[#94A3B8]">${escapeHtml(t.type||'select')}</div></div><div class="flex items-center gap-2"><button type="button" class="edit-variation-type text-xs font-semibold text-[#0052CC]" data-variation-index="${i}">Edit</button><button type="button" class="remove-variation-type text-xs font-semibold text-[#B42318]" data-variation-index="${i}">Remove</button></div></div><div class="flex flex-wrap gap-2">${(t.options||[]).map((o,j)=>`<span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium">${escapeHtml(o)}<button type="button" class="edit-remove-variation-option leading-none text-[#94A3B8] hover:text-[#B42318]" data-variation-index="${i}" data-option-index="${j}">&times;</button></span>`).join('')}</div></div>`).join(''); document.querySelectorAll('.edit-remove-variation-option').forEach((b)=>b.addEventListener('click',()=>{const vi=Number(b.dataset.variationIndex),oi=Number(b.dataset.optionIndex); if(!editVariationTypes[vi]) return; editVariationTypes[vi].options.splice(oi,1); if(!(editVariationTypes[vi].options||[]).length) editVariationTypes.splice(vi,1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows();})); document.querySelectorAll('.edit-variation-type').forEach((b)=>b.addEventListener('click',()=>openVariationEditor(Number(b.dataset.variationIndex)))); document.querySelectorAll('.remove-variation-type').forEach((b)=>b.addEventListener('click',()=>{editVariationTypes.splice(Number(b.dataset.variationIndex),1); normalizeRowsAfterVariationChange(); renderVariationInputs(); renderVariationCards(); renderVariantRows();}));};
const closeAll=()=>{editModal.classList.add('hidden'); editModal.classList.remove('flex'); editVariationModal.classList.add('hidden'); editVariationModal.classList.remove('flex'); deleteWarningModal.classList.add('hidden'); deleteWarningModal.classList.remove('flex'); document.body.classList.remove('overflow-hidden');};
const openEdit=(product)=>{currentProduct=product; retainedExistingImages=JSON.parse(JSON.stringify(product.image_paths||[])); selectedEditImages=[]; syncSelectedFiles(editImageInput,selectedEditImages); editProductId.value=product.id||''; editForm.action=product.update_url; deleteForm.action=product.delete_url; editName.value=product.name||''; editDescription.value=product.description||''; editSku.value=product.sku||''; editPrice.value=product.base_price||''; editStockAlert.value=product.stock_alert||0; editBulkPrice.value=product.base_price||''; editBulkStock.value=''; if(defaultTypes.includes(product.product_type)){editTypeSelect.value=product.product_type; editCustomType.value='';}else{editTypeSelect.value='custom'; editCustomType.value=product.product_type||'';} editVariationTypes=JSON.parse(JSON.stringify(product.variation_types||[])); editRows=JSON.parse(JSON.stringify(product.variants||[])); if(!editRows.length&&editVariationTypes.length){editRows=buildEditRowsFromVariationTypes(editRows);} renderEditImages(); syncType(); renderVariationInputs(); renderVariationCards(); renderVariantRows(); editModal.classList.remove('hidden'); editModal.classList.add('flex'); document.body.classList.add('overflow-hidden');};
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
editApplyBulkValues?.addEventListener('click',()=>{editRows=editRows.map((r)=>({...r,price:editBulkPrice.value||r.price,stock:editBulkStock.value||r.stock})); renderVariantRows();});
openDeleteWarning?.addEventListener('click',()=>{if(!currentProduct) return; deleteProductName.textContent=currentProduct.name||'this product'; deleteWarningModal.classList.remove('hidden'); deleteWarningModal.classList.add('flex');});
cancelDeleteProduct?.addEventListener('click',()=>{deleteWarningModal.classList.add('hidden'); deleteWarningModal.classList.remove('flex');});
renderEditVariationOptionTags();
if(editModal.dataset.autoOpen==='true'){editModal.classList.remove('hidden'); editModal.classList.add('flex'); document.body.classList.add('overflow-hidden'); syncType();}
})();
</script>
