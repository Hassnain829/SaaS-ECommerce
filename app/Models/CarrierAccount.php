<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierAccount extends Model
{
    use SoftDeletes;

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_FEDEX = 'fedex';

    public const PROVIDER_UPS = 'ups';

    public const PROVIDER_DHL = 'dhl';

    public const PROVIDER_USPS = 'usps';

    public const CONNECTION_MANUAL = 'manual';

    public const CONNECTION_API = 'api';

    public const CONNECTION_EXTERNAL = 'external';

    public const CONNECTION_TYPES = [
        self::CONNECTION_MANUAL,
        self::CONNECTION_API,
        self::CONNECTION_EXTERNAL,
    ];

    public const CONNECTION_MODE_MANUAL = 'manual';

    public const CONNECTION_MODE_FEDEX_INTEGRATOR = 'fedex_integrator_account';

    public const CONNECTION_MODE_USPS_PLATFORM = 'usps_platform_api';

    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const ENVIRONMENT_TESTING = 'testing';

    public const ENVIRONMENT_LIVE = 'live';

    public const BILLING_OWNER_MERCHANT = 'merchant';

    public const BILLING_OWNER_PLATFORM = 'platform';

    public const STATUS_SETUP_REQUIRED = 'setup_required';

    public const STATUS_ENABLED = 'enabled';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_INTERNAL_ONLY = 'internal_only';

    public const STATUSES = [
        self::STATUS_SETUP_REQUIRED,
        self::STATUS_ENABLED,
        self::STATUS_DISABLED,
        self::STATUS_INTERNAL_ONLY,
    ];

    public const CONNECTION_NOT_CONNECTED = 'not_connected';

    public const CONNECTION_SETUP_REQUIRED = 'setup_required';

    public const CONNECTION_PENDING_VALIDATION = 'pending_validation';

    public const CONNECTION_CONNECTED = 'connected';

    public const CONNECTION_FAILED = 'failed';

    public const CONNECTION_BLOCKED_BY_FEDEX = 'blocked_by_fedex';

    public const CONNECTION_SANDBOX_PLATFORM_FALLBACK = 'sandbox_platform_fallback';

    public const CONNECTION_DISABLED = 'disabled';

    public const CONNECTION_STATUSES = [
        self::CONNECTION_NOT_CONNECTED,
        self::CONNECTION_SETUP_REQUIRED,
        self::CONNECTION_PENDING_VALIDATION,
        self::CONNECTION_CONNECTED,
        self::CONNECTION_FAILED,
        self::CONNECTION_BLOCKED_BY_FEDEX,
        self::CONNECTION_SANDBOX_PLATFORM_FALLBACK,
        self::CONNECTION_DISABLED,
    ];

    protected $fillable = [
        'store_id',
        'carrier_id',
        'provider',
        'environment',
        'display_name',
        'connection_type',
        'connection_mode',
        'billing_owner',
        'provider_account_number',
        'status',
        'connection_status',
        'credentials_encrypted',
        'settings',
        'capabilities',
        'supported_countries',
        'enabled_for_checkout',
        'last_verified_at',
        'last_error_code',
        'last_error_message',
        'created_by',
    ];

    protected $casts = [
        'credentials_encrypted' => 'encrypted:array',
        'settings' => 'array',
        'capabilities' => 'array',
        'supported_countries' => 'array',
        'enabled_for_checkout' => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials_encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function apiEvents(): HasMany
    {
        return $this->hasMany(CarrierApiEvent::class);
    }

    public function isFedEx(): bool
    {
        return $this->provider === self::PROVIDER_FEDEX
            || $this->carrier?->code === 'fedex';
    }

    public function isUsps(): bool
    {
        return $this->provider === self::PROVIDER_USPS
            || $this->carrier?->code === 'usps';
    }

    public function isTestingEnvironment(): bool
    {
        return $this->environment === self::ENVIRONMENT_TESTING;
    }

    public function isManualProvider(): bool
    {
        return $this->provider === self::PROVIDER_MANUAL
            || $this->connection_mode === self::CONNECTION_MODE_MANUAL;
    }

    public function isSandbox(): bool
    {
        return $this->environment === self::ENVIRONMENT_SANDBOX;
    }

    public function isConnected(): bool
    {
        return $this->connection_status === self::CONNECTION_CONNECTED;
    }

    public function isBlockedByFedEx(): bool
    {
        return $this->connection_status === self::CONNECTION_BLOCKED_BY_FEDEX;
    }

    public function isSandboxPlatformFallback(): bool
    {
        return $this->connection_status === self::CONNECTION_SANDBOX_PLATFORM_FALLBACK;
    }

    public function usesSandboxPlatformFallback(): bool
    {
        return (bool) data_get($this->settings, 'sandbox_platform_fallback', false);
    }

    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function markConnected(array $capabilities = []): void
    {
        $this->forceFill([
            'connection_status' => self::CONNECTION_CONNECTED,
            'status' => self::STATUS_ENABLED,
            'last_verified_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'capabilities' => $capabilities !== [] ? $capabilities : $this->capabilities,
        ])->save();
    }

    public function markFailed(string $message, ?string $code = null): void
    {
        $this->forceFill([
            'connection_status' => self::CONNECTION_FAILED,
            'last_error_code' => $code,
            'last_error_message' => $message,
        ])->save();
    }

    public function markBlockedByFedEx(string $message, ?string $code = null): void
    {
        $this->forceFill([
            'connection_status' => self::CONNECTION_BLOCKED_BY_FEDEX,
            'last_error_code' => $code,
            'last_error_message' => $message,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function markSandboxPlatformFallback(array $capabilities = []): void
    {
        $this->forceFill([
            'connection_status' => self::CONNECTION_SANDBOX_PLATFORM_FALLBACK,
            'status' => self::STATUS_ENABLED,
            'last_verified_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'credentials_encrypted' => null,
            'capabilities' => $capabilities !== [] ? $capabilities : [
                'rates' => true,
                'labels' => false,
                'tracking' => false,
                'sandbox_connection' => true,
                'sandbox_platform_fallback' => true,
                'merchant_owned_connection' => false,
            ],
        ])->save();
    }

    public function markDisabled(): void
    {
        $this->forceFill([
            'connection_status' => self::CONNECTION_DISABLED,
            'status' => self::STATUS_DISABLED,
        ])->save();
    }

    public function maskedAccountNumber(): string
    {
        $number = (string) ($this->provider_account_number ?? '');

        if ($number === '') {
            return '—';
        }

        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return is_array($this->credentials_encrypted) ? $this->credentials_encrypted : [];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = $credentials;
    }

    public function hasMerchantCredentials(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['customer_key'] ?? null)
            && filled($credentials['customer_password'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationDetails(): array
    {
        return is_array($this->settings['registration'] ?? null)
            ? $this->settings['registration']
            : [];
    }
}
