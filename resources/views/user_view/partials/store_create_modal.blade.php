@php
    $primaryMarkets = ['Global Market', 'North America', 'Europe', 'Middle East', 'South Asia'];
    $currencies = ['USD', 'EUR', 'GBP', 'PKR', 'AED'];
    $timezones = ['UTC', 'Asia/Karachi', 'America/New_York', 'Europe/London', 'Asia/Dubai'];

    $storeModalHasErrors = $errors->has('name')
        || $errors->has('primary_market')
        || $errors->has('address')
        || $errors->has('currency')
        || $errors->has('timezone')
        || $errors->has('category')
        || $errors->has('business_models')
        || $errors->has('custom_category')
        || $errors->has('store_logo');

    $selectedCategory = old('category', 'physical');
    $businessModelOld = old('business_models', []);
    $customCategoryOld = old('custom_category', '');
@endphp

<div
    id="createStoreModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-[#0F172A]/60 px-4 py-6 backdrop-blur-[2px]"
    data-auto-open="{{ $storeModalHasErrors ? 'true' : 'false' }}"
>
    <div class="relative flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-[#E2E8F0] bg-[#F5F7F8] shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] bg-white px-5 py-4 sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#64748B]">Store Setup</p>
                <h2 class="mt-1 text-2xl font-medium text-[#0F172A] font-[Poppins]">Create a New Store</h2>
            </div>
            <button
                type="button"
                id="closeCreateStoreModal"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B] transition hover:border-[#CBD5E1] hover:text-[#334155]"
                aria-label="Close create store modal"
            >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <path d="M4.5 4.5L13.5 13.5M13.5 4.5L4.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="overflow-y-auto px-4 py-5 sm:px-6 sm:py-6">
            <div class="mb-8">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-inter font-medium uppercase tracking-wider text-[#64748B]">Step 1 of 3</span>
                    <span class="text-xs text-[#64748B]">Setup Progress: 33% Complete</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-[#E2E8F0]">
                    <div class="h-2 w-1/3 rounded-full bg-[#0052CC]"></div>
                </div>
                <div class="mt-1 flex justify-end">
                    <span class="text-xs font-inter font-medium text-[#0052CC]">Next: Add First Product</span>
                </div>
            </div>

            <div class="rounded-xl border border-[#E2E8F0] bg-white p-6 md:p-8">
                <div class="mb-8">
                    <h3 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Let's set up your store</h3>
                    <p class="mt-1 text-base text-[#64748B]">Fill in the essential details to create your digital storefront.</p>
                </div>

                @if ($storeModalHasErrors)
                    <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                        <ul class="ml-5 list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="store-create-modal-form" action="{{ route('onboarding-StoreDetails-1.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                    @csrf
                    <input type="hidden" name="mode" value="create">
                    <input type="hidden" name="_open_create_store_modal" value="1">

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="modal_store_name" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Store Name</label>
                            <input
                                id="modal_store_name"
                                name="name"
                                type="text"
                                placeholder="e.g. Modern Marketplace"
                                value="{{ old('name', '') }}"
                                class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:border-[#0052CC] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"
                            >
                        </div>
                        <div>
                            <label for="modal_primary_market" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Primary Market</label>
                            <select
                                id="modal_primary_market"
                                name="primary_market"
                                class="w-full appearance-none rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"
                            >
                                @foreach ($primaryMarkets as $market)
                                    <option value="{{ $market }}" @selected(old('primary_market', 'Global Market') === $market)>{{ $market }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Store Logo</label>
                        <div class="flex flex-col items-center gap-4 rounded-xl border-2 border-dashed border-[#CBD5E1] bg-[#F8FAFC] p-8">
                            <div class="rounded-full bg-white p-3 shadow-sm">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M14 2V8H20" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 18V12" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M9 15H15" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-bold text-[#0F172A]">Upload Store Logo</p>
                                <p class="mt-1 text-xs text-[#64748B]">PNG, JPG or SVG. Max 2MB.</p>
                            </div>
                            <label for="modal_store_logo" class="inline-flex h-9 cursor-pointer items-center justify-center whitespace-nowrap rounded-lg bg-[#0052CC] px-4 text-xs font-bold text-white shadow-md transition hover:bg-[#0042a3]">Browse Files</label>
                            <input id="modal_store_logo" name="store_logo" type="file" accept=".jpg,.jpeg,.png,.svg" class="hidden">
                        </div>
                    </div>

                    <div>
                        <label for="modal_address" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Business Address</label>
                        <textarea
                            id="modal_address"
                            name="address"
                            rows="3"
                            placeholder="Street address, City, State, Zip Code"
                            class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"
                        >{{ old('address', '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="modal_currency" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Store Currency</label>
                            <select id="modal_currency" name="currency" class="w-full appearance-none rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}" @selected(old('currency', 'USD') === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="modal_timezone" class="mb-2 block text-sm font-medium text-[#334155] font-[Poppins]">Timezone</label>
                            <select id="modal_timezone" name="timezone" class="w-full appearance-none rounded-lg border border-[#CBD5E1] bg-white px-4 py-3 text-sm text-[#0F172A]">
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', 'UTC') === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-[#334155]">Business Model & Category</h4>
                            <span class="text-xs font-inter font-medium text-[#94A3B8]">Select one or more</span>
                        </div>
                        <input id="modalCategoryInput" type="hidden" name="category" value="{{ $selectedCategory }}">
                        <input id="modalCustomCategoryInput" name="custom_category" type="hidden" value="{{ $customCategoryOld }}">

                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            @php
                                $baseTileClass = 'modal-category-tile cursor-pointer rounded-xl p-4 flex flex-col items-center gap-2';
                            @endphp
                            <div class="{{ $baseTileClass }}" data-category="physical" data-model="Physical Goods">
                                <svg width="24" height="24" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z" fill="#0052CC"/>
                                </svg>
                                <span class="text-center text-xs font-[Poppins] text-[#0F172A]">Physical Goods</span>
                            </div>
                            <div class="{{ $baseTileClass }}" data-category="digital" data-model="Digital Products">
                                <svg width="24" height="24" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M8 12L3 7L4.4 5.55L7 8.15V0H9V8.15L11.6 5.55L13 7L8 12ZM2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V11H2V14H14V11H16V14C16 14.55 15.8042 15.0208 15.4125 15.4125C15.0208 15.8042 14.55 16 14 16H2Z" fill="#64748B"/>
                                </svg>
                                <span class="text-center text-xs font-[Poppins] text-[#0F172A]">Digital Products</span>
                            </div>
                            <div class="{{ $baseTileClass }}" data-category="service" data-model="Services">
                                <svg width="24" height="24" viewBox="0 0 23 20" fill="none" aria-hidden="true">
                                    <path d="M10.885 18C10.9517 18 11.0183 17.9833 11.085 17.95C11.1517 17.9167 11.2017 17.8833 11.235 17.85L19.435 9.65C19.635 9.45 19.7808 9.225 19.8725 8.975C19.9642 8.725 20.01 8.475 20.01 8.225C20.01 7.95833 19.9642 7.70417 19.8725 7.4625C19.7808 7.22083 19.635 7.00833 19.435 6.825L15.185 2.575C15.0017 2.375 14.7892 2.22917 14.5475 2.1375C14.3058 2.04583 14.0517 2 13.785 2C13.535 2 13.285 2.04583 13.035 2.1375C12.785 2.22917 12.56 2.375 12.36 2.575L12.085 2.85L13.935 4.725C14.185 4.95833 14.3683 5.225 14.485 5.525C14.6017 5.825 14.66 6.14167 14.66 6.475C14.66 7.175 14.4225 7.7625 13.9475 8.2375C13.4725 8.7125 12.885 8.95 12.185 8.95C11.8517 8.95 11.5308 8.89167 11.2225 8.775C10.9142 8.65833 10.6433 8.48333 10.41 8.25L8.535 6.4L4.16 10.775C4.11 10.825 4.0725 10.8792 4.0475 10.9375C4.0225 10.9958 4.01 11.0583 4.01 11.125C4.01 11.2583 4.06 11.3792 4.16 11.4875C4.26 11.5958 4.37667 11.65 4.51 11.65C4.57667 11.65 4.64333 11.6333 4.71 11.6C4.77667 11.5667 4.82667 11.5333 4.86 11.5L8.26 8.1L9.66 9.5L6.285 12.9C6.235 12.95 6.1975 13.0042 6.1725 13.0625C6.1475 13.1208 6.135 13.1833 6.135 13.25C6.135 13.3833 6.185 13.5 6.285 13.6C6.385 13.7 6.50167 13.75 6.635 13.75C6.70167 13.75 6.76833 13.7333 6.835 13.7C6.90167 13.6667 6.95167 13.6333 6.985 13.6L10.385 10.225L11.785 11.625L8.41 15.025C8.36 15.0583 8.3225 15.1083 8.2975 15.175C8.2725 15.2417 8.26 15.3083 8.26 15.375C8.26 15.5083 8.31 15.625 8.41 15.725C8.51 15.825 8.62667 15.875 8.76 15.875C8.82667 15.875 8.88917 15.8625 8.9475 15.8375C9.00583 15.8125 9.06 15.775 9.11 15.725L12.51 12.35L13.91 13.75L10.51 17.15C10.46 17.2 10.4225 17.2542 10.3975 17.3125C10.3725 17.3708 10.36 17.4333 10.36 17.5C10.36 17.6333 10.4142 17.75 10.5225 17.85C10.6308 17.95 10.7517 18 10.885 18Z" fill="#64748B"/>
                                </svg>
                                <span class="text-center text-xs font-[Poppins] text-[#0F172A]">Services</span>
                            </div>
                            <div class="{{ $baseTileClass }}" data-category="subscription" data-model="Subscriptions">
                                <svg width="24" height="24" viewBox="0 0 21 22" fill="none" aria-hidden="true">
                                    <path d="M2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V4C0 3.45 0.195833 2.97917 0.5875 2.5875C0.979167 2.19583 1.45 2 2 2H3V0H5V2H13V0H15V2H16C16.55 2 17.0208 2.19583 17.4125 2.5875C17.8042 2.97917 18 3.45 18 4V10H16V8H2V18H9V20H2ZM16 22C14.7833 22 13.7208 21.6208 12.8125 20.8625C11.9042 20.1042 11.3333 19.15 11.1 18H12.65C12.8667 18.7333 13.2792 19.3333 13.8875 19.8C14.4958 20.2667 15.2 20.5 16 20.5C16.9667 20.5 17.7917 20.1583 18.475 19.475C19.1583 18.7917 19.5 17.9667 19.5 17C19.5 16.0333 19.1583 15.2083 18.475 14.525C17.7917 13.8417 16.9667 13.5 16 13.5C15.5167 13.5 15.0667 13.5875 14.65 13.7625C14.2333 13.9375 13.8667 14.1833 13.55 14.5H15V16H11V12H12.5V13.425C12.95 12.9917 13.475 12.6458 14.075 12.3875C14.675 12.1292 15.3167 12 16 12C17.3833 12 18.5625 12.4875 19.5375 13.4625C20.5125 14.4375 21 15.6167 21 17C21 18.3833 20.5125 19.5625 19.5375 20.5375C18.5625 21.5125 17.3833 22 16 22Z" fill="#64748B"/>
                                </svg>
                                <span class="text-center text-xs font-[Poppins] text-[#0F172A]">Subscriptions</span>
                            </div>
                            <div class="{{ $baseTileClass }}" data-category="virtual" data-model="Memberships">
                                <svg width="24" height="24" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M2 11V13H18V11H2ZM2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V13C20 13.55 19.8042 14.0208 19.4125 14.4125C19.0208 14.8042 18.55 15 18 15H14V20L10 18L6 20V15H2C1.45 15 0.979167 14.8042 0.5875 14.4125C0.195833 14.0208 0 13.55 0 13V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0Z" fill="#64748B"/>
                                </svg>
                                <span class="text-center text-xs font-[Poppins] text-[#0F172A]">Memberships</span>
                            </div>
                            <button id="openModalCustomCategoryModal" type="button" class="modal-category-tile rounded-xl border-2 border-dashed border-[#CBD5E1] bg-white p-4 flex flex-col items-center justify-center gap-2 transition hover:border-[#94A3B8]" data-category="custom" data-model="custom">
                                <svg width="24" height="24" viewBox="0 0 25 25" fill="none" aria-hidden="true">
                                    <path d="M11.25 18.75H13.75V13.75H18.75V11.25H13.75V6.25H11.25V11.25H6.25V13.75H11.25V18.75ZM12.5 25C10.7708 25 9.14583 24.6719 7.625 24.0156C6.10417 23.3594 4.78125 22.4688 3.65625 21.3438C2.53125 20.2188 1.64062 18.8958 0.984375 17.375C0.328125 15.8542 0 14.2292 0 12.5C0 10.7708 0.328125 9.14583 0.984375 7.625C1.64062 6.10417 2.53125 4.78125 3.65625 3.65625C4.78125 2.53125 6.10417 1.64062 7.625 0.984375C9.14583 0.328125 10.7708 0 12.5 0C14.2292 0 15.8542 0.328125 17.375 0.984375C18.8958 1.64062 20.2188 2.53125 21.3438 3.65625C22.4688 4.78125 23.3594 6.10417 24.0156 7.625C24.6719 9.14583 25 10.7708 25 12.5C25 14.2292 24.6719 15.8542 24.0156 17.375C23.3594 18.8958 22.4688 20.2188 21.3438 21.3438C20.2188 22.4688 18.8958 23.3594 17.375 24.0156C15.8542 24.6719 14.2292 25 12.5 25Z" fill="#94A3B8"/>
                                </svg>
                                <span id="modalCustomCategoryLabel" class="text-center text-xs font-[Poppins] text-[#475569]">{{ $customCategoryOld !== '' ? $customCategoryOld : 'Other / Custom' }}</span>
                            </button>
                        </div>

                        <div class="hidden">
                            <input id="modal_bm_physical" type="checkbox" name="business_models[]" value="Physical Goods" @checked(in_array('Physical Goods', $businessModelOld, true))>
                            <input id="modal_bm_digital" type="checkbox" name="business_models[]" value="Digital Products" @checked(in_array('Digital Products', $businessModelOld, true))>
                            <input id="modal_bm_service" type="checkbox" name="business_models[]" value="Services" @checked(in_array('Services', $businessModelOld, true))>
                            <input id="modal_bm_subscription" type="checkbox" name="business_models[]" value="Subscriptions" @checked(in_array('Subscriptions', $businessModelOld, true))>
                            <input id="modal_bm_virtual" type="checkbox" name="business_models[]" value="Memberships" @checked(in_array('Memberships', $businessModelOld, true))>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#E2E8F0] pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <button type="button" id="dismissCreateStoreModal" class="text-left text-sm font-semibold text-[#64748B] transition hover:text-[#334155]">Cancel</button>
                        <button type="submit" class="inline-flex min-w-[170px] items-center justify-center gap-2 rounded-lg bg-[#0052CC] px-6 py-3 font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042a3]">
                            <span>Create Store</span>
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M10.1458 7.5H0V5.83333H10.1458L5.47917 1.16667L6.66667 0L13.3333 6.66667L6.66667 13.3333L5.47917 12.1667L10.1458 7.5Z" fill="white"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="modalCustomCategoryOverlay" class="absolute inset-0 z-10 hidden items-center justify-center bg-[#0F172A]/60 px-4 py-6 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-2xl border border-[#E2E8F0] bg-white p-8 shadow-2xl">
                <div class="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-poppins text-[26px] font-medium leading-tight text-[#0F172A]">Browse All Categories</h3>
                        <p class="mt-2 text-sm text-[#94A3B8]">Select the category that best describes your business, or create a custom one.</p>
                    </div>
                    <button type="button" id="closeModalCustomCategoryModal" class="text-[#94A3B8] transition hover:text-[#64748B]" aria-label="Close custom category modal">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <input id="modalCategorySearchInput" type="text" placeholder="Search categories..." class="mb-5 h-11 w-full rounded-[10px] border border-[#CBD5E1] bg-white px-4 text-sm text-[#0F172A] outline-none transition placeholder:text-[#94A3B8] focus:border-[#0052CC] focus:ring-2 focus:ring-[#0052CC]/15">

                <div class="grid gap-4 md:grid-cols-2">
                    <button type="button" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="physical" data-model="Physical Goods" data-search="physical goods fashion retail">
                        <span class="category-title block text-[15px] font-semibold text-[#475569]">Physical Goods</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">Retail products and storefront inventory</span>
                    </button>
                    <button type="button" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="digital" data-model="Digital Products" data-search="digital products downloads assets">
                        <span class="category-title block text-[15px] font-semibold text-[#475569]">Digital Products</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">Downloads, licenses, and files</span>
                    </button>
                    <button type="button" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="service" data-model="Services" data-search="services consultations appointments">
                        <span class="category-title block text-[15px] font-semibold text-[#475569]">Services</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">Appointments, bookings, and consulting</span>
                    </button>
                    <button type="button" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="subscription" data-model="Subscriptions" data-search="subscriptions recurring plans">
                        <span class="category-title block text-[15px] font-semibold text-[#475569]">Subscriptions</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">Recurring plans and membership billing</span>
                    </button>
                    <button type="button" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="virtual" data-model="Memberships" data-search="memberships virtual community">
                        <span class="category-title block text-[15px] font-semibold text-[#475569]">Memberships</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">Communities and gated access</span>
                    </button>
                    <button type="button" id="modalCustomCategoryCard" class="modal-custom-category-card rounded-xl bg-[#F8FAFC] p-4 text-left" data-category="custom" data-model="" data-search="">
                        <span id="modalCustomCategoryTitle" class="category-title block text-[15px] font-semibold text-[#475569]">Custom</span>
                        <span class="category-subtitle mt-1 block text-[13px] text-[#94A3B8]">User Added Category</span>
                    </button>
                </div>

                <div class="mt-6 rounded-[14px] bg-[#F8FAFC] px-6 py-5">
                    <div class="grid items-center gap-4 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <h4 class="text-base font-semibold leading-[1.25] text-[#4A84DA]">Can't find your category?</h4>
                            <p class="mt-1 text-[13px] leading-[1.45] text-[#94A3B8]">Create a custom one that fits your unique business model.</p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:justify-end">
                            <input id="modalCustomCategoryTextInput" type="text" placeholder="Create custom category..." class="h-11 min-w-0 flex-1 rounded-xl border border-[#CBD5E1] bg-white px-4 text-sm text-[#0F172A] outline-none placeholder:text-[#94A3B8] focus:border-[#0052CC] focus:ring-2 focus:ring-[#0052CC]/15">
                            <button id="addModalCustomCategoryBtn" type="button" class="inline-flex h-12 min-w-20 items-center justify-center whitespace-nowrap rounded-xl bg-[#0052CC] px-[18px] text-sm font-bold leading-none text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042A3]">+ Add</button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" id="cancelModalCustomCategorySelection" class="text-left text-sm font-inter font-medium text-[#94A3B8] transition hover:text-[#64748B]">Cancel and close</button>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-[22px]">
                        <span id="modalSelectedCountText" class="text-sm text-[#94A3B8]">0 category selected</span>
                        <button type="button" id="confirmModalCustomCategorySelection" class="inline-flex h-[46px] min-w-[170px] items-center justify-center whitespace-nowrap rounded-xl bg-[#0052CC] px-6 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042A3]">Confirm Selection</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const createStoreModal = document.getElementById('createStoreModal');
        if (!createStoreModal) {
            return;
        }

        const body = document.body;
        const openButtons = [...document.querySelectorAll('.js-open-create-store-modal')];
        const closeCreateStoreModal = document.getElementById('closeCreateStoreModal');
        const dismissCreateStoreModal = document.getElementById('dismissCreateStoreModal');
        const categoryInput = document.getElementById('modalCategoryInput');
        const customCategoryInput = document.getElementById('modalCustomCategoryInput');
        const customCategoryLabel = document.getElementById('modalCustomCategoryLabel');
        const categoryTiles = [...createStoreModal.querySelectorAll('.modal-category-tile')];
        const modelMap = {
            physical: document.getElementById('modal_bm_physical'),
            digital: document.getElementById('modal_bm_digital'),
            service: document.getElementById('modal_bm_service'),
            subscription: document.getElementById('modal_bm_subscription'),
            virtual: document.getElementById('modal_bm_virtual'),
        };

        const customCategoryOverlay = document.getElementById('modalCustomCategoryOverlay');
        const openCustomCategoryModal = document.getElementById('openModalCustomCategoryModal');
        const closeCustomCategoryModal = document.getElementById('closeModalCustomCategoryModal');
        const cancelCustomCategorySelection = document.getElementById('cancelModalCustomCategorySelection');
        const confirmCustomCategorySelection = document.getElementById('confirmModalCustomCategorySelection');
        const searchInput = document.getElementById('modalCategorySearchInput');
        const customCategoryTextInput = document.getElementById('modalCustomCategoryTextInput');
        const addCustomCategoryBtn = document.getElementById('addModalCustomCategoryBtn');
        const selectedCountText = document.getElementById('modalSelectedCountText');
        const customCategoryTitle = document.getElementById('modalCustomCategoryTitle');
        const customCategoryCard = document.getElementById('modalCustomCategoryCard');
        const customCards = [...createStoreModal.querySelectorAll('.modal-custom-category-card')];

        const overlayState = {
            selectedCategory: categoryInput?.value || '',
            selectedModel: '',
            customCategory: customCategoryInput?.value || '',
        };

        const setBodyLock = (locked) => {
            body.classList.toggle('overflow-hidden', locked);
        };

        const openStoreModal = () => {
            createStoreModal.classList.remove('hidden');
            createStoreModal.classList.add('flex');
            setBodyLock(true);
        };

        const closeNestedCustomCategoryModal = () => {
            customCategoryOverlay?.classList.add('hidden');
            customCategoryOverlay?.classList.remove('flex');
        };

        const closeStoreModal = () => {
            createStoreModal.classList.add('hidden');
            createStoreModal.classList.remove('flex');
            closeNestedCustomCategoryModal();
            setBodyLock(false);
        };

        const setActiveTile = (active) => {
            categoryTiles.forEach((tile) => {
                const selected = tile.dataset.category === active;
                tile.classList.toggle('bg-[#0052CC]/5', selected);
                tile.classList.toggle('border-2', selected);
                tile.classList.toggle('border-[#0052CC]', selected);
                tile.classList.toggle('bg-white', !selected);
                tile.classList.toggle('border', !selected);
                tile.classList.toggle('border-[#E2E8F0]', !selected);
            });

            Object.entries(modelMap).forEach(([key, input]) => {
                if (input) {
                    input.checked = key === active;
                }
            });
        };

        const syncCustomCategoryLabel = () => {
            if (!customCategoryLabel || !customCategoryInput) {
                return;
            }

            customCategoryLabel.textContent = customCategoryInput.value.trim() !== ''
                ? customCategoryInput.value.trim()
                : 'Other / Custom';
        };

        const refreshCustomCards = () => {
            customCards.forEach((card) => {
                const selected = overlayState.selectedCategory === card.dataset.category
                    && overlayState.selectedModel === card.dataset.model;

                card.classList.toggle('ring-2', selected);
                card.classList.toggle('ring-[#0052CC]', selected);
                card.classList.toggle('bg-[#EAF2FF]', selected);
            });
        };

        const refreshSelectedCount = () => {
            if (selectedCountText) {
                selectedCountText.textContent = overlayState.selectedModel ? '1 category selected' : '0 category selected';
            }
        };

        const hydrateOverlayStateFromParent = () => {
            const checkedInput = Object.values(modelMap).find((input) => input?.checked);
            overlayState.selectedCategory = categoryInput?.value || '';
            overlayState.selectedModel = checkedInput?.value || '';
            overlayState.customCategory = customCategoryInput?.value || '';

            if (overlayState.customCategory && customCategoryTitle && customCategoryCard) {
                customCategoryTitle.textContent = overlayState.customCategory;
                customCategoryCard.dataset.model = overlayState.customCategory;
                customCategoryCard.dataset.search = overlayState.customCategory.toLowerCase();
            }

            refreshCustomCards();
            refreshSelectedCount();
        };

        categoryTiles.forEach((tile) => {
            tile.addEventListener('click', () => {
                const selected = tile.dataset.category || '';
                categoryInput.value = selected;

                if (selected !== 'custom') {
                    customCategoryInput.value = '';
                }

                setActiveTile(selected);
                syncCustomCategoryLabel();
            });
        });

        if (categoryInput?.value) {
            setActiveTile(categoryInput.value);
        }
        syncCustomCategoryLabel();

        const openNestedCustomCategoryModal = () => {
            hydrateOverlayStateFromParent();
            customCategoryOverlay?.classList.remove('hidden');
            customCategoryOverlay?.classList.add('flex');
        };

        customCards.forEach((card) => {
            card.addEventListener('click', () => {
                overlayState.selectedCategory = card.dataset.category || '';
                overlayState.selectedModel = card.dataset.model || '';
                refreshCustomCards();
                refreshSelectedCount();
            });
        });

        const addCustomCategory = () => {
            const value = customCategoryTextInput?.value.trim() || '';
            if (value === '' || !customCategoryTitle || !customCategoryCard) {
                return;
            }

            overlayState.selectedCategory = 'custom';
            overlayState.selectedModel = value;
            overlayState.customCategory = value;
            customCategoryTitle.textContent = value;
            customCategoryCard.dataset.model = value;
            customCategoryCard.dataset.search = value.toLowerCase();
            refreshCustomCards();
            refreshSelectedCount();
        };

        addCustomCategoryBtn?.addEventListener('click', addCustomCategory);
        customCategoryTextInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCustomCategory();
            }
        });

        searchInput?.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            customCards.forEach((card) => {
                const haystack = card.dataset.search || '';
                card.style.display = !query || haystack.includes(query) ? '' : 'none';
            });
        });

        confirmCustomCategorySelection?.addEventListener('click', () => {
            categoryInput.value = overlayState.selectedCategory || categoryInput.value;

            Object.values(modelMap).forEach((input) => {
                if (input) {
                    input.checked = input.value === overlayState.selectedModel;
                }
            });

            customCategoryInput.value = overlayState.selectedCategory === 'custom'
                ? (overlayState.customCategory || overlayState.selectedModel)
                : '';

            setActiveTile(categoryInput.value);
            syncCustomCategoryLabel();
            closeNestedCustomCategoryModal();
        });

        [closeCreateStoreModal, dismissCreateStoreModal].forEach((button) => {
            button?.addEventListener('click', closeStoreModal);
        });

        [closeCustomCategoryModal, cancelCustomCategorySelection].forEach((button) => {
            button?.addEventListener('click', closeNestedCustomCategoryModal);
        });

        openButtons.forEach((button) => {
            button.addEventListener('click', openStoreModal);
        });

        openCustomCategoryModal?.addEventListener('click', openNestedCustomCategoryModal);

        createStoreModal.addEventListener('click', (event) => {
            if (event.target === createStoreModal) {
                closeStoreModal();
            }
        });

        customCategoryOverlay?.addEventListener('click', (event) => {
            if (event.target === customCategoryOverlay) {
                closeNestedCustomCategoryModal();
            }
        });

        if (createStoreModal.dataset.autoOpen === 'true') {
            openStoreModal();
        }
    })();
</script>
