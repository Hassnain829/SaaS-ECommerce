<?php

namespace App\Models;

use App\Support\OrderLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = OrderLifecycle::SHIPMENT_PENDING;
    public const STATUS_LABEL_CREATED = OrderLifecycle::SHIPMENT_LABEL_CREATED;
    public const STATUS_SHIPPED = OrderLifecycle::SHIPMENT_SHIPPED;
    public const STATUS_IN_TRANSIT = OrderLifecycle::SHIPMENT_IN_TRANSIT;
    public const STATUS_DELIVERED = OrderLifecycle::SHIPMENT_DELIVERED;
    public const STATUS_FAILED = OrderLifecycle::SHIPMENT_FAILED;
    public const STATUS_RETURNED = OrderLifecycle::SHIPMENT_RETURNED;
    public const STATUS_CANCELLED = OrderLifecycle::SHIPMENT_CANCELLED;

    public const STATUSES_COUNTED_FOR_FULFILLMENT = [
        self::STATUS_SHIPPED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
    ];

    protected $fillable = [
        'store_id',
        'order_id',
        'shipment_number',
        'origin_location_id',
        'carrier_account_id',
        'shipping_method_id',
        'status',
        'tracking_number',
        'tracking_url',
        'carrier_service',
        'package_count',
        'package_weight',
        'shipping_cost',
        'label_url',
        'shipped_at',
        'delivered_at',
        'shipped_by',
        'metadata',
    ];

    protected $casts = [
        'package_count' => 'integer',
        'package_weight' => 'decimal:3',
        'shipping_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }
}
