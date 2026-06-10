<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentPackage extends Model
{
    protected $fillable = [
        'store_id',
        'shipment_id',
        'order_id',
        'origin_location_id',
        'name',
        'weight_value',
        'weight_unit',
        'length',
        'width',
        'height',
        'dimension_unit',
        'package_type',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'weight_value' => 'decimal:3',
        'length' => 'decimal:3',
        'width' => 'decimal:3',
        'height' => 'decimal:3',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function rateQuotes(): HasMany
    {
        return $this->hasMany(CarrierRateQuote::class, 'package_id');
    }
}
