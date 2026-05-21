<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierAccount extends Model
{
    use SoftDeletes;

    public const CONNECTION_MANUAL = 'manual';
    public const CONNECTION_API = 'api';
    public const CONNECTION_EXTERNAL = 'external';

    public const CONNECTION_TYPES = [
        self::CONNECTION_MANUAL,
        self::CONNECTION_API,
        self::CONNECTION_EXTERNAL,
    ];

    public const STATUS_SETUP_REQUIRED = 'setup_required';
    public const STATUS_ENABLED = 'enabled';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_INTERNAL_ONLY = 'internal_only';

    public const STATUSES = [
        self::STATUS_SETUP_REQUIRED,
        self::STATUS_ENABLED,
        self::STATUS_DISABLED,
        self::STATUS_INTERNAL_ONLY,
    ];

    protected $fillable = [
        'store_id',
        'carrier_id',
        'display_name',
        'connection_type',
        'status',
        'credentials_encrypted',
        'settings',
        'supported_countries',
        'enabled_for_checkout',
        'created_by',
    ];

    protected $casts = [
        'credentials_encrypted' => 'encrypted:array',
        'settings' => 'array',
        'supported_countries' => 'array',
        'enabled_for_checkout' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
