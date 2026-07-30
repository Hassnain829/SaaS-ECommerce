<?php

namespace App\Services\Coupons;

use App\Data\Coupons\CouponDiscountResult;
use App\Models\Checkout;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\SecurityLogRecorder;
use App\Support\Money\CurrencyPrecision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Store $store, array $validated, User $actor, ?Request $request = null): Coupon
    {
        return DB::transaction(function () use ($store, $validated, $actor, $request): Coupon {
            $coupon = Coupon::query()->create($this->attributes($store, $validated));
            $this->syncEligibility($coupon, $validated);
            $this->recordChange($request, 'coupon.created', $store, $actor, $coupon);

            return $coupon->load(['products:id', 'categories:id']);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Store $store, Coupon $coupon, array $validated, User $actor, ?Request $request = null): Coupon
    {
        $this->ensureStoreOwns($store, $coupon);

        return DB::transaction(function () use ($store, $coupon, $validated, $actor, $request): Coupon {
            $coupon->update($this->attributes($store, $validated));
            $this->syncEligibility($coupon, $validated);
            $this->recordChange($request, 'coupon.updated', $store, $actor, $coupon);

            return $coupon->fresh(['products:id', 'categories:id']);
        });
    }

    public function delete(Store $store, Coupon $coupon, User $actor, ?Request $request = null): void
    {
        $this->ensureStoreOwns($store, $coupon);

        DB::transaction(function () use ($store, $coupon, $actor, $request): void {
            // Soft-deleted rows still occupy the (store_id, code) unique index.
            // Free the merchant-facing code so the same code can be recreated later.
            $originalCode = $coupon->code;
            $coupon->forceFill([
                'code' => $this->retiredCode($coupon),
                'is_active' => false,
            ])->save();
            $coupon->delete();
            $this->recordChange($request, 'coupon.deleted', $store, $actor, $coupon, [
                'retired_from_code' => $originalCode,
            ]);
        });
    }

    /**
     * @param  list<array{variant: \App\Models\ProductVariant, quantity: int}>  $preparedItems
     */
    public function calculate(
        Store $store,
        Customer $customer,
        string $currencyCode,
        array $preparedItems,
        string $code,
    ): CouponDiscountResult {
        $normalizedCode = Coupon::normalizeCode($code);

        $coupon = Coupon::query()
            ->with(['products:id', 'categories:id'])
            ->forStore($store->id)
            ->where('code', $normalizedCode)
            ->lockForUpdate()
            ->first();

        if (! $coupon) {
            $this->invalid('This coupon code is not valid for this store.');
        }

        $this->validateAvailability($coupon, $customer);

        $currencyCode = strtoupper(trim($currencyCode));
        $zero = CurrencyPrecision::roundMajor('0', $currencyCode);
        $cartSubtotal = $zero;
        $eligibleLines = [];
        $eligibleSubtotalMinor = 0;
        $restrictedProductIds = $coupon->products->modelKeys();
        $restrictedCategoryIds = $coupon->categories->modelKeys();

        foreach ($preparedItems as $item) {
            $variant = $item['variant'];
            $product = $variant->product;
            $lineKey = CheckoutTotalsService::lineKeyForVariant((int) $variant->id);
            $lineSubtotal = CurrencyPrecision::roundMajor(
                bcmul((string) $variant->price, (string) $item['quantity'], 6),
                $currencyCode,
            );
            $cartSubtotal = CurrencyPrecision::roundMajor(bcadd($cartSubtotal, $lineSubtotal, 6), $currencyCode);

            if (! $this->productIsEligible($product, $restrictedProductIds, $restrictedCategoryIds)) {
                continue;
            }

            $lineMinor = CurrencyPrecision::toMinorUnits($lineSubtotal, $currencyCode);
            if ($lineMinor <= 0) {
                continue;
            }

            $eligibleLines[$lineKey] = $lineMinor;
            $eligibleSubtotalMinor += $lineMinor;
        }

        $minimum = CurrencyPrecision::roundMajor((string) $coupon->minimum_order_amount, $currencyCode);
        if (bccomp($cartSubtotal, $minimum, 6) < 0) {
            $this->invalid('This coupon requires a minimum order of '.$minimum.' '.$currencyCode.'.');
        }

        if ($eligibleLines === [] || $eligibleSubtotalMinor <= 0) {
            $this->invalid('This coupon does not apply to the products in this cart.');
        }

        $eligibleSubtotal = CurrencyPrecision::fromMinorUnits($eligibleSubtotalMinor, $currencyCode);
        $rawDiscount = $coupon->type === Coupon::TYPE_PERCENTAGE
            ? bcdiv(bcmul($eligibleSubtotal, (string) $coupon->value, 8), '100', 8)
            : (string) $coupon->value;
        $discount = CurrencyPrecision::roundMajor($rawDiscount, $currencyCode);

        if ($coupon->maximum_discount_amount !== null) {
            $maximum = CurrencyPrecision::roundMajor((string) $coupon->maximum_discount_amount, $currencyCode);
            if (bccomp($discount, $maximum, 6) > 0) {
                $discount = $maximum;
            }
        }

        if (bccomp($discount, $eligibleSubtotal, 6) > 0) {
            $discount = $eligibleSubtotal;
        }

        $discountMinor = CurrencyPrecision::toMinorUnits($discount, $currencyCode);
        if ($discountMinor <= 0) {
            $this->invalid('This coupon does not produce a discount for this cart.');
        }

        $itemDiscounts = $this->allocateMinorUnits($eligibleLines, $eligibleSubtotalMinor, $discountMinor, $currencyCode);
        $appliedAt = now('UTC');
        $snapshot = [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'type' => $coupon->type,
            'value' => (string) $coupon->value,
            'minimum_order_amount' => (string) $coupon->minimum_order_amount,
            'maximum_discount_amount' => $coupon->maximum_discount_amount !== null
                ? (string) $coupon->maximum_discount_amount
                : null,
            'product_ids' => array_map('intval', $restrictedProductIds),
            'category_ids' => array_map('intval', $restrictedCategoryIds),
            'discount_amount' => $discount,
            'item_discounts' => $itemDiscounts,
            'applied_at' => $appliedAt->toIso8601String(),
        ];

        return new CouponDiscountResult($coupon, $discount, $itemDiscounts, $snapshot);
    }

    public function reserve(Checkout $checkout, Customer $customer, CouponDiscountResult $result): CouponRedemption
    {
        if ((int) $checkout->store_id !== (int) $result->coupon->store_id || (int) $customer->store_id !== (int) $checkout->store_id) {
            throw new \InvalidArgumentException('Coupon reservation does not belong to this checkout store.');
        }

        return CouponRedemption::query()->updateOrCreate(
            ['checkout_id' => $checkout->id],
            [
                'store_id' => $checkout->store_id,
                'coupon_id' => $result->coupon->id,
                'customer_id' => $customer->id,
                'code_snapshot' => $result->coupon->code,
                'discount_amount' => $result->discountTotal,
                'status' => CouponRedemption::STATUS_RESERVED,
                'order_id' => null,
                'redeemed_at' => null,
            ],
        );
    }

    /**
     * Record a redeemed coupon usage for an external order that opted into platform calculation.
     */
    public function recordRedeemedForOrder(
        Order $order,
        Customer $customer,
        CouponDiscountResult $result,
    ): CouponRedemption {
        if ((int) $order->store_id !== (int) $result->coupon->store_id || (int) $customer->store_id !== (int) $order->store_id) {
            throw new \InvalidArgumentException('Coupon redemption does not belong to this order store.');
        }

        return CouponRedemption::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'store_id' => $order->store_id,
                'coupon_id' => $result->coupon->id,
                'checkout_id' => null,
                'customer_id' => $customer->id,
                'code_snapshot' => $result->coupon->code,
                'discount_amount' => $result->discountTotal,
                'status' => CouponRedemption::STATUS_REDEEMED,
                'redeemed_at' => now('UTC'),
            ],
        );
    }

    public function redeem(Checkout $checkout, Order $order): void
    {
        if ((int) $checkout->store_id !== (int) $order->store_id) {
            throw new \InvalidArgumentException('Coupon redemption does not belong to this order store.');
        }

        CouponRedemption::query()
            ->where('store_id', $checkout->store_id)
            ->where('checkout_id', $checkout->id)
            ->where('status', CouponRedemption::STATUS_RESERVED)
            ->update([
                'order_id' => $order->id,
                'status' => CouponRedemption::STATUS_REDEEMED,
                'redeemed_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
    }

    public function release(Checkout $checkout): void
    {
        CouponRedemption::query()
            ->where('store_id', $checkout->store_id)
            ->where('checkout_id', $checkout->id)
            ->where('status', CouponRedemption::STATUS_RESERVED)
            ->update([
                'status' => CouponRedemption::STATUS_RELEASED,
                'updated_at' => now('UTC'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(Store $store, array $validated): array
    {
        return [
            'store_id' => $store->id,
            'code' => Coupon::normalizeCode($validated['code']),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'value' => $validated['value'],
            'minimum_order_amount' => $validated['minimum_order_amount'] ?? 0,
            'maximum_discount_amount' => $validated['maximum_discount_amount'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'total_usage_limit' => $validated['total_usage_limit'] ?? null,
            'per_customer_usage_limit' => $validated['per_customer_usage_limit'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncEligibility(Coupon $coupon, array $validated): void
    {
        $coupon->products()->sync(array_map('intval', $validated['product_ids'] ?? []));
        $coupon->categories()->sync(array_map('intval', $validated['category_ids'] ?? []));
    }

    private function validateAvailability(Coupon $coupon, Customer $customer): void
    {
        if (! $coupon->is_active) {
            $this->invalid('This coupon is not active.');
        }

        $now = now('UTC');
        if ($coupon->starts_at && $coupon->starts_at->isAfter($now)) {
            $this->invalid('This coupon is not available yet.');
        }

        if ($coupon->expires_at && ! $coupon->expires_at->isAfter($now)) {
            $this->invalid('This coupon has expired.');
        }

        $activeUsage = $this->activeUsageQuery($coupon);
        if ($coupon->total_usage_limit !== null && (clone $activeUsage)->count() >= $coupon->total_usage_limit) {
            $this->invalid('This coupon has reached its usage limit.');
        }

        if (
            $coupon->per_customer_usage_limit !== null
            && (clone $activeUsage)->where('customer_id', $customer->id)->count() >= $coupon->per_customer_usage_limit
        ) {
            $this->invalid('This coupon has already been used the maximum number of times for this customer.');
        }
    }

    private function activeUsageQuery(Coupon $coupon): \Illuminate\Database\Eloquent\Builder
    {
        return CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where(function ($query): void {
                $query->where('status', CouponRedemption::STATUS_REDEEMED)
                    ->orWhere(function ($reserved): void {
                        $reserved->where('status', CouponRedemption::STATUS_RESERVED)
                            ->whereHas('checkout', function ($checkout): void {
                                $checkout->where('status', Checkout::STATUS_PAYMENT_PENDING)
                                    ->where(function ($expiry): void {
                                        $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC'));
                                    });
                            });
                    });
            });
    }

    /**
     * @param  list<int>  $restrictedProductIds
     * @param  list<int>  $restrictedCategoryIds
     */
    private function productIsEligible(?\App\Models\Product $product, array $restrictedProductIds, array $restrictedCategoryIds): bool
    {
        if (! $product) {
            return false;
        }

        if ($restrictedProductIds === [] && $restrictedCategoryIds === []) {
            return true;
        }

        if (in_array((int) $product->id, array_map('intval', $restrictedProductIds), true)) {
            return true;
        }

        if ($restrictedCategoryIds === []) {
            return false;
        }

        $categoryIds = $product->relationLoaded('categories')
            ? $product->categories->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : $product->categories()->pluck('categories.id')->map(fn ($id): int => (int) $id)->all();

        return array_intersect($categoryIds, array_map('intval', $restrictedCategoryIds)) !== [];
    }

    private function retiredCode(Coupon $coupon): string
    {
        $suffix = '__DEL_'.$coupon->id;
        $maxBaseLength = max(1, 100 - strlen($suffix));

        return substr($coupon->code, 0, $maxBaseLength).$suffix;
    }

    /**
     * @param  array<string, int>  $eligibleLines
     * @return array<string, string>
     */
    private function allocateMinorUnits(
        array $eligibleLines,
        int $eligibleSubtotalMinor,
        int $discountMinor,
        string $currencyCode,
    ): array {
        $minorAllocations = [];
        $allocated = 0;

        foreach ($eligibleLines as $lineKey => $lineMinor) {
            $share = (int) bcdiv(
                bcmul((string) $discountMinor, (string) $lineMinor, 0),
                (string) $eligibleSubtotalMinor,
                0,
            );
            $share = max(0, min($share, $lineMinor));
            $minorAllocations[$lineKey] = $share;
            $allocated += $share;
        }

        $remaining = $discountMinor - $allocated;
        while ($remaining > 0) {
            $distributed = false;
            foreach ($eligibleLines as $lineKey => $lineMinor) {
                if ($minorAllocations[$lineKey] >= $lineMinor) {
                    continue;
                }

                $minorAllocations[$lineKey]++;
                $remaining--;
                $distributed = true;

                if ($remaining === 0) {
                    break;
                }
            }

            if (! $distributed) {
                break;
            }
        }

        return collect($minorAllocations)
            ->map(fn (int $amount): string => CurrencyPrecision::fromMinorUnits($amount, $currencyCode))
            ->all();
    }

    private function ensureStoreOwns(Store $store, Coupon $coupon): void
    {
        abort_unless((int) $coupon->store_id === (int) $store->id, 404);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['coupon_code' => $message]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function recordChange(
        ?Request $request,
        string $event,
        Store $store,
        User $actor,
        Coupon $coupon,
        array $extra = [],
    ): void {
        app(SecurityLogRecorder::class)->record(
            $request,
            $event,
            store: $store,
            user: $actor,
            metadata: array_merge(['coupon_id' => $coupon->id, 'code' => $coupon->code], $extra),
        );
    }
}
