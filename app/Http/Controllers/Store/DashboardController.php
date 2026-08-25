<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarrierAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\DraftOrder;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\PaymentProviderAccount;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\Tag;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Currency\ReportingMoneyConverter;
use App\Services\CustomerMetricsService;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Services\OrderEventRecorder;
use App\Services\ReturnService;
use App\Services\SecurityLogRecorder;
use App\Services\Store\StoreCurrencyChangeGuard;
use App\Services\UserSessionTracker;
use App\Support\OrderLifecycle;
use App\Support\ProductCustomFieldHelper;
use App\Support\ProductEditPayload;
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

        $defaultHome = $user?->role?->name === 'admin'
            ? route('admin-dashboard')
            : route('dashboard');

        return redirect()->intended($defaultHome);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'terms' => ['accepted'],
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

        $status = 'Account created. Check your email for a verification link before you continue.';

        try {
            // sendNow so SMTP failures stay inside this try/catch even when the notification is queueable
            // and the sync driver uses after-commit dispatch.
            $user->notifyNow(new \App\Notifications\QueuedVerifyEmail);
        } catch (\Throwable $exception) {
            report($exception);
            $status = 'Account created. We could not send the verification email yet. Use Resend on the next screen.';
        }

        return redirect()
            ->route('verification.notice')
            ->with('status', $status);
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

    public function index(Request $request): View
    {
        $store = $request->attributes->get('currentStore');

        return view('user_view.dashboard', [
            'dashboard' => $this->merchantDashboardSnapshot($store),
        ]);
    }

    /**
     * Store-scoped metrics for the merchant dashboard home.
     *
     * @return array<string, mixed>
     */
    protected function merchantDashboardSnapshot(?Store $store): array
    {
        if (! $store instanceof Store) {
            return ['has_store' => false];
        }

        $storeId = $store->id;
        $since30 = now()->subDays(30);
        $since7 = now()->subDays(7);
        $excludeStatuses = [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED];
        $activeStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
        ];

        $ordersBase = Order::query()->where('store_id', $storeId);
        $storeCurrency = strtoupper((string) ($store->currency ?: 'USD'));
        $reporting = app(ReportingMoneyConverter::class);

        $revenue30d = 0.0;
        (clone $ordersBase)
            ->whereNotIn('status', $excludeStatuses)
            ->where(function ($q) use ($since30): void {
                $q->where(function ($q2) use ($since30): void {
                    $q2->whereNotNull('placed_at')->where('placed_at', '>=', $since30);
                })->orWhere(function ($q2) use ($since30): void {
                    $q2->whereNull('placed_at')->where('created_at', '>=', $since30);
                });
            })
            ->get(['grand_total', 'currency_code'])
            ->each(function (Order $order) use (&$revenue30d, $reporting, $storeCurrency): void {
                $revenue30d += $reporting->convert(
                    $order->grand_total,
                    (string) ($order->currency_code ?: 'USD'),
                    $storeCurrency
                );
            });

        $orders30dCount = (clone $ordersBase)
            ->whereNotIn('status', $excludeStatuses)
            ->where(function ($q) use ($since30): void {
                $q->where(function ($q2) use ($since30): void {
                    $q2->whereNotNull('placed_at')->where('placed_at', '>=', $since30);
                })->orWhere(function ($q2) use ($since30): void {
                    $q2->whereNull('placed_at')->where('created_at', '>=', $since30);
                });
            })
            ->count();

        $activeOrdersCount = (clone $ordersBase)
            ->whereIn('status', $activeStatuses)
            ->count();

        $customersCount = Customer::query()->where('store_id', $storeId)->count();
        $customersNew30d = Customer::query()
            ->where('store_id', $storeId)
            ->where('created_at', '>=', $since30)
            ->count();

        $productsCount = Product::query()->where('store_id', $storeId)->count();

        $ordersLast7 = (clone $ordersBase)
            ->whereNotIn('status', $excludeStatuses)
            ->where(function ($q) use ($since7): void {
                $q->where(function ($q2) use ($since7): void {
                    $q2->whereNotNull('placed_at')->where('placed_at', '>=', $since7);
                })->orWhere(function ($q2) use ($since7): void {
                    $q2->whereNull('placed_at')->where('created_at', '>=', $since7);
                });
            })
            ->get(['placed_at', 'created_at', 'grand_total', 'currency_code']);

        $chartDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $chartDays[$day->format('Y-m-d')] = [
                'label' => $day->format('D'),
                'total' => 0.0,
            ];
        }
        foreach ($ordersLast7 as $order) {
            $dt = $order->placed_at ?? $order->created_at;
            if (! $dt) {
                continue;
            }
            $key = $dt->clone()->startOfDay()->format('Y-m-d');
            if (! isset($chartDays[$key])) {
                continue;
            }
            $chartDays[$key]['total'] += $reporting->convert(
                $order->grand_total,
                (string) ($order->currency_code ?: 'USD'),
                $storeCurrency
            );
        }
        $chartDays = array_values($chartDays);

        $recentOrders = (clone $ordersBase)
            ->orderByDesc(DB::raw('COALESCE(placed_at, created_at)'))
            ->limit(6)
            ->get(['id', 'order_number', 'status', 'grand_total', 'currency_code', 'placed_at', 'created_at']);

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $storeId)
            ->whereNotIn('orders.status', $excludeStatuses)
            ->where(function ($q) use ($since30): void {
                $q->where(function ($q2) use ($since30): void {
                    $q2->whereNotNull('orders.placed_at')->where('orders.placed_at', '>=', $since30);
                })->orWhere(function ($q2) use ($since30): void {
                    $q2->whereNull('orders.placed_at')->where('orders.created_at', '>=', $since30);
                });
            })
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id', 'orders.currency_code')
            ->orderByDesc(DB::raw('SUM(order_items.total)'))
            ->limit(20)
            ->get([
                'order_items.product_id',
                'orders.currency_code',
                DB::raw('MAX(COALESCE(products.name, order_items.product_name)) as display_name'),
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total) as revenue'),
            ])
            ->groupBy('product_id')
            ->map(function ($rows) use ($reporting, $storeCurrency) {
                $first = $rows->first();
                $revenue = 0.0;
                $units = 0.0;
                foreach ($rows as $row) {
                    $units += (float) $row->units_sold;
                    $revenue += $reporting->convert(
                        $row->revenue,
                        (string) ($row->currency_code ?: 'USD'),
                        $storeCurrency
                    );
                }

                return (object) [
                    'product_id' => $first->product_id,
                    'display_name' => $first->display_name,
                    'units_sold' => $units,
                    'revenue' => $revenue,
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        $activeLocationsCount = $store->locations()->where('is_active', true)->count();
        $activeDeliveryAreasCount = $store->shippingZones()->where('is_active', true)->count();
        $checkoutDeliveryOptionsCount = $store->shippingMethods()
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->count();
        $connectedProvidersCount = $store->carrierAccounts()
            ->whereIn('connection_status', [
                CarrierAccount::CONNECTION_CONNECTED,
                CarrierAccount::CONNECTION_SANDBOX_PLATFORM_FALLBACK,
            ])
            ->count();
        $taxSetting = $store->taxSetting()->first();
        $taxRatesCount = $store->taxRates()->where('is_active', true)->count();
        $taxReady = (bool) ($taxSetting?->enabled) && $taxRatesCount > 0;

        return [
            'has_store' => true,
            'store' => $store,
            'currency' => $storeCurrency,
            'revenue_30d' => $revenue30d,
            'orders_30d_count' => $orders30dCount,
            'active_orders_count' => $activeOrdersCount,
            'customers_count' => $customersCount,
            'customers_new_30d' => $customersNew30d,
            'products_count' => $productsCount,
            'chart_days' => $chartDays,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'setup_progress' => [
                'location' => [
                    'ready' => $activeLocationsCount > 0,
                    'count' => $activeLocationsCount,
                ],
                'tax' => [
                    'ready' => $taxReady,
                    'count' => $taxRatesCount,
                ],
                'delivery' => [
                    'ready' => $activeDeliveryAreasCount > 0 && $checkoutDeliveryOptionsCount > 0,
                    'areas_count' => $activeDeliveryAreasCount,
                    'options_count' => $checkoutDeliveryOptionsCount,
                    'providers_count' => $connectedProvidersCount,
                ],
            ],
        ];
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

        if ($request->boolean('openAddProduct')) {
            return redirect()->route('products.create');
        }

        $search = trim((string) $request->query('q', ''));
        $viewQuery = trim((string) $request->query('view', ''));
        $catalogView = in_array($viewQuery, ['deleted', 'archived'], true) ? 'deleted' : 'active';
        $perPageRaw = (int) $request->query('per_page', 25);
        $perPage = in_array($perPageRaw, [10, 25, 50, 100], true) ? $perPageRaw : 25;
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

        $shippingWeightFilter = trim((string) $request->query('shipping_weight', ''));
        if (! in_array($shippingWeightFilter, ['has', 'missing', 'uses_fallback'], true)) {
            $shippingWeightFilter = '';
        }

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

        if ($catalogView === 'deleted') {
            $baseQuery->onlyTrashed();
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('products.meta', 'like', '%'.$search.'%')
                    ->orWhereHas('categories', static function ($q) use ($search): void {
                        $q->where('categories.name', 'like', '%'.$search.'%');
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

        if ($shippingWeightFilter === 'has' || $shippingWeightFilter === 'missing' || $shippingWeightFilter === 'uses_fallback') {
            $baseQuery->where('requires_shipping', true);
        }

        if ($shippingWeightFilter === 'has') {
            $baseQuery->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('meta->shipping_weight')
                        ->where('meta->shipping_weight', '!=', '')
                        ->where('meta->shipping_weight', '>', 0);
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('meta->weight')
                        ->where('meta->weight', '!=', '')
                        ->where('meta->weight', '>', 0);
                });
            });
        } elseif ($shippingWeightFilter === 'missing') {
            $baseQuery->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNull('meta->shipping_weight')
                        ->orWhere('meta->shipping_weight', '')
                        ->orWhere('meta->shipping_weight', '<=', 0);
                })->where(function ($inner): void {
                    $inner->whereNull('meta->weight')
                        ->orWhere('meta->weight', '')
                        ->orWhere('meta->weight', '<=', 0);
                });
            });
        } elseif ($shippingWeightFilter === 'uses_fallback') {
            $coverage = app(\App\Services\Delivery\ShippingWeightCoverageService::class);
            $baseQuery->whereIn('id', $coverage->missingExactCoverageQuery($selectedStore)->select('products.id'));
        }

        if ($stockFilter === 'low') {
            $baseQuery->whereHas('variants', function ($query) {
                $query->whereColumn('stock', '<=', 'stock_alert')
                    ->where('stock', '>', 0)
                    ->where('stock_alert', '>', 0);
            });
        } elseif ($stockFilter === 'out') {
            $baseQuery->where(function ($query) {
                $query->whereDoesntHave('variants')
                    ->orWhereHas('variants', fn ($variantQuery) => $variantQuery->selectRaw('product_id')->groupBy('product_id')->havingRaw('SUM(stock) = 0'));
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

        $products = $productsQuery->paginate($perPage)->withQueryString();

        // All products matching the current filters (not capped) — used for "Select all matching".
        $bulkSelectableProductIds = (clone $baseQuery)
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $bulkMatchingCount = count($bulkSelectableProductIds);

        $deletedCount = Product::onlyTrashed()
            ->where('store_id', $selectedStore->id)
            ->count();

        $totalProducts = $statsProducts->count();
        $outOfStockCount = $statsProducts->filter(function (Product $product): bool {
            return \App\Support\ProductInventoryState::forProduct($product)['is_out'];
        })->count();
        $lowStockCount = $statsProducts->filter(function (Product $product): bool {
            return \App\Support\ProductInventoryState::forProduct($product)['is_low'];
        })->count();
        $distinctProductTypeCount = $statsProducts->pluck('product_type')->filter()->unique()->count();

        $catalogTaxonomyCategories = $selectedStore->categories()
            ->where('status', 'active')
            ->withCount(['products' => function ($query) use ($catalogView) {
                // Match the Products / Deleted tab so counts reflect the list being filtered.
                if ($catalogView === 'deleted') {
                    $query->onlyTrashed();
                }
            }])
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
                'shipping_weight' => $shippingWeightFilter,
                'view' => $catalogView,
                'per_page' => $perPage,
            ],
            'shippingWeightUnit' => app(\App\Services\Delivery\StoreShippingPreferences::class)->weightUnitLabel($selectedStore),
            'shippingWeightMax' => app(\App\Services\Delivery\StoreShippingPreferences::class)->maxItemWeightForStore($selectedStore),
            'shippingWeightFallback' => app(\App\Services\Delivery\StoreShippingPreferences::class)->fallbackItemWeight($selectedStore),
            'catalogView' => $catalogView,
            'deletedCount' => $deletedCount,
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
            'bulkMatchingCount' => $bulkMatchingCount,
        ]);
    }

    public function createProduct(Request $request): View|RedirectResponse
    {
        $stores = $request->attributes->get('availableStores')
            ?? $request->user()->memberStores()->orderBy('stores.name')->get();
        $selectedStore = $request->attributes->get('currentStore');

        if (! $selectedStore) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No accessible store was found for your account. Create a store or ask for access first.']);
        }

        $user = $request->user();
        abort_unless($user && $user->hasStorePermission($selectedStore, StorePermission::CATALOG_MANAGE), 403);

        $catalogTaxonomyCategories = $selectedStore->categories()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $catalogBrands = $selectedStore->brands()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $catalogTags = $selectedStore->tags()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $catalogAttributes = $selectedStore->attributes()
            ->with(['terms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /** @var array<string, mixed> $old */
        $old = $request->session()->get('_old_input', []);
        $createProductPayload = ProductEditPayload::withFormOldForCreate(
            $selectedStore,
            $old,
            (bool) ($selectedStore->taxSetting?->default_product_taxable ?? true)
        );

        return view('user_view.product_create', [
            'selectedStore' => $selectedStore,
            'stores' => $stores,
            'catalogBrands' => $catalogBrands,
            'catalogTags' => $catalogTags,
            'catalogTaxonomyCategories' => $catalogTaxonomyCategories,
            'catalogAttributes' => $catalogAttributes,
            'createProductPayload' => $createProductPayload,
            'taxSetting' => $selectedStore->taxSetting,
            'shippingPreferences' => app(\App\Services\Delivery\StoreShippingPreferences::class)->get($selectedStore),
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
        $search = trim((string) $request->query('q', ''));

        $query = Order::query()
            ->where('store_id', $selectedStore->id)
            ->with(['customer', 'items']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('external_order_number', 'like', '%'.$search.'%')
                    ->orWhere('payment_reference', 'like', '%'.$search.'%')
                    ->orWhere('meta', 'like', '%'.$search.'%')
                    ->orWhere('customer_email', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                        $customerQuery->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $orders = $query
            ->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $draftQuery = DraftOrder::query()
            ->where('store_id', $selectedStore->id)
            ->whereIn('status', [DraftOrder::STATUS_DRAFT, DraftOrder::STATUS_CANCELLED])
            ->with('customer')
            ->withCount('items');

        if ($search !== '') {
            $draftQuery->where(function ($inner) use ($search): void {
                $inner->where('draft_number', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                        $customerQuery->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $draftOrders = $draftQuery
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'cancelled' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $statusCounts = Order::query()
            ->where('store_id', $selectedStore->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $statusCounts['all'] = array_sum($statusCounts);

        return view('user_view.orders', [
            'orders' => $orders,
            'draftOrders' => $draftOrders,
            'currentStatus' => $status,
            'statusCounts' => $statusCounts,
            'orderStatuses' => OrderLifecycle::orderStatuses(),
            'selectedStore' => $selectedStore,
            'search' => $search,
            'canManageOrders' => $request->user()?->canManageOrders($selectedStore) ?? false,
        ]);
    }

    public function orderViewDetails(Request $request, Order $order)
    {
        $selectedStore = $request->attributes->get('currentStore');

        if ($order->store_id !== $selectedStore->id) {
            abort(404);
        }

        $order->load([
            'items.shipmentItems.shipment',
            'customer',
            'addresses',
            'items.product.images',
            'items.variant.options.variationType',
            'events.actor',
            'shipments.items.orderItem',
            'shipments.carrierAccount.carrier',
            'shipments.shippingMethod',
            'shipments.originLocation',
            'shipments.shippedBy',
            'taxLines',
            'returns.items.orderItem',
            'returns.reason',
            'returns.requestedBy',
            'returns.approvedBy',
            'returns.receivedBy',
            'refunds.items',
            'refunds.adjustments',
            'exchanges.items',
        ]);

        $channelOwnership = app(\App\Services\Channels\ChannelOwnershipService::class);
        $returnService = app(ReturnService::class);
        $refundService = app(\App\Services\RefundService::class);
        $exchangeService = app(\App\Services\ExchangeService::class);

        $returnEligibility = $returnService->eligibilityForReturn($order);
        $exchangeEligibility = $exchangeService->eligibilityForExchange($order, $selectedStore);

        return view('user_view.orderViewDetails', [
            'order' => $order,
            'taxDisplay' => \App\Support\Tax\TaxDisplayPresenter::forOrder($order),
            'orderStatuses' => OrderLifecycle::orderStatuses(),
            'selectedStore' => $selectedStore,
            'isOrderExternallyManaged' => $channelOwnership->isOrderExternallyManaged($order),
            'externalFulfillmentSnapshot' => data_get($order->meta, 'fulfillment', []),
            'externalShipmentsMeta' => data_get($order->meta, 'external_shipments', []),
            'fulfillmentLocations' => $selectedStore->locations()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'carrierAccounts' => $selectedStore->carrierAccounts()
                ->with('carrier')
                ->whereIn('status', [
                    \App\Models\CarrierAccount::STATUS_ENABLED,
                    \App\Models\CarrierAccount::STATUS_INTERNAL_ONLY,
                ])
                ->orderBy('display_name')
                ->get()
                ->reject(fn ($account) => $account->isFedEx() && $account->usesFedExIntegratorProvider())
                ->values(),
            'shippingMethods' => $selectedStore->shippingMethods()
                ->with(['shippingZone', 'carrierAccount.carrier'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->reject(fn ($method) => method_exists($method, 'isFedExLiveRateMethod') && $method->isFedExLiveRateMethod())
                ->values(),
            'remainingFulfillmentQuantities' => app(FulfillmentStatusService::class)->remainingQuantities($order),
            'returnReasons' => $returnService->activeReasonsForStore($selectedStore),
            'remainingReturnableQuantities' => $returnService->remainingReturnableQuantities($order),
            'returnableItems' => $returnEligibility['returnable_items'],
            'canRecordReturn' => $returnEligibility['eligible'],
            'returnEligibilityMessage' => $returnEligibility['reason'],
            'remainingRefundableAmount' => $refundService->remainingRefundableAmount($order),
            'exchangeableItems' => $exchangeEligibility['exchangeable_items'],
            'remainingExchangeableQuantities' => $exchangeService->remainingExchangeableQuantities($order),
            'canCreateExchange' => $exchangeEligibility['eligible'],
            'exchangeEligibilityMessage' => $exchangeEligibility['reason'],
            'exchangeVariants' => $exchangeEligibility['replacement_variants'],
            'fedExActiveAccount' => app(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::class)
                ->resolveActiveModelAAccount($selectedStore),
            'shippingPackagePresets' => \Illuminate\Support\Facades\Schema::hasTable('shipping_package_presets')
                ? $selectedStore->shippingPackagePresets()
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
                : collect(),
            'shippingPreferences' => app(\App\Services\Delivery\StoreShippingPreferences::class)->get($selectedStore),
            'fedExTradeDocuments' => \App\Models\FedExTradeDocument::query()
                ->where('store_id', $selectedStore->id)
                ->where('order_id', $order->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'shipment_id', 'document_type', 'status', 'fedex_document_id', 'destination_country_code', 'uploaded_at', 'created_at']),
            'fedExOrderApiEvents' => \App\Models\CarrierApiEvent::query()
                ->where('store_id', $selectedStore->id)
                ->where('provider', \App\Models\CarrierAccount::PROVIDER_FEDEX)
                ->where(function ($q) use ($order): void {
                    $q->where('response_summary->order_id', $order->id)
                        ->orWhere('request_summary->order_id', $order->id);
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'action', 'status', 'error_code', 'response_summary', 'created_at']),
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

        if ($newStatus === OrderLifecycle::ORDER_REFUNDED) {
            return back()->withErrors([
                'status' => 'Marking an order refunded from status alone is not available. Use a successful refund when refunds are enabled.',
            ]);
        }

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

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $tagId = (int) $request->query('tag', 0);

        $query = Customer::query()
            ->where('store_id', $selectedStore->id)
            ->with('tags');

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($tagId > 0) {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery
                ->where('customer_tags.store_id', $selectedStore->id)
                ->where('customer_tags.id', $tagId));
        }

        $customers = $query
            ->orderByDesc('last_order_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $statusCounts = Customer::query()
            ->where('store_id', $selectedStore->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
        $statusCounts['all'] = array_sum($statusCounts);

        $customerTags = CustomerTag::query()
            ->where('store_id', $selectedStore->id)
            ->orderBy('name')
            ->get();

        return view('user_view.customers', [
            'customers' => $customers,
            'selectedStore' => $selectedStore,
            'search' => $search,
            'currentStatus' => $status,
            'currentTagId' => $tagId,
            'statusCounts' => $statusCounts,
            'customerTags' => $customerTags,
            'canManageOrders' => $request->user()?->canManageOrders($selectedStore) ?? false,
        ]);
    }

    public function customersProfile(Request $request, Customer $customer, CustomerMetricsService $metrics)
    {
        $selectedStore = $request->attributes->get('currentStore');

        if ($customer->store_id !== $selectedStore->id) {
            abort(404);
        }

        $metrics->recalculate($customer);

        $customer->load([
            'addresses' => fn ($query) => $query->orderByDesc('is_default')->orderBy('type')->orderBy('id'),
            'profileNotes.user',
            'tags',
            'orders' => function ($q) {
                $q->with(['items', 'returns', 'refunds', 'exchanges'])
                    ->orderByDesc('placed_at')
                    ->orderByDesc('created_at')
                    ->take(10);
            },
        ]);

        $customerReturns = \App\Models\OrderReturn::query()
            ->where('store_id', $selectedStore->id)
            ->where('customer_id', $customer->id)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $customerRefunds = \App\Models\Refund::query()
            ->where('store_id', $selectedStore->id)
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $customerExchanges = \App\Models\Exchange::query()
            ->where('store_id', $selectedStore->id)
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('user_view.customersProfileTab', [
            'customer' => $customer,
            'selectedStore' => $selectedStore,
            'canManageCustomers' => $request->user()?->canManageCustomers($selectedStore) ?? false,
            'customerReturns' => $customerReturns,
            'customerRefunds' => $customerRefunds,
            'customerExchanges' => $customerExchanges,
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
        return redirect()
            ->route('dashboard')
            ->with('success', 'Analytics will appear here when store-scoped reports are available. Demo metrics have been removed.');
    }

    public function billingSubscription()
    {
        return redirect()
            ->route('dashboard')
            ->with('success', 'SaaS billing is not part of the current merchant experience and has been hidden.');
    }

    public function generalSettings(Request $request)
    {
        $selectedStore = $request->attributes->get('currentStore');
        $defaultLocation = null;
        $storeLocations = collect();
        $requiresCatalogConversion = false;
        $user = $request->user();

        if ($selectedStore) {
            // Read-only: do not create or mutate locations on GET.
            $storeLocations = $selectedStore->locations()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'address_line1', 'city', 'state', 'postal_code', 'country_code', 'is_default']);

            $defaultLocation = $storeLocations->firstWhere('is_default', true)
                ?? $storeLocations->first();

            $requiresCatalogConversion = app(StoreCurrencyChangeGuard::class)
                ->requiresCatalogConversion($selectedStore);
        }

        $settingsTab = $request->query('tab') === 'account' ? 'account' : 'store';

        return view('user_view.generalSettings', [
            'selectedStore' => $selectedStore,
            'defaultLocation' => $defaultLocation,
            'storeLocations' => $storeLocations,
            'requiresCatalogConversion' => $requiresCatalogConversion,
            'stores' => $selectedStore ? collect([$selectedStore]) : collect(),
            'profileUser' => $user,
            'memberStores' => $user
                ? $user->memberStores()->orderBy('stores.name')->get()
                : collect(),
            'settingsTab' => $settingsTab,
        ]);
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

    public function profileSettings(Request $request): RedirectResponse
    {
        return redirect()->route('generalSettings', ['tab' => 'account']);
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

        return redirect()
            ->route('generalSettings', ['tab' => 'account'])
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

        return redirect()
            ->to(route('generalSettings', ['tab' => 'account']).'#password')
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
        $user = request()->user();
        $stores = $user->memberStores()
            ->orderBy('stores.name')
            ->withCount(['products', 'brands'])
            ->get();

        $ownedClosedStoreIds = \Illuminate\Support\Facades\DB::table('store_user')
            ->where('user_id', $user->id)
            ->where('role', Store::ROLE_OWNER)
            ->pluck('store_id');

        $closedStores = $ownedClosedStoreIds->isEmpty()
            ? collect()
            : Store::onlyTrashed()
                ->whereIn('id', $ownedClosedStoreIds)
                ->orderBy('name')
                ->get(['id', 'name', 'deleted_at']);

        $storeIds = $stores->pluck('id');
        $liveStoresCount = $stores->where('onboarding_completed', true)->count();
        $draftStoresCount = $stores->where('onboarding_completed', false)->count();
        $totalProducts = (int) $stores->sum(fn (Store $store): int => (int) ($store->products_count ?? 0));
        $totalBrands = (int) $stores->sum(fn (Store $store): int => (int) ($store->brands_count ?? 0));

        $storeMetrics = $this->storeManagementMetrics($storeIds->all());
        $storesNeedingCurrencyConversion = Product::query()
            ->whereIn('store_id', $storeIds)
            ->distinct()
            ->pluck('store_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $recentActivity = $storeIds->isEmpty()
            ? collect()
            : OrderEvent::query()
                ->whereIn('store_id', $storeIds)
                ->with(['store:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'store_id', 'order_id', 'event_type', 'title', 'description', 'created_at']);

        $activeStoreId = (int) request()->session()->get('current_store_id');
        $draftStoreForNextStep = $stores->firstWhere('onboarding_completed', false);

        return view('user_view.store_management', [
            'stores' => $stores,
            'closedStores' => $closedStores,
            'storeMetrics' => $storeMetrics,
            'storesNeedingCurrencyConversion' => $storesNeedingCurrencyConversion,
            'liveStoresCount' => $liveStoresCount,
            'draftStoresCount' => $draftStoresCount,
            'totalProducts' => $totalProducts,
            'totalBrands' => $totalBrands,
            'recentActivity' => $recentActivity,
            'activeStoreId' => $activeStoreId,
            'draftStoreForNextStep' => $draftStoreForNextStep,
        ]);
    }

    /**
     * Per-store 7-day revenue, order counts, sparkline series, and operational health for hub cards.
     *
     * Health is independent of Live/Draft. "Setup needed" stays until catalog + location + tax +
     * delivery + payments readiness matches the merchant dashboard checklist.
     *
     * @param  list<int>  $storeIds
     * @return array<int, array{
     *     revenue_7d: float,
     *     orders_7d: int,
     *     orders_prev_7d: int,
     *     orders_change_pct: float|null,
     *     sparkline: list<float>,
     *     health: string,
     *     health_label: string,
     *     setup_complete: bool,
     *     setup_ready_count: int,
     *     setup_total: int
     * }>
     */
    protected function storeManagementMetrics(array $storeIds): array
    {
        // Catalog, location, tax, and delivery only — payments/billing are excluded from readiness setup %.
        $setupTotal = 4;
        $metrics = [];
        foreach ($storeIds as $storeId) {
            $metrics[(int) $storeId] = [
                'revenue_7d' => 0.0,
                'orders_7d' => 0,
                'orders_prev_7d' => 0,
                'orders_change_pct' => null,
                'sparkline' => array_fill(0, 7, 0.0),
                'health' => 'setup',
                'health_label' => 'Setup needed',
                'setup_complete' => false,
                'setup_ready_count' => 0,
                'setup_total' => $setupTotal,
            ];
        }

        if ($storeIds === []) {
            return $metrics;
        }

        $excludeStatuses = [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED];
        $since14 = now()->subDays(14)->startOfDay();
        $since7 = now()->subDays(7)->startOfDay();

        $dayKeys = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayKeys[] = now()->subDays($i)->startOfDay()->format('Y-m-d');
        }

        $orders = Order::query()
            ->whereIn('store_id', $storeIds)
            ->whereNotIn('status', $excludeStatuses)
            ->where(function ($q) use ($since14): void {
                $q->where(function ($q2) use ($since14): void {
                    $q2->whereNotNull('placed_at')->where('placed_at', '>=', $since14);
                })->orWhere(function ($q2) use ($since14): void {
                    $q2->whereNull('placed_at')->where('created_at', '>=', $since14);
                });
            })
            ->get(['id', 'store_id', 'grand_total', 'currency_code', 'placed_at', 'created_at']);

        $storeCurrencies = Store::query()
            ->whereIn('id', $storeIds)
            ->pluck('currency', 'id')
            ->map(fn ($currency) => strtoupper((string) ($currency ?: 'USD')));

        $reporting = app(ReportingMoneyConverter::class);

        foreach ($orders as $order) {
            $storeId = (int) $order->store_id;
            if (! isset($metrics[$storeId])) {
                continue;
            }

            $dt = $order->placed_at ?? $order->created_at;
            if (! $dt) {
                continue;
            }

            $inLast7 = $dt->gte($since7);
            $targetCurrency = (string) ($storeCurrencies[$storeId] ?? 'USD');
            $amount = $reporting->convert(
                $order->grand_total,
                (string) ($order->currency_code ?: 'USD'),
                $targetCurrency
            );

            if ($inLast7) {
                $metrics[$storeId]['revenue_7d'] += $amount;
                $metrics[$storeId]['orders_7d']++;
                $key = $dt->clone()->startOfDay()->format('Y-m-d');
                $idx = array_search($key, $dayKeys, true);
                if ($idx !== false) {
                    $metrics[$storeId]['sparkline'][$idx] += $amount;
                }
            } else {
                $metrics[$storeId]['orders_prev_7d']++;
            }
        }

        $productCounts = Product::query()
            ->whereIn('store_id', $storeIds)
            ->selectRaw('store_id, COUNT(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        $locationReady = Location::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->selectRaw('store_id, COUNT(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        $taxEnabled = TaxSetting::query()
            ->whereIn('store_id', $storeIds)
            ->where('enabled', true)
            ->pluck('store_id')
            ->flip();

        $taxRateCounts = \App\Models\TaxRate::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->selectRaw('store_id, COUNT(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        $zoneCounts = \App\Models\ShippingZone::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->selectRaw('store_id, COUNT(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        $checkoutMethodCounts = \App\Models\ShippingMethod::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->selectRaw('store_id, COUNT(*) as aggregate')
            ->groupBy('store_id')
            ->pluck('aggregate', 'store_id');

        foreach ($metrics as $storeId => &$row) {
            $prev = (int) $row['orders_prev_7d'];
            $curr = (int) $row['orders_7d'];
            if ($prev === 0 && $curr === 0) {
                $row['orders_change_pct'] = null;
            } elseif ($prev === 0) {
                $row['orders_change_pct'] = 100.0;
            } else {
                $row['orders_change_pct'] = round((($curr - $prev) / $prev) * 100, 1);
            }

            $readyFlags = [
                (int) ($productCounts[$storeId] ?? 0) > 0,
                (int) ($locationReady[$storeId] ?? 0) > 0,
                $taxEnabled->has($storeId) && (int) ($taxRateCounts[$storeId] ?? 0) > 0,
                (int) ($zoneCounts[$storeId] ?? 0) > 0 && (int) ($checkoutMethodCounts[$storeId] ?? 0) > 0,
            ];
            $readyCount = count(array_filter($readyFlags));
            $setupComplete = $readyCount === $setupTotal;

            $row['setup_ready_count'] = $readyCount;
            $row['setup_total'] = $setupTotal;
            $row['setup_complete'] = $setupComplete;

            if (! $setupComplete) {
                $row['health'] = 'setup';
                $row['health_label'] = 'Setup needed';
            } elseif ($curr > 0) {
                $row['health'] = 'healthy';
                $row['health_label'] = 'Selling';
            } else {
                $row['health'] = 'ready';
                $row['health_label'] = 'Ready to sell';
            }
        }
        unset($row);

        return $metrics;
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
