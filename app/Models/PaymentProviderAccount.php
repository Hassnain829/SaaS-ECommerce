<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentProviderAccount extends Model
{
    use SoftDeletes;

    public const MODE_TEST = 'test';

    public const MODE_LIVE = 'live';

    public const CONNECTION_CONNECT = 'connect';

    public const CONNECTION_PLATFORM = 'platform';

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
        return $this->isStripe() && $this->isConnect() && filled($this->provider_account_id);
    }

    public function isStripe(): bool
    {
        return $this->provider === 'stripe';
    }

    public function isConnect(): bool
    {
        return $this->connection_type === self::CONNECTION_CONNECT;
    }

    public function isTestMode(): bool
    {
        return $this->mode === self::MODE_TEST;
    }

    public function isLiveMode(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @param  Builder<PaymentProviderAccount>  $query
     * @return Builder<PaymentProviderAccount>
     */
    public function scopeForStore(Builder $query, Store $store): Builder
    {
        return $query->where('store_id', $store->id);
    }

    /**
     * @param  Builder<PaymentProviderAccount>  $query
     * @return Builder<PaymentProviderAccount>
     */
    public function scopeStripe(Builder $query): Builder
    {
        return $query->where('provider', 'stripe');
    }

    /**
     * @param  Builder<PaymentProviderAccount>  $query
     * @return Builder<PaymentProviderAccount>
     */
    public function scopeConnect(Builder $query): Builder
    {
        return $query->where('connection_type', self::CONNECTION_CONNECT);
    }

    /**
     * @param  Builder<PaymentProviderAccount>  $query
     * @return Builder<PaymentProviderAccount>
     */
    public function scopeMode(Builder $query, string $mode): Builder
    {
        return $query->where('mode', strtolower($mode));
    }

    public function maskedProviderAccountId(): ?string
    {
        if (! filled($this->provider_account_id)) {
            return null;
        }

        $id = (string) $this->provider_account_id;
        if (strlen($id) <= 8) {
            return $id;
        }

        return substr($id, 0, 5).'••••'.substr($id, -4);
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
