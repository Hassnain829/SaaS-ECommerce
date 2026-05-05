<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Role;
use App\Models\Store;
use App\Support\ProductCustomFieldHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function signin(): RedirectResponse|\Illuminate\View\View
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('user_view.signin');
    }

    public function register(): RedirectResponse|\Illuminate\View\View
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('user_view.register');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return $this->redirectByRole();
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $userRole = Role::query()->where('name', 'user')->first();

        if (! $userRole) {
            abort(500, 'The default user role is missing. Please seed roles before registering users.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role_id' => $userRole->id,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding-StoreDetails-1');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register');
    }

    private function redirectByRole(): RedirectResponse
    {
        $role = Auth::user()?->role?->name;

        if ($role === 'admin') {
            return redirect()->route('admin-dashboard');
        }

        return redirect()->route('dashboard');
    }

    public function index()
    {
        return view('user_view.dashboard');
    }

    public function product(Request $request): \Illuminate\View\View|RedirectResponse|StreamedResponse
    {
        $stores = $request->attributes->get('availableStores')
            ?? $request->user()->memberStores()->orderBy('stores.name')->get();
        $selectedStore = $request->attributes->get('currentStore');

        if (! $selectedStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No accessible store was found for your account. Create a store or ask for access first.']);
        }

        $search = trim((string) $request->query('q', ''));
        $taxonomyCategoryQuery = $request->query('category');
        $taxonomyCategoryFilterId = null;
        if ($taxonomyCategoryQuery !== null && $taxonomyCategoryQuery !== '' && ctype_digit((string) $taxonomyCategoryQuery)) {
            $candidateCategory = (int) $taxonomyCategoryQuery;
            if (Category::query()->where('store_id', $selectedStore->id)->where('id', $candidateCategory)->exists()) {
                $taxonomyCategoryFilterId = $candidateCategory;
            }
        }

        $productTypeFilter = trim((string) $request->query('product_type', ''));
        $status = trim((string) $request->query('status', ''));
        $stockFilter = trim((string) $request->query('stock', ''));
        $sort = trim((string) $request->query('sort', 'latest'));
        $brandQuery = $request->query('brand');
        $brandFilterId = null;
        if ($brandQuery !== null && $brandQuery !== '' && ctype_digit((string) $brandQuery)) {
            $candidate = (int) $brandQuery;
            if (Brand::query()->where('store_id', $selectedStore->id)->where('id', $candidate)->exists()) {
                $brandFilterId = $candidate;
            }
        }

        $tagQuery = $request->query('tag');
        $tagFilterId = null;
        if ($tagQuery !== null && $tagQuery !== '' && ctype_digit((string) $tagQuery)) {
            $candidateTag = (int) $tagQuery;
            if (Tag::query()->where('store_id', $selectedStore->id)->where('id', $candidateTag)->exists()) {
                $tagFilterId = $candidateTag;
            }
        }

        $cfKey = trim((string) $request->query('cf_key', ''));
        $cfValue = trim((string) $request->query('cf_value', ''));
        $cfFilterActive = $cfKey !== '' && $cfValue !== ''
            && ProductCustomFieldHelper::isValidKey($cfKey)
            && ProductCustomFieldHelper::isAllowedKey($cfKey);

        $baseQuery = Product::query()
            ->where('store_id', $selectedStore->id)
            ->with([
                'store:id,name,currency',
                'brand:id,name',
                'tags:id,name,color',
                'categories:id,name,store_id',
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variationTypes.options:id,variation_type_id,value,sort_order',
                'variants.options:id,variation_type_id,value',
                'variants:id,product_id,sku,price,compare_at_price,stock,stock_alert',
                'variants.linkedCatalogImage:id,product_id,product_variant_id,image_path,status,sort_order,is_primary',
            ])
            ->withSum('variants', 'stock')
            ->withMax('variants', 'stock_alert');

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('products.meta', 'like', '%' . $search . '%')
                    ->orWhereHas('categories', static function ($q) use ($search): void {
                        $q->where('categories.name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($cfFilterActive) {
            ProductCustomFieldHelper::metaJsonContainsCustomField($baseQuery, $cfKey, $cfValue);
        }

        if ($taxonomyCategoryFilterId !== null) {
            $baseQuery->whereHas('categories', fn ($query) => $query->where('categories.id', $taxonomyCategoryFilterId));
        }

        if ($productTypeFilter !== '') {
            $baseQuery->where('product_type', $productTypeFilter);
        }

        if ($status === 'published') {
            $baseQuery->where('status', true);
        } elseif ($status === 'draft') {
            $baseQuery->where('status', false);
        }

        if ($brandFilterId !== null) {
            $baseQuery->where('brand_id', $brandFilterId);
        }

        if ($tagFilterId !== null) {
            $baseQuery->whereHas('tags', fn ($query) => $query->where('tags.id', $tagFilterId));
        }

        if ($stockFilter === 'low') {
            $baseQuery->whereHas('variants', function ($query) {
                $query->whereColumn('stock', '<=', 'stock_alert')
                    ->where('stock', '>', 0);
            });
        } elseif ($stockFilter === 'out') {
            $baseQuery->where(function ($query) {
                $query->whereDoesntHave('variants')
                    ->orWhereHas('variants', fn($variantQuery) => $variantQuery->selectRaw('product_id')->groupBy('product_id')->havingRaw('SUM(stock) = 0'));
            });
        }

        $productsQuery = clone $baseQuery;

        switch ($sort) {
            case 'name':
                $productsQuery->orderBy('name');
                break;
            case 'price_high':
                $productsQuery->orderByDesc('base_price');
                break;
            case 'price_low':
                $productsQuery->orderBy('base_price');
                break;
            case 'stock_high':
                $productsQuery->orderByDesc('variants_sum_stock');
                break;
            case 'stock_low':
                $productsQuery->orderBy('variants_sum_stock');
                break;
            default:
                $productsQuery->latest('id');
                break;
        }

        if ($request->query('export') === 'csv') {
            $exportProducts = $productsQuery->get();

            return response()->streamDownload(function () use ($exportProducts) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Store', 'Product', 'SKU', 'Brand', 'Categories', 'Product type', 'Status', 'Base Price', 'Inventory']);

                foreach ($exportProducts as $product) {
                    $inventory = (int) ($product->variants_sum_stock ?? 0);
                    $taxonomyNames = $product->categories->pluck('name')->filter()->implode('; ');
                    fputcsv($handle, [
                        $product->store?->name,
                        $product->name,
                        $product->sku,
                        $product->brand?->name ?? '',
                        $taxonomyNames,
                        $product->product_type,
                        $product->status ? 'Published' : 'Draft',
                        number_format((float) $product->base_price, 2, '.', ''),
                        $inventory,
                    ]);
                }

                fclose($handle);
            }, 'products-export.csv');
        }

        $statsQuery = clone $baseQuery;
        $statsProducts = $statsQuery->get();

        $defaultProductTypes = ['physical', 'digital', 'service', 'subscription', 'virtual'];
        $productTypesInStats = $statsProducts
            ->pluck('product_type')
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $productTypeFilterOptions = collect($defaultProductTypes)
            ->merge($productTypesInStats)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $type): array => [
                $type => Str::title(str_replace(['-', '_'], ' ', $type)),
            ])
            ->all();

        $distinctTaxonomyCategoryCount = $statsProducts
            ->pluck('categories')
            ->flatten()
            ->unique('id')
            ->count();

        $products = $productsQuery->paginate(10)->withQueryString();

        $bulkSelectableProductIds = (clone $baseQuery)
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $totalProducts = $statsProducts->count();
        $outOfStockCount = $statsProducts->filter(fn(Product $product) => (int) ($product->variants_sum_stock ?? 0) === 0)->count();
        $lowStockCount = $statsProducts->filter(function (Product $product): bool {
            $inventory = (int) ($product->variants_sum_stock ?? 0);
            $alertLevel = (int) ($product->variants_max_stock_alert ?? ($product->meta['stock_alert'] ?? 0));

            return $inventory > 0 && $inventory <= max($alertLevel, 0);
        })->count();
        $distinctProductTypeCount = $statsProducts->pluck('product_type')->filter()->unique()->count();

        $catalogTaxonomyCategories = $selectedStore->categories()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $managementCategories = $selectedStore->categories()
            ->withCount('products')
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $catalogBrands = $selectedStore->brands()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $managementBrands = $selectedStore->brands()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $catalogTags = $selectedStore->tags()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $managementTags = $selectedStore->tags()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $brandCount = $selectedStore->brands()->count();
        $activeBrandFilter = $brandFilterId !== null
            ? $catalogBrands->firstWhere('id', $brandFilterId)
            : null;

        $activeTagFilter = $tagFilterId !== null
            ? $catalogTags->firstWhere('id', $tagFilterId)
            : null;

        $activeTaxonomyCategoryFilter = $taxonomyCategoryFilterId !== null
            ? $catalogTaxonomyCategories->firstWhere('id', $taxonomyCategoryFilterId)
            : null;

        $currentUserStoreRole = $request->user()->roleInStore($selectedStore);

        $settings = is_array($selectedStore->settings) ? $selectedStore->settings : [];
        $catalogSettings = is_array($settings['catalog'] ?? null) ? $settings['catalog'] : [];
        $rawListKeys = is_array($catalogSettings['product_list_detail_keys'] ?? null)
            ? $catalogSettings['product_list_detail_keys']
            : [];
        $productListDetailKeys = array_values(array_filter(
            array_slice(array_map('strval', $rawListKeys), 0, 2),
            static fn (string $k): bool => $k !== '' && ProductCustomFieldHelper::isValidKey($k) && ProductCustomFieldHelper::isAllowedKey($k)
        ));

        $detectedCatalogCustomFieldKeys = ProductCustomFieldHelper::detectCustomFieldKeysForStore((int) $selectedStore->id);
        $mergedHighlightKeys = array_values(array_unique(array_merge(
            $detectedCatalogCustomFieldKeys,
            $productListDetailKeys
        )));
        $catalogCustomFieldKeyOptions = ProductCustomFieldHelper::keyOptionsForSelect($mergedHighlightKeys);

        return view('user_view.products', [
            'selectedStore' => $selectedStore,
            'stores' => $stores,
            'products' => $products,
            'catalogBrands' => $catalogBrands,
            'managementBrands' => $managementBrands,
            'catalogTags' => $catalogTags,
            'managementTags' => $managementTags,
            'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
            'managementCategories' => $managementCategories,
            'productTypeFilterOptions' => $productTypeFilterOptions,
            'currentUserStoreRole' => $currentUserStoreRole,
            'brandCount' => $brandCount,
            'activeBrandFilter' => $activeBrandFilter,
            'activeTagFilter' => $activeTagFilter,
            'activeTaxonomyCategoryFilter' => $activeTaxonomyCategoryFilter,
            'filters' => [
                'q' => $search,
                'category' => $taxonomyCategoryFilterId !== null ? (string) $taxonomyCategoryFilterId : '',
                'product_type' => $productTypeFilter,
                'status' => $status,
                'stock' => $stockFilter,
                'sort' => $sort !== '' ? $sort : 'latest',
                'brand' => $brandFilterId !== null ? (string) $brandFilterId : '',
                'tag' => $tagFilterId !== null ? (string) $tagFilterId : '',
                'cf_key' => $cfFilterActive ? $cfKey : '',
                'cf_value' => $cfFilterActive ? $cfValue : '',
            ],
            'productListDetailKeys' => $productListDetailKeys,
            'catalogCustomFieldKeyOptions' => $catalogCustomFieldKeyOptions,
            'stats' => [
                'total_products' => $totalProducts,
                'out_of_stock' => $outOfStockCount,
                'low_stock' => $lowStockCount,
                'taxonomy_labels_in_view' => $distinctTaxonomyCategoryCount,
                'product_types_in_view' => $distinctProductTypeCount,
            ],
            'bulkSelectableProductIds' => $bulkSelectableProductIds,
        ]);
    }

    public function saveProductListDetailKeys(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        $user = $request->user();
        abort_unless(
            $store && $user && $user->hasStoreRole($store, [Store::ROLE_OWNER, Store::ROLE_MANAGER]),
            403
        );

        $validated = $request->validate([
            'detail_key_1' => ['nullable', 'string', 'max:128'],
            'detail_key_2' => ['nullable', 'string', 'max:128'],
        ]);

        $keys = [];
        foreach ([$validated['detail_key_1'] ?? '', $validated['detail_key_2'] ?? ''] as $raw) {
            $k = trim((string) $raw);
            if ($k !== '' && ProductCustomFieldHelper::isValidKey($k) && ProductCustomFieldHelper::isAllowedKey($k)) {
                $keys[] = $k;
            }
        }
        $keys = array_slice(array_values(array_unique($keys)), 0, 2);

        $settings = is_array($store->settings) ? $store->settings : [];
        $catalog = is_array($settings['catalog'] ?? null) ? $settings['catalog'] : [];
        $catalog['product_list_detail_keys'] = $keys;
        $settings['catalog'] = $catalog;
        $store->update(['settings' => $settings]);

        return redirect()
            ->route('products')
            ->with('success', 'Product list highlights saved for this store.')
            ->with('success_title', 'List preferences');
    }

    public function orders(Request $request)
    {
        $selectedStore = $request->attributes->get('currentStore');
        
        $status = $request->query('status');

        $query = \App\Models\Order::query()
            ->where('store_id', $selectedStore->id)
            ->with(['customer', 'items']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->orderByDesc('placed_at')->paginate(20)->withQueryString();

        $statusCounts = \App\Models\Order::query()
            ->where('store_id', $selectedStore->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
            
        $statusCounts['all'] = array_sum($statusCounts);

        return view('user_view.orders', [
            'orders' => $orders,
            'currentStatus' => $status ?? 'all',
            'statusCounts' => $statusCounts,
            'selectedStore' => $selectedStore,
        ]);
    }

    public function orderViewDetails(Request $request, \App\Models\Order $order)
    {
        $selectedStore = $request->attributes->get('currentStore');
        
        if ($order->store_id !== $selectedStore->id) {
            abort(404);
        }

        $order->load(['items', 'customer', 'addresses', 'items.product', 'items.variant.options']);

        return view('user_view.orderViewDetails', [
            'order' => $order,
            'selectedStore' => $selectedStore,
        ]);
    }

    public function updateOrderStatus(Request $request, \App\Models\Order $order)
    {
        $selectedStore = $request->attributes->get('currentStore');
        
        if ($order->store_id !== $selectedStore->id) {
            abort(404);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);

        $order->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated successfully.');
    }

    public function customers(Request $request)
    {
        $selectedStore = $request->attributes->get('currentStore');

        $customers = \App\Models\Customer::query()
            ->where('store_id', $selectedStore->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('user_view.customers', [
            'customers' => $customers,
            'selectedStore' => $selectedStore,
        ]);
    }

    public function customersProfile(Request $request, \App\Models\Customer $customer)
    {
        $selectedStore = $request->attributes->get('currentStore');
        
        if ($customer->store_id !== $selectedStore->id) {
            abort(404);
        }

        $customer->load(['addresses', 'orders' => function($q) {
            $q->orderByDesc('placed_at')->take(5);
        }]);

        return view('user_view.customersProfileTab', [
            'customer' => $customer,
            'selectedStore' => $selectedStore,
        ]);
    }

    public function teamMembers(Request $request): RedirectResponse|View
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found. Please select a store before managing team members.']);
        }

        $members = $currentStore->members()
            ->with('role')
            ->orderByRaw("CASE store_user.role WHEN 'owner' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")
            ->orderBy('users.name')
            ->get();

        return view('user_view.team_members', [
            'selectedStore' => $currentStore,
            'members' => $members,
            'currentUserStoreRole' => $request->user()->roleInStore($currentStore),
            'memberRoleOptions' => Store::memberRoles(),
        ]);
    }

    public function analytics()
    {
        return view('user_view.analytics');
    }

    public function notifications()
    {
        return view('user_view.notifications');
    }

    public function billingSubscription()
    {
        return view('user_view.billingSubscription');
    }

    public function generalSettings()
    {
        return view('user_view.generalSettings');
    }

    public function shippingAutomation()
    {
        return view('user_view.shippingAutomation');
    }

    public function security()
    {
        return view('user_view.security');
    }

    public function profileSettings()
    {
        return view('user_view.profileSettings');
    }

    public function onboarding_StoreDetails_1()
    {
        return view('user_view.onboarding-Step1-StoreDetails');
    }

    public function onboarding_AddCustom_Category()
    {
        return view('user_view.onboarding-Step1-addCustom-Category');
    }

    public function onboarding_AddProduct_Variations()
    {
        return view('user_view.onboarding-Step2-AddProductVariations');
    }

    public function onboarding_AddProduct_VariationsPopup()
    {
        return view('user_view.onboarding-Step2-AddVariationPopup');
    }

    public function onboarding_StoreReady()
    {
        return view('user_view.onboarding-Step3-StoreReady');
    }
    public function store_management()
    {
        $stores = request()->user()->memberStores()
            ->orderBy('stores.name')
            ->withCount(['products', 'brands'])
            ->get();

        return view('user_view.store_management', compact('stores'));
    }

    public function store_products(Request $request, $storeId)
    {
        $store = $request->user()->memberStores()
            ->where('stores.id', $storeId)
            ->firstOrFail();

        $request->session()->put('current_store_id', $store->id);
        $request->attributes->set('currentStore', $store);
        view()->share('currentStore', $store);

        return redirect()->route('products');
    }

    /**
     * Lightweight polling payload for product list primary image cells.
     */
    public function productPrimaryImages(Request $request): JsonResponse
    {
        $selectedStore = $request->attributes->get('currentStore');
        abort_unless($selectedStore, 404);

        $raw = $request->query('ids', '');
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($v): int => (int) $v,
            explode(',', is_string($raw) ? $raw : '')
        ))));
        if (count($ids) > 150) {
            $ids = array_slice($ids, 0, 150);
        }
        if ($ids === []) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->where('store_id', $selectedStore->id)
            ->whereIn('id', $ids)
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')])
            ->get(['id']);

        $out = [];
        foreach ($products as $p) {
            $primary = $p->images->first();
            $state = 'none';
            $url = null;
            if ($primary) {
                if ($primary->isReady()) {
                    $state = 'ready';
                    $url = asset('storage/'.$primary->image_path);
                } elseif ($primary->isPendingVisual()) {
                    $state = 'pending';
                } elseif ($primary->isFailed()) {
                    $state = 'failed';
                }
            }
            $out[(string) $p->id] = ['state' => $state, 'url' => $url];
        }

        return response()->json(['products' => $out]);
    }
}
