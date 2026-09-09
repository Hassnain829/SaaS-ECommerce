<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSubscription extends Model
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_TRIAL,
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_SUSPENDED,
    ];

    protected $fillable = [
        'store_id',
        'package_id',
        'status',
        'trial_days',
        'trial_ends_at',
        'access_ends_at',
        'notes',
        'assigned_by',
    ];

    protected $casts = [
        'trial_days' => 'integer',
        'trial_ends_at' => 'datetime',
        'access_ends_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SaasPackage::class, 'package_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isExpiredStatus(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function accessHasEnded(?\DateTimeInterface $at = null): bool
    {
        if (! $this->access_ends_at) {
            return false;
        }

        $at = $at ?? now();

        return $this->access_ends_at->lte($at);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_TRIAL => 'Trial',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_SUSPENDED => 'Suspended',
            default => ucfirst((string) $this->status),
        };
    }
}
