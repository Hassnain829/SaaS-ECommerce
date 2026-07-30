<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPES = [self::TYPE_FIXED, self::TYPE_PERCENTAGE];

    protected $fillable = [
        'store_id',
        'code',
        'name',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount_amount',
        'is_active',
        'starts_at',
        'expires_at',
        'total_usage_limit',
        'per_customer_usage_limit',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'total_usage_limit' => 'integer',
        'per_customer_usage_limit' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_coupon');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
