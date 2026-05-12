<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProviderAccount extends Model
{
    protected $fillable = [
        'store_id',
        'provider',
        'mode',
        'connection_type',
        'display_name',
        'status',
        'is_default',
        'settings',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }
}
