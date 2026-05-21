<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    public const TYPE_MANUAL = 'manual';
    public const TYPE_COURIER = 'courier';
    public const TYPE_PICKUP = 'pickup';
    public const TYPE_LOCAL_DELIVERY = 'local_delivery';
    public const TYPE_THIRD_PARTY = 'third_party';

    public const TYPES = [
        self::TYPE_MANUAL,
        self::TYPE_COURIER,
        self::TYPE_PICKUP,
        self::TYPE_LOCAL_DELIVERY,
        self::TYPE_THIRD_PARTY,
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'website_url',
        'tracking_url_template',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function carrierAccounts(): HasMany
    {
        return $this->hasMany(CarrierAccount::class);
    }
}
