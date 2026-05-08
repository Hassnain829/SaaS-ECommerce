<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_DEDUCTED = 'deducted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'store_id',
        'inventory_item_id',
        'location_id',
        'order_id',
        'checkout_reference',
        'reference_type',
        'reference_id',
        'quantity',
        'status',
        'expires_at',
        'committed_at',
        'released_at',
        'deducted_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'committed_at' => 'datetime',
        'released_at' => 'datetime',
        'deducted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
