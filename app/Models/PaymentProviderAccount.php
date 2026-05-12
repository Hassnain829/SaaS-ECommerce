<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentProviderAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'provider',
        'provider_account_id',
        'mode',
        'connection_type',
        'display_name',
        'status',
        'is_default',
        'settings',
        'capabilities',
        'metadata',
        'last_verified_at',
        'created_by',
        'onboarding_completed_at',
        'charges_enabled',
        'payouts_enabled',
        'requirements_currently_due',
        'requirements_disabled_reason',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
        'capabilities' => 'array',
        'metadata' => 'array',
        'last_verified_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'requirements_currently_due' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isConnectedStripeAccount(): bool
    {
        return $this->provider === 'stripe'
            && $this->connection_type === 'connect'
            && filled($this->provider_account_id);
    }

    public function isReadyForCheckout(): bool
    {
        if ($this->status !== 'active' || ! $this->is_default) {
            return false;
        }

        if (! $this->isConnectedStripeAccount()) {
            return $this->connection_type === 'platform';
        }

        return $this->charges_enabled === true;
    }
}
