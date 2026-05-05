<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'refunded_quantity',
        'returned_quantity',
        'unit_price',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'cost_price_snapshot',
        'weight_snapshot',
        'sku_snapshot',
        'barcode_snapshot',
        'product_name',
        'product_slug_snapshot',
        'brand_name_snapshot',
        'product_image_snapshot',
        'product_type_snapshot',
        'fulfillment_status',
        'variant_label',
        'variant_details',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refunded_quantity' => 'integer',
        'returned_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'cost_price_snapshot' => 'decimal:2',
        'weight_snapshot' => 'decimal:3',
        'variant_details' => 'array',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
