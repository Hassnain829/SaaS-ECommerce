<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Store;
use App\Services\Coupons\CouponService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentStore($request);

        $coupons = Coupon::query()
            ->with(['products:id,sku,name', 'categories:id,name'])
            ->withCount(['redemptions as redeemed_count' => fn ($query) => $query->where('status', 'redeemed')])
            ->forStore($store->id)
            ->latest()
            ->get();

        $storeTimezone = (string) ($store->timezone ?: 'UTC');
        $statusCounts = ['active' => 0, 'scheduled' => 0, 'expired' => 0, 'inactive' => 0];
        foreach ($coupons as $coupon) {
            $statusCounts[$coupon->merchantStatus(null, $storeTimezone)]++;
        }

        $totalRedemptions = (int) $coupons->sum('redeemed_count');
        $topCoupon = $coupons
            ->filter(fn (Coupon $coupon): bool => (int) $coupon->redeemed_count > 0)
            ->sortByDesc('redeemed_count')
            ->first();
        $spotlight = $this->portfolioSpotlight($coupons, $storeTimezone);

        return view('user_view.settings.coupons', [
            'coupons' => $coupons,
            'couponEditorPayload' => $this->editorPayload($coupons, $storeTimezone),
            'couponMetrics' => [
                'total' => $coupons->count(),
                'available' => $statusCounts['active'],
                'status_counts' => $statusCounts,
                'total_redemptions' => $totalRedemptions,
                'top_code' => $topCoupon?->code,
                'top_uses' => (int) ($topCoupon?->redeemed_count ?? 0),
                'top_share' => $totalRedemptions > 0
                    ? (int) round(((int) ($topCoupon?->redeemed_count ?? 0) / $totalRedemptions) * 100)
                    : 0,
                'spotlight' => $spotlight,
            ],
            'categories' => $store->categories()->orderBy('name')->get(['id', 'name']),
            'canManageCoupons' => $store->userHasPermission($request->user(), 'settings.discounts'),
            'currencyCode' => strtoupper((string) ($store->currency ?: 'USD')),
            'storeTimezone' => $storeTimezone,
        ]);
    }

    public function store(Request $request, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        $validated = $this->validated($request, $store);

        $coupon = $couponService->create($store, $validated, $request->user(), $request);

        return $this->workspaceRedirect($coupon, 'Coupon created.', 'Coupon created');
    }

    public function update(Request $request, Coupon $coupon, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        abort_unless((int) $coupon->store_id === (int) $store->id, 404);
        $validated = $this->validated($request, $store, $coupon);

        $couponService->update($store, $coupon, $validated, $request->user(), $request);

        return $this->workspaceRedirect($coupon, 'Coupon updated.', 'Coupon updated');
    }

    public function toggleActive(Request $request, Coupon $coupon): RedirectResponse
    {
        $store = $this->currentStore($request);
        abort_unless((int) $coupon->store_id === (int) $store->id, 404);

        $next = ! $coupon->is_active;
        $coupon->update(['is_active' => $next]);

        app(SecurityLogRecorder::class)->record(
            $request,
            $next ? 'coupon.activated' : 'coupon.deactivated',
            store: $store,
            metadata: ['coupon_id' => $coupon->id, 'code' => $coupon->code],
        );

        return $this->workspaceRedirect(
            $coupon,
            $next ? "{$coupon->code} was activated." : "{$coupon->code} was deactivated.",
            'Coupon updated',
        );
    }

    public function destroy(Request $request, Coupon $coupon, CouponService $couponService): RedirectResponse
    {
        $store = $this->currentStore($request);
        $couponService->delete($store, $coupon, $request->user(), $request);

        return redirect()
            ->route('settings.coupons.index')
            ->with('success', 'Coupon deleted. Existing order records were kept.')
            ->with('success_title', 'Coupon deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Store $store, ?Coupon $coupon = null): array
    {
        $nullable = ['starts_at', 'expires_at', 'maximum_discount_amount', 'total_usage_limit', 'per_customer_usage_limit', 'product_skus'];
        foreach ($nullable as $field) {
            if (! $request->filled($field)) {
                $request->merge([$field => null]);
            }
        }

        if (! $request->filled('minimum_order_amount')) {
            $request->merge(['minimum_order_amount' => null]);
        }

        $request->merge(['code' => Coupon::normalizeCode((string) $request->input('code'))]);

        $uniqueCode = Rule::unique('coupons', 'code')->where(
            fn ($query) => $query->where('store_id', $store->id)->whereNull('deleted_at')
        );
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

            $applies = (string) $request->input('applies', '');
            if ($applies === 'products' && ! $request->filled('product_skus') && empty($request->input('product_ids'))) {
                $validator->errors()->add('product_skus', 'Enter at least one product SKU.');
            }
            if ($applies === 'categories' && empty($request->input('category_ids'))) {
                $validator->errors()->add('category_ids', 'Choose at least one category.');
            }
        });

        $validated = $validator->validate();
        $skuProductIds = $this->productIdsFromSkus($store, (string) ($validated['product_skus'] ?? ''));
        $validated['product_ids'] = array_values(array_unique(array_merge(
            array_map('intval', $validated['product_ids'] ?? []),
            $skuProductIds,
        )));
        unset($validated['product_skus']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['minimum_order_amount'] = $validated['minimum_order_amount'] ?? 0;

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

    /**
     * @param  Collection<int, Coupon>  $coupons
     * @return array{label: string, code: ?string, detail: string}
     */
    private function portfolioSpotlight(Collection $coupons, string $timezone): array
    {
        $now = now($timezone);

        if ($coupons->isEmpty()) {
            return [
                'label' => 'Next step',
                'code' => null,
                'detail' => 'Create a code',
            ];
        }

        $ending = $coupons
            ->filter(function (Coupon $coupon) use ($now, $timezone): bool {
                $status = $coupon->merchantStatus($now, $timezone);
                $expires = $coupon->scheduleInStoreTimezone($coupon->expires_at, $timezone);

                return in_array($status, ['active', 'scheduled'], true)
                    && $expires
                    && $expires->isAfter($now);
            })
            ->sortBy(fn (Coupon $coupon): int => (int) $coupon->scheduleInStoreTimezone($coupon->expires_at, $timezone)?->getTimestamp())
            ->first();

        if ($ending) {
            $when = $ending->scheduleInStoreTimezone($ending->expires_at, $timezone);

            return [
                'label' => 'Ending soon',
                'code' => $ending->code,
                'detail' => $when?->format('M j, g:i A') ?? '',
            ];
        }

        $starting = $coupons
            ->filter(fn (Coupon $coupon): bool => $coupon->merchantStatus($now, $timezone) === 'scheduled')
            ->sortBy(fn (Coupon $coupon): int => (int) $coupon->scheduleInStoreTimezone($coupon->starts_at, $timezone)?->getTimestamp())
            ->first();

        if ($starting) {
            $when = $starting->scheduleInStoreTimezone($starting->starts_at, $timezone);

            return [
                'label' => 'Starts next',
                'code' => $starting->code,
                'detail' => $when?->format('M j, g:i A') ?? '',
            ];
        }

        $available = $coupons
            ->filter(fn (Coupon $coupon): bool => $coupon->merchantStatus($now, $timezone) === 'active')
            ->count();

        return [
            'label' => 'Ready now',
            'code' => null,
            'detail' => $available === 1 ? '1 at checkout' : $available.' at checkout',
        ];
    }

    /**
     * @param  Collection<int, Coupon>  $coupons
     * @return array<string, array<string, mixed>>
     */
    private function editorPayload(Collection $coupons, string $storeTimezone): array
    {
        return $coupons->mapWithKeys(function (Coupon $coupon) use ($storeTimezone): array {
            $productSkus = $coupon->products->pluck('sku')->filter()->values()->all();
            $categoryIds = $coupon->categories->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $applies = 'all-products';
            if ($productSkus !== []) {
                $applies = 'products';
            } elseif ($categoryIds !== []) {
                $applies = 'categories';
            }

            $starts = $coupon->scheduleInStoreTimezone($coupon->starts_at, $storeTimezone);
            $expires = $coupon->scheduleInStoreTimezone($coupon->expires_at, $storeTimezone);

            return [(string) $coupon->id => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => (float) $coupon->value,
                'min_order' => (float) $coupon->minimum_order_amount > 0 ? (float) $coupon->minimum_order_amount : null,
                'max_discount' => $coupon->maximum_discount_amount !== null ? (float) $coupon->maximum_discount_amount : null,
                'total_limit' => $coupon->total_usage_limit,
                'per_customer_limit' => $coupon->per_customer_usage_limit,
                'starts_at' => $starts?->format('Y-m-d\TH:i'),
                'expires_at' => $expires?->format('Y-m-d\TH:i'),
                'applies' => $applies,
                'skus' => $productSkus,
                'category_ids' => $categoryIds,
                'active' => (bool) $coupon->is_active,
                'status' => $coupon->merchantStatus(null, $storeTimezone),
                'redeemed_count' => (int) ($coupon->redeemed_count ?? 0),
                'update_url' => route('settings.coupons.update', $coupon),
                'toggle_url' => route('settings.coupons.toggle', $coupon),
                'delete_url' => route('settings.coupons.destroy', $coupon),
            ]];
        })->all();
    }

    private function workspaceRedirect(Coupon $coupon, string $success, string $title): RedirectResponse
    {
        return redirect()
            ->route('settings.coupons.index')
            ->with('success', $success)
            ->with('success_title', $title);
    }

    private function currentStore(Request $request): Store
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }
}
