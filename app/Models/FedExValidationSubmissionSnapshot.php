<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExValidationSubmissionSnapshot extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_INVALIDATED = 'invalidated';

    protected $table = 'fedex_validation_submission_snapshots';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'case_reference',
        'status',
        'preflight_hash',
        'snapshot_manifest_json',
        'evidence_ids_json',
        'artifact_ids_json',
        'waiver_ids_json',
        'baseline_versions_json',
        'capability_registry_version',
        'logo_sha256',
        'created_by',
        'finalized_at',
        'invalidated_at',
        'invalidation_reason',
        'export_zip_path',
    ];

    protected $casts = [
        'snapshot_manifest_json' => 'array',
        'evidence_ids_json' => 'array',
        'artifact_ids_json' => 'array',
        'waiver_ids_json' => 'array',
        'baseline_versions_json' => 'array',
        'finalized_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->invalidated_at === null;
    }
}
