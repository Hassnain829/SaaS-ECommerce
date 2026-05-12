<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutEvent extends Model
{
    protected $fillable = [
        'store_id',
        'checkout_id',
        'event_type',
        'title',
        'description',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }
}
