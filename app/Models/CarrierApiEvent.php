<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierApiEvent extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const ACTION_ACCOUNT_REGISTRATION = 'account_registration';

    public const ACTION_OAUTH_TOKEN = 'oauth_token';

    public const ACTION_PLATFORM_OAUTH_TOKEN = 'platform_oauth_token';

    public const ACTION_MERCHANT_OAUTH_TOKEN = 'merchant_oauth_token';

    public const SCENARIO_AUTHORIZATION_PARENT = 'authorization_parent';

    public const SCENARIO_AUTHORIZATION_CHILD = 'authorization_child';

    public const SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS = 'registration_sweden_passthrough_address';

    public const SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD = 'authorization_sweden_passthrough_child';

    public const ACTION_TEST_CONNECTION = 'test_connection';

    public const ACTION_ADDRESS_VALIDATION = 'address_validation';

    public const ACTION_DOMESTIC_RATE_QUOTE = 'domestic_rate_quote';

    public const ACTION_FEDEX_ADDRESS_VALIDATION = 'fedex_address_validation';

    public const ACTION_FEDEX_SERVICE_AVAILABILITY = 'fedex_service_availability';

    public const ACTION_FEDEX_RATE_QUOTE = 'fedex_rate_quote';

    public const ACTION_FEDEX_SHIP_VALIDATE = 'fedex_ship_validate';

    public const ACTION_FEDEX_SHIP_CREATE_LABEL = 'fedex_ship_create_label';

    public const ACTION_FEDEX_SHIP_CANCEL = 'fedex_ship_cancel';

    public const ACTION_FEDEX_SHIP_EVIDENCE_EXPORT = 'fedex_ship_evidence_export';

    public const ACTION_FEDEX_BASIC_INTEGRATED_VISIBILITY = 'fedex_basic_integrated_visibility';

    public const ACTION_FEDEX_TRADE_DOCUMENTS_UPLOAD = 'fedex_trade_documents_upload';

    public const SCENARIO_REGISTRATION_ADDRESS = 'registration_address_validation';

    public const SCENARIO_REGISTRATION_INVOICE = 'registration_invoice_validation';

    public const SCENARIO_REGISTRATION_PIN_GENERATION_SMS = 'registration_pin_generation_sms';

    public const SCENARIO_REGISTRATION_PIN_VALIDATION_SMS = 'registration_pin_validation_sms';

    public const SCENARIO_REGISTRATION_PIN_GENERATION_EMAIL = 'registration_pin_generation_email';

    public const SCENARIO_REGISTRATION_PIN_VALIDATION_EMAIL = 'registration_pin_validation_email';

    public const SCENARIO_REGISTRATION_PIN_GENERATION_CALL = 'registration_pin_generation_call';

    public const SCENARIO_REGISTRATION_PIN_VALIDATION_CALL = 'registration_pin_validation_call';

    public const SCENARIO_REGISTRATION_CHILD_CREDENTIALS = 'registration_child_credentials_generated';

    public const SCENARIO_RATE_COMPREHENSIVE_QUOTE = 'rate_comprehensive_quote';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'registration_session_id',
        'shipment_id',
        'provider',
        'environment',
        'action',
        'scenario_key',
        'test_case_key',
        'mfa_method',
        'label_format',
        'package_count',
        'endpoint',
        'http_method',
        'http_status',
        'fedex_transaction_id',
        'status',
        'request_id',
        'duration_ms',
        'request_summary',
        'response_summary',
        'error_code',
        'error_message',
        'request_headers_encrypted',
        'request_body_encrypted',
        'response_headers_encrypted',
        'response_body_encrypted',
        'evidence_recorded_at',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'response_summary' => 'array',
        'duration_ms' => 'integer',
        'package_count' => 'integer',
        'http_status' => 'integer',
        'request_headers_encrypted' => 'encrypted:array',
        'request_body_encrypted' => 'encrypted:array',
        'response_headers_encrypted' => 'encrypted:array',
        'response_body_encrypted' => 'encrypted:array',
        'evidence_recorded_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function registrationSession(): BelongsTo
    {
        return $this->belongsTo(CarrierAccountRegistrationSession::class, 'registration_session_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function validationArtifacts(): HasMany
    {
        return $this->hasMany(FedExValidationArtifact::class, 'carrier_api_event_id');
    }

    public function hasCompleteEvidence(): bool
    {
        return filled($this->request_body_encrypted)
            && filled($this->response_body_encrypted)
            && filled($this->http_status);
    }

    public function isSuccessfulHttp(): bool
    {
        return $this->http_status !== null
            && $this->http_status >= 200
            && $this->http_status < 300
            && $this->status === self::STATUS_SUCCEEDED;
    }
}
