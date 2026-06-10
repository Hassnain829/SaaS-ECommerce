<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierRateQuote extends Model
{
    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'shipment_id',
        'order_id',
        'package_id',
        'provider',
        'environment',
        'origin_postal_code',
        'destination_postal_code',
        'service_code',
        'service_name',
        'amount',
        'currency',
        'estimated_days',
        'status',
        'request_summary',
        'response_summary',
        'error_code',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_summary' => 'array',
        'response_summary' => 'array',
        'estimated_days' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ShipmentPackage::class, 'package_id');
    }
}
