<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExValidationArtifact extends Model
{
    protected $table = 'fedex_validation_artifacts';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'registration_session_id',
        'environment',
        'artifact_type',
        'label',
        'file_path',
        'request_summary_json',
        'response_summary_json',
        'fedex_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'request_summary_json' => 'array',
        'response_summary_json' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
