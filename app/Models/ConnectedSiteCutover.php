<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedSiteCutover extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_READY = 'ready';

    public const STATUS_ACTIVATED = 'activated';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'store_id',
        'connected_site_id',
        'status',
        'started_by',
        'activated_by',
        'rolled_back_by',
        'backup_acknowledged_at',
        'backup_acknowledged_by',
        'import_exceptions_acknowledged_at',
        'import_exceptions_acknowledged_by',
        'tax_off_acknowledged_at',
        'tax_off_acknowledged_by',
        'external_cache_acknowledged_at',
        'external_cache_acknowledged_by',
        'rollback_acknowledged_at',
        'rollback_acknowledged_by',
        'woo_archive_acknowledged_at',
        'woo_archive_acknowledged_by',
        'smoke_checkout_id',
        'smoke_order_id',
        'last_verified_at',
        'activation_requested_at',
        'activated_at',
        'rolled_back_at',
        'verification_snapshot',
    ];

    protected $casts = [
        'backup_acknowledged_at' => 'datetime',
        'import_exceptions_acknowledged_at' => 'datetime',
        'tax_off_acknowledged_at' => 'datetime',
        'external_cache_acknowledged_at' => 'datetime',
        'rollback_acknowledged_at' => 'datetime',
        'woo_archive_acknowledged_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'activation_requested_at' => 'datetime',
        'activated_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'verification_snapshot' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function connectedSite(): BelongsTo
    {
        return $this->belongsTo(ConnectedSite::class);
    }

    public function isActivated(): bool
    {
        return $this->status === self::STATUS_ACTIVATED;
    }
}
