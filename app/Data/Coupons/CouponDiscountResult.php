<?php

namespace App\Data\Coupons;

use App\Models\Coupon;

final readonly class CouponDiscountResult
{
    /**
     * @param  array<string, string>  $itemDiscounts
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public Coupon $coupon,
        public string $discountTotal,
        public array $itemDiscounts,
        public array $snapshot,
    ) {}

    public function discountFor(string $lineKey, string $zero): string
    {
        return $this->itemDiscounts[$lineKey] ?? $zero;
    }
}
