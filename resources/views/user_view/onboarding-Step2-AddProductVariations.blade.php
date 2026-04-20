<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding - Add Product | BaaS Platform</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="user-typography bg-[#F5F7F8] antialiased text-[#0F172A] min-h-screen flex flex-col overflow-x-hidden font-[Inter]">
    @include('user_view.partials.flash_success')

    <div class="w-full bg-[#F5F7F8] flex flex-col">
        <header
            class="flex justify-between items-center px-4 sm:px-6 lg:px-16 py-3 bg-white border-b border-[#E2E8F0] w-full">
            <div class="flex items-center gap-4">
                <div class="w-6 h-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2H15.3333V8.6667H8.6667V15.3333H2V22H22V2Z" fill="#0052CC" />
                    </svg>
                </div>
                <span class="text-lg font-bold text-[#0F172A]">BaaS Platform</span>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden md:flex items-center gap-4 lg:gap-8 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-[#475569] font-inter font-medium">Dashboard</a>
                    <a href="{{ route('products') }}" class="text-[#0052CC] font-semibold">Products</a>
                    <a href="{{ route('orders') }}" class="text-[#475569] font-inter font-medium">Orders</a>
                    <a href="{{ route('generalSettings') }}" class="text-[#475569] font-inter font-medium">Settings</a>
                </nav>
                <div class="flex items-center gap-3 sm:gap-4">
                    <button form="product-onboarding-form" type="submit"
                        class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Save
                        & Continue</button>
                    <div class="w-10 h-10 rounded-full border border-[#E2E8F0] overflow-hidden bg-[#E2E8F0]"></div>
                </div>
            </div>
        </header>

        <main class="px-6 md:px-10 py-8 max-w-[1024px] w-full mx-auto">
            <nav class="flex items-center gap-2 text-sm font-inter font-medium mb-6">
                <a href="{{ route('onboarding-StoreDetails-1') }}"
                    class="text-[#0052CC] opacity-70 hover:opacity-100">Onboarding</a>
                <svg width="5" height="7" viewBox="0 0 5 7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z"
                        fill="#94A3B8" />
                </svg>
                <span class="text-[#0F172A]">Add Product</span>
            </nav>

            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-inter font-medium text-[#64748B] uppercase tracking-wider">Step 2 of 3</span>
                    <span class="text-xs text-[#64748B]">Setup Progress: 55% Complete</span>
                </div>
                <div class="w-full h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                    <div class="h-2 w-[55%] bg-[#0052CC] rounded-full"></div>
                </div>
                <div class="flex justify-end mt-1">
                    <span class="text-xs text-[#0052CC] font-inter font-medium">Next: Launch</span>
                </div>
            </div>

            <div class="flex justify-between items-start mb-8 gap-4">
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    @if ($store->logo)
                        <div class="mt-1 flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-sm">
                            <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="max-h-full max-w-full object-contain p-1.5">
                        </div>
                    @endif
                    <div class="min-w-0">
                    @if ($store->products->count() > 0)
                        <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Add Product to {{ $store->name }}</h1>
                        <p class="text-base text-[#64748B] mt-1">Expand your store catalog. Define product basics, add variation types, then add variant rows by selecting options.</p>
                    @else
                        <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Add Product</h1>
                        <p class="text-base text-[#64748B] mt-1">Define product basics, add variation types, then add
                            variant rows by selecting options.</p>
                    @endif
                    </div>
                </div>
                <button
                    class="bg-[#E2E8F0] text-[#64748B] text-sm font-inter font-medium px-4 py-2 rounded border border-[#E2E8F0]"
                    type="button">Upload CSV</button>
            </div>

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $step2Data = old();
                if (empty($step2Data)) {
                    $step2Data = $draft ?? [];
                }

                $defaultProductTypes = ['physical', 'digital', 'service', 'subscription', 'virtual'];
                $rawStep2ProductType = (string) ($step2Data['product_type'] ?? 'physical');
                $usesCustomStep2ProductType = $rawStep2ProductType !== '' && !in_array($rawStep2ProductType, $defaultProductTypes, true);
                $selectedStep2ProductType = $usesCustomStep2ProductType ? 'custom' : $rawStep2ProductType;
                $customStep2ProductType = $usesCustomStep2ProductType ? $rawStep2ProductType : (string) ($step2Data['custom_product_type'] ?? '');

                $variationTypes = $step2Data['variation_types'] ?? ($draft['variation_types'] ?? []);
                $customVariants = $step2Data['variants'] ?? ($draft['variants'] ?? []);

                $previewRows = [];

                if (!empty($customVariants)) {
                    foreach ($customVariants as $variantRow) {
                        if (!is_array($variantRow)) {
                            continue;
                        }

                        $labelParts = [];
                        foreach ($variationTypes as $variationIndex => $variationType) {
                            $selectedIndex = $variantRow['option_map'][$variationIndex] ?? null;
                            if ($selectedIndex !== null && $selectedIndex !== '' && isset($variationType['options'][$selectedIndex])) {
                                $labelParts[] = $variationType['options'][$selectedIndex];
                            }
                        }

                        $previewRows[] = [
                            'label' => !empty($labelParts) ? implode(' / ', $labelParts) : 'Default Variant',
                            'sku' => $variantRow['sku'] ?? '',
                            'price' => $variantRow['price'] ?? ($step2Data['base_price'] ?? '0'),
                            'stock' => $variantRow['stock'] ?? ($step2Data['default_stock'] ?? 50),
                        ];
                    }
                }

                if (empty($previewRows)) {
                    $previewRows[] = [
                        'label' => 'No variants added yet',
                        'sku' => '-',
                        'price' => '-',
                        'stock' => '-',
                    ];
                }
            @endphp

            <form id="product-onboarding-form" action="{{ route('onboarding-Step2-AddProductVariations.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                {{-- Determine if this is a new product creation or editing --}}
                <input type="hidden" name="mode" value="{{ !empty($draft) && isset($draft['name']) ? 'edit' : 'create' }}">
                <input id="base-price-hidden" type="hidden" name="base_price"
                    value="{{ $step2Data['base_price'] ?? '' }}">
                <input id="default-stock-hidden" type="hidden" name="default_stock"
                    value="{{ $step2Data['default_stock'] ?? '' }}">
                <input type="hidden" name="stock_alert" value="{{ $step2Data['stock_alert'] ?? 5 }}">
                <input id="step2-product-type-value" type="hidden" name="product_type" value="{{ $selectedStep2ProductType === 'custom' ? $customStep2ProductType : $selectedStep2ProductType }}">

                <div id="variation-hidden-inputs">
                    @foreach ($variationTypes as $variationIndex => $variationType)
                        <input type="hidden" name="variation_types[{{ $variationIndex }}][name]"
                            value="{{ $variationType['name'] ?? '' }}">
                        <input type="hidden" name="variation_types[{{ $variationIndex }}][type]"
                            value="{{ $variationType['type'] ?? 'select' }}">
                        @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                            <input type="hidden" name="variation_types[{{ $variationIndex }}][options][{{ $optionIndex }}]"
                                value="{{ $option }}">
                        @endforeach
                    @endforeach
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex items-center gap-2 mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Basic Information</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
                        <div>
                            <label for="step2-product-type" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product Type</label>
                            <div class="relative">
                                <select id="step2-product-type"
                                    class="w-full appearance-none px-4 py-3 pr-10 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                    @foreach ($defaultProductTypes as $productType)
                                        <option value="{{ $productType }}" @selected($selectedStep2ProductType === $productType)>{{ ucfirst($productType) }}</option>
                                    @endforeach
                                    <option value="custom" @selected($selectedStep2ProductType === 'custom')>Custom Type</option>
                                </select>
                                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2" width="12" height="12" viewBox="0 0 14 14" fill="none">
                                    <path d="M7 9L3 5H11L7 9Z" fill="#64748B"/>
                                </svg>
                            </div>
                            <div id="step2-custom-product-type-wrap" class="mt-3 {{ $selectedStep2ProductType === 'custom' ? '' : 'hidden' }}">
                                <input id="step2-custom-product-type" name="custom_product_type" type="text"
                                    value="{{ $customStep2ProductType }}" placeholder="e.g. Home Decor"
                                    class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                        </div>
                        <div>
                            <label for="name" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product
                                Name</label>
                            <input id="name" name="name" type="text" placeholder="e.g. Premium Cotton T-Shirt"
                                value="{{ $step2Data['name'] ?? '' }}"
                                class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                    </div>

                    <div>
                        <label for="description"
                            class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Description</label>
                        <textarea id="description" name="description" rows="3"
                            placeholder="Describe your product's key features and benefits..."
                            class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ $step2Data['description'] ?? '' }}</textarea>
                    </div>

                    <div class="mt-4">
                        <label for="sku" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Base SKU
                            (optional)</label>
                        <input id="sku" name="sku" type="text" value="{{ $step2Data['sku'] ?? '' }}"
                            class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>

                    @php
                        $onboardingBrands = $brands ?? collect();
                        $onboardingTags = $tags ?? collect();
                        $onboardingProductCategories = $productCategories ?? collect();
                    @endphp
                    <div class="mt-4">
                        <label for="onboarding-brand-id" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Brand (optional)</label>
                        <select id="onboarding-brand-id" name="brand_id"
                            class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20 bg-white">
                            <option value="">No brand</option>
                            @foreach ($onboardingBrands as $b)
                                <option value="{{ $b->id }}" @selected((string) ($step2Data['brand_id'] ?? '') === (string) $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-[#64748B]">Applies to <span class="font-semibold text-[#475569]">{{ $store->name }}</span> only. You can add or change brands later on the Products page.</p>
                    </div>

                    @if ($onboardingTags->isNotEmpty())
                        <div class="mt-4">
                            <label for="onboarding-tag-ids" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Tags (optional)</label>
                            <select id="onboarding-tag-ids" name="tag_ids[]" multiple size="4"
                                class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                @foreach ($onboardingTags as $tg)
                                    <option value="{{ $tg->id }}" @selected(collect($step2Data['tag_ids'] ?? [])->contains($tg->id))>{{ $tg->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-[#64748B]">Use tags to add lightweight labels like Featured, Sale, or Summer.</p>
                        </div>
                    @endif

                    @if ($onboardingProductCategories->isNotEmpty())
                        <div class="mt-4">
                            <label for="onboarding-category-ids" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Categories</label>
                            <select id="onboarding-category-ids" name="category_ids[]" multiple size="4"
                                class="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                @foreach ($onboardingProductCategories as $pc)
                                    <option value="{{ $pc->id }}" @selected(collect($step2Data['category_ids'] ?? [])->contains($pc->id))>{{ $pc->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-[#64748B]">Catalog categories are separate from product type (physical, digital, etc.).</p>
                        </div>
                    @endif

                    <div class="mt-4">
                        <label for="step2-product-image" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product Images</label>
                        <input id="step2-product-image" name="product_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple
                            class="w-full px-4 py-3 border border-dashed border-[#CBD5E1] rounded-lg text-sm text-[#475569] bg-[#F8FAFC] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        <p class="mt-2 text-xs text-[#64748B]">You can upload multiple images. They will be stored in your project storage.</p>
                        <div id="step2-product-image-preview" class="mt-3 flex flex-wrap gap-3"></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h2>
                        <button id="openVariationModal" type="button"
                            class="flex items-center gap-2 text-[#0052CC] text-sm font-medium">Add Variation
                            Type</button>
                    </div>

                    <div id="no-variation-state"
                        class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B] {{ empty($variationTypes) ? '' : 'hidden' }}">
                        No variation type added yet. Click <span class="font-semibold text-[#0052CC]">Add Variation
                            Type</span> to start.
                    </div>
                    <div id="variation-types-list" class="space-y-4 {{ empty($variationTypes) ? 'hidden' : '' }}">
                        @foreach ($variationTypes as $index => $variationType)
                            <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-base text-[#0F172A] font-poppins">Variation {{ $index + 1 }}:
                                        {{ $variationType['name'] ?? 'Variation' }}</span>
                                    <span
                                        class="text-xs text-[#94A3B8] uppercase">{{ $variationType['type'] ?? 'select' }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                                        <span
                                            class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-medium inline-flex items-center gap-2">
                                            {{ $option }}
                                            <button type="button"
                                                class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none"
                                                data-variation-index="{{ $index }}" data-option-index="{{ $optionIndex }}"
                                                aria-label="Remove option {{ $option }}">×</button>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants</h2>
                        <span class="text-sm font-medium text-[#64748B]">Rows are created automatically from variation options</span>
                    </div>

                    <div
                        class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4 p-4 border border-[#E2E8F0] rounded-xl bg-[#F8FAFC]">
                        <div class="md:col-span-2">
                            <p class="text-sm font-semibold text-[#0F172A]">Bulk Set Price & Stock</p>
                            <p class="text-xs text-[#64748B]">Apply one value to all variant rows.</p>
                        </div>
                        <div>
                            <label for="bulk-price"
                                class="block text-xs font-semibold text-[#64748B] mb-1">Price</label>
                            <input id="bulk-price" type="number" min="0" step="0.01"
                                value="{{ $step2Data['base_price'] ?? '' }}"
                                class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                        <div class="flex gap-2 items-end">
                            <div class="flex-1">
                                <label for="bulk-stock"
                                    class="block text-xs font-semibold text-[#64748B] mb-1">Stock</label>
                                <input id="bulk-stock" type="number" min="0" step="1"
                                    value="{{ $step2Data['default_stock'] ?? '' }}"
                                    class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <button id="apply-bulk-values" type="button"
                                class="px-3 py-2 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Apply</button>
                        </div>
                    </div>

                    <div id="variant-rows" class="space-y-4"></div>

                    <p class="mt-3 text-xs text-[#64748B]">Each row is created automatically from your variation options.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                    <div class="flex flex-wrap justify-between items-center mb-6">
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h2>
                        <span id="preview-count" class="text-sm text-[#94A3B8]">{{ count($previewRows) }} variant
                            row(s)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-[#F1F5F9]">
                                <tr>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Variant
                                    </th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">SKU</th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Price ($)
                                    </th>
                                    <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Stock
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="preview-table-body" class="divide-y divide-[#F1F5F9]">
                                @foreach ($previewRows as $row)
                                    <tr>
                                        <td class="py-4 px-2 font-medium text-[#0F172A]">{{ $row['label'] }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['sku'] ?: 'Auto-generated' }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['price'] }}</td>
                                        <td class="py-4 px-2 text-[#475569]">{{ $row['stock'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-6 border-t border-[#E2E8F0]">
                    <a href="{{ route('onboarding-StoreDetails-1') }}" class="text-[#475569] font-bold">Back to Basic
                        Setup</a>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('onboarding_StoreReady') }}" class="text-[#475569] font-bold px-6 py-2">Skip
                            for Now</a>
                        <button type="submit"
                            class="bg-[#0052CC] text-white font-bold px-8 py-3 rounded-lg shadow-lg shadow-[#0052CC]/20">Save
                            & Continue</button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <div id="variationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-[#0F172A]/60 p-4 backdrop-blur-[2px]">
        <div class="w-full max-w-[512px] overflow-hidden rounded-xl border border-[#E2E8F0] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-[#F1F5F9] px-6 py-4">
                <div>
                    <h3 id="variationModalTitle" class="text-lg font-semibold text-[#0F172A]">Add Variation Type</h3>
                    <p class="mt-0.5 text-xs text-[#64748B]">Define how customers will differentiate your items</p>
                </div>
                <button type="button" id="closeVariationModal" class="text-[#94A3B8] hover:text-[#64748B]">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M1.4 14L0 12.6L5.6 7L0 1.4L1.4 0L7 5.6L12.6 0L14 1.4L8.4 7L14 12.6L12.6 14L7 8.4L1.4 14Z" fill="currentColor"/>
                    </svg>
                </button>
            </div>

            <form id="variationForm" class="space-y-6 p-6">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#334155]">Variation Name</label>
                    <input id="variationName" type="text" placeholder="e.g., Size" class="w-full rounded-lg border border-[#E2E8F0] px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-[#334155]">Option Values</label>
                    <div class="rounded-lg border border-[#E2E8F0] px-3 py-3 focus-within:ring-2 focus-within:ring-[#0052CC]/20">
                        <div id="variationOptionChips" class="mb-2 flex flex-wrap gap-2"></div>
                        <input id="variationOptionInput" type="text" placeholder="Type a value and press Enter" class="w-full border-0 p-0 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-0">
                    </div>
                    <textarea id="variationOptions" rows="3" placeholder="S, M, L, XL" class="hidden"></textarea>
                </div>
            </form>

            <div class="flex items-center justify-end gap-3 border-t border-[#F1F5F9] bg-[#F8FAFC] px-6 py-4">
                <button type="button" id="cancelVariationModal" class="px-4 py-2 text-sm font-semibold text-[#475569] hover:text-[#1E293B]">Cancel</button>
                <button type="button" id="submitVariationModal" class="flex items-center gap-2 rounded-lg bg-[#0052CC] px-5 py-2 text-sm font-bold text-white shadow-md shadow-[#0052CC]/20 transition hover:bg-[#0047B3]">
                    <span id="submitVariationModalLabel">Add Variation</span>
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                        <path d="M4 5.33333H0V4H4V0H5.33333V4H9.33333V5.33333H5.33333V9.33333H4V5.33333Z" fill="white"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            let variationTypes = @json(array_values($variationTypes));
            const existingVariants = @json(array_values($customVariants));
            let defaultPrice = @json((string) ($step2Data['base_price'] ?? ''));
            let defaultStock = @json((string) ($step2Data['default_stock'] ?? ''));
            const defaultStockAlert = @json((int) ($step2Data['stock_alert'] ?? 5));
            // sessionStorage form element references added
            const step2StorageKey = 'onboarding_step2_unsaved_state';
            const productForm = document.getElementById('product-onboarding-form');
            const nameInput = document.getElementById('name');
            const descriptionInput = document.getElementById('description');
            const skuInput = document.getElementById('sku');
            const productTypeSelect = document.getElementById('step2-product-type');
            const productTypeValueInput = document.getElementById('step2-product-type-value');
            const customProductTypeWrap = document.getElementById('step2-custom-product-type-wrap');
            const customProductTypeInput = document.getElementById('step2-custom-product-type');
            const productImageInput = document.getElementById('step2-product-image');
            const productImagePreview = document.getElementById('step2-product-image-preview');
            let selectedStep2Images = Array.from(productImageInput?.files || []);

            const rowsContainer = document.getElementById('variant-rows');
            const bulkPriceInput = document.getElementById('bulk-price');
            const bulkStockInput = document.getElementById('bulk-stock');
            const applyBulkButton = document.getElementById('apply-bulk-values');
            const previewCount = document.getElementById('preview-count');
            const previewTableBody = document.getElementById('preview-table-body');
            const variationTypesList = document.getElementById('variation-types-list');
            const noVariationState = document.getElementById('no-variation-state');
            const variationHiddenInputs = document.getElementById('variation-hidden-inputs');
            const variationModal = document.getElementById('variationModal');
            const openVariationModal = document.getElementById('openVariationModal');
            const basePriceHiddenInput = document.getElementById('base-price-hidden');
            const defaultStockHiddenInput = document.getElementById('default-stock-hidden');
            const closeVariationModalButton = document.getElementById('closeVariationModal');
            const cancelVariationModalButton = document.getElementById('cancelVariationModal');
            const submitVariationModalButton = document.getElementById('submitVariationModal');
            const submitVariationModalLabel = document.getElementById('submitVariationModalLabel');
            const variationModalTitle = document.getElementById('variationModalTitle');
            const variationNameInput = document.getElementById('variationName');
            const variationOptionInput = document.getElementById('variationOptionInput');
            const variationOptionChips = document.getElementById('variationOptionChips');
            const variationOptionsTextarea = document.getElementById('variationOptions');
            let variationOptionTags = [];
            let editingVariationIndex = null;

            if (!rowsContainer) {
                return;
            }

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
            const getRowKey = (optionMap) => Object.entries(optionMap || {}).sort(([left], [right]) => Number(left) - Number(right)).map(([variationIndex, optionIndex]) => `${variationIndex}:${optionIndex}`).join('|');
            const buildRowsFromVariationTypes = (existingRows = []) => {
                if (!variationTypes.length || variationTypes.some((variationType) => !(variationType.options || []).length)) {
                    return [];
                }

                const existingRowsByKey = new Map(existingRows.map((row) => [getRowKey(row.option_map || {}), row]));
                const combinations = [];

                const walk = (variationIndex, optionMap) => {
                    if (variationIndex >= variationTypes.length) {
                        combinations.push({ ...optionMap });
                        return;
                    }

                    (variationTypes[variationIndex].options || []).forEach((_, optionIndex) => {
                        walk(variationIndex + 1, {
                            ...optionMap,
                            [variationIndex]: optionIndex,
                        });
                    });
                };

                walk(0, {});

                return combinations.map((optionMap) => {
                    const existingRow = existingRowsByKey.get(getRowKey(optionMap));

                    return {
                        option_map: optionMap,
                        sku: existingRow?.sku ?? '',
                        price: existingRow?.price ?? defaultPrice,
                        stock: existingRow?.stock ?? defaultStock,
                        stock_alert: existingRow?.stock_alert ?? defaultStockAlert,
                    };
                });
            };

            const syncProductTypeState = () => {
                const isCustomType = productTypeSelect?.value === 'custom';
                customProductTypeWrap?.classList.toggle('hidden', !isCustomType);
                if (customProductTypeInput) {
                    customProductTypeInput.required = Boolean(isCustomType);
                }
                if (productTypeValueInput) {
                    productTypeValueInput.value = isCustomType
                        ? (customProductTypeInput?.value.trim() || 'custom')
                        : (productTypeSelect?.value || 'physical');
                }
            };

            const syncSelectedFiles = (input, files) => {
                if (!input) {
                    return;
                }

                const transfer = new DataTransfer();
                files.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
            };

            const renderSelectedImages = () => {
                if (!productImagePreview || !productImageInput) {
                    return;
                }

                if (!selectedStep2Images.length) {
                    productImagePreview.innerHTML = '';
                    return;
                }

                productImagePreview.innerHTML = selectedStep2Images.map((file, index) => {
                    const objectUrl = URL.createObjectURL(file);
                    return `<div class="group relative overflow-hidden rounded-2xl border border-[#D9E2EC] bg-white p-2 shadow-sm"><img src="${objectUrl}" alt="${escapeHtml(file.name)}" class="h-20 w-20 rounded-xl object-cover"><button type="button" class="remove-step2-image absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#0F172A]/70 text-xs font-bold text-white" data-index="${index}" aria-label="Remove image">&times;</button><p class="mt-2 max-w-[80px] truncate text-[11px] text-[#64748B]">${escapeHtml(file.name)}</p></div>`;
                }).join('');

                productImagePreview.querySelectorAll('.remove-step2-image').forEach((button) => {
                    button.addEventListener('click', () => {
                        selectedStep2Images = selectedStep2Images.filter((_, index) => index !== Number(button.dataset.index));
                        syncSelectedFiles(productImageInput, selectedStep2Images);
                        renderSelectedImages();
                    });
                });
            };

            const renderVariationHiddenInputs = () => {
                if (!variationHiddenInputs) {
                    return;
                }

                variationHiddenInputs.innerHTML = variationTypes.map((variationType, variationIndex) => {
                    const optionInputs = (variationType.options || []).map((option, optionIndex) => (
                        `<input type="hidden" name="variation_types[${variationIndex}][options][${optionIndex}]" value="${escapeHtml(option)}">`
                    )).join('');

                    return `
                        <input type="hidden" name="variation_types[${variationIndex}][name]" value="${escapeHtml(variationType.name || '')}">
                        <input type="hidden" name="variation_types[${variationIndex}][type]" value="${escapeHtml(variationType.type || 'select')}">
                        ${optionInputs}
                    `;
                }).join('');
            };

            const saveStep2StateToSession = () => {
                const payload = {
                    name: nameInput ? nameInput.value : '',
                    description: descriptionInput ? descriptionInput.value : '',
                    sku: skuInput ? skuInput.value : '',
                    product_type: productTypeSelect ? productTypeSelect.value : 'physical',
                    custom_product_type: customProductTypeInput ? customProductTypeInput.value : '',
                    base_price: bulkPriceInput ? bulkPriceInput.value : '',
                    default_stock: bulkStockInput ? bulkStockInput.value : '',
                    variation_types: variationTypes.map((variationType) => ({
                        name: variationType.name ?? '',
                        type: variationType.type ?? 'select',
                        options: [...(variationType.options || [])],
                    })),
                    rows: rows.map((row) => ({
                        option_map: { ...(row.option_map || {}) },
                        sku: row.sku ?? '',
                        price: row.price ?? '',
                        stock: row.stock ?? '',
                        stock_alert: row.stock_alert ?? defaultStockAlert,
                    })),
                };

                sessionStorage.setItem(step2StorageKey, JSON.stringify(payload));
            };

            const getSavedStep2State = () => {
                try {
                    const raw = sessionStorage.getItem(step2StorageKey);
                    return raw ? JSON.parse(raw) : null;
                } catch (error) {
                    return null;
                }
            };

            const restoreStep2StateFromSession = () => {
                const savedState = getSavedStep2State();
                if (!savedState) {
                    return;
                }

                if (nameInput && typeof savedState.name === 'string') {
                    nameInput.value = savedState.name;
                }

                if (descriptionInput && typeof savedState.description === 'string') {
                    descriptionInput.value = savedState.description;
                }

                if (skuInput && typeof savedState.sku === 'string') {
                    skuInput.value = savedState.sku;
                }

                if (productTypeSelect && typeof savedState.product_type === 'string') {
                    productTypeSelect.value = savedState.product_type || 'physical';
                }

                if (customProductTypeInput && typeof savedState.custom_product_type === 'string') {
                    customProductTypeInput.value = savedState.custom_product_type;
                }

                if (bulkPriceInput && typeof savedState.base_price === 'string') {
                    bulkPriceInput.value = savedState.base_price;
                    defaultPrice = savedState.base_price;
                }

                if (bulkStockInput && typeof savedState.default_stock === 'string') {
                    bulkStockInput.value = savedState.default_stock;
                    defaultStock = savedState.default_stock;
                }

                if (Array.isArray(savedState.variation_types)) {
                    variationTypes = savedState.variation_types.map((variationType) => ({
                        name: variationType.name ?? '',
                        type: variationType.type ?? 'select',
                        options: [...(variationType.options || [])],
                    }));
                }

                if (basePriceHiddenInput) {
                    basePriceHiddenInput.value = bulkPriceInput ? bulkPriceInput.value : defaultPrice;
                }

                if (defaultStockHiddenInput) {
                    defaultStockHiddenInput.value = bulkStockInput ? bulkStockInput.value : defaultStock;
                }

                if (Array.isArray(savedState.rows)) {
                    rows = savedState.rows.map((row) => ({
                        option_map: { ...(row.option_map || {}) },
                        sku: row.sku ?? '',
                        price: row.price ?? '',
                        stock: row.stock ?? '',
                        stock_alert: row.stock_alert ?? defaultStockAlert,
                    }));
                }

                syncProductTypeState();
            };

            const clearSavedStep2State = () => {
                sessionStorage.removeItem(step2StorageKey);
            };

            const buildOptionLabel = (rowData) => {
                if (!variationTypes.length) {
                    return 'Default Variant';
                }

                const parts = variationTypes.map((variation, variationIndex) => {
                    const selectedIndex = rowData.option_map?.[variationIndex];
                    if (selectedIndex === undefined || selectedIndex === null || selectedIndex === '') {
                        return '';
                    }
                    return variation.options?.[selectedIndex] ?? variation.name;
                }).filter(Boolean);

                return parts.length ? parts.join(' / ') : 'Default Variant';
            };

            const syncVariationOptionTextarea = () => {
                if (variationOptionsTextarea) {
                    variationOptionsTextarea.value = variationOptionTags.join(', ');
                }
            };

            const renderVariationOptionTags = () => {
                if (!variationOptionChips) {
                    return;
                }

                variationOptionChips.innerHTML = variationOptionTags.map((tag, index) => `
                    <span class="inline-flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-1.5 text-sm font-medium text-[#0F172A]">
                        ${escapeHtml(tag)}
                        <button type="button" class="remove-variation-tag leading-none text-[#94A3B8] hover:text-[#B42318]" data-index="${index}">&times;</button>
                    </span>
                `).join('');

                variationOptionChips.querySelectorAll('.remove-variation-tag').forEach((button) => {
                    button.addEventListener('click', () => {
                        variationOptionTags = variationOptionTags.filter((_, index) => index !== Number(button.dataset.index));
                        syncVariationOptionTextarea();
                        renderVariationOptionTags();
                    });
                });
            };

            const addVariationOptionTags = (rawValue) => {
                const nextTags = String(rawValue || '').split(',').map((value) => value.trim()).filter(Boolean);
                if (!nextTags.length) {
                    return;
                }

                variationOptionTags = [...variationOptionTags, ...nextTags];
                syncVariationOptionTextarea();
                renderVariationOptionTags();
                if (variationOptionInput) {
                    variationOptionInput.value = '';
                }
            };

            const openVariationDialog = (variationIndex = null) => {
                editingVariationIndex = variationIndex;
                const variation = variationIndex === null ? null : variationTypes[variationIndex];
                if (variationNameInput) {
                    variationNameInput.value = variation?.name || '';
                }
                variationOptionTags = [...(variation?.options || [])];
                syncVariationOptionTextarea();
                renderVariationOptionTags();
                if (variationModalTitle) {
                    variationModalTitle.textContent = variation ? 'Edit Variation Type' : 'Add Variation Type';
                }
                if (submitVariationModalLabel) {
                    submitVariationModalLabel.textContent = variation ? 'Update Variation' : 'Add Variation';
                }
                showVariationModal();
                variationNameInput?.focus();
            };

            const closeVariationDialog = () => {
                editingVariationIndex = null;
                if (variationNameInput) {
                    variationNameInput.value = '';
                }
                variationOptionTags = [];
                syncVariationOptionTextarea();
                renderVariationOptionTags();
                if (variationModalTitle) {
                    variationModalTitle.textContent = 'Add Variation Type';
                }
                if (submitVariationModalLabel) {
                    submitVariationModalLabel.textContent = 'Add Variation';
                }
                hideVariationModal();
            };

            const renderVariationTypeCards = () => {
                if (!variationTypesList || !noVariationState) {
                    return;
                }

                if (!variationTypes.length) {
                    variationTypesList.classList.add('hidden');
                    noVariationState.classList.remove('hidden');
                    return;
                }

                noVariationState.classList.add('hidden');
                variationTypesList.classList.remove('hidden');
                variationTypesList.innerHTML = variationTypes.map((variationType, variationIndex) => {
                    const chips = (variationType.options || []).map((option, optionIndex) => (
                        `<span class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-medium inline-flex items-center gap-2">
                            ${escapeHtml(option)}
                            <button
                                type="button"
                                class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none"
                                data-variation-index="${variationIndex}"
                                data-option-index="${optionIndex}"
                                aria-label="Remove option ${escapeHtml(option)}"
                            >×</button>
                        </span>`
                    )).join('');

                    return `
                        <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                            <div class="flex items-center justify-between mb-3 gap-3">
                                <div>
                                    <span class="text-base text-[#0F172A] font-poppins">Variation ${variationIndex + 1}: ${escapeHtml(variationType.name || 'Variation')}</span>
                                    <div class="mt-1 text-xs text-[#94A3B8] uppercase">${escapeHtml(variationType.type || 'select')}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="edit-variation-type text-xs font-semibold text-[#0052CC]" data-variation-index="${variationIndex}">Edit</button>
                                    <button type="button" class="remove-variation-type text-xs font-semibold text-[#B42318]" data-variation-index="${variationIndex}">Delete</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">${chips}</div>
                        </div>
                    `;
                }).join('');

                variationTypesList.querySelectorAll('.edit-variation-type').forEach((button) => {
                    button.addEventListener('click', () => {
                        const variationIndex = Number(button.dataset.variationIndex);
                        if (!Number.isInteger(variationIndex) || !variationTypes[variationIndex]) {
                            return;
                        }

                        saveStep2StateToSession();
                        openVariationDialog(variationIndex);
                    });
                });

                variationTypesList.querySelectorAll('.remove-variation-type').forEach((button) => {
                    button.addEventListener('click', () => {
                        const variationIndex = Number(button.dataset.variationIndex);
                        if (!Number.isInteger(variationIndex) || !variationTypes[variationIndex]) {
                            return;
                        }

                        variationTypes.splice(variationIndex, 1);
                        renderVariationHiddenInputs();
                        renderVariationTypeCards();
                        rows = buildRowsFromVariationTypes(rows);
                        renderRows(rows);
                        saveStep2StateToSession();
                    });
                });

                variationTypesList.querySelectorAll('.remove-variation-option').forEach((button) => {
                    button.addEventListener('click', () => {
                        const variationIndex = Number(button.dataset.variationIndex);
                        const optionIndex = Number(button.dataset.optionIndex);

                        if (!Number.isInteger(variationIndex) || !Number.isInteger(optionIndex) || !variationTypes[variationIndex]) {
                            return;
                        }

                        variationTypes[variationIndex].options.splice(optionIndex, 1);
                        if ((variationTypes[variationIndex].options || []).length === 0) {
                            variationTypes.splice(variationIndex, 1);
                        }
                        renderVariationHiddenInputs();
                        renderVariationTypeCards();
                        rows = buildRowsFromVariationTypes(rows);
                        renderRows(rows);
                        saveStep2StateToSession();
                    });
                });
            };

            const renderRows = (rows) => {
                rowsContainer.innerHTML = '';

                rows.forEach((rowData, rowIndex) => {
                    const card = document.createElement('div');
                    card.className = 'border border-[#E2E8F0] rounded-xl p-4 bg-[#F8FAFC]';

                    if (variationTypes.length) {
                        const optionsWrap = document.createElement('div');
                        optionsWrap.className = 'flex flex-wrap gap-2 mb-3';
                        Object.entries(rowData.option_map || {}).forEach(([variationIndex, optionIndex]) => {
                            const variation = variationTypes[Number(variationIndex)];
                            const optionValue = variation?.options?.[Number(optionIndex)] ?? '';

                            const badge = document.createElement('span');
                            badge.className = 'inline-flex items-center rounded-lg border border-[#DDE7F3] bg-white px-3 py-1.5 text-sm font-medium text-[#0F172A]';
                            badge.textContent = `${variation?.name || 'Variation'}: ${optionValue}`;
                            optionsWrap.appendChild(badge);

                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = `variants[${rowIndex}][option_map][${variationIndex}]`;
                            hiddenInput.value = String(optionIndex);
                            optionsWrap.appendChild(hiddenInput);
                        });
                        card.appendChild(optionsWrap);
                    }

                    const inputsWrap = document.createElement('div');
                    inputsWrap.className = 'grid grid-cols-1 md:grid-cols-4 gap-3';

                    const fields = [
                        { key: 'sku', label: 'SKU (optional)', type: 'text', value: rowData.sku ?? '' },
                        { key: 'price', label: 'Price', type: 'number', step: '0.01', min: '0', value: rowData.price ?? defaultPrice },
                        { key: 'stock', label: 'Stock', type: 'number', step: '1', min: '0', value: rowData.stock ?? defaultStock },
                        { key: 'stock_alert', label: 'Stock Alert', type: 'number', step: '1', min: '0', value: rowData.stock_alert ?? defaultStockAlert },
                    ];

                    fields.forEach((field) => {
                        const block = document.createElement('div');

                        const label = document.createElement('label');
                        label.className = 'block text-xs font-semibold text-[#64748B] mb-1';
                        label.textContent = field.label;

                        const input = document.createElement('input');
                        input.type = field.type;
                        input.name = `variants[${rowIndex}][${field.key}]`;
                        input.value = field.value;
                        if (field.step) input.step = field.step;
                        if (field.min) input.min = field.min;
                        input.className = 'w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20';

                        block.appendChild(label);
                        block.appendChild(input);
                        inputsWrap.appendChild(block);
                    });

                    card.appendChild(inputsWrap);
                    rowsContainer.appendChild(card);
                });

                renderPreview(rows);
            };

            const renderPreview = (rows) => {
                if (!previewTableBody || !previewCount) {
                    return;
                }

                let previewRows = rows.map((rowData) => ({
                    label: buildOptionLabel(rowData),
                    sku: rowData.sku || '',
                    price: rowData.price ?? defaultPrice,
                    stock: rowData.stock ?? defaultStock,
                }));

                if (!previewRows.length) {
                    previewRows = [{
                        label: 'No variants added yet',
                        sku: '-',
                        price: '-',
                        stock: '-',
                    }];
                }

                previewCount.textContent = `${previewRows.length} variant row(s)`;
                previewTableBody.innerHTML = previewRows.map((row) => `
                    <tr>
                        <td class="py-4 px-2 font-medium text-[#0F172A]">${row.label}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.sku || '-'}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.price}</td>
                        <td class="py-4 px-2 text-[#475569]">${row.stock}</td>
                    </tr>
                `).join('');
            };
            // rows restore on page reload with sessionStorage
            let rows = existingVariants.length ? existingVariants : [];

            restoreStep2StateFromSession();
            if (!rows.length && variationTypes.length) {
                rows = buildRowsFromVariationTypes(rows);
            }

            renderRows(rows);
            renderVariationHiddenInputs();
            renderVariationTypeCards();
            syncProductTypeState();
            renderSelectedImages();

            if (applyBulkButton) {
                applyBulkButton.addEventListener('click', () => {
                    const bulkPrice = bulkPriceInput ? bulkPriceInput.value : '';
                    const bulkStock = bulkStockInput ? bulkStockInput.value : '';

                    rows.forEach((row) => {
                        if (bulkPrice !== '') {
                            row.price = bulkPrice;
                        }
                        if (bulkStock !== '') {
                            row.stock = bulkStock;
                        }
                    });

                    if (bulkPrice !== '') {
                        defaultPrice = bulkPrice;
                        if (basePriceHiddenInput) {
                            basePriceHiddenInput.value = bulkPrice;
                        }
                    }
                    if (bulkStock !== '') {
                        defaultStock = bulkStock;
                        if (defaultStockHiddenInput) {
                            defaultStockHiddenInput.value = bulkStock;
                        }
                    }

                    renderRows(rows);
                    saveStep2StateToSession();
                });
            }

            if (bulkPriceInput && basePriceHiddenInput) {
                bulkPriceInput.addEventListener('input', () => {
                    basePriceHiddenInput.value = bulkPriceInput.value !== '' ? bulkPriceInput.value : defaultPrice;
                });
            }

            if (bulkStockInput && defaultStockHiddenInput) {
                bulkStockInput.addEventListener('input', () => {
                    defaultStockHiddenInput.value = bulkStockInput.value !== '' ? bulkStockInput.value : defaultStock;
                });
            }

            productTypeSelect?.addEventListener('change', () => {
                syncProductTypeState();
                saveStep2StateToSession();
            });

            customProductTypeInput?.addEventListener('input', () => {
                syncProductTypeState();
                saveStep2StateToSession();
            });

            productImageInput?.addEventListener('change', () => {
                const incomingFiles = Array.from(productImageInput.files || []);
                if (incomingFiles.length) {
                    selectedStep2Images = [...selectedStep2Images, ...incomingFiles];
                    syncSelectedFiles(productImageInput, selectedStep2Images);
                }
                renderSelectedImages();
            });

            rowsContainer.addEventListener('input', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                const match = target.name.match(/^variants\[(\d+)]\[(sku|price|stock|stock_alert)]$/);
                if (!match) {
                    return;
                }

                const rowIndex = Number(match[1]);
                const key = match[2];

                if (!rows[rowIndex]) {
                    return;
                }

                rows[rowIndex][key] = target.value;
                renderPreview(rows);
                saveStep2StateToSession();
            });

            const showVariationModal = () => {
                if (!variationModal) {
                    return;
                }
                variationModal.classList.remove('hidden');
                variationModal.classList.add('flex');
            };

            const hideVariationModal = () => {
                if (!variationModal) {
                    return;
                }
                variationModal.classList.add('hidden');
                variationModal.classList.remove('flex');
            };

            if (openVariationModal) {
                openVariationModal.addEventListener('click', () => {
                    saveStep2StateToSession();
                    openVariationDialog();
                });
            }

            variationOptionInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    addVariationOptionTags(variationOptionInput.value);
                }
            });

            variationOptionInput?.addEventListener('blur', () => {
                if (variationOptionInput.value.trim()) {
                    addVariationOptionTags(variationOptionInput.value);
                }
            });

            [closeVariationModalButton, cancelVariationModalButton].forEach((button) => {
                button?.addEventListener('click', closeVariationDialog);
            });

            submitVariationModalButton?.addEventListener('click', () => {
                addVariationOptionTags(variationOptionInput?.value || '');
                const variationName = variationNameInput?.value.trim() || '';
                const options = (variationOptionsTextarea?.value || '').split(',').map((option) => option.trim()).filter(Boolean);

                if (!variationName || !options.length) {
                    alert('Please add a variation name and at least one option.');
                    return;
                }

                const variationPayload = {
                    name: variationName,
                    type: 'select',
                    options,
                };

                if (editingVariationIndex === null) {
                    variationTypes.push(variationPayload);
                } else {
                    variationTypes[editingVariationIndex] = variationPayload;
                }

                rows = buildRowsFromVariationTypes(rows);
                renderVariationHiddenInputs();
                renderVariationTypeCards();
                renderRows(rows);
                saveStep2StateToSession();
                closeVariationDialog();
            });

            [nameInput, descriptionInput, skuInput, bulkPriceInput, bulkStockInput].forEach((field) => {
                if (!field) {
                    return;
                }

                field.addEventListener('input', () => {
                    saveStep2StateToSession();
                });
            });
            if (variationModal) {
                variationModal.addEventListener('click', (event) => {
                    if (event.target === variationModal) {
                        closeVariationDialog();
                    }
                });
            }
            if (productForm) {
                productForm.addEventListener('submit', (event) => {
                    syncProductTypeState();
                    if (productTypeSelect?.value === 'custom' && !customProductTypeInput?.value.trim()) {
                        event.preventDefault();
                        customProductTypeInput?.focus();
                        return;
                    }
                    clearSavedStep2State();
                });
            }
            renderVariationOptionTags();
        })();
    </script>
</body>

</html>
