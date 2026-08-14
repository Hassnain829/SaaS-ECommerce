<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedSiteOutboxEvent extends Model
{
    protected $fillable = [
        'store_id',
        'public_id',
        'type',
        'payload',
        'catalog_version',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ConnectedSiteEventDelivery::class, 'outbox_event_id');
    }
}
