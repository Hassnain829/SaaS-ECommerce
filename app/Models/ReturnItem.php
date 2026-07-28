<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = [
        'store_id',
        'return_id',
        'order_item_id',
        'requested_quantity',
        'approved_quantity',
        'received_quantity',
        'restocked_quantity',
        'condition',
        'disposition',
        'restock',
        'restock_location_id',
        'product_name_snapshot',
        'variant_label_snapshot',
        'sku_snapshot',
        'product_type_snapshot',
        'meta',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'approved_quantity' => 'integer',
        'received_quantity' => 'integer',
        'restocked_quantity' => 'integer',
        'restock' => 'boolean',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function restockLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'restock_location_id');
    }

    public function claimQuantity(): int
    {
        $approved = (int) $this->approved_quantity;

        return $approved > 0 ? $approved : (int) $this->requested_quantity;
    }
}
