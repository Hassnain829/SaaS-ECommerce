<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedSiteEventDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'connected_site_id',
        'outbox_event_id',
        'status',
        'attempt_count',
        'next_retry_at',
        'last_error',
        'last_http_status',
        'delivered_at',
    ];

    protected $casts = [
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(ConnectedSite::class, 'connected_site_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ConnectedSiteOutboxEvent::class, 'outbox_event_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
