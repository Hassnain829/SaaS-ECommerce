<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCapture extends Model
{
    protected $fillable = [
        'store_id',
        'payment_intent_id',
        'provider',
        'provider_capture_id',
        'status',
        'amount',
        'amount_minor',
        'currency_code',
        'response_payload',
        'captured_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'response_payload' => 'array',
        'captured_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
