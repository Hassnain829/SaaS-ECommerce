<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\UserSession;
use App\Services\OrderEventRecorder;
use App\Services\SecurityLogRecorder;
use App\Services\UserSessionTracker;
use App\Support\OrderLifecycle;
use App\Support\ProductCustomFieldHelper;
use App\Support\ProductTypeBehavior;
use App\Support\StorePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            app(SecurityLogRecorder::class)->record(
                $request,
                'login_throttled',
                SecurityLog::SEVERITY_WARNING,
                user: User::query()->where('email', $credentials['email'])->first(),
                metadata: ['email' => $credentials['email'], 'retry_after_seconds' => $seconds]
            );

            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please wait '.$seconds.' seconds and try again.',
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);
            app(SecurityLogRecorder::class)->record(
                $request,
                'failed_login',
                SecurityLog::SEVERITY_WARNING,
                user: User::query()->where('email', $credentials['email'])->first(),
                metadata: ['email' => $credentials['email']]
            );

            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ])->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        RateLimiter::clear($throttleKey);

        $user = $request->user();
        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This account is deactivated. Contact the store owner before signing in again.',
            ])->withInput($request->only('email'));
        }

        $user?->forceFill(['last_login_at' => now()])->save();
        app(SecurityLogRecorder::class)->record($request, 'login', user: $user);

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
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        app(SecurityLogRecorder::class)->record($request, 'account_registered', user: $user);

        return redirect()->route('onboarding-StoreDetails-1');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            app(SecurityLogRecorder::class)->record($request, 'logout');
        }

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

        $attributeTermQuery = $request->query('attribute_term');
        $attributeTermFilterId = null;
        if ($attributeTermQuery !== null && $attributeTermQuery !== '' && ctype_digit((string) $attributeTermQuery)) {
            $candidateTerm = (int) $attributeTermQuery;
            if (\App\Models\AttributeTerm::query()
                ->where('id', $candidateTerm)
                ->whereHas('attribute', fn ($query) => $query->where('store_id', $selectedStore->id))
                ->exists()) {
                $attributeTermFilterId = $candidateTerm;
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
                'productAttributes.attribute:id,store_id,name,slug,display_type,is_filterable,is_visible',
                'productAttributes.terms:id,attribute_id,name,slug,swatch_value',
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

        if ($attributeTermFilterId !== null) {
            $baseQuery->whereHas('productAttributes.terms', fn ($query) => $query->where('attribute_terms.id', $attributeTermFilterId));
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

        $defaultProductTypes = ProductTypeBehavior::types();
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

        $catalogAttributes = $selectedStore->attributes()
            ->where('is_visible', true)
            ->with(['terms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
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

        $activeAttributeTermFilter = $attributeTermFilterId !== null
            ? $catalogAttributes->flatMap(fn ($attribute) => $attribute->terms)->firstWhere('id', $attributeTermFilterId)
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
            'catalogAttributes' => $catalogAttributes,
            'managementTags' => $managementTags,
            'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
            'managementCategories' => $managementCategories,
            'productTypeFilterOptions' => $productTypeFilterOptions,
            'currentUserStoreRole' => $currentUserStoreRole,
            'brandCount' => $brandCount,
            'activeBrandFilter' => $activeBrandFilter,
            'activeTagFilter' => $activeTagFilter,
            'activeTaxonomyCategoryFilter' => $activeTaxonomyCategoryFilter,
            'activeAttributeTermFilter' => $activeAttributeTermFilter,
            'filters' => [
                'q' => $search,
                'category' => $taxonomyCategoryFilterId !== null ? (string) $taxonomyCategoryFilterId : '',
                'product_type' => $productTypeFilter,
                'status' => $status,
                'stock' => $stockFilter,
                'sort' => $sort !== '' ? $sort : 'latest',
                'brand' => $brandFilterId !== null ? (string) $brandFilterId : '',
                'tag' => $tagFilterId !== null ? (string) $tagFilterId : '',
                'attribute_term' => $attributeTermFilterId !== null ? (string) $attributeTermFilterId : '',
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
            $store && $user && $user->hasStorePermission($store, StorePermission::CATALOG_MANAGE),
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

        app(SecurityLogRecorder::class)->record(
            $request,
            'catalog_list_preferences_updated',
            store: $store,
            metadata: ['detail_keys' => $keys]
        );

        return redirect()
            ->route('products')
            ->with('success', 'Product list highlights saved for this store.')
            ->with('success_title', 'List preferences');
    }

    public function orders(Request $request)
    {
        $selectedStore = $request->attributes->get('currentStore');

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! in_array($status, OrderLifecycle::orderStatuses(), true)) {
            $status = 'all';
        }

        $query = Order::query()
            ->where('store_id', $selectedStore->id)
            ->with(['customer', 'items']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->orderByDesc('placed_at')->paginate(20)->withQueryString();

        $statusCounts = Order::query()
            ->where('store_id', $selectedStore->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $statusCounts['all'] = array_sum($statusCounts);

        return view('user_view.orders', [
            'orders' => $orders,
            'currentStatus' => $status,
            'statusCounts' => $statusCounts,
            'orderStatuses' => OrderLifecycle::orderStatuses(),
            'selectedStore' => $selectedStore,
        ]);
    }

    public function orderViewDetails(Request $request, Order $order)
    {
        $selectedStore = $request->attributes->get('currentStore');

        if ($order->store_id !== $selectedStore->id) {
            abort(404);
        }

        $order->load([
            'items',
            'customer',
            'addresses',
            'items.product.images',
            'items.variant.options.variationType',
            'events.actor',
        ]);

        return view('user_view.orderViewDetails', [
            'order' => $order,
            'orderStatuses' => OrderLifecycle::orderStatuses(),
            'selectedStore' => $selectedStore,
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $selectedStore = $request->attributes->get('currentStore');

        if ($order->store_id !== $selectedStore->id) {
            abort(404);
        }

        abort_unless($request->user()?->canManageOrders($selectedStore), 403);

        $request->validate([
            'status' => ['required', 'string', Rule::in(OrderLifecycle::orderStatuses())],
        ]);

        $previousStatus = (string) $order->status;
        $newStatus = (string) $request->status;

        if ($previousStatus === $newStatus) {
            return back()->with('success', 'Order status is already '.OrderLifecycle::orderStatusLabel($newStatus).'.');
        }

        if (! OrderLifecycle::canTransitionOrderStatus($previousStatus, $newStatus)) {
            return back()->withErrors([
                'status' => 'This order cannot move from '.OrderLifecycle::orderStatusLabel($previousStatus).' to '.OrderLifecycle::orderStatusLabel($newStatus).'.',
            ]);
        }

        DB::transaction(function () use ($order, $request, $previousStatus, $newStatus): void {
            $updates = [
                'status' => $newStatus,
                'updated_by' => $request->user()?->id,
            ];

            if ($newStatus === OrderLifecycle::ORDER_CONFIRMED && ! $order->confirmed_at) {
                $updates['confirmed_at'] = now();
            }

            if ($newStatus === OrderLifecycle::ORDER_CANCELLED) {
                $updates['cancelled_at'] = now();
            }

            if ($newStatus === OrderLifecycle::ORDER_REFUNDED) {
                $updates['refunded_at'] = now();
            }

            if ($newStatus === OrderLifecycle::ORDER_COMPLETED) {
                $updates['closed_at'] = now();
            }

            $order->update($updates);

            app(OrderEventRecorder::class)->record(
                $order,
                OrderLifecycle::EVENT_ORDER_STATUS_CHANGED,
                'Order status changed',
                'Order status changed from '.OrderLifecycle::orderStatusLabel($previousStatus).' to '.OrderLifecycle::orderStatusLabel($newStatus).'.',
                [
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                ],
                $request->user()
            );

            $terminalEvents = [
                OrderLifecycle::ORDER_CANCELLED => [
                    OrderLifecycle::EVENT_ORDER_CANCELLED,
                    'Order cancelled',
                    'The order was cancelled.',
                ],
                OrderLifecycle::ORDER_COMPLETED => [
                    OrderLifecycle::EVENT_ORDER_COMPLETED,
                    'Order completed',
                    'The order was marked completed.',
                ],
                OrderLifecycle::ORDER_REFUNDED => [
                    OrderLifecycle::EVENT_ORDER_REFUNDED,
                    'Order refunded',
                    'The order was marked refunded.',
                ],
            ];

            if (isset($terminalEvents[$newStatus])) {
                [$eventType, $title, $description] = $terminalEvents[$newStatus];

                app(OrderEventRecorder::class)->record(
                    $order,
                    $eventType,
                    $title,
                    $description,
                    ['status' => $newStatus],
                    $request->user()
                );
            }
        });

        app(SecurityLogRecorder::class)->record(
            $request,
            'order_status_changed',
            store: $selectedStore,
            metadata: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ]
        );

        return back()->with('success', 'Order status updated to '.OrderLifecycle::orderStatusLabel($newStatus).'.');
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

    public function security(Request $request, UserSessionTracker $sessionTracker)
    {
        $user = $request->user();
        $selectedStore = $request->attributes->get('currentStore');
        $currentSession = $sessionTracker->touch($request);

        $sessions = UserSession::query()
            ->where('user_id', $user->id)
            ->orderByRaw('revoked_at IS NULL DESC')
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get();

        $securityLogs = SecurityLog::query()
            ->with(['store:id,name', 'user:id,name,email', 'targetUser:id,name,email'])
            ->where(function ($query) use ($user, $selectedStore): void {
                $query->where('user_id', $user->id);

                if ($selectedStore) {
                    $query->orWhere('store_id', $selectedStore->id);
                }
            })
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('user_view.security', [
            'selectedStore' => $selectedStore,
            'sessions' => $sessions,
            'currentSessionId' => $currentSession?->id,
            'securityLogs' => $securityLogs,
        ]);
    }

    public function revokeUserSession(Request $request, UserSession $userSession, UserSessionTracker $sessionTracker): RedirectResponse
    {
        abort_unless((int) $userSession->user_id === (int) $request->user()->id, 404);

        if ($userSession->session_id === $sessionTracker->sessionId($request)) {
            return back()->withErrors(['session' => 'You are using this session right now. Use Logout when you want to leave this device.']);
        }

        $sessionTracker->revoke($userSession);

        app(SecurityLogRecorder::class)->record(
            $request,
            'user_session_revoked',
            targetUser: $request->user(),
            metadata: [
                'session_record_id' => $userSession->id,
                'browser' => $userSession->browser,
                'os' => $userSession->os,
            ]
        );

        return back()
            ->with('success', 'That session was signed out.')
            ->with('success_title', 'Session revoked');
    }

    public function profileSettings(Request $request)
    {
        $user = $request->user();
        $stores = $user->memberStores()
            ->orderBy('stores.name')
            ->get();

        return view('user_view.profileSettings', [
            'profileUser' => $user,
            'memberStores' => $stores,
            'selectedStore' => $request->attributes->get('currentStore'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldEmail = (string) $user->email;
        $avatar = $user->avatar;
        if ($request->hasFile('avatar')) {
            if ($avatar) {
                Storage::disk('public')->delete($avatar);
            }
            $avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'avatar' => $avatar,
        ]);

        if ($oldEmail !== $validated['email']) {
            $user->email_verified_at = null;
        }

        $user->save();

        app(SecurityLogRecorder::class)->record(
            $request,
            'profile_updated',
            metadata: ['email_changed' => $oldEmail !== $validated['email']]
        );

        return back()
            ->with('success', 'Profile updated.')
            ->with('success_title', 'Account saved');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        app(SecurityLogRecorder::class)->record($request, 'password_changed');

        return back()
            ->with('success', 'Password changed.')
            ->with('success_title', 'Account secured');
    }

    public function deactivateAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_deactivation' => ['required', 'string', 'in:deactivate'],
        ]);

        $user = $request->user();
        $blockingStore = $this->storeWhereUserIsLastOwner($user);
        if ($blockingStore) {
            return back()->withErrors([
                'confirm_deactivation' => "Transfer ownership of {$blockingStore->name} before deactivating your account.",
            ]);
        }

        app(SecurityLogRecorder::class)->record(
            $request,
            'account_deactivated',
            SecurityLog::SEVERITY_WARNING,
            metadata: ['confirmation' => $validated['confirm_deactivation']]
        );

        $user->forceFill(['is_active' => false])->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('signin')
            ->withErrors(['email' => 'Your account has been deactivated.']);
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

    private function storeWhereUserIsLastOwner(User $user): ?Store
    {
        $ownedStores = $user->memberStores()
            ->wherePivot('role', Store::ROLE_OWNER)
            ->get();

        foreach ($ownedStores as $store) {
            $hasAnotherOwner = $store->members()
                ->wherePivot('role', Store::ROLE_OWNER)
                ->where('users.id', '!=', $user->id)
                ->exists();

            if (! $hasAnotherOwner) {
                return $store;
            }
        }

        return null;
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
