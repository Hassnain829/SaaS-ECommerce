<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Store;
use App\Services\Coupons\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('user_view.settings.coupons', [
            'coupons' => Coupon::query()
                ->with(['products:id,sku,name', 'categories:id,name'])
                ->withCount(['redemptions as redeemed_count' => fn ($query) => $query->where('status', 'redeemed')])
                ->forStore($store->id)
                ->latest()
                ->get(),
            'categories' => $store->categories()->orderBy('name')->get(['id', 'name']),
            'canManageCoupons' => $store->userHasPermission($request->user(), 'settings.manage'),
            'currencyCode' => strtoupper((string) ($store->currency ?: 'USD')),
        ]);
    }

    public function store(Request $request, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        $validated = $this->validated($request, $store);

        $couponService->create($store, $validated, $request->user(), $request);

        return back()->with('success', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        abort_unless((int) $coupon->store_id === (int) $store->id, 404);
        $validated = $this->validated($request, $store, $coupon);

        $couponService->update($store, $coupon, $validated, $request->user(), $request);

        return back()->with('success', 'Coupon updated.');
    }

    public function destroy(Request $request, Coupon $coupon, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        $couponService->delete($store, $coupon, $request->user(), $request);

        return back()->with('success', 'Coupon deleted. Existing order records were kept.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Store $store, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => Coupon::normalizeCode((string) $request->input('code'))]);

        $uniqueCode = Rule::unique('coupons', 'code')->where(fn ($query) => $query->where('store_id', $store->id));
        if ($coupon) {
            $uniqueCode->ignore($coupon->id);
        }

        $validator = validator($request->all(), [
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', $uniqueCode],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Coupon::TYPES)],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'total_usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'per_customer_usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => [
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id)->whereNull('deleted_at')),
            ],
            'product_skus' => ['nullable', 'string', 'max:4000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('store_id', $store->id)),
            ],
        ], [
            'code.regex' => 'Use letters, numbers, hyphens, or underscores for the coupon code.',
            'code.unique' => 'This store already has a coupon with that code.',
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->input('type') === Coupon::TYPE_PERCENTAGE && (float) $request->input('value') > 100) {
                $validator->errors()->add('value', 'Percentage discount cannot be greater than 100%.');
            }

            if ($request->filled('starts_at') && $request->filled('expires_at')) {
                if (strtotime((string) $request->input('expires_at')) <= strtotime((string) $request->input('starts_at'))) {
                    $validator->errors()->add('expires_at', 'Expiry must be after the start date.');
                }
            }
        });

        $validated = $validator->validate();
        $skuProductIds = $this->productIdsFromSkus($store, (string) ($validated['product_skus'] ?? ''));
        $validated['product_ids'] = array_values(array_unique(array_merge(
            array_map('intval', $validated['product_ids'] ?? []),
            $skuProductIds,
        )));
        unset($validated['product_skus']);

        return $validated;
    }

    /**
     * @return list<int>
     */
    private function productIdsFromSkus(Store $store, string $input): array
    {
        $skus = collect(preg_split('/[,\r\n]+/', $input) ?: [])
            ->map(fn (string $sku): string => trim($sku))
            ->filter()
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->where('store_id', $store->id)
            ->whereIn('sku', $skus)
            ->get(['id', 'sku']);
        $found = $products->pluck('sku')->all();
        $missing = $skus->diff($found)->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'product_skus' => 'These product SKUs were not found in this store: '.$missing->take(5)->implode(', '),
            ]);
        }

        return $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    private function currentStore(Request $request): Store
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }
}
