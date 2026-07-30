<?php

namespace App\Models;

use App\Support\ExchangeLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exchange extends Model
{
    protected $fillable = [
        'store_id',
        'order_id',
        'return_id',
        'refund_id',
        'exchange_number',
        'idempotency_key',
        'request_hash',
        'status',
        'currency_code',
        'outbound_total',
        'inbound_total',
        'price_difference',
        'balance_due',
        'collected_amount',
        'collection_method',
        'collection_reference',
        'collected_at',
        'collection_evidence',
        'notes',
        'meta',
        'created_by',
        'completed_by',
        'cancelled_by',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'outbound_total' => 'decimal:4',
        'inbound_total' => 'decimal:4',
        'price_difference' => 'decimal:4',
        'balance_due' => 'decimal:4',
        'collected_amount' => 'decimal:4',
        'collection_evidence' => 'array',
        'meta' => 'array',
        'collected_at' => 'datetime',
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

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExchangeItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return ExchangeLifecycle::statusLabel($this->status);
    }
}
