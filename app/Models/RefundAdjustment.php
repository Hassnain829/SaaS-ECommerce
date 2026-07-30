<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundAdjustment extends Model
{
    protected $fillable = [
        'store_id',
        'refund_id',
        'type',
        'label',
        'amount',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'meta' => 'array',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
