<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderSequence extends Model
{
    protected $fillable = [
        'store_id',
        'next_number',
    ];

    protected $casts = [
        'next_number' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
