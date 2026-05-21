<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingMethod extends Model
{
    use SoftDeletes;

    public const RATE_FLAT = 'flat';
    public const RATE_FREE = 'free';
    public const RATE_MANUAL = 'manual';
    public const RATE_CARRIER_CALCULATED_LATER = 'carrier_calculated_later';

    public const RATE_TYPES = [
        self::RATE_FLAT,
        self::RATE_FREE,
        self::RATE_MANUAL,
        self::RATE_CARRIER_CALCULATED_LATER,
    ];

    protected $fillable = [
        'store_id',
        'shipping_zone_id',
        'carrier_account_id',
        'name',
        'code',
        'description',
        'delivery_speed_label',
        'rate_type',
        'flat_rate',
        'free_over_amount',
        'min_order_amount',
        'max_order_amount',
        'estimated_min_days',
        'estimated_max_days',
        'enabled_for_checkout',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'flat_rate' => 'decimal:2',
        'free_over_amount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_order_amount' => 'decimal:2',
        'estimated_min_days' => 'integer',
        'estimated_max_days' => 'integer',
        'enabled_for_checkout' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
