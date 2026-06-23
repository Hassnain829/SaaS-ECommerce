<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use SoftDeletes;

    public const TYPE_WAREHOUSE = 'warehouse';

    public const TYPE_STORE = 'store';

    public const TYPE_THIRD_PARTY = 'third_party';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_WAREHOUSE,
        self::TYPE_STORE,
        self::TYPE_THIRD_PARTY,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'store_id',
        'name',
        'type',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'phone',
        'is_default',
        'is_active',
        'fulfills_online_orders',
        'pickup_enabled',
        'routing_priority',
        'service_countries',
        'service_regions',
        'service_postal_patterns',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'fulfills_online_orders' => 'boolean',
        'pickup_enabled' => 'boolean',
        'routing_priority' => 'integer',
        'service_countries' => 'array',
        'service_regions' => 'array',
        'service_postal_patterns' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'origin_location_id');
    }

    public function checkoutOrigins(): HasMany
    {
        return $this->hasMany(Checkout::class, 'fulfillment_origin_location_id');
    }

    public function pickupCheckouts(): HasMany
    {
        return $this->hasMany(Checkout::class, 'pickup_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
