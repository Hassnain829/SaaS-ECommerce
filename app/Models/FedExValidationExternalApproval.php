<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExValidationExternalApproval extends Model
{
    public const AREA_TRACKING = 'tracking';

    public const AREA_SCOPE_REDUCTION = 'scope_reduction';

    public const AREA_REGIONAL_CASE_EXCEPTION = 'regional_case_exception';

    protected $table = 'fedex_validation_external_approvals';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'case_reference',
        'area',
        'approval_date',
        'source_artifact_id',
        'applies_to_check_keys_json',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'approval_date' => 'date',
        'applies_to_check_keys_json' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(FedExValidationArtifact::class, 'source_artifact_id');
    }
}
