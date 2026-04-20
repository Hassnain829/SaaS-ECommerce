@php
    $editStoreHasErrors = $errors->has('name')
        || $errors->has('primary_market')
        || $errors->has('address')
        || $errors->has('currency')
        || $errors->has('timezone')
        || $errors->has('category')
        || $errors->has('business_models')
        || $errors->has('custom_category')
        || $errors->has('store_logo');

    $fallbackEditStoreRecord = old('_edit_store_id') ? $stores->firstWhere('id', (int) old('_edit_store_id')) : null;
    $fallbackEditStore = $fallbackEditStoreRecord ? [
        'id' => (int) old('_edit_store_id'),
        'name' => old('name', $fallbackEditStoreRecord->name),
        'primary_market' => old('primary_market', $fallbackEditStoreRecord->settings['primary_market'] ?? 'Global Market'),
        'currency' => old('currency', $fallbackEditStoreRecord->currency),
        'timezone' => old('timezone', $fallbackEditStoreRecord->timezone),
        'address' => old('address', $fallbackEditStoreRecord->address),
        'category' => old('category', $fallbackEditStoreRecord->category),
        'custom_category' => old('custom_category', $fallbackEditStoreRecord->settings['custom_category'] ?? ''),
        'business_models' => old('business_models', $fallbackEditStoreRecord->settings['business_models'] ?? []),
        'logo_url' => $fallbackEditStoreRecord->logoPublicUrl(),
        'update_url' => route('store.update', ['storeId' => (int) old('_edit_store_id')]),
        'delete_url' => route('store.destroy', ['storeId' => (int) old('_edit_store_id')]),
    ] : null;
@endphp

<div id="editStoreModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#0F172A]/60 px-4 py-6 backdrop-blur-[2px]" data-auto-open="{{ old('_open_edit_store_modal') && old('_edit_store_id') ? 'true' : 'false' }}">
    <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4 sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Store Actions</p>
                <h2 class="mt-1 text-2xl font-medium text-[#0F172A] font-[Poppins]">Edit Store</h2>
            </div>
            <button type="button" id="closeEditStoreModal" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#E2E8F0] text-[#64748B] transition hover:text-[#334155]" aria-label="Close edit modal">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <path d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="overflow-y-auto px-5 py-6 sm:px-6">
            @if ($editStoreHasErrors && old('_open_edit_store_modal'))
                <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="ml-5 list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="editStoreForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="_open_edit_store_modal" value="1">
                <input type="hidden" name="_edit_store_id" id="edit_store_id" value="{{ old('_edit_store_id', '') }}">
                <input type="hidden" name="category" id="editStoreCategoryInput" value="{{ old('category', '') }}">

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="edit_store_name" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Store Name</label>
                        <input id="edit_store_name" name="name" type="text" value="{{ old('name', '') }}" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:border-[#0052CC] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                    <div>
                        <label for="edit_primary_market" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Primary Market</label>
                        <select id="edit_primary_market" name="primary_market" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                            @foreach (['Global Market', 'North America', 'Europe', 'Middle East', 'South Asia'] as $market)
                                <option value="{{ $market }}">{{ $market }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <label for="edit_currency" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Store Currency</label>
                        <select id="edit_currency" name="currency" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                            @foreach (['USD', 'EUR', 'GBP', 'PKR', 'AED'] as $currency)
                                <option value="{{ $currency }}">{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit_timezone" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Timezone</label>
                        <select id="edit_timezone" name="timezone" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                            @foreach (['UTC', 'Asia/Karachi', 'America/New_York', 'Europe/London', 'Asia/Dubai'] as $timezone)
                                <option value="{{ $timezone }}">{{ $timezone }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="edit_address" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Business Address</label>
                    <textarea id="edit_address" name="address" rows="3" class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ old('address', '') }}</textarea>
                </div>

                <div>
                    <div id="edit-store-current-logo-wrap" class="mb-4 hidden">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Current logo</p>
                        <div class="inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-sm">
                            <img id="edit-store-current-logo-img" src="" alt="" class="max-h-full max-w-full object-contain p-1">
                        </div>
                    </div>
                    <label for="edit_store_logo" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Replace Logo</label>
                    <input id="edit_store_logo" name="store_logo" type="file" accept=".jpg,.jpeg,.png,.svg" class="w-full rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                </div>

                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-[#334155]">Business Model & Category</h3>
                        <p class="mt-1 text-xs text-[#94A3B8]">Pick the primary store type and optionally keep a matching business model tag.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        @foreach ([
                            ['physical', 'Physical Goods'],
                            ['digital', 'Digital Products'],
                            ['service', 'Services'],
                            ['subscription', 'Subscriptions'],
                            ['virtual', 'Memberships'],
                        ] as [$categoryKey, $label])
                            <button type="button" class="edit-store-category rounded-xl border border-[#E2E8F0] bg-white px-4 py-4 text-center text-xs font-semibold text-[#0F172A] transition hover:border-[#0052CC]" data-category="{{ $categoryKey }}" data-model="{{ $label }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="edit_custom_category" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Custom Category</label>
                            <input id="edit_custom_category" name="custom_category" type="text" value="{{ old('custom_category', '') }}" placeholder="Optional custom label" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A] focus:border-[#0052CC] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Business Model Tags</label>
                            <div class="grid grid-cols-2 gap-2 text-sm text-[#475569]">
                                @foreach (['Physical Goods', 'Digital Products', 'Services', 'Subscriptions', 'Memberships'] as $model)
                                    <label class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">
                                        <input type="checkbox" name="business_models[]" value="{{ $model }}" class="rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]/20">
                                        <span>{{ $model }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 border-t border-[#E2E8F0] pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" id="openDeleteStoreWarning" class="inline-flex items-center justify-center rounded-lg border border-[#F4B8BF] bg-[#FFF5F5] px-4 py-3 text-sm font-bold text-[#B42318] transition hover:bg-[#FEEBEC]">
                        Delete Store
                    </button>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" id="dismissEditStoreModal" class="rounded-lg border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Cancel</button>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-6 py-3 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042a3]">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="deleteStoreWarningModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-[#0F172A]/70 px-4 py-6 backdrop-blur-[3px]">
    <div class="w-full max-w-lg overflow-hidden rounded-3xl border border-[#FECACA] bg-white shadow-2xl">
        <div class="bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.18),_transparent_60%)] px-6 pb-4 pt-6">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 9V13M12 17H12.01M10.29 3.85999L1.81999 18C1.6442 18.3045 1.5512 18.6496 1.55029 19.0012C1.54939 19.3528 1.64063 19.6984 1.81485 20.0037C1.98907 20.3089 2.24014 20.5632 2.5431 20.7413C2.84606 20.9194 3.19024 21.0151 3.54199 21.02H20.458C20.8097 21.0151 21.1539 20.9194 21.4569 20.7413C21.7598 20.5632 22.0109 20.3089 22.1851 20.0037C22.3593 19.6984 22.4506 19.3528 22.4497 19.0012C22.4488 18.6496 22.3558 18.3045 22.18 18L13.71 3.85999C13.5294 3.56428 13.2758 3.31986 12.9735 3.15044C12.6711 2.98102 12.3303 2.89233 11.9837 2.89282C11.6371 2.8933 11.2965 2.98295 10.9946 3.15322C10.6928 3.32349 10.4398 3.56863 10.26 3.86499L10.29 3.85999Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="mt-5 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Delete this store?</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">This action will permanently remove the store, its products, categories, and related onboarding data.</p>
        </div>

        <div class="px-6 pb-6 pt-2">
            <div class="rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]">Warning</p>
                <p class="mt-2 text-sm text-[#7F1D1D]">You are about to delete <span id="deleteStoreName" class="font-bold"></span>. This cannot be undone.</p>
            </div>

            <form id="deleteStoreForm" method="POST" class="mt-6">
                @csrf
                @method('DELETE')
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button type="button" id="cancelDeleteStore" class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#475569] transition hover:bg-[#F8FAFC]">Keep Store</button>
                    <button type="submit" class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (() => {
        const editModal = document.getElementById('editStoreModal');
        if (!editModal) {
            return;
        }

        const closeEditButtons = [document.getElementById('closeEditStoreModal'), document.getElementById('dismissEditStoreModal')];
        const openDeleteWarning = document.getElementById('openDeleteStoreWarning');
        const deleteWarningModal = document.getElementById('deleteStoreWarningModal');
        const cancelDeleteStore = document.getElementById('cancelDeleteStore');
        const editStoreForm = document.getElementById('editStoreForm');
        const deleteStoreForm = document.getElementById('deleteStoreForm');
        const editStoreIdInput = document.getElementById('edit_store_id');
        const editStoreName = document.getElementById('edit_store_name');
        const editPrimaryMarket = document.getElementById('edit_primary_market');
        const editCurrency = document.getElementById('edit_currency');
        const editTimezone = document.getElementById('edit_timezone');
        const editAddress = document.getElementById('edit_address');
        const editCategoryInput = document.getElementById('editStoreCategoryInput');
        const editCustomCategory = document.getElementById('edit_custom_category');
        const deleteStoreName = document.getElementById('deleteStoreName');
        const editButtons = [...document.querySelectorAll('.js-open-edit-store-modal')];
        const categoryButtons = [...document.querySelectorAll('.edit-store-category')];
        const editStoreLogoWrap = document.getElementById('edit-store-current-logo-wrap');
        const editStoreLogoImg = document.getElementById('edit-store-current-logo-img');
        const editStoreLogoInput = document.getElementById('edit_store_logo');
        let currentStore = null;

        const setBodyLock = (locked) => document.body.classList.toggle('overflow-hidden', locked);

        const setActiveCategory = (category) => {
            editCategoryInput.value = category || '';
            categoryButtons.forEach((button) => {
                const active = button.dataset.category === category;
                button.classList.toggle('border-[#0052CC]', active);
                button.classList.toggle('bg-[#EAF2FF]', active);
                button.classList.toggle('text-[#0052CC]', active);
            });
        };

        const openEditModal = (store) => {
            currentStore = store;
            editStoreIdInput.value = store.id;
            editStoreForm.action = store.update_url;
            deleteStoreForm.action = store.delete_url;
            editStoreName.value = store.name || '';
            editPrimaryMarket.value = store.primary_market || 'Global Market';
            editCurrency.value = store.currency || 'USD';
            editTimezone.value = store.timezone || 'UTC';
            editAddress.value = store.address || '';
            editCustomCategory.value = store.custom_category || '';
            setActiveCategory(store.category || 'physical');

            [...editStoreForm.querySelectorAll('input[name="business_models[]"]')].forEach((input) => {
                input.checked = (store.business_models || []).includes(input.value);
            });

            if (store.logo_url && editStoreLogoWrap && editStoreLogoImg) {
                editStoreLogoImg.src = store.logo_url;
                editStoreLogoWrap.classList.remove('hidden');
            } else if (editStoreLogoWrap) {
                editStoreLogoWrap.classList.add('hidden');
                if (editStoreLogoImg) {
                    editStoreLogoImg.removeAttribute('src');
                }
            }

            if (editStoreLogoInput) {
                editStoreLogoInput.value = '';
            }

            editModal.classList.remove('hidden');
            editModal.classList.add('flex');
            setBodyLock(true);
        };

        const closeEditModal = () => {
            editModal.classList.add('hidden');
            editModal.classList.remove('flex');
            deleteWarningModal.classList.add('hidden');
            deleteWarningModal.classList.remove('flex');
            setBodyLock(false);
        };

        categoryButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setActiveCategory(button.dataset.category || '');
            });
        });

        editButtons.forEach((button) => {
            button.addEventListener('click', () => {
                openEditModal(JSON.parse(button.dataset.store));
            });
        });

        closeEditButtons.forEach((button) => {
            button?.addEventListener('click', closeEditModal);
        });

        editModal.addEventListener('click', (event) => {
            if (event.target === editModal) {
                closeEditModal();
            }
        });

        openDeleteWarning?.addEventListener('click', () => {
            if (!currentStore) {
                return;
            }

            deleteStoreName.textContent = currentStore.name;
            deleteWarningModal.classList.remove('hidden');
            deleteWarningModal.classList.add('flex');
        });

        cancelDeleteStore?.addEventListener('click', () => {
            deleteWarningModal.classList.add('hidden');
            deleteWarningModal.classList.remove('flex');
        });

        deleteWarningModal?.addEventListener('click', (event) => {
            if (event.target === deleteWarningModal) {
                deleteWarningModal.classList.add('hidden');
                deleteWarningModal.classList.remove('flex');
            }
        });

        @if (old('_open_edit_store_modal') && old('_edit_store_id'))
            const fallbackStore = @json($fallbackEditStore);

            if (fallbackStore) {
                openEditModal(fallbackStore);
            }
        @endif
    })();
</script>
