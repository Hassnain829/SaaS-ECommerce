<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    protected $fillable = [
        'store_id',
        'checkout_id',
        'order_id',
        'payment_provider_account_id',
        'provider',
        'mode',
        'provider_intent_id',
        'client_secret',
        'status',
        'currency_code',
        'amount',
        'amount_minor',
        'request_payload',
        'response_payload',
        'confirmed_at',
        'failed_at',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentProviderAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderAccount::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function captures(): HasMany
    {
        return $this->hasMany(PaymentCapture::class);
    }
}
