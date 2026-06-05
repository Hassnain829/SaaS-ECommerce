<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierApiEvent extends Model
{
    public const STATUS_STARTED = 'started';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const ACTION_ACCOUNT_REGISTRATION = 'account_registration';

    public const ACTION_OAUTH_TOKEN = 'oauth_token';

    public const ACTION_PLATFORM_OAUTH_TOKEN = 'platform_oauth_token';

    public const ACTION_MERCHANT_OAUTH_TOKEN = 'merchant_oauth_token';

    public const ACTION_TEST_CONNECTION = 'test_connection';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'shipment_id',
        'provider',
        'environment',
        'action',
        'status',
        'request_id',
        'duration_ms',
        'request_summary',
        'response_summary',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'request_summary' => 'array',
        'response_summary' => 'array',
        'duration_ms' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
