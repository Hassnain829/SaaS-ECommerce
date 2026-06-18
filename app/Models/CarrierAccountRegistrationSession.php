<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierAccountRegistrationSession extends Model
{
    public const PROVIDER_FEDEX = 'fedex';

    public const CONNECTION_MODEL_INTEGRATOR_PROVIDER = 'integrator_provider';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ORIGIN_SELECTED = 'origin_selected';

    public const STATUS_EULA_REQUIRED = 'eula_required';

    public const STATUS_EULA_ACCEPTED = 'eula_accepted';

    public const STATUS_ACCOUNT_DETAILS_SUBMITTED = 'account_details_submitted';

    public const STATUS_FACTOR1_PENDING = 'factor1_pending';

    public const STATUS_MFA_METHOD_REQUIRED = 'mfa_method_required';

    public const STATUS_PIN_PENDING = 'pin_pending';

    public const STATUS_INVOICE_PENDING = 'invoice_pending';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_CANCELLED = 'cancelled';

    public const MFA_EMAIL = 'email';

    public const MFA_SMS = 'sms';

    public const MFA_CALL = 'call';

    public const MFA_INVOICE = 'invoice';

    public const MFA_NONE = 'none';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'provider',
        'environment',
        'connection_model',
        'status',
        'origin_location_id',
        'account_number_encrypted',
        'account_last4',
        'account_name',
        'registration_address_json',
        'residential',
        'eula_version',
        'eula_accepted_at',
        'eula_accepted_by',
        'mfa_method',
        'mfa_destination_masked',
        'mfa_attempt_count',
        'mfa_expires_at',
        'fedex_transaction_id',
        'fedex_account_auth_token_encrypted',
        'account_auth_token_expires_at',
        'fedex_customer_key_encrypted',
        'fedex_customer_password_encrypted',
        'last_error_code',
        'last_error_message',
        'request_summary_json',
        'response_summary_json',
        'mfa_options_json',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'account_number_encrypted' => 'encrypted',
        'fedex_account_auth_token_encrypted' => 'encrypted',
        'fedex_customer_key_encrypted' => 'encrypted',
        'fedex_customer_password_encrypted' => 'encrypted',
        'registration_address_json' => 'array',
        'request_summary_json' => 'array',
        'response_summary_json' => 'array',
        'mfa_options_json' => 'array',
        'residential' => 'boolean',
        'eula_accepted_at' => 'datetime',
        'account_auth_token_expires_at' => 'datetime',
        'mfa_expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'account_number_encrypted',
        'fedex_account_auth_token_encrypted',
        'fedex_customer_key_encrypted',
        'fedex_customer_password_encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function eulaAcceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eula_accepted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validationArtifacts(): HasMany
    {
        return $this->hasMany(FedExValidationArtifact::class, 'registration_session_id');
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_REGISTERED,
            self::STATUS_FAILED,
            self::STATUS_LOCKED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function maskedAccountNumber(): string
    {
        if (filled($this->account_last4)) {
            return '*****'.$this->account_last4;
        }

        return '—';
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationAddress(): array
    {
        return is_array($this->registration_address_json) ? $this->registration_address_json : [];
    }

    public function accountNumber(): ?string
    {
        $value = $this->account_number_encrypted;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setAccountNumber(string $accountNumber): void
    {
        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';
        $this->account_number_encrypted = $digits;
        $this->account_last4 = strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    public function setAccountAuthToken(string $token, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->fedex_account_auth_token_encrypted = $token;
        $this->account_auth_token_expires_at = $expiresAt;
    }

    public function accountAuthToken(): ?string
    {
        $value = $this->fedex_account_auth_token_encrypted;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function hasAccountAuthToken(): bool
    {
        return filled($this->accountAuthToken());
    }

    public function isAccountAuthTokenExpired(): bool
    {
        if ($this->account_auth_token_expires_at === null) {
            return false;
        }

        return $this->account_auth_token_expires_at->isPast();
    }

    public function setChildCredentials(string $customerKey, string $customerPassword): void
    {
        $this->fedex_customer_key_encrypted = $customerKey;
        $this->fedex_customer_password_encrypted = $customerPassword;
    }

    /**
     * @return array{customer_key: string, customer_password: string}|null
     */
    public function childCredentials(): ?array
    {
        $key = $this->fedex_customer_key_encrypted;
        $password = $this->fedex_customer_password_encrypted;

        if (! filled($key) || ! filled($password)) {
            return null;
        }

        return [
            'customer_key' => (string) $key,
            'customer_password' => (string) $password,
        ];
    }
}
