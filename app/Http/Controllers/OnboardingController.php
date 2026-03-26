<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function step1(Request $request): View
    {
        if ($request->boolean('fresh') || $request->string('mode') === 'create') {
            $request->session()->forget([
                'onboarding_store_draft',
                'onboarding_store_id',
                'onboarding_last_store_id',
                'onboarding_product_draft',
                'onboarding_product_id',
                'onboarding_last_product_id',
            ]);
        }
        $store = $this->resolveOnboardingStore($request);
        $storeDraft = $request->session()->get('onboarding_store_draft', []);

        if (empty($storeDraft) && $store) {
            $storeDraft = $this->mapStoreToDraft($store);
            $request->session()->put('onboarding_store_draft', $storeDraft);
        }

        return view('user_view.onboarding-Step1-StoreDetails', [
            'storeDraft' => $storeDraft,
            'primaryMarkets' => ['Global Market', 'North America', 'Europe', 'Middle East', 'South Asia'],
            'currencies' => ['USD', 'EUR', 'GBP', 'PKR', 'AED'],
            'timezones' => ['UTC', 'Asia/Karachi', 'America/New_York', 'Europe/London', 'Asia/Dubai'],
            'categories' => ['physical', 'digital', 'service', 'subscription', 'virtual'],
        ]);
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'primary_market' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80', 'required_without:custom_category'],
            'business_models' => ['nullable', 'array'],
            'business_models.*' => ['string', 'max:80'],
            'custom_category' => ['nullable', 'string', 'max:80', 'required_without:category'],
            'store_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
            'mode' => ['nullable', 'string', 'in:create,edit'],
        ]);

        $normalizedCategory = $validated['category'] ?? null;
        if (!$normalizedCategory && !empty($validated['custom_category'])) {
            $normalizedCategory = $validated['custom_category'];
        }

        // Only look for existing store if in edit mode
        $existingStore = ($validated['mode'] === 'edit') ? $this->resolveSessionStore($request) : null;
        $logoPath = $existingStore?->logo;

        if ($request->hasFile('store_logo')) {
            $logoPath = $request->file('store_logo')->store('store-logos', 'public');
        }

        $storePayload = [
            'name' => $validated['name'],
            'logo' => $logoPath,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'category' => $normalizedCategory,
            'settings' => $this->mergeStoreSettings($existingStore?->settings, [
                'primary_market' => $validated['primary_market'],
                'business_models' => $validated['business_models'] ?? [],
                'custom_category' => $validated['custom_category'] ?? null,
                'onboarding_step' => 1,
            ]),
        ];

        if ($existingStore) {
            $existingStore->update($storePayload);
            $store = $existingStore->refresh();
        } else {
            $store = Store::create($storePayload + [
                'user_id' => $request->user()->id,
                'slug' => $this->uniqueSlug(Store::class, $validated['name']),
            ]);
        }

        $request->session()->put('onboarding_store_id', $store->id);
        $request->session()->put('onboarding_store_draft', Arr::only($validated, [
            'name',
            'primary_market',
            'address',
            'currency',
            'timezone',
            'category',
            'business_models',
            'custom_category',
        ]));

        if ($request->boolean('_open_create_store_modal')) {
            return redirect()
                ->route('store-management')
                ->with('success', "Store '{$store->name}' created successfully.")
                ->with('success_title', 'Store created')
                ->with('success_meta', 'Store management updated');
        }

        return redirect()
            ->route('onboarding-Step2-AddProductVariations')
            ->with('success', "Store '{$store->name}' saved. You're ready to add your first product.")
            ->with('success_title', 'Store draft ready')
            ->with('success_meta', 'Step 1 of 3 completed');
    }

    public function step2(Request $request): RedirectResponse|View
    {
        // Allow direct access to a specific store via store_id parameter for adding products
        $storeId = $request->query('store_id');
        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
            // Set session store ID for this flow
            $request->session()->put('onboarding_store_id', $store->id);
        } else {
            $store = $this->resolveOnboardingStore($request);
        }

        if (!$store) {
            return redirect()
                ->route('onboarding-StoreDetails-1')
                ->withErrors(['store' => 'Please create a store first.']);
        }

        if ($request->boolean('fresh')) {
            $request->session()->forget([
                'onboarding_product_draft',
                'onboarding_last_product_id',
                'onboarding_product_id',
            ]);
        }

        $draft = $request->session()->get('onboarding_product_draft', []);

        return view('user_view.onboarding-Step2-AddProductVariations', [
            'store' => $store,
            'draft' => $draft,
            'productTypes' => ['physical', 'digital', 'service', 'subscription', 'virtual'],
            'variationInputTypes' => ['select', 'radio', 'checkbox'],
        ]);
    }

    public function storeStep2(Request $request): RedirectResponse
    {
        $store = $this->resolveOnboardingStore($request);

        if (!$store) {
            return redirect()
                ->route('onboarding-StoreDetails-1')
                ->withErrors(['store' => 'Please create a store first.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', 'string', 'max:80'],
            'custom_product_type' => ['nullable', 'string', 'max:80', 'required_if:product_type,custom'],
            'product_images' => ['nullable', 'array', 'max:8'],
            'product_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'default_stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['required', 'integer', 'min:0'],
            'variation_types' => ['nullable', 'array'],
            'variation_types.*.name' => ['required', 'string', 'max:100'],
            'variation_types.*.type' => ['required', 'in:select,radio,checkbox'],
            'variation_types.*.options' => ['required', 'array', 'min:1'],
            'variation_types.*.options.*' => ['required', 'string', 'max:80'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_alert' => ['nullable', 'integer', 'min:0'],
            'variants.*.option_map' => ['nullable', 'array'],
            'mode' => ['nullable', 'string', 'in:create,edit'],
        ]);

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput();
        }

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            (int) $validated['default_stock'],
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput();
        }

        $validated['variants'] = $normalizedVariants['variants'];
        $request->session()->put('onboarding_product_draft', $validated);

        $imagePaths = [];
        foreach ($request->file('product_images', []) as $imageFile) {
            $imagePaths[] = $imageFile->store('product-images', 'public');
        }

        DB::transaction(function () use ($validated, $store, $request, $imagePaths): void {
            // Only look for existing product if in edit mode
            $product = ($validated['mode'] === 'edit') ? $this->resolveSessionProduct($request, $store) : null;
            $productPayload = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'status' => true,
                'meta' => [
                    'default_stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
                    'image_path' => $imagePaths[0] ?? null,
                    'image_paths' => $imagePaths,
                    'onboarding_created' => true,
                ],
            ];

            if ($product) {
                $product->update($productPayload);
                $product->variationTypes()->delete();
                $product->variants()->delete();
            } else {
                $product = Product::create($productPayload + [
                    'store_id' => $store->id,
                    'slug' => $this->uniqueProductSlug($store->id, $validated['name']),
                ]);
            }

            $variationOptionSets = [];
            $variationOptionMap = [];

            foreach (($validated['variation_types'] ?? []) as $variationIndex => $variationData) {
                $variationType = ProductVariationType::create([
                    'product_id' => $product->id,
                    'name' => $variationData['name'],
                    'type' => $variationData['type'],
                ]);

                $options = [];

                foreach ($variationData['options'] as $optionIndex => $optionValue) {
                    $option = ProductVariationOption::create([
                        'variation_type_id' => $variationType->id,
                        'value' => $optionValue,
                        'sort_order' => $optionIndex,
                    ]);

                    $options[] = $option;
                    $variationOptionMap[$variationIndex][$optionIndex] = $option;
                }

                if (!empty($options)) {
                    $variationOptionSets[] = [
                        'index' => $variationIndex,
                        'variation_name' => $variationType->name,
                        'options' => $options,
                    ];
                }
            }

            $combinations = $this->generateCombinations($variationOptionSets);
            $customVariants = $validated['variants'] ?? [];

            if (!empty($customVariants)) {
                foreach ($customVariants as $variantData) {
                    $selectedOptions = [];

                    foreach (($variantData['option_map'] ?? []) as $variationIndex => $optionIndex) {
                        if (isset($variationOptionMap[$variationIndex][$optionIndex])) {
                            $selectedOptions[] = $variationOptionMap[$variationIndex][$optionIndex];
                        }
                    }

                    $suffix = implode('-', array_map(
                        static fn(ProductVariationOption $option): string => $option->value,
                        $selectedOptions
                    ));

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?: $this->buildSku($store->name, $product->name, $suffix),
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                        'stock_alert' => $variantData['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(
                        static fn(ProductVariationOption $option): int => $option->id,
                        $selectedOptions
                    ));
                }
            } elseif (empty($combinations)) {
                $defaultVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->buildSku($store->name, $product->name),
                    'price' => $validated['base_price'],
                    'stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
                ]);

                $defaultVariant->options()->sync([]);
            } else {
                foreach ($combinations as $combination) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $this->buildSku(
                            $store->name,
                            $product->name,
                            implode('-', array_map(static fn($entry) => $entry['option']->value, $combination))
                        ),
                        'price' => $validated['base_price'],
                        'stock' => $validated['default_stock'],
                        'stock_alert' => $validated['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(static fn($entry) => $entry['option']->id, $combination));
                }
            }

            $store->update([
                'settings' => $this->mergeStoreSettings($store->settings, [
                    'onboarding_step' => 2,
                ]),
            ]);

            $request->session()->put('onboarding_last_product_id', $product->id);
            $request->session()->put('onboarding_product_id', $product->id);
        });

        return redirect()
            ->route('onboarding_StoreReady')
            ->with('success', "Product '{$validated['name']}' saved. Complete the final launch step.")
            ->with('success_title', 'Product added')
            ->with('success_meta', 'Step 2 of 3 completed');
    }

    public function variationPopup(Request $request): RedirectResponse|View
    {
        $store = $this->resolveOnboardingStore($request);

        if (!$store) {
            return redirect()
                ->route('onboarding-StoreDetails-1')
                ->withErrors(['store' => 'Please create a store first.']);
        }

        $draft = $request->session()->get('onboarding_product_draft', []);
        $variationTypes = $draft['variation_types'] ?? [];
        $variationIndexInput = $request->query('variation_index');
        $variationIndex = is_numeric($variationIndexInput) ? (int) $variationIndexInput : null;
        $editingVariation = $variationIndex !== null && array_key_exists($variationIndex, $variationTypes)
            ? $variationTypes[$variationIndex]
            : null;

        return view('user_view.onboarding-Step2-AddVariationPopup', [
            'store' => $store,
            'variationInputTypes' => ['select', 'radio', 'checkbox'],
            'editingVariation' => $editingVariation,
            'editingVariationIndex' => $editingVariation ? $variationIndex : null,
        ]);
    }

    public function storeVariationPopup(Request $request): RedirectResponse
    {
        $store = $this->resolveOnboardingStore($request);

        if (!$store) {
            return redirect()
                ->route('onboarding-StoreDetails-1')
                ->withErrors(['store' => 'Please create a store first.']);
        }

        $payload = [
            'variation_index' => $request->input('variation_index'),
            'variation_name' => $request->input('variation_name', $request->input('name')),
            'variation_type' => $request->input('variation_type', $request->input('type')),
            'variation_options' => $request->input('variation_options', $request->input('options')),
        ];

        $validated = validator($payload, [
            'variation_index' => ['nullable', 'integer', 'min:0'],
            'variation_name' => ['required', 'string', 'max:100'],
            'variation_type' => ['required', 'in:select,radio,checkbox'],
            'variation_options' => ['required', 'string', 'max:1000'],
        ])->validate();

        $parsedOptions = collect(preg_split('/[\r\n,]+/', $validated['variation_options']))
            ->map(static fn(string $value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($parsedOptions)) {
            return back()->withErrors(['variation_options' => 'Please provide at least one option value.'])->withInput();
        }

        $draft = $request->session()->get('onboarding_product_draft', []);
        $variationTypes = $draft['variation_types'] ?? [];

        $variationData = [
            'name' => $validated['variation_name'],
            'type' => $validated['variation_type'],
            'options' => $parsedOptions,
        ];

        $variationIndex = $validated['variation_index'] ?? null;
        if ($variationIndex !== null && array_key_exists($variationIndex, $variationTypes)) {
            $variationTypes[$variationIndex] = $variationData;
        } else {
            $variationTypes[] = $variationData;
        }

        $draft['variation_types'] = $variationTypes;
        $request->session()->put('onboarding_product_draft', $draft);

        return redirect()
            ->route('onboarding-Step2-AddProductVariations')
            ->with('success', $variationIndex !== null ? 'Variation updated in your product draft.' : 'Variation added to your product draft.')
            ->with('success_title', $variationIndex !== null ? 'Variation updated' : 'Variation added')
            ->with('success_meta', 'Product options refreshed');
    }

    public function customCategoryOverlay(Request $request): View
    {
        if ($request->boolean('reset')) {
            $draft = $request->session()->get('onboarding_store_draft', []);
            unset($draft['category'], $draft['business_models'], $draft['custom_category']);
            $request->session()->put('onboarding_store_draft', $draft);
        }

        $storeDraft = $request->session()->get('onboarding_store_draft', []);

        return view('user_view.onboarding-Step1-addCustom-Category', [
            'storeDraft' => $storeDraft,
        ]);
    }

    public function storeCustomCategoryOverlay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selected_category' => ['nullable', 'in:physical,digital,service,subscription,virtual,custom'],
            'business_models' => ['nullable', 'array'],
            'business_models.*' => ['string', 'max:80'],
            'custom_category' => ['nullable', 'string', 'max:80'],
        ]);

        if (
            empty($validated['selected_category']) &&
            empty($validated['custom_category']) &&
            empty($validated['business_models'])
        ) {
            return back()->withErrors([
                'selection' => 'Please select at least one category or add custom category.',
            ])->withInput();
        }

        $draft = $request->session()->get('onboarding_store_draft', []);

        if (!empty($validated['selected_category'])) {
            $draft['category'] = $validated['selected_category'];
        }

        if (array_key_exists('business_models', $validated)) {
            $draft['business_models'] = $validated['business_models'];
        }

        if (array_key_exists('custom_category', $validated)) {
            $draft['custom_category'] = $validated['custom_category'];
        }

        $request->session()->put('onboarding_store_draft', $draft);

        return redirect()
            ->route('onboarding-StoreDetails-1')
            ->with('success', 'Category selection applied. You can continue store setup.');
    }

    public function step3(Request $request): RedirectResponse|View
    {
        $store = $this->resolveOnboardingStore($request);

        if (!$store) {
            return redirect()->route('store-management');
        }

        $lastProductId = $request->session()->get('onboarding_last_product_id');
        $product = $lastProductId
            ? Product::where('store_id', $store->id)->find($lastProductId)
            : $store->products()->latest('id')->first();

        return view('user_view.onboarding-Step3-StoreReady', [
            'store' => $store,
            'product' => $product,
        ]);
    }

    public function completeStep3(Request $request): RedirectResponse
    {
        $store = $this->resolveOnboardingStore($request);

        if (!$store) {
            return redirect()->route('store-management');
        }

        $validated = $request->validate([
            'dont_show_again' => ['nullable', 'boolean'],
        ]);

        $store->update([
            'onboarding_completed' => true,
            'settings' => $this->mergeStoreSettings($store->settings, [
                'onboarding_step' => 3,
                'onboarding_ready_screen_hidden' => (bool) ($validated['dont_show_again'] ?? false),
            ]),
        ]);

        $request->session()->forget([
            'onboarding_store_draft',
            'onboarding_store_id',
            'onboarding_last_store_id',
            'onboarding_product_draft',
            'onboarding_last_product_id',
            'onboarding_product_id',
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Onboarding completed successfully.');
    }

    private function resolveSessionStore(Request $request): ?Store
    {
        $storeId = $request->session()->get('onboarding_store_id');

        if (!$storeId) {
            return null;
        }

        return Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->first();
    }
    private function resolveOnboardingStore(Request $request): ?Store
    {
        return $this->resolveSessionStore($request);
    }

    private function resolveSessionProduct(Request $request, Store $store): ?Product
    {
        $productId = $request->session()->get('onboarding_product_id');

        if (!$productId) {
            return null;
        }

        return Product::where('id', $productId)
            ->where('store_id', $store->id)
            ->first();
    }

    private function resolveOnboardingProduct(Request $request, Store $store): ?Product
    {
        return $this->resolveSessionProduct($request, $store);
    }

    private function mapStoreToDraft(Store $store): array
    {
        $settings = is_array($store->settings) ? $store->settings : [];

        return array_filter([
            'name' => $store->name,
            'primary_market' => $settings['primary_market'] ?? null,
            'address' => $store->address,
            'currency' => $store->currency,
            'timezone' => $store->timezone,
            'category' => $store->category,
            'business_models' => is_array($settings['business_models'] ?? null) ? $settings['business_models'] : [],
            'custom_category' => $settings['custom_category'] ?? null,
        ], static fn($value): bool => $value !== null);
    }

    private function mapProductToDraft(Product $product): array
    {
        $meta = is_array($product->meta) ? $product->meta : [];
        $variationTypes = $product->variationTypes()->with('options')->get();
        $variants = $product->variants()->with('options')->get();

        $variationTypeIndexById = [];
        $variationTypesPayload = $variationTypes->values()->map(function (ProductVariationType $variationType, int $index) use (&$variationTypeIndexById): array {
            $variationTypeIndexById[$variationType->id] = $index;

            return [
                'name' => $variationType->name,
                'type' => $variationType->type,
                'options' => $variationType->options
                    ->sortBy('sort_order')
                    ->values()
                    ->map(static fn(ProductVariationOption $option): string => $option->value)
                    ->all(),
            ];
        })->all();

        $optionIndexMap = [];
        foreach ($variationTypes as $variationType) {
            $typeIndex = $variationTypeIndexById[$variationType->id] ?? null;
            if ($typeIndex === null) {
                continue;
            }

            foreach ($variationType->options->sortBy('sort_order')->values() as $optionIndex => $option) {
                $optionIndexMap[$option->id] = [
                    'type_index' => $typeIndex,
                    'option_index' => $optionIndex,
                ];
            }
        }

        $variantsPayload = $variants->map(function (ProductVariant $variant) use ($optionIndexMap): array {
            $optionMap = [];
            foreach ($variant->options as $option) {
                if (!isset($optionIndexMap[$option->id])) {
                    continue;
                }

                $entry = $optionIndexMap[$option->id];
                $optionMap[$entry['type_index']] = $entry['option_index'];
            }

            ksort($optionMap);

            return [
                'option_map' => $optionMap,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'stock' => (int) $variant->stock,
                'stock_alert' => (int) $variant->stock_alert,
            ];
        })->values()->all();

        return [
            'name' => $product->name,
            'description' => $product->description,
            'base_price' => (float) $product->base_price,
            'sku' => $product->sku,
            'product_type' => $product->product_type,
            'default_stock' => (int) ($meta['default_stock'] ?? 0),
            'stock_alert' => (int) ($meta['stock_alert'] ?? 0),
            'variation_types' => $variationTypesPayload,
            'variants' => $variantsPayload,
        ];
    }

    private function mergeStoreSettings(?array $existingSettings, array $updates): array
    {
        return array_merge($existingSettings ?? [], $updates);
    }

    private function uniqueSlug(string $modelClass, string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'store';

        $slug = $base;
        $counter = 1;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueProductSlug(int $storeId, string $name, ?int $ignoreProductId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'product';

        $slug = $base;
        $counter = 1;

        while (Product::where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($ignoreProductId, fn($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @param array<int, array{index:int, variation_name:string, options:array<int, ProductVariationOption>}> $sets
     * @return array<int, array<int, array{variation_name:string, option:ProductVariationOption}>>
     */
    private function generateCombinations(array $sets): array
    {
        if (empty($sets)) {
            return [];
        }

        $results = [[]];

        foreach ($sets as $set) {
            $temp = [];

            foreach ($results as $result) {
                foreach ($set['options'] as $option) {
                    $copy = $result;
                    $copy[] = [
                        'variation_name' => $set['variation_name'],
                        'option' => $option,
                    ];
                    $temp[] = $copy;
                }
            }

            $results = $temp;
        }

        return $results;
    }

    private function buildSku(string $storeName, string $productName, ?string $suffix = null): string
    {
        $parts = [
            Str::upper(Str::substr(Str::slug($storeName, ''), 0, 4)),
            Str::upper(Str::substr(Str::slug($productName, ''), 0, 6)),
        ];

        if ($suffix) {
            $parts[] = Str::upper(Str::substr(Str::slug($suffix, ''), 0, 8));
        }

        $parts[] = Str::upper(Str::random(4));

        return implode('-', array_filter($parts));
    }

    /**
     * @param array<int|string, mixed> $variantRows
     * @param array<int, array{name:string, type:string, options:array<int, string>}> $variationTypes
     * @return array{variants: array<int, array{option_map: array<int, int>, sku: string|null, price: float, stock: int, stock_alert: int}>, errors: array<string, string>}
     */
    private function normalizeCustomVariants(
        array $variantRows,
        array $variationTypes,
        float $basePrice,
        int $defaultStock,
        int $defaultStockAlert
    ): array {
        $normalized = [];
        $errors = [];
        $seenCombinationKeys = [];

        foreach ($variantRows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $optionMapInput = is_array($row['option_map'] ?? null) ? $row['option_map'] : [];
            $optionMap = [];

            foreach ($variationTypes as $variationIndex => $variationType) {
                $rawOptionIndex = $optionMapInput[$variationIndex] ?? null;

                if ($rawOptionIndex === null || $rawOptionIndex === '') {
                    continue;
                }

                if (!is_numeric($rawOptionIndex)) {
                    $errors["variants.$rowIndex.option_map.$variationIndex"] = sprintf(
                        'Variant %d has an invalid value for %s.',
                        $rowIndex + 1,
                        $variationType['name']
                    );
                    continue;
                }

                $optionIndex = (int) $rawOptionIndex;
                if (!array_key_exists($optionIndex, $variationType['options'] ?? [])) {
                    $errors["variants.$rowIndex.option_map.$variationIndex"] = sprintf(
                        'Variant %d has an unavailable option for %s.',
                        $rowIndex + 1,
                        $variationType['name']
                    );
                    continue;
                }

                $optionMap[$variationIndex] = $optionIndex;
            }

            if (!empty($variationTypes)) {
                if (count($optionMap) === 0) {
                    continue;
                }

                ksort($optionMap);
                $combinationKey = implode('|', array_map(
                    static fn(int $variationIndex, int $optionIndex): string => $variationIndex . ':' . $optionIndex,
                    array_keys($optionMap),
                    $optionMap
                ));

                if (isset($seenCombinationKeys[$combinationKey])) {
                    $errors["variants.$rowIndex.option_map"] = sprintf(
                        'Variant %d duplicates another variant combination.',
                        $rowIndex + 1
                    );
                    continue;
                }

                $seenCombinationKeys[$combinationKey] = true;
            }

            $normalized[] = [
                'option_map' => $optionMap,
                'sku' => isset($row['sku']) && trim((string) $row['sku']) !== '' ? trim((string) $row['sku']) : null,
                'price' => isset($row['price']) && $row['price'] !== '' ? (float) $row['price'] : $basePrice,
                'stock' => isset($row['stock']) && $row['stock'] !== '' ? (int) $row['stock'] : $defaultStock,
                'stock_alert' => isset($row['stock_alert']) && $row['stock_alert'] !== '' ? (int) $row['stock_alert'] : $defaultStockAlert,
            ];
        }

        return [
            'variants' => $normalized,
            'errors' => $errors,
        ];
    }

    public function addProductFromStore(Request $request, $storeId): View|RedirectResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return redirect()->route('products', [
            'storeId' => $store->id,
            'openAddProduct' => 1,
        ]);
    }

    public function updateStoreFromManagement(Request $request, $storeId): RedirectResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'primary_market' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'max:8'],
            'timezone' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80', 'required_without:custom_category'],
            'business_models' => ['nullable', 'array'],
            'business_models.*' => ['string', 'max:80'],
            'custom_category' => ['nullable', 'string', 'max:80', 'required_without:category'],
            'store_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg', 'max:2048'],
        ]);

        $normalizedCategory = $validated['category'] ?? null;
        if (!$normalizedCategory && !empty($validated['custom_category'])) {
            $normalizedCategory = 'custom';
        }

        $logoPath = $store->logo;
        if ($request->hasFile('store_logo')) {
            $logoPath = $request->file('store_logo')->store('store-logos', 'public');
        }

        $store->update([
            'name' => $validated['name'],
            'logo' => $logoPath,
            'address' => $validated['address'] ?? null,
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'category' => $normalizedCategory,
            'settings' => $this->mergeStoreSettings($store->settings, [
                'primary_market' => $validated['primary_market'],
                'business_models' => $validated['business_models'] ?? [],
                'custom_category' => $validated['custom_category'] ?? null,
            ]),
        ]);

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$store->name}' updated successfully.")
            ->with('success_title', 'Store updated')
            ->with('success_meta', 'Changes are now live');
    }

    public function destroyStoreFromManagement(Request $request, $storeId): RedirectResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $deletedStoreName = $store->name;
        $store->delete();

        if ((int) $request->session()->get('onboarding_store_id') === (int) $storeId) {
            $request->session()->forget([
                'onboarding_store_draft',
                'onboarding_store_id',
                'onboarding_last_store_id',
                'onboarding_product_draft',
                'onboarding_product_id',
                'onboarding_last_product_id',
            ]);
        }

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$deletedStoreName}' deleted successfully.")
            ->with('success_title', 'Store removed')
            ->with('success_meta', 'Store list refreshed');
    }

    public function updateProductFromManagement(Request $request, $productId): RedirectResponse
    {
        $product = Product::query()
            ->where('id', $productId)
            ->whereHas('store', fn($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', 'string', 'max:80'],
            'custom_product_type' => ['nullable', 'string', 'max:80', 'required_if:product_type,custom'],
            'existing_image_paths' => ['nullable', 'array', 'max:8'],
            'existing_image_paths.*' => ['string', 'max:255'],
            'stock_alert' => ['required', 'integer', 'min:0'],
            'product_images' => ['nullable', 'array', 'max:8'],
            'product_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'variation_types' => ['nullable', 'array'],
            'variation_types.*.name' => ['required', 'string', 'max:100'],
            'variation_types.*.type' => ['required', 'in:select,radio,checkbox'],
            'variation_types.*.options' => ['required', 'array', 'min:1'],
            'variation_types.*.options.*' => ['required', 'string', 'max:80'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_alert' => ['nullable', 'integer', 'min:0'],
            'variants.*.option_map' => ['nullable', 'array'],
        ]);

        $targetStore = Store::query()
            ->where('id', (int) $validated['store_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput();
        }

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            0,
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput();
        }

        $validated['variants'] = $normalizedVariants['variants'];

        $meta = $product->meta ?? [];
        $currentImagePaths = collect($meta['image_paths'] ?? array_filter([$meta['image_path'] ?? null]))
            ->filter()
            ->values();
        $retainedImagePaths = collect($validated['existing_image_paths'] ?? [])
            ->filter(fn($path) => $currentImagePaths->contains($path))
            ->values();

        foreach ($currentImagePaths->diff($retainedImagePaths) as $removedImagePath) {
            Storage::disk('public')->delete($removedImagePath);
        }

        $newImagePaths = [];
        foreach ($request->file('product_images', []) as $imageFile) {
            $newImagePaths[] = $imageFile->store('product-images', 'public');
        }

        $finalImagePaths = $retainedImagePaths->merge($newImagePaths)->values()->all();
        $meta['image_paths'] = $finalImagePaths;
        $meta['image_path'] = $finalImagePaths[0] ?? null;

        $meta['default_stock'] = $meta['default_stock'] ?? 0;
        $meta['stock_alert'] = (int) $validated['stock_alert'];

        DB::transaction(function () use ($product, $targetStore, $validated, $meta): void {
            $product->update([
                'store_id' => $targetStore->id,
                'name' => $validated['name'],
                'slug' => $this->uniqueProductSlug($targetStore->id, $validated['name'], $product->id),
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'meta' => $meta,
            ]);

            $product->variationTypes()->delete();
            $product->variants()->delete();

            $variationOptionSets = [];
            $variationOptionMap = [];

            foreach (($validated['variation_types'] ?? []) as $variationIndex => $variationData) {
                $variationType = ProductVariationType::create([
                    'product_id' => $product->id,
                    'name' => $variationData['name'],
                    'type' => $variationData['type'],
                ]);

                $options = [];

                foreach ($variationData['options'] as $optionIndex => $optionValue) {
                    $option = ProductVariationOption::create([
                        'variation_type_id' => $variationType->id,
                        'value' => $optionValue,
                        'sort_order' => $optionIndex,
                    ]);

                    $options[] = $option;
                    $variationOptionMap[$variationIndex][$optionIndex] = $option;
                }

                if (!empty($options)) {
                    $variationOptionSets[] = [
                        'index' => $variationIndex,
                        'variation_name' => $variationType->name,
                        'options' => $options,
                    ];
                }
            }

            $combinations = $this->generateCombinations($variationOptionSets);
            $customVariants = $validated['variants'] ?? [];

            if (!empty($customVariants)) {
                foreach ($customVariants as $variantData) {
                    $selectedOptions = [];

                    foreach (($variantData['option_map'] ?? []) as $variationIndex => $optionIndex) {
                        if (isset($variationOptionMap[$variationIndex][$optionIndex])) {
                            $selectedOptions[] = $variationOptionMap[$variationIndex][$optionIndex];
                        }
                    }

                    $suffix = implode('-', array_map(
                        static fn(ProductVariationOption $option): string => $option->value,
                        $selectedOptions
                    ));

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?: $this->buildSku($targetStore->name, $product->name, $suffix),
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                        'stock_alert' => $variantData['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(
                        static fn(ProductVariationOption $option): int => $option->id,
                        $selectedOptions
                    ));
                }
            } elseif (empty($combinations)) {
                $defaultVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->buildSku($targetStore->name, $product->name),
                    'price' => $validated['base_price'],
                    'stock' => 0,
                    'stock_alert' => $validated['stock_alert'],
                ]);

                $defaultVariant->options()->sync([]);
            } else {
                foreach ($combinations as $combination) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $this->buildSku(
                            $targetStore->name,
                            $product->name,
                            implode('-', array_map(static fn($entry) => $entry['option']->value, $combination))
                        ),
                        'price' => $validated['base_price'],
                        'stock' => 0,
                        'stock_alert' => $validated['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(static fn($entry) => $entry['option']->id, $combination));
                }
            }
        });

        return redirect()
            ->route('products', ['storeId' => $targetStore->id])
            ->with('success', "Product '{$product->name}' updated successfully.")
            ->with('success_title', 'Product updated')
            ->with('success_meta', "Store: {$targetStore->name}");
    }

    public function destroyProductFromManagement(Request $request, $productId): RedirectResponse
    {
        $product = Product::query()
            ->where('id', $productId)
            ->whereHas('store', fn($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        $redirectStoreId = (int) ($request->input('store_id') ?: $product->store_id);
        $imagePaths = $product->meta['image_paths'] ?? array_filter([$product->meta['image_path'] ?? null]);

        $deletedProductName = $product->name;
        $product->delete();

        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()
            ->route('products', ['storeId' => $redirectStoreId ?: null])
            ->with('success', "Product '{$deletedProductName}' deleted successfully.")
            ->with('success_title', 'Product removed')
            ->with('success_meta', 'Catalog updated');
    }

    public function storeProductFromStore(Request $request, $storeId): RedirectResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'bulk_price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', 'string', 'max:80'],
            'custom_product_type' => ['nullable', 'string', 'max:80', 'required_if:product_type,custom'],
            'product_images' => ['nullable', 'array', 'max:8'],
            'product_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'bulk_stock' => ['required', 'integer', 'min:0'],
            'stock_alert' => ['required', 'integer', 'min:0'],
            'variation_types' => ['nullable', 'array'],
            'variation_types.*.name' => ['required', 'string', 'max:100'],
            'variation_types.*.type' => ['required', 'in:select,radio,checkbox'],
            'variation_types.*.options' => ['required', 'array', 'min:1'],
            'variation_types.*.options.*' => ['required', 'string', 'max:80'],
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock_alert' => ['nullable', 'integer', 'min:0'],
            'variants.*.option_map' => ['nullable', 'array'],
        ]);

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput();
        }

        // Map bulk_price and bulk_stock to base_price and default_stock for processing
        $validated['base_price'] = $validated['bulk_price'];
        $validated['default_stock'] = $validated['bulk_stock'];

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            (int) $validated['default_stock'],
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput();
        }

        $validated['variants'] = $normalizedVariants['variants'];
        $imagePaths = [];
        foreach ($request->file('product_images', []) as $imageFile) {
            $imagePaths[] = $imageFile->store('product-images', 'public');
        }

        DB::transaction(function () use ($validated, $store, $imagePaths): void {
            $productPayload = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'status' => true,
                'meta' => [
                    'default_stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
                    'image_path' => $imagePaths[0] ?? null,
                    'image_paths' => $imagePaths,
                ],
            ];

            $product = Product::create($productPayload + [
                'store_id' => $store->id,
                'slug' => $this->uniqueProductSlug($store->id, $validated['name']),
            ]);

            $variationOptionSets = [];
            $variationOptionMap = [];

            foreach (($validated['variation_types'] ?? []) as $variationIndex => $variationData) {
                $variationType = ProductVariationType::create([
                    'product_id' => $product->id,
                    'name' => $variationData['name'],
                    'type' => $variationData['type'],
                ]);

                $options = [];

                foreach ($variationData['options'] as $optionIndex => $optionValue) {
                    $option = ProductVariationOption::create([
                        'variation_type_id' => $variationType->id,
                        'value' => $optionValue,
                        'sort_order' => $optionIndex,
                    ]);

                    $options[] = $option;
                    $variationOptionMap[$variationIndex][$optionIndex] = $option;
                }

                if (!empty($options)) {
                    $variationOptionSets[] = [
                        'index' => $variationIndex,
                        'variation_name' => $variationType->name,
                        'options' => $options,
                    ];
                }
            }

            $combinations = $this->generateCombinations($variationOptionSets);
            $customVariants = $validated['variants'] ?? [];

            if (!empty($customVariants)) {
                foreach ($customVariants as $variantData) {
                    $selectedOptions = [];

                    foreach (($variantData['option_map'] ?? []) as $variationIndex => $optionIndex) {
                        if (isset($variationOptionMap[$variationIndex][$optionIndex])) {
                            $selectedOptions[] = $variationOptionMap[$variationIndex][$optionIndex];
                        }
                    }

                    $suffix = implode('-', array_map(
                        static fn(ProductVariationOption $option): string => $option->value,
                        $selectedOptions
                    ));

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?: $this->buildSku($store->name, $product->name, $suffix),
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                        'stock_alert' => $variantData['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(
                        static fn(ProductVariationOption $option): int => $option->id,
                        $selectedOptions
                    ));
                }
            } elseif (empty($combinations)) {
                $defaultVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->buildSku($store->name, $product->name),
                    'price' => $validated['base_price'],
                    'stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
                ]);

                $defaultVariant->options()->sync([]);
            } else {
                foreach ($combinations as $combination) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $this->buildSku(
                            $store->name,
                            $product->name,
                            implode('-', array_map(static fn($entry) => $entry['option']->value, $combination))
                        ),
                        'price' => $validated['base_price'],
                        'stock' => $validated['default_stock'],
                        'stock_alert' => $validated['stock_alert'],
                    ]);

                    $variant->options()->sync(array_map(static fn($entry) => $entry['option']->id, $combination));
                }
            }
        });

        return redirect()
            ->route('products', ['storeId' => $store->id])
            ->with('success', "Product '{$validated['name']}' added to {$store->name}.")
            ->with('success_title', 'Product added')
            ->with('success_meta', 'Catalog updated');
    }
}
