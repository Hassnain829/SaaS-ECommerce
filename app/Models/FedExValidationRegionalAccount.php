<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExValidationRegionalAccount extends Model
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_REGISTRATION_REQUIRED = 'registration_required';

    public const STATUS_MFA_REQUIRED = 'mfa_required';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'fedex_validation_regional_accounts';

    protected $fillable = [
        'store_id',
        'root_carrier_account_id',
        'environment',
        'region',
        'country_code',
        'account_number_encrypted',
        'account_number_hash',
        'account_last4',
        'registration_session_id',
        'child_key_encrypted',
        'child_secret_encrypted',
        'status',
        'credential_source',
        'baseline_version',
        'registered_at',
        'last_oauth_at',
        'metadata_json',
    ];

    protected $casts = [
        'account_number_encrypted' => 'encrypted',
        'child_key_encrypted' => 'encrypted',
        'child_secret_encrypted' => 'encrypted',
        'metadata_json' => 'array',
        'registered_at' => 'datetime',
        'last_oauth_at' => 'datetime',
    ];

    protected $hidden = [
        'account_number_encrypted',
        'account_number_hash',
        'child_key_encrypted',
        'child_secret_encrypted',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function rootCarrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class, 'root_carrier_account_id');
    }

    public function maskedAccountNumber(): string
    {
        $last4 = (string) ($this->account_last4 ?? '');

        return $last4 !== '' ? '****'.$last4 : '—';
    }

    public function isReadyForShip(): bool
    {
        return in_array($this->status, [self::STATUS_CONNECTED, self::STATUS_MFA_REQUIRED], true)
            && filled($this->account_number_encrypted);
    }
}
