<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
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
        $category = trim((string) $request->query('category', ''));
        $status = trim((string) $request->query('status', ''));
        $stockFilter = trim((string) $request->query('stock', ''));
        $sort = trim((string) $request->query('sort', 'latest'));

        $baseQuery = Product::query()
            ->where('store_id', $selectedStore->id)
            ->with([
                'store:id,name,currency',
                'variationTypes.options:id,variation_type_id,value,sort_order',
                'variants.options:id,variation_type_id,value',
                'variants:id,product_id,sku,price,stock,stock_alert',
            ])
            ->withSum('variants', 'stock')
            ->withMax('variants', 'stock_alert');

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        if ($category !== '') {
            $baseQuery->where('product_type', $category);
        }

        if ($status === 'published') {
            $baseQuery->where('status', true);
        } elseif ($status === 'draft') {
            $baseQuery->where('status', false);
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
                fputcsv($handle, ['Store', 'Product', 'SKU', 'Category', 'Status', 'Base Price', 'Inventory']);

                foreach ($exportProducts as $product) {
                    $inventory = (int) ($product->variants_sum_stock ?? 0);
                    fputcsv($handle, [
                        $product->store?->name,
                        $product->name,
                        $product->sku,
                        ucfirst($product->product_type),
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
        $availableCategories = $statsProducts
            ->pluck('product_type')
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn(string $type): array => [$type => Str::title(str_replace(['-', '_'], ' ', $type))])
            ->all();

        $products = $productsQuery->paginate(10)->withQueryString();

        $totalProducts = $statsProducts->count();
        $outOfStockCount = $statsProducts->filter(fn(Product $product) => (int) ($product->variants_sum_stock ?? 0) === 0)->count();
        $lowStockCount = $statsProducts->filter(function (Product $product): bool {
            $inventory = (int) ($product->variants_sum_stock ?? 0);
            $alertLevel = (int) ($product->variants_max_stock_alert ?? ($product->meta['stock_alert'] ?? 0));

            return $inventory > 0 && $inventory <= max($alertLevel, 0);
        })->count();
        $activeCategoriesCount = $statsProducts->pluck('product_type')->filter()->unique()->count();

        return view('user_view.products', [
            'selectedStore' => $selectedStore,
            'stores' => $stores,
            'products' => $products,
            'filters' => [
                'q' => $search,
                'category' => $category,
                'status' => $status,
                'stock' => $stockFilter,
                'sort' => $sort !== '' ? $sort : 'latest',
            ],
            'stats' => [
                'total_products' => $totalProducts,
                'out_of_stock' => $outOfStockCount,
                'low_stock' => $lowStockCount,
                'active_categories' => $activeCategoriesCount,
            ],
            'categories' => $availableCategories,
        ]);
    }

    public function orders()
    {
        return view('user_view.orders');
    }

    public function orderViewDetails()
    {
        return view('user_view.orderViewDetails');
    }

    public function customers()
    {
        return view('user_view.customers');
    }

    public function customersProfile()
    {
        return view('user_view.customersProfileTab');
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
}
