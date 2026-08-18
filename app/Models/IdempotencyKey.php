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
        'claim_token',
        'response_code',
        'response_body',
        'resource_type',
        'resource_id',
        'completed_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'response_code' => 'integer',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'claim_token',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
