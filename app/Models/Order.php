<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'store_id',
        'customer_id',
        'order_number',
        'external_order_number',
        'status',
        'fulfillment_status',
        'payment_status',
        'order_source',
        'channel',
        'currency_code',
        'exchange_rate',
        'item_count',
        'total_quantity',
        'subtotal',
        'discount',
        'discount_tax',
        'shipping',
        'shipping_tax',
        'tax',
        'total',
        'grand_total',
        'refunded_total',
        'outstanding_total',
        'payment_method',
        'payment_gateway',
        'payment_reference',
        'transaction_id',
        'fraud_status',
        'invoice_status',
        'customer_email',
        'customer_phone',
        'billing_same_as_shipping',
        'notes',
        'meta',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
        'refunded_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_tax' => 'decimal:2',
        'shipping' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'refunded_total' => 'decimal:2',
        'outstanding_total' => 'decimal:2',
        'billing_same_as_shipping' => 'boolean',
        'meta' => 'array',
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }
}
