<?php

namespace App\Http\Controllers;

use App\Support\CatalogRules;
use App\Support\ProductImageStorage;
use App\Support\StockMovementRecorder;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
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

        $store->members()->syncWithoutDetaching([
            $request->user()->id => ['role' => 'owner'],
        ]);

        $this->syncActiveStoreSessions($request, $store);
        $storeDraft = Arr::only($validated, [
            'name',
            'primary_market',
            'address',
            'currency',
            'timezone',
            'category',
            'business_models',
            'custom_category',
        ]);
        if ($logoPath) {
            $storeDraft['logo'] = $logoPath;
        }
        $request->session()->put('onboarding_store_draft', $storeDraft);

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
        $store = $this->resolveOnboardingStore($request);

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
            'brands' => $store->brands()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => $store->tags()->orderBy('sort_order')->orderBy('name')->get(),
            'productCategories' => $store->categories()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(),
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
            'brand_id' => CatalogRules::brandIdForStore($store),
            ...CatalogRules::tagIdsForStore($store),
            ...CatalogRules::categoryIdsForStore($store),
        ]);

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput($request->except(['product_images']));
        }

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            (int) $validated['default_stock'],
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput($request->except(['product_images']));
        }

        $validated['variants'] = $normalizedVariants['variants'];
        $validated['brand_id'] = $validated['brand_id'] ?? null;
        $tagIds = collect($validated['tag_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        // Never persist UploadedFile instances to session (not serializable).
        $request->session()->put('onboarding_product_draft', Arr::except($validated, ['product_images']));

        DB::transaction(function () use ($validated, $store, $request, $tagIds, $categoryIds): void {
            // Only look for existing product if in edit mode
            $product = ($validated['mode'] === 'edit') ? $this->resolveSessionProduct($request, $store) : null;
            $oldFingerprintStocks = [];
            if ($product) {
                $product->load(['variants.options.variationType']);
                $oldFingerprintStocks = StockMovementRecorder::snapshotFingerprintsToStock($product);
            }
            $productPayload = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'brand_id' => $validated['brand_id'] ?? null,
                'status' => true,
                'meta' => [
                    'default_stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
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

            $galleryPaths = [];
            foreach ($request->file('product_images', []) as $imageFile) {
                $galleryPaths[] = ProductImageStorage::store($imageFile, $store);
            }
            $this->replaceProductGallery($product, $galleryPaths, $request->user()?->id, ($validated['mode'] ?? '') === 'edit');

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

            $product->tags()->sync($tagIds);
            $product->categories()->sync($categoryIds);

            $product->refresh();
            $product->load(['variants.options.variationType']);
            StockMovementRecorder::syncAfterVariantRebuild(
                $store,
                $product,
                $oldFingerprintStocks,
                $request->user()?->id,
                'onboarding'
            );

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
        return $this->resolveAccessibleStoreFromSession($request, 'onboarding_store_id');
    }

    private function resolveOnboardingStore(Request $request): ?Store
    {
        return $this->resolveAccessibleStoreFromSession($request, 'onboarding_store_id', 'current_store_id');
    }

    private function resolveAccessibleStoreFromSession(Request $request, string ...$sessionKeys): ?Store
    {
        foreach ($sessionKeys as $sessionKey) {
            $storeId = (int) $request->session()->get($sessionKey);

            if (! $storeId) {
                continue;
            }

            $store = $request->user()->memberStores()
                ->where('stores.id', $storeId)
                ->first();

            if ($store) {
                return $store;
            }
        }

        return null;
    }

    private function syncActiveStoreSessions(Request $request, Store $store): void
    {
        $request->session()->put('current_store_id', $store->id);
        $request->session()->put('onboarding_store_id', $store->id);
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
            'logo' => $store->logo,
        ], static fn ($value): bool => $value !== null && $value !== '');
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
            'brand_id' => $product->brand_id,
            'default_stock' => (int) ($meta['default_stock'] ?? 0),
            'stock_alert' => (int) ($meta['stock_alert'] ?? 0),
            'variation_types' => $variationTypesPayload,
            'variants' => $variantsPayload,
            'tag_ids' => $product->tags()->pluck('id')->all(),
            'category_ids' => $product->categories()->pluck('id')->all(),
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
        $store = $request->user()->memberStores()
            ->where('stores.id', $storeId)
            ->firstOrFail();

        $this->syncActiveStoreSessions($request, $store);

        return redirect()->route('products', [
            'openAddProduct' => 1,
        ]);
    }

    public function updateStoreFromManagement(Request $request, $storeId): RedirectResponse
    {
        $store = $request->user()->memberStores()
            ->where('stores.id', $storeId)
            ->firstOrFail();

        $this->authorizeStoreRoles($request, $store, [
            Store::ROLE_OWNER,
            Store::ROLE_MANAGER,
        ]);

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

        $store->members()->syncWithoutDetaching([
            $request->user()->id => ['role' => 'owner'],
        ]);

        $this->syncActiveStoreSessions($request, $store->refresh());

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$store->name}' updated successfully.")
            ->with('success_title', 'Store updated')
            ->with('success_meta', 'Changes are now live');
    }

    public function destroyStoreFromManagement(Request $request, $storeId): RedirectResponse
    {
        $store = $request->user()->memberStores()
            ->where('stores.id', $storeId)
            ->firstOrFail();

        $this->authorizeStoreRoles($request, $store, Store::ROLE_OWNER);

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

        if ((int) $request->session()->get('current_store_id') === (int) $storeId) {
            $request->session()->forget('current_store_id');
        }

        return redirect()
            ->route('store-management')
            ->with('success', "Store '{$deletedStoreName}' deleted successfully.")
            ->with('success_title', 'Store removed')
            ->with('success_meta', 'Store list refreshed');
    }

    public function updateProductFromManagement(Request $request, $productId): RedirectResponse
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please switch to a store before editing a product.']);
        }

        $product = Product::query()
            ->where('id', $productId)
            ->where('store_id', $currentStore->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:120'],
            'product_type' => ['required', 'string', 'max:80'],
            'custom_product_type' => ['nullable', 'string', 'max:80', 'required_if:product_type,custom'],
            'existing_image_paths' => ['nullable', 'array', 'max:8'],
            'existing_image_paths.*' => [
                'string',
                'max:512',
                Rule::exists('product_images', 'image_path')->where('product_id', $product->id),
            ],
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
            'brand_id' => CatalogRules::brandIdForStore($currentStore),
            ...CatalogRules::tagIdsForStore($currentStore),
            ...CatalogRules::categoryIdsForStore($currentStore),
        ]);

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput($request->except(['product_images']));
        }

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            0,
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput($request->except(['product_images']));
        }

        $validated['variants'] = $normalizedVariants['variants'];
        $validated['brand_id'] = $validated['brand_id'] ?? null;
        $tagIds = collect($validated['tag_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $meta = $product->meta ?? [];
        unset($meta['image_path'], $meta['image_paths']);
        $meta['default_stock'] = $meta['default_stock'] ?? 0;
        $meta['stock_alert'] = (int) $validated['stock_alert'];

        $retainedPaths = collect($validated['existing_image_paths'] ?? [])
            ->filter(static fn ($p): bool => is_string($p) && $p !== '')
            ->unique()
            ->values();

        $product->load(['variants.options.variationType']);
        $oldFingerprintStocks = StockMovementRecorder::snapshotFingerprintsToStock($product);

        DB::transaction(function () use ($product, $currentStore, $request, $validated, $meta, $tagIds, $categoryIds, $retainedPaths, $oldFingerprintStocks): void {
            foreach ($product->images()->get() as $imageRow) {
                if (! $retainedPaths->contains($imageRow->image_path)) {
                    $imageRow->delete();
                }
            }

            $nextOrder = (int) $product->images()->max('sort_order') + 1;
            foreach ($request->file('product_images', []) as $imageFile) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_path' => ProductImageStorage::store($imageFile, $currentStore),
                    'alt_text' => null,
                    'sort_order' => $nextOrder,
                    'is_primary' => false,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
                $nextOrder++;
            }

            $this->normalizePrimaryProductImage($product);

            $product->update([
                'name' => $validated['name'],
                'slug' => $this->uniqueProductSlug($currentStore->id, $validated['name'], $product->id),
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'brand_id' => $validated['brand_id'] ?? null,
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
                        'sku' => $variantData['sku'] ?: $this->buildSku($currentStore->name, $product->name, $suffix),
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
                    'sku' => $this->buildSku($currentStore->name, $product->name),
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
                            $currentStore->name,
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

            $product->tags()->sync($tagIds);
            $product->categories()->sync($categoryIds);

            $product->refresh();
            $product->load(['variants.options.variationType']);
            StockMovementRecorder::syncAfterVariantRebuild(
                $currentStore,
                $product,
                $oldFingerprintStocks,
                $request->user()?->id,
                'catalog'
            );
        });

        $this->syncActiveStoreSessions($request, $currentStore);

        return redirect()
            ->route('products')
            ->with('success', "Product '{$product->name}' updated successfully.")
            ->with('success_title', 'Product updated')
            ->with('success_meta', "Store: {$currentStore->name}");
    }

    public function destroyProductFromManagement(Request $request, $productId): RedirectResponse
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please switch to a store before deleting a product.']);
        }

        $product = Product::query()
            ->where('id', $productId)
            ->where('store_id', $currentStore->id)
            ->firstOrFail();

        $deletedProductName = $product->name;
        $product->forceDelete();

        return redirect()
            ->route('products')
            ->with('success', "Product '{$deletedProductName}' deleted successfully.")
            ->with('success_title', 'Product removed')
            ->with('success_meta', 'Catalog updated');
    }

    public function storeProductFromStore(Request $request, $storeId): RedirectResponse
    {
        $store = $request->user()->memberStores()
            ->where('stores.id', $storeId)
            ->firstOrFail();

        $this->authorizeStoreRoles($request, $store, [
            Store::ROLE_OWNER,
            Store::ROLE_MANAGER,
        ]);

        $this->syncActiveStoreSessions($request, $store);

        return $this->storeProductForStore($request, $store);
    }

    public function storeProductFromCurrentStore(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please switch to a store before adding a product.']);
        }

        $this->authorizeStoreRoles($request, $store, [
            Store::ROLE_OWNER,
            Store::ROLE_MANAGER,
        ]);

        return $this->storeProductForStore($request, $store);
    }

    private function authorizeStoreRoles(Request $request, Store $store, string|array $roles): void
    {
        if (! $request->user()?->hasStoreRole($store, $roles)) {
            abort(403, 'You are not authorized to perform this action in this store.');
        }
    }

    private function storeProductForStore(Request $request, Store $store): RedirectResponse
    {
        $this->syncActiveStoreSessions($request, $store);

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
            'brand_id' => CatalogRules::brandIdForStore($store),
            ...CatalogRules::tagIdsForStore($store),
            ...CatalogRules::categoryIdsForStore($store),
        ]);

        if (($validated['product_type'] ?? '') === 'custom') {
            $validated['product_type'] = trim((string) ($validated['custom_product_type'] ?? ''));
        } else {
            $validated['product_type'] = trim((string) $validated['product_type']);
        }

        if ($validated['product_type'] === '') {
            return back()
                ->withErrors(['custom_product_type' => 'Please enter a valid product type.'])
                ->withInput($request->except(['product_images']));
        }

        // Map bulk_price and bulk_stock to base_price and default_stock for processing
        $validated['base_price'] = $validated['bulk_price'];
        $validated['default_stock'] = $validated['bulk_stock'];
        $validated['brand_id'] = $validated['brand_id'] ?? null;
        $tagIds = collect($validated['tag_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $normalizedVariants = $this->normalizeCustomVariants(
            $request->input('variants', []),
            $validated['variation_types'] ?? [],
            (float) $validated['base_price'],
            (int) $validated['default_stock'],
            (int) $validated['stock_alert']
        );

        if (!empty($normalizedVariants['errors'])) {
            return back()->withErrors($normalizedVariants['errors'])->withInput($request->except(['product_images']));
        }

        $validated['variants'] = $normalizedVariants['variants'];

        DB::transaction(function () use ($validated, $store, $request, $tagIds, $categoryIds): void {
            $oldFingerprintStocks = [];
            $productPayload = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'base_price' => $validated['base_price'],
                'sku' => $validated['sku'] ?? null,
                'product_type' => $validated['product_type'],
                'brand_id' => $validated['brand_id'] ?? null,
                'status' => true,
                'meta' => [
                    'default_stock' => $validated['default_stock'],
                    'stock_alert' => $validated['stock_alert'],
                ],
            ];

            $product = Product::create($productPayload + [
                'store_id' => $store->id,
                'slug' => $this->uniqueProductSlug($store->id, $validated['name']),
            ]);

            $galleryPaths = [];
            foreach ($request->file('product_images', []) as $imageFile) {
                $galleryPaths[] = ProductImageStorage::store($imageFile, $store);
            }
            $this->replaceProductGallery($product, $galleryPaths, $request->user()?->id, false);

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

            $product->tags()->sync($tagIds);
            $product->categories()->sync($categoryIds);

            $product->refresh();
            $product->load(['variants.options.variationType']);
            StockMovementRecorder::syncAfterVariantRebuild(
                $store,
                $product,
                $oldFingerprintStocks,
                $request->user()?->id,
                'catalog'
            );
        });

        return redirect()
            ->route('products')
            ->with('success', "Product '{$validated['name']}' added to {$store->name}.")
            ->with('success_title', 'Product added')
            ->with('success_meta', 'Catalog updated');
    }

    private function replaceProductGallery(Product $product, array $paths, ?int $userId, bool $preserveWhenEmpty): void
    {
        $paths = array_values(array_filter($paths));
        if ($paths === []) {
            if ($preserveWhenEmpty) {
                return;
            }
            foreach ($product->images()->get() as $img) {
                $img->delete();
            }

            return;
        }

        foreach ($product->images()->get() as $img) {
            $img->delete();
        }

        foreach ($paths as $index => $path) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $path,
                'alt_text' => null,
                'sort_order' => $index,
                'is_primary' => $index === 0,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function normalizePrimaryProductImage(Product $product): void
    {
        $rows = $product->images()->orderBy('sort_order')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows->values() as $index => $row) {
            $row->update([
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }
}
