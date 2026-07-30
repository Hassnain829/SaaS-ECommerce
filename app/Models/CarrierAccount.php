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

    public const CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS = 'fedex_merchant_credentials';

    public const CONNECTION_MODEL_INTEGRATOR_PROVIDER = 'integrator_provider';

    public const CONNECTION_MODEL_MERCHANT_DEVELOPER = 'merchant_developer';

    public const CONNECTION_MODEL_USPS_PLATFORM_LABEL_PROVIDER = 'usps_platform_label_provider';

    public const CONNECTION_MODE_USPS_PLATFORM = 'usps_platform_api';

    public const CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER = 'usps_merchant_label_provider';

    public const USPS_AUTH_SETUP_REQUIRED = 'setup_required';

    public const USPS_AUTH_AWAITING_AUTHORIZATION = 'awaiting_authorization';

    public const USPS_AUTH_VERIFYING = 'verifying';

    public const USPS_AUTH_CONNECTED = 'connected';

    public const USPS_AUTH_ACTION_REQUIRED = 'action_required';

    public const USPS_AUTH_REVOKED = 'revoked';

    public const USPS_AUTH_DISABLED = 'disabled';

    public const USPS_AUTH_STATUSES = [
        self::USPS_AUTH_SETUP_REQUIRED,
        self::USPS_AUTH_AWAITING_AUTHORIZATION,
        self::USPS_AUTH_VERIFYING,
        self::USPS_AUTH_CONNECTED,
        self::USPS_AUTH_ACTION_REQUIRED,
        self::USPS_AUTH_REVOKED,
        self::USPS_AUTH_DISABLED,
    ];

    public const USPS_ENROLLMENT_NOT_STARTED = 'not_started';

    public const USPS_ENROLLMENT_PENDING = 'pending';

    public const USPS_ENROLLMENT_VERIFIED = 'verified';

    public const USPS_ENROLLMENT_FAILED = 'failed';

    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const ENVIRONMENT_TESTING = 'testing';

    public const ENVIRONMENT_LIVE = 'live';

    public const BILLING_OWNER_MERCHANT = 'merchant';

    public const BILLING_OWNER_PLATFORM = 'platform';

    public const BILLING_OWNER_EXTERNAL = 'external';

    public const BILLING_OWNER_NONE = 'none';

    public const OWNERSHIP_PLATFORM_TESTING = 'platform_testing';

    public const OWNERSHIP_MERCHANT_OWNED = 'merchant_owned';

    public const OWNERSHIP_MANUAL = 'manual';

    public const OWNERSHIP_EXTERNAL_MANAGED = 'external_managed';

    public const CONNECTION_OWNER_PLATFORM = 'platform';

    public const CONNECTION_OWNER_MERCHANT = 'merchant';

    public const CONNECTION_OWNER_EXTERNAL = 'external';

    public const CONNECTION_OWNER_NONE = 'none';

    public const CREDENTIALS_PLATFORM_ENV = 'platform_env';

    public const CREDENTIALS_MERCHANT_ACCOUNT = 'merchant_account';

    public const CREDENTIALS_MERCHANT_ENCRYPTED = 'merchant_encrypted';

    public const CREDENTIALS_MERCHANT_OAUTH = 'merchant_oauth';

    public const CREDENTIALS_USPS_MERCHANT_AUTHORIZATION = 'usps_merchant_authorization';

    public const CREDENTIALS_MANUAL_ENTRY = 'manual_entry';

    public const CREDENTIALS_EXTERNAL_SYSTEM = 'external_system';

    public const CREDENTIALS_NONE = 'none';

    public const ORIGIN_VALIDATION_READY = 'ready';

    public const ORIGIN_VALIDATION_NEEDS_ATTENTION = 'needs_attention';

    public const ORIGIN_VALIDATION_MISSING = 'missing';

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
        'connection_model',
        'fedex_integrator_account',
        'registration_session_id',
        'eula_accepted_at',
        'eula_version',
        'eula_document_hash',
        'connection_context_json',
        'usps_authorization_status',
        'usps_enrollment_status',
        'usps_payment_verified_at',
        'usps_active_store_key',
        'billing_owner',
        'ownership_mode',
        'connection_owner',
        'credentials_source',
        'default_origin_location_id',
        'origin_validation_status',
        'origin_validation_summary',
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
        'connection_context_json' => 'array',
        'fedex_integrator_account' => 'boolean',
        'supported_countries' => 'array',
        'enabled_for_checkout' => 'boolean',
        'last_verified_at' => 'datetime',
        'eula_accepted_at' => 'datetime',
        'usps_payment_verified_at' => 'datetime',
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

    public function defaultOriginLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_origin_location_id');
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

    public function registrationSessions(): HasMany
    {
        return $this->hasMany(CarrierAccountRegistrationSession::class);
    }

    public function latestRegistrationSession(): BelongsTo
    {
        return $this->belongsTo(CarrierAccountRegistrationSession::class, 'registration_session_id');
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

    public function isUspsMerchantLabelProvider(): bool
    {
        return $this->isUsps()
            && $this->connection_mode === self::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER;
    }

    public function isUspsPlatformTestingAccount(): bool
    {
        return $this->isUsps()
            && (
                $this->connection_mode === self::CONNECTION_MODE_USPS_PLATFORM
                || $this->isPlatformTesting()
            )
            && ! $this->isUspsMerchantLabelProvider();
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
            'ownership_mode' => self::OWNERSHIP_PLATFORM_TESTING,
            'connection_owner' => self::CONNECTION_OWNER_PLATFORM,
            'credentials_source' => self::CREDENTIALS_PLATFORM_ENV,
            'billing_owner' => self::BILLING_OWNER_PLATFORM,
            'last_verified_at' => now(),
            'last_error_code' => null,
            'last_error_message' => null,
            'credentials_encrypted' => null,
            'capabilities' => $capabilities !== [] ? $capabilities : [
                'rates' => true,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
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

    public function isPlatformTesting(): bool
    {
        return $this->ownership_mode === self::OWNERSHIP_PLATFORM_TESTING
            || $this->connection_mode === self::CONNECTION_MODE_USPS_PLATFORM
            || $this->connection_status === self::CONNECTION_SANDBOX_PLATFORM_FALLBACK;
    }

    public function isMerchantOwned(): bool
    {
        return $this->ownership_mode === self::OWNERSHIP_MERCHANT_OWNED
            || ($this->connection_owner === self::CONNECTION_OWNER_MERCHANT
                && ! $this->isManualProvider()
                && ! $this->isPlatformTesting());
    }

    public function usesPlatformCredentials(): bool
    {
        return $this->credentials_source === self::CREDENTIALS_PLATFORM_ENV;
    }

    public function usesMerchantCredentials(): bool
    {
        return in_array($this->credentials_source, [
            self::CREDENTIALS_MERCHANT_ACCOUNT,
            self::CREDENTIALS_MERCHANT_ENCRYPTED,
            self::CREDENTIALS_MERCHANT_OAUTH,
            self::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION,
        ], true);
    }

    public function supportsRates(): bool
    {
        return (bool) data_get($this->capabilities, 'rates', false);
    }

    public function supportsLabels(): bool
    {
        return (bool) data_get($this->capabilities, 'labels', false);
    }

    public function supportsTracking(): bool
    {
        return (bool) data_get($this->capabilities, 'tracking', false);
    }

    public function supportsPickup(): bool
    {
        return (bool) data_get($this->capabilities, 'pickup', false);
    }

    public function defaultOriginLocationId(): ?int
    {
        if (filled($this->default_origin_location_id)) {
            return (int) $this->default_origin_location_id;
        }

        $settingsId = data_get($this->settings, 'default_origin_location_id');

        return filled($settingsId) ? (int) $settingsId : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForManual(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_MANUAL,
            'connection_owner' => self::CONNECTION_OWNER_MERCHANT,
            'credentials_source' => self::CREDENTIALS_MANUAL_ENTRY,
            'billing_owner' => self::BILLING_OWNER_MERCHANT,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForUspsPlatformTesting(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_PLATFORM_TESTING,
            'connection_owner' => self::CONNECTION_OWNER_PLATFORM,
            'credentials_source' => self::CREDENTIALS_PLATFORM_ENV,
            'billing_owner' => self::BILLING_OWNER_PLATFORM,
            'capabilities' => [
                'rates' => true,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'sandbox_connection' => true,
                'platform_credentials' => true,
                'merchant_owned_connection' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForUspsMerchantLabelProvider(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_MERCHANT_OWNED,
            'connection_owner' => self::CONNECTION_OWNER_MERCHANT,
            'credentials_source' => self::CREDENTIALS_NONE,
            'billing_owner' => self::BILLING_OWNER_MERCHANT,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
                'merchant_owned_connection' => true,
                'usps_label_provider' => true,
                'portal_authorization_required' => true,
            ],
            'connection_model' => self::CONNECTION_MODEL_USPS_PLATFORM_LABEL_PROVIDER,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForFedExMerchantOwned(): array
    {
        return self::ownershipAttributesForFedExMerchantCredentials();
    }

    /**
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForFedExMerchantCredentials(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_MERCHANT_OWNED,
            'connection_owner' => self::CONNECTION_OWNER_MERCHANT,
            'credentials_source' => self::CREDENTIALS_MERCHANT_ENCRYPTED,
            'billing_owner' => self::BILLING_OWNER_MERCHANT,
            'connection_mode' => self::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
                'merchant_owned_connection' => true,
                'merchant_credentials_mode' => true,
            ],
        ];
    }

    public static function ownershipAttributesForFedExIntegratorProvider(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_MERCHANT_OWNED,
            'connection_owner' => self::CONNECTION_OWNER_MERCHANT,
            'credentials_source' => self::CREDENTIALS_MERCHANT_ACCOUNT,
            'billing_owner' => self::BILLING_OWNER_MERCHANT,
            'connection_mode' => self::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_model' => self::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'fedex_integrator_account' => true,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
                'merchant_owned_connection' => true,
                'integrator_provider' => true,
            ],
        ];
    }

    /**
     * Legacy integrator registration attributes (local/testing diagnostics only).
     *
     * @return array<string, mixed>
     */
    public static function ownershipAttributesForFedExIntegratorRegistration(): array
    {
        return [
            'ownership_mode' => self::OWNERSHIP_MERCHANT_OWNED,
            'connection_owner' => self::CONNECTION_OWNER_MERCHANT,
            'credentials_source' => self::CREDENTIALS_MERCHANT_ACCOUNT,
            'billing_owner' => self::BILLING_OWNER_MERCHANT,
            'connection_mode' => self::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'fedex_integrator_account' => false,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
                'merchant_owned_connection' => true,
            ],
        ];
    }

    public function syncOriginValidation(?string $status, ?string $summary): void
    {
        $this->forceFill([
            'origin_validation_status' => $status,
            'origin_validation_summary' => $summary,
        ])->save();
    }

    public function assignDefaultOriginLocation(?int $locationId): void
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        if ($locationId === null) {
            unset($settings['default_origin_location_id']);
        } else {
            $settings['default_origin_location_id'] = $locationId;
        }

        $this->forceFill([
            'default_origin_location_id' => $locationId,
            'settings' => $settings,
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

    public function setUspsMerchantIdentifiers(
        string $crid,
        string $mid,
        string $epa,
        ?string $manifestMid = null,
    ): void {
        $credentials = $this->credentials();
        $credentials['merchant_crid'] = trim($crid);
        $credentials['merchant_mid'] = trim($mid);
        $credentials['merchant_epa'] = trim($epa);

        if (filled($manifestMid)) {
            $credentials['merchant_manifest_mid'] = trim((string) $manifestMid);
        } else {
            unset($credentials['merchant_manifest_mid']);
        }

        $this->credentials_encrypted = $credentials;
        $this->credentials_source = self::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION;

        $context = is_array($this->connection_context_json) ? $this->connection_context_json : [];
        $merchant = is_array($context['usps_merchant'] ?? null) ? $context['usps_merchant'] : [];
        $merchant['merchant_crid_masked'] = self::maskSensitiveIdentifier($crid);
        $merchant['merchant_mid_masked'] = self::maskSensitiveIdentifier($mid);
        $merchant['merchant_epa_masked'] = self::maskSensitiveIdentifier($epa);

        if (filled($manifestMid)) {
            $merchant['manifest_mid_masked'] = self::maskSensitiveIdentifier((string) $manifestMid);
        }

        $context['usps_merchant'] = $merchant;
        $this->connection_context_json = $context;
    }

    public function hasUspsMerchantIdentifiers(): bool
    {
        return filled($this->uspsMerchantCrid())
            && filled($this->uspsMerchantMid())
            && filled($this->uspsMerchantEpa());
    }

    public function uspsMerchantCrid(): ?string
    {
        $value = (string) ($this->credentials()['merchant_crid'] ?? '');

        return $value !== '' ? $value : null;
    }

    public function uspsMerchantMid(): ?string
    {
        $value = (string) ($this->credentials()['merchant_mid'] ?? '');

        return $value !== '' ? $value : null;
    }

    public function uspsMerchantEpa(): ?string
    {
        $value = (string) ($this->credentials()['merchant_epa'] ?? '');

        return $value !== '' ? $value : null;
    }

    public function uspsMerchantManifestMid(): ?string
    {
        $value = (string) ($this->credentials()['merchant_manifest_mid'] ?? '');

        return $value !== '' ? $value : null;
    }

    public function setMerchantOAuthTokens(
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn,
        ?string $subjectId = null,
    ): void {
        $credentials = $this->credentials();
        $credentials['oauth_access_token'] = trim($accessToken);

        if (filled($refreshToken)) {
            $credentials['oauth_refresh_token'] = trim((string) $refreshToken);
        }

        $credentials['oauth_expires_at'] = now()->addSeconds(max(60, $expiresIn))->toIso8601String();

        if (filled($subjectId)) {
            $credentials['oauth_subject_id'] = trim((string) $subjectId);
        }

        $this->credentials_encrypted = $credentials;
        $this->credentials_source = self::CREDENTIALS_MERCHANT_OAUTH;
        $this->save();

        if (filled($subjectId)) {
            $this->storeMerchantOAuthSubjectContext(trim((string) $subjectId));
        }
    }

    public function setMerchantOAuthSubjectId(string $subjectId): void
    {
        $subjectId = trim($subjectId);

        if ($subjectId === '') {
            return;
        }

        $credentials = $this->credentials();
        $credentials['oauth_subject_id'] = $subjectId;
        $this->credentials_encrypted = $credentials;
        $this->save();

        $this->storeMerchantOAuthSubjectContext($subjectId);
    }

    public function merchantOAuthSubjectId(): ?string
    {
        $subjectId = (string) ($this->credentials()['oauth_subject_id'] ?? '');

        return $subjectId !== '' ? $subjectId : null;
    }

    public function hasMerchantOAuthSubjectId(): bool
    {
        return filled($this->merchantOAuthSubjectId());
    }

    public function clearMerchantOAuthSubjectId(): void
    {
        $credentials = $this->credentials();
        unset($credentials['oauth_subject_id']);
        $this->credentials_encrypted = $credentials;
        $this->save();

        $context = is_array($this->connection_context_json) ? $this->connection_context_json : [];
        $merchant = is_array($context['usps_merchant'] ?? null) ? $context['usps_merchant'] : [];
        unset($merchant['oauth_subject_id_masked'], $merchant['oauth_subject_recorded_at']);
        $context['usps_merchant'] = $merchant;
        $this->forceFill(['connection_context_json' => $context])->save();
    }

    public function markUspsMerchantActiveForStore(): void
    {
        if (! $this->isUspsMerchantLabelProvider()) {
            return;
        }

        $this->forceFill([
            'usps_active_store_key' => $this->store_id,
        ])->save();
    }

    public function clearUspsMerchantActiveForStore(): void
    {
        if (! $this->isUspsMerchantLabelProvider()) {
            return;
        }

        $this->forceFill([
            'usps_active_store_key' => null,
        ])->save();
    }

    private function storeMerchantOAuthSubjectContext(string $subjectId): void
    {
        $context = is_array($this->connection_context_json) ? $this->connection_context_json : [];
        $merchant = is_array($context['usps_merchant'] ?? null) ? $context['usps_merchant'] : [];
        $merchant['oauth_subject_id_masked'] = self::maskSensitiveIdentifier($subjectId);
        $merchant['oauth_subject_recorded_at'] = now()->toIso8601String();
        $context['usps_merchant'] = $merchant;
        $this->forceFill(['connection_context_json' => $context])->save();
    }

    public function hasMerchantOAuthTokens(): bool
    {
        return filled($this->credentials()['oauth_access_token'] ?? null);
    }

    public function merchantOAuthAccessToken(): ?string
    {
        $token = (string) ($this->credentials()['oauth_access_token'] ?? '');

        return $token !== '' ? $token : null;
    }

    public function merchantOAuthRefreshToken(): ?string
    {
        $token = (string) ($this->credentials()['oauth_refresh_token'] ?? '');

        return $token !== '' ? $token : null;
    }

    public function merchantOAuthAccessTokenExpired(): bool
    {
        $expiresAt = (string) ($this->credentials()['oauth_expires_at'] ?? '');

        if ($expiresAt === '') {
            return true;
        }

        try {
            return now()->greaterThanOrEqualTo(\Illuminate\Support\Carbon::parse($expiresAt)->subSeconds(60));
        } catch (\Throwable) {
            return true;
        }
    }

    public function clearMerchantOAuthTokens(): void
    {
        $credentials = $this->credentials();
        unset(
            $credentials['oauth_access_token'],
            $credentials['oauth_refresh_token'],
            $credentials['oauth_expires_at'],
        );

        $this->credentials_encrypted = $credentials;

        if ($this->hasUspsMerchantIdentifiers()) {
            $this->credentials_source = self::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION;
        } elseif ($credentials === []) {
            $this->credentials_source = self::CREDENTIALS_NONE;
        }

        $this->save();
    }

    public static function maskSensitiveIdentifier(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', max(0, strlen($value) - 4)).substr($value, -4);
    }

    public function hasMerchantCredentials(): bool
    {
        return $this->hasMerchantFedExDeveloperCredentials()
            || $this->hasLegacyFedExChildCredentials();
    }

    public function hasMerchantFedExDeveloperCredentials(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['client_id'] ?? null)
            && filled($credentials['client_secret'] ?? null);
    }

    public function hasLegacyFedExChildCredentials(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['customer_key'] ?? null)
            && filled($credentials['customer_password'] ?? null);
    }

    public function usesMerchantFedExDeveloperCredentials(): bool
    {
        return $this->isFedEx()
            && (
                $this->connection_mode === self::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS
                || $this->credentials_source === self::CREDENTIALS_MERCHANT_ENCRYPTED
            );
    }

    public function usesUspsMerchantAuthorizationCredentials(): bool
    {
        return $this->isUspsMerchantLabelProvider()
            && in_array($this->credentials_source, [
                self::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION,
                self::CREDENTIALS_MERCHANT_OAUTH,
            ], true);
    }

    public function usesLegacyFedExIntegratorRegistration(): bool
    {
        return $this->isFedEx()
            && $this->connection_mode === self::CONNECTION_MODE_FEDEX_INTEGRATOR
            && ! $this->usesMerchantFedExDeveloperCredentials();
    }

    public function usesFedExIntegratorProvider(): bool
    {
        return $this->isFedEx()
            && (
                $this->connection_model === self::CONNECTION_MODEL_INTEGRATOR_PROVIDER
                || (bool) $this->fedex_integrator_account
            )
            && ! $this->usesMerchantFedExDeveloperCredentials();
    }

    public function usesIntegratorChildCredentials(): bool
    {
        return $this->usesFedExIntegratorProvider() && $this->hasLegacyFedExChildCredentials();
    }

    public function canUseFedExApiChecks(): bool
    {
        return $this->usesIntegratorChildCredentials()
            || ($this->usesMerchantFedExDeveloperCredentials() && $this->hasMerchantFedExDeveloperCredentials());
    }

    public function merchantFedExClientId(): ?string
    {
        $clientId = (string) ($this->credentials()['client_id'] ?? '');

        return $clientId !== '' ? $clientId : null;
    }

    public function merchantFedExClientSecret(): ?string
    {
        $secret = (string) ($this->credentials()['client_secret'] ?? '');

        return $secret !== '' ? $secret : null;
    }

    public function maskedMerchantClientId(): string
    {
        $clientId = (string) ($this->merchantFedExClientId() ?? '');

        if ($clientId === '') {
            return '—';
        }

        if (strlen($clientId) <= 4) {
            return str_repeat('*', strlen($clientId));
        }

        return str_repeat('*', max(0, strlen($clientId) - 4)).substr($clientId, -4);
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
