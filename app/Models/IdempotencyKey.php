<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'store_id',
        'key',
        'request_method',
        'request_path',
        'request_hash',
        'response_code',
        'response_body',
        'resource_type',
        'resource_id',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_code' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
