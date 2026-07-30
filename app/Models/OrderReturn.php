<?php

namespace App\Models;

use App\Support\ReturnLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'store_id',
        'order_id',
        'customer_id',
        'return_number',
        'status',
        'source',
        'return_reason_id',
        'merchant_notes',
        'customer_notes',
        'manual_instructions',
        'tracking_reference',
        'requested_by',
        'approved_by',
        'received_by',
        'completed_by',
        'cancelled_by',
        'requested_at',
        'approved_at',
        'rejected_at',
        'received_at',
        'completed_at',
        'cancelled_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusLabel(): string
    {
        return ReturnLifecycle::statusLabel($this->status);
    }

    public function isOpenClaim(): bool
    {
        return in_array($this->status, ReturnLifecycle::OPEN_CLAIM_STATUSES, true);
    }
}
