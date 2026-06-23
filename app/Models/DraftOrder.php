<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DraftOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'store_id',
        'customer_id',
        'draft_number',
        'status',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'total',
        'notes',
        'created_by',
        'converted_order_id',
        'converted_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'total' => 'decimal:2',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DraftOrderItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function shippingAddress(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return is_array($metadata['shipping_address'] ?? null) ? $metadata['shipping_address'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function billingAddress(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return is_array($metadata['billing_address'] ?? null) ? $metadata['billing_address'] : [];
    }

    public function billingSameAsShipping(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return (bool) ($metadata['billing_same_as_shipping'] ?? true);
    }
}
