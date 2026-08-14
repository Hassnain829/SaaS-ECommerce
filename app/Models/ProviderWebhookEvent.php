<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderWebhookEvent extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'provider_intent_id',
        'status',
        'payload',
        'processed_at',
        'skip_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
