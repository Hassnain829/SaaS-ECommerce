<?php

namespace App\Models;

use App\Support\RefundLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $fillable = [
        'store_id',
        'order_id',
        'return_id',
        'payment_intent_id',
        'payment_provider_account_id',
        'refund_number',
        'status',
        'method',
        'currency_code',
        'amount',
        'amount_minor',
        'reason',
        'notes',
        'idempotency_key',
        'request_hash',
        'provider_idempotency_key',
        'provider',
        'provider_refund_id',
        'provider_status',
        'mode',
        'provider_account_id',
        'payment_owner',
        'order_source_snapshot',
        'routing_snapshot',
        'meta',
        'requested_by',
        'processed_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_minor' => 'integer',
        'routing_snapshot' => 'array',
        'meta' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function paymentProviderAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderAccount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(RefundAdjustment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function statusLabel(): string
    {
        return RefundLifecycle::statusLabel($this->status);
    }

    public function isSucceeded(): bool
    {
        return $this->status === RefundLifecycle::STATUS_SUCCEEDED;
    }
}
