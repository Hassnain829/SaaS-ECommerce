@extends('layouts.user.user-sidebar')

@section('title', 'Add Product to ' . $store->name . ' | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 bg-white border-b border-[#E2E8F0] px-4 lg:px-6 py-3 flex items-center justify-between gap-3 shrink-0">
    <!-- Mobile sidebar toggle -->
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <!-- Title -->
    <div class="flex-1">
        <h1 class="text-lg font-bold text-[#0B1C30]">Add Product to {{ $store->name }}</h1>
    </div>

    <!-- Right icons -->
    <div class="flex items-center gap-3 shrink-0">
        <button form="product-form" type="submit" class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg hover:bg-[#0047B3] transition-colors">
            Save & Add
        </button>

        <!-- Profile avatar placeholder -->
        <div class="w-9 h-9 rounded-full bg-[#E2E8F0] border border-[#CBD5E1] overflow-hidden shrink-0">
            <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
                <circle cx="18" cy="13" r="6" fill="#94A3B8"/>
                <path d="M28 28C28 24 24 22 18 22C12 22 8 24 8 28" fill="#94A3B8"/>
            </svg>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="px-6 md:px-10 py-8 max-w-[1024px] w-full mx-auto">
    <!-- Back button -->
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="flex items-center gap-2 text-[#64748B] hover:text-[#0052CC] transition-colors">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M13 10L6 3M13 10L6 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="font-inter font-medium">Back to Products</span>
        </a>
    </div>

    <!-- Success/Error Messages -->
    @if (session('success'))
        <div class="mb-6 rounded-lg border border-[#CBE8D1] bg-[#ECFDF3] px-4 py-3 text-sm text-[#05603A]">
            {{ session('success') }}
        </div>
    @endif

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
        $formData = old();
        if (empty($formData)) {
            $formData = $draft ?? [];
        }

        $variationTypes = $formData['variation_types'] ?? ($draft['variation_types'] ?? []);
        $customVariants = $formData['variants'] ?? ($draft['variants'] ?? []);

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
                    'price' => $variantRow['price'] ?? ($formData['base_price'] ?? '0'),
                    'stock' => $variantRow['stock'] ?? ($formData['default_stock'] ?? 50),
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

    <form id="product-form" action="{{ route('store.add-product.store', ['storeId' => $store->id]) }}" method="POST">
        @csrf
        <input id="bulk-price-hidden" type="hidden" name="bulk_price" value="{{ $formData['bulk_price'] ?? '' }}">
        <input id="bulk-stock-hidden" type="hidden" name="bulk_stock" value="{{ $formData['bulk_stock'] ?? '' }}">
        <input type="hidden" name="stock_alert" value="{{ $formData['stock_alert'] ?? 5 }}">
        <input type="hidden" name="product_type" value="{{ $formData['product_type'] ?? 'physical' }}">

        <div id="variation-hidden-inputs">
            @foreach ($variationTypes as $variationIndex => $variationType)
                <input type="hidden" name="variation_types[{{ $variationIndex }}][name]" value="{{ $variationType['name'] ?? '' }}">
                <input type="hidden" name="variation_types[{{ $variationIndex }}][type]" value="{{ $variationType['type'] ?? 'select' }}">
                @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                    <input type="hidden" name="variation_types[{{ $variationIndex }}][options][{{ $optionIndex }}]" value="{{ $option }}">
                @endforeach
            @endforeach
        </div>

        <!-- Basic Information Section -->
        <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
            <div class="flex items-center gap-2 mb-6">
                <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Basic Information</h2>
            </div>

            <div class="grid grid-cols-1 gap-6 mb-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product Name</label>
                    <input id="name" name="name" type="text" placeholder="e.g. Premium Cotton T-Shirt"
                        value="{{ $formData['name'] ?? '' }}"
                        class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Description</label>
                <textarea id="description" name="description" rows="3"
                    placeholder="Describe your product's key features and benefits..."
                    class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ $formData['description'] ?? '' }}</textarea>
            </div>

            <div class="mt-4">
                <label for="sku" class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Base SKU (optional)</label>
                <input id="sku" name="sku" type="text" value="{{ $formData['sku'] ?? '' }}"
                    class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
            </div>
        </div>

        <!-- Product Variations Section -->
        <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h2>
                <button id="openVariationModal" type="button"
                    class="flex items-center gap-2 text-[#0052CC] text-sm font-inter font-medium">Add Variation
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
                            <span class="text-base text-[#0F172A] font-poppins">Variation {{ $index + 1 }}: {{ $variationType['name'] ?? 'Variation' }}</span>
                            <span class="text-xs text-[#94A3B8] uppercase">{{ $variationType['type'] ?? 'select' }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach (($variationType['options'] ?? []) as $optionIndex => $option)
                                <span class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-inter font-medium inline-flex items-center gap-2">
                                    {{ $option }}
                                    <button type="button" class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none"
                                        data-variation-index="{{ $index }}" data-option-index="{{ $optionIndex }}" aria-label="Remove option">×</button>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Variants Section -->
        <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants</h2>
                <button id="add-variant-row" type="button"
                    class="px-3 py-2 rounded-lg border border-[#0052CC] text-[#0052CC] text-sm font-semibold {{ empty($variationTypes) ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ empty($variationTypes) ? 'disabled' : '' }}>Add Variant</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4 p-4 border border-[#E2E8F0] rounded-xl bg-[#F8FAFC]">
                <div class="md:col-span-2">
                    <p class="text-sm font-semibold text-[#0F172A]">Bulk Set Price & Stock</p>
                    <p class="text-xs text-[#64748B]">Apply one value to all variant rows.</p>
                </div>
                <div>
                    <label for="bulk-price" class="block text-xs font-semibold text-[#64748B] mb-1">Price</label>
                    <input id="bulk-price" type="number" min="0" step="0.01"
                        value="{{ $formData['base_price'] ?? '' }}"
                        class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                </div>
                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <label for="bulk-stock" class="block text-xs font-semibold text-[#64748B] mb-1">Stock</label>
                        <input id="bulk-stock" type="number" min="0" step="1"
                            value="{{ $formData['default_stock'] ?? '' }}"
                            class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                    <button id="apply-bulk-values" type="button" class="px-3 py-2 rounded-lg bg-[#0052CC] text-white text-sm font-semibold">Apply</button>
                </div>
            </div>

            <div id="variant-rows" class="space-y-4"></div>

            <p class="mt-3 text-xs text-[#64748B]">Each row is one variant. Select one option from each variation type.</p>
        </div>

        <!-- Variants Matrix Preview Section -->
        <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
            <div class="flex flex-wrap justify-between items-center mb-6">
                <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h2>
                <span id="preview-count" class="text-sm text-[#94A3B8]">{{ count($previewRows) }} variant row(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-[#F1F5F9]">
                        <tr>
                            <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Variant</th>
                            <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">SKU</th>
                            <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Price ($)</th>
                            <th class="text-left py-3 px-2 text-xs font-bold uppercase text-[#94A3B8]">Stock</th>
                        </tr>
                    </thead>
                    <tbody id="preview-table-body" class="divide-y divide-[#F1F5F9]">
                        @foreach ($previewRows as $row)
                            <tr>
                                <td class="py-4 px-2 font-inter font-medium text-[#0F172A]">{{ $row['label'] }}</td>
                                <td class="py-4 px-2 text-[#475569]">{{ $row['sku'] ?: 'Auto-generated' }}</td>
                                <td class="py-4 px-2 text-[#475569]">{{ $row['price'] }}</td>
                                <td class="py-4 px-2 text-[#475569]">{{ $row['stock'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-between items-center pt-6 border-t border-[#E2E8F0]">
            <a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="text-[#475569] font-bold">Back to Products</a>
            <div class="flex items-center gap-4">
                <a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="text-[#475569] font-bold px-6 py-2">Cancel</a>
                <button type="submit" class="bg-[#0052CC] text-white font-bold px-8 py-3 rounded-lg shadow-lg shadow-[#0052CC]/20">Save Product</button>
            </div>
        </div>
    </form>
</div>

<!-- Variation Type Modal (Embedded) -->
<div id="variationModal" class="fixed inset-0 bg-[#0F172A]/60 backdrop-blur-[2px] flex items-center justify-center p-4 z-50 hidden">
    <div class="w-full max-w-[512px] bg-white rounded-xl shadow-2xl border border-[#E2E8F0] overflow-hidden">
        <!-- Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-[#F1F5F9]">
            <div>
                <h3 class="text-lg font-semibold text-[#0F172A]">Add Variation Type</h3>
                <p class="text-xs text-[#64748B] mt-0.5">Define how customers will differentiate your items</p>
            </div>
            <button type="button" id="closeVariationModal" class="text-[#94A3B8] hover:text-[#64748B]">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M1.4 14L0 12.6L5.6 7L0 1.4L1.4 0L7 5.6L12.6 0L14 1.4L8.4 7L14 12.6L12.6 14L7 8.4L1.4 14Z" fill="currentColor"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <form id="variationForm" class="space-y-6 p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-[#334155] mb-2">Input Type</label>
                    <div class="relative">
                        <select id="variationType" class="w-full appearance-none border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            <option value="select">Select</option>
                            <option value="radio">Radio</option>
                            <option value="checkbox">Checkbox</option>
                        </select>
                        <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#6B7280]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#334155] mb-2">Variation Name</label>
                    <input id="variationName" type="text" placeholder="e.g., Size" class="w-full border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-[#334155] mb-2">Option Values</label>
                <textarea id="variationOptions" rows="3" placeholder="S, M, L, XL" class="w-full border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20"></textarea>
            </div>

            <!-- Suggestions -->
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-[#94A3B8]">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                        <path d="M12 5.33333L11.1667 3.5L9.33333 2.66667L11.1667 1.83333L12 0L12.8333 1.83333L14.6667 2.66667L12.8333 3.5L12 5.33333ZM12 14.6667L11.1667 12.8333L9.33333 12L11.1667 11.1667L12 9.33333L12.8333 11.1667L14.6667 12L12.8333 12.8333L12 14.6667ZM5.33333 12.6667L3.66667 9L0 7.33333L3.66667 5.66667L5.33333 2L7 5.66667L10.6667 7.33333L7 9L5.33333 12.6667ZM5.33333 9.43333L6 8L7.43333 7.33333L6 6.66667L5.33333 5.23333L4.66667 6.66667L3.23333 7.33333L4.66667 8L5.33333 9.43333Z" fill="#94A3B8"/>
                    </svg>
                    <span class="text-xs font-bold uppercase tracking-[0.6px]">Type-Specific Suggestions</span>
                </div>

                <div class="space-y-3">
                    <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                        <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Fashion</div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Size</button>
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Color</button>
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Material</button>
                        </div>
                    </div>

                    <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                        <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Digital</div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">License Type</button>
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Resolution</button>
                        </div>
                    </div>

                    <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                        <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Food & Beverage</div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Add-ons</button>
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Dietary</button>
                        </div>
                    </div>

                    <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                        <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Subscriptions</div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Tier</button>
                            <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm hover:border-[#0052CC] transition">Billing Interval</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="bg-[#F8FAFC] border-t border-[#F1F5F9] px-6 py-4 flex justify-end items-center gap-3">
            <button type="button" id="cancelVariationModal" class="px-4 py-2 text-sm font-semibold text-[#475569] hover:text-[#1E293B]">Cancel</button>
            <button type="button" id="submitVariationModal" class="bg-[#0052CC] text-white text-sm font-bold px-5 py-2 rounded-lg shadow-md shadow-[#0052CC]/20 flex items-center gap-2 hover:bg-[#0047B3] transition">
                <span>Add Variation</span>
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
        let rows = [];
        let defaultPrice = @json((string) ($formData['base_price'] ?? ''));
        let defaultStock = @json((string) ($formData['default_stock'] ?? ''));
        const defaultStockAlert = @json((int) ($formData['stock_alert'] ?? 5));

        const productForm = document.getElementById('product-form');
        const bulkPriceInput = document.getElementById('bulk-price');
        const bulkStockInput = document.getElementById('bulk-stock');
        const applyBulkButton = document.getElementById('apply-bulk-values');
        const addRowButton = document.getElementById('add-variant-row');
        const rowsContainer = document.getElementById('variant-rows');
        const previewCount = document.getElementById('preview-count');
        const previewTableBody = document.getElementById('preview-table-body');
        const variationTypesList = document.getElementById('variation-types-list');
        const noVariationState = document.getElementById('no-variation-state');
        const variationHiddenInputs = document.getElementById('variation-hidden-inputs');
        const bulkPriceHiddenInput = document.getElementById('bulk-price-hidden');
        const bulkStockHiddenInput = document.getElementById('bulk-stock-hidden');
        const openVariationModal = document.getElementById('openVariationModal');

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        // Render hidden inputs for variation types
        const renderVariationHiddenInputs = () => {
            if (!variationHiddenInputs) return;

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

        // Render variation type cards
        const renderVariationTypeCards = () => {
            if (!variationTypesList || !noVariationState) return;

            if (!variationTypes.length) {
                variationTypesList.classList.add('hidden');
                noVariationState.classList.remove('hidden');
                if (addRowButton) {
                    addRowButton.disabled = true;
                    addRowButton.classList.add('opacity-50', 'cursor-not-allowed');
                }
                return;
            }

            noVariationState.classList.add('hidden');
            variationTypesList.classList.remove('hidden');
            if (addRowButton) {
                addRowButton.disabled = false;
                addRowButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            variationTypesList.innerHTML = variationTypes.map((variationType, variationIndex) => {
                const chips = (variationType.options || []).map((option, optionIndex) => (
                    `<span class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 text-sm font-medium inline-flex items-center gap-2">
                        ${escapeHtml(option)}
                        <button type="button" class="remove-variation-option text-[#94A3B8] hover:text-[#B42318] leading-none" 
                            data-variation-index="${variationIndex}" data-option-index="${optionIndex}" aria-label="Remove option">×</button>
                    </span>`
                )).join('');

                return `
                    <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-base text-[#0F172A] font-medium">Variation ${variationIndex + 1}: ${escapeHtml(variationType.name || 'Variation')}</span>
                            <span class="text-xs text-[#94A3B8] uppercase">${escapeHtml(variationType.type || 'select')}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">${chips}</div>
                    </div>
                `;
            }).join('');

            attachVariationOptionRemoveListeners();
        };

        // Render variant rows
        const renderVariantRows = () => {
            if (!rowsContainer) return;

            if (!rows.length) {
                rowsContainer.innerHTML = '';
                return;
            }

            rowsContainer.innerHTML = rows.map((row, rowIndex) => {
                const selects = variationTypes.map((variationType, variationIndex) => `
                    <select name="variants[${rowIndex}][option_map][${variationIndex}]" class="variant-option-select w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        <option value="">Select ${escapeHtml(variationType.name)}</option>
                        ${(variationType.options || []).map((option, optionIndex) => `
                            <option value="${optionIndex}" ${row.option_map?.[variationIndex] == optionIndex ? 'selected' : ''}>
                                ${escapeHtml(option)}
                            </option>
                        `).join('')}
                    </select>
                `).join('');

                return `
                    <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5 space-y-4">
                        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                            ${selects}
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-[#64748B] mb-1">SKU (optional)</label>
                                <input type="text" name="variants[${rowIndex}][sku]" value="${row.sku || ''}" 
                                    class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#64748B] mb-1">Price ($)</label>
                                <input type="number" min="0" step="0.01" name="variants[${rowIndex}][price]" value="${row.price || ''}"
                                    class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#64748B] mb-1">Stock</label>
                                <input type="number" min="0" step="1" name="variants[${rowIndex}][stock]" value="${row.stock || ''}"
                                    class="w-full border border-[#E2E8F0] rounded-lg px-3 py-2 text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" class="remove-variant-row text-sm font-medium text-[#B42318] hover:text-[#8B1A1A]" data-row-index="${rowIndex}">
                                Remove Variant
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            attachVariantRowListeners();
            updatePreview();
        };

        // Update preview table
        const updatePreview = () => {
            if (!previewTableBody || !previewCount) return;

            const previewRows = rows.map((row) => {
                const labelParts = variationTypes.map((variation, variationIndex) => {
                    const selectedIndex = row.option_map?.[variationIndex];
                    if (selectedIndex !== undefined && selectedIndex !== null && selectedIndex !== '') {
                        return variation.options?.[selectedIndex] || '';
                    }
                    return '';
                }).filter(p => p);

                return {
                    label: labelParts.length ? labelParts.join(' / ') : 'Default Variant',
                    sku: row.sku || 'Auto-generated',
                    price: row.price || defaultPrice,
                    stock: row.stock || defaultStock,
                };
            });

            previewTableBody.innerHTML = (previewRows.length ? previewRows : [{
                label: 'No variants added yet',
                sku: '-',
                price: '-',
                stock: '-',
            }]).map(row => `
                <tr>
                    <td class="py-4 px-2 font-medium text-[#0F172A]">${escapeHtml(row.label)}</td>
                    <td class="py-4 px-2 text-[#475569]">${escapeHtml(row.sku)}</td>
                    <td class="py-4 px-2 text-[#475569]">${escapeHtml(row.price)}</td>
                    <td class="py-4 px-2 text-[#475569]">${escapeHtml(row.stock)}</td>
                </tr>
            `).join('');

            previewCount.textContent = `${previewRows.length} variant row(s)`;
        };

        // Attach event listeners
        const attachVariantRowListeners = () => {
            document.querySelectorAll('.remove-variant-row').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const rowIndex = parseInt(btn.dataset.rowIndex);
                    rows.splice(rowIndex, 1);
                    renderVariantRows();
                });
            });

            document.querySelectorAll('.variant-option-select').forEach(select => {
                select.addEventListener('change', updatePreview);
            });

            document.querySelectorAll('input[name^="variants"]').forEach(input => {
                input.addEventListener('change', updatePreview);
            });
        };

        const attachVariationOptionRemoveListeners = () => {
            document.querySelectorAll('.remove-variation-option').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const variationIndex = parseInt(btn.dataset.variationIndex);
                    const optionIndex = parseInt(btn.dataset.optionIndex);
                    
                    if (!Number.isInteger(variationIndex) || !Number.isInteger(optionIndex) || !variationTypes[variationIndex]) {
                        return;
                    }

                    variationTypes[variationIndex].options.splice(optionIndex, 1);
                    
                    // If variation has no options left, remove the entire variation
                    if ((variationTypes[variationIndex].options || []).length === 0) {
                        variationTypes.splice(variationIndex, 1);
                    }
                    
                    // Clear all variants when variations change
                    if (!variationTypes.length) {
                        rows = [];
                    } else {
                        rows = rows.map((row) => ({
                            ...row,
                            option_map: Object.fromEntries(
                                Object.entries(row.option_map || {}).filter(([key]) => Number(key) < variationTypes.length)
                            ),
                        }));
                    }
                    
                    renderVariationHiddenInputs();
                    renderVariationTypeCards();
                    renderVariantRows();
                    updatePreview();
                });
            });
        };

        // Initialize hidden inputs with default values
        if (bulkPriceHiddenInput && !bulkPriceHiddenInput.value) {
            bulkPriceHiddenInput.value = defaultPrice;
        }
        if (bulkStockHiddenInput && !bulkStockHiddenInput.value) {
            bulkStockHiddenInput.value = defaultStock;
        }

        // Event listeners
        if (addRowButton) {
            addRowButton.addEventListener('click', (e) => {
                e.preventDefault();
                rows.push({ option_map: {}, sku: '', price: defaultPrice, stock: defaultStock, stock_alert: defaultStockAlert });
                renderVariantRows();
            });
        }

        if (applyBulkButton) {
            applyBulkButton.addEventListener('click', (e) => {
                e.preventDefault();
                const price = bulkPriceInput?.value || defaultPrice;
                const stock = bulkStockInput?.value || defaultStock;
                rows.forEach(row => {
                    row.price = price;
                    row.stock = stock;
                });
                renderVariantRows();
            });
        }

        if (bulkPriceInput) {
            bulkPriceInput.addEventListener('input', () => {
                defaultPrice = bulkPriceInput.value;
                if (bulkPriceHiddenInput) bulkPriceHiddenInput.value = defaultPrice;
                updatePreview();
            });
            
            bulkPriceInput.addEventListener('change', () => {
                defaultPrice = bulkPriceInput.value;
                if (bulkPriceHiddenInput) bulkPriceHiddenInput.value = defaultPrice;
                updatePreview();
            });
        }

        if (bulkStockInput) {
            bulkStockInput.addEventListener('input', () => {
                defaultStock = bulkStockInput.value;
                if (bulkStockHiddenInput) bulkStockHiddenInput.value = defaultStock;
                updatePreview();
            });
            
            bulkStockInput.addEventListener('change', () => {
                defaultStock = bulkStockInput.value;
                if (bulkStockHiddenInput) bulkStockHiddenInput.value = defaultStock;
                updatePreview();
            });
        }

        // Sync hidden inputs before form submission
        if (productForm) {
            productForm.addEventListener('submit', (e) => {
                if (bulkPriceInput && bulkPriceHiddenInput) {
                    bulkPriceHiddenInput.value = bulkPriceInput.value || defaultPrice;
                }
                if (bulkStockInput && bulkStockHiddenInput) {
                    bulkStockHiddenInput.value = bulkStockInput.value || defaultStock;
                }
            });
        }

        // Handle variation type modal
        if (openVariationModal) {
            openVariationModal.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('variationModal').classList.remove('hidden');
                document.getElementById('variationName').focus();
            });
        }

        const closeModal = () => {
            document.getElementById('variationModal').classList.add('hidden');
            document.getElementById('variationName').value = '';
            document.getElementById('variationOptions').value = '';
            document.getElementById('variationType').value = 'select';
        };

        const submitModal = () => {
            const variationName = document.getElementById('variationName').value.trim();
            const variationType = document.getElementById('variationType').value;
            const optionsStr = document.getElementById('variationOptions').value.trim();

            if (!variationName) {
                alert('Please enter a variation name');
                return;
            }

            if (!optionsStr) {
                alert('Please enter at least one option');
                return;
            }

            const options = optionsStr.split(',').map(o => o.trim()).filter(o => o);
            if (!options.length) {
                alert('Please enter valid options separated by commas');
                return;
            }

            variationTypes.push({ name: variationName, type: variationType, options });
            rows = [];
            renderVariationTypeCards();
            renderVariationHiddenInputs();
            renderVariantRows();
            updatePreview();
            closeModal();
        };

        document.getElementById('closeVariationModal').addEventListener('click', closeModal);
        document.getElementById('cancelVariationModal').addEventListener('click', closeModal);
        document.getElementById('submitVariationModal').addEventListener('click', submitModal);

        // Close modal when clicking outside
        document.getElementById('variationModal').addEventListener('click', (e) => {
            if (e.target.id === 'variationModal') {
                closeModal();
            }
        });

        // Handle suggestion buttons
        document.querySelectorAll('.suggestion-btn').forEach((button) => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const value = button.textContent.trim();
                document.getElementById('variationName').value = value;
                if (!document.getElementById('variationOptions').value.trim()) {
                    document.getElementById('variationOptions').value = value;
                }
            });
        });

        // Initialize
        renderVariationTypeCards();
        renderVariationHiddenInputs();
        renderVariantRows();
        updatePreview();
    })();
</script>
@endsection
