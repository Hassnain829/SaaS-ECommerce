<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundItem extends Model
{
    protected $fillable = [
        'store_id',
        'refund_id',
        'order_item_id',
        'quantity',
        'unit_amount',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'total_minor',
        'product_name_snapshot',
        'variant_label_snapshot',
        'sku_snapshot',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'total_minor' => 'integer',
        'meta' => 'array',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
