<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeItem extends Model
{
    protected $fillable = [
        'store_id',
        'exchange_id',
        'direction',
        'order_item_id',
        'product_id',
        'product_variant_id',
        'inventory_reservation_id',
        'quantity',
        'unit_price',
        'line_total',
        'product_name_snapshot',
        'variant_label_snapshot',
        'sku_snapshot',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:4',
        'meta' => 'array',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id');
    }
}
