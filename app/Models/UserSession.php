<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'browser',
        'os',
        'device_type',
        'location',
        'last_activity',
        'ended_at',
        'revoked_at',
        'is_current',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'ended_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
