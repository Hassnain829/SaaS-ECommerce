<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checkout extends Model
{
    use SoftDeletes;

    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'store_id',
        'customer_id',
        'checkout_number',
        'source_channel',
        'mode',
        'status',
        'currency_code',
        'subtotal',
        'discount_total',
        'shipping_total',
        'shipping_method_id',
        'shipping_snapshot',
        'fulfillment_origin_location_id',
        'pickup_location_id',
        'fulfillment_routing_snapshot',
        'tax_total',
        'grand_total',
        'payment_provider',
        'payment_provider_account_id',
        'stripe_payment_intent_id',
        'metadata',
        'expires_at',
        'completed_at',
        'converted_order_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'shipping_snapshot' => 'array',
        'fulfillment_routing_snapshot' => 'array',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
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
        return $this->hasMany(CheckoutItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CheckoutAddress::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CheckoutEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function paymentProviderAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderAccount::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function fulfillmentOriginLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'fulfillment_origin_location_id');
    }

    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
