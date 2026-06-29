<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExValidationArtifact extends Model
{
    public const ROLE_GENERATED_LABEL = 'generated_label';

    public const ROLE_PRINTED_SCAN = 'printed_scan';

    public const ROLE_VALIDATION_DOCUMENT = 'validation_document';

    public const ROLE_CUSTOMER_SCREENSHOT = 'customer_screenshot_pdf';

    public const ROLE_TRACKING_SCREENSHOT = 'tracking_screenshot';

    public const ROLE_SWEDEN_PASSTHROUGH_SCREENSHOT = 'sweden_passthrough_screenshot';

    public const TYPE_SWEDEN_PASSTHROUGH_ADDRESS_SCREENSHOT = 'sweden_passthrough_address_result';

    public const TYPE_SWEDEN_PASSTHROUGH_CHILD_AUTH_SCREENSHOT = 'sweden_passthrough_child_authorization_result';

    public const ROLE_TRADE_DOCUMENT = 'trade_document_sample';

    public const DOC_COVER_SHEET = 'integrator_validation_cover_sheet';

    public const DOC_PIW = 'product_information_worksheet';

    public const DOC_CUSTOMER_SCREENSHOTS = 'customer_facing_screenshots';

    protected $table = 'fedex_validation_artifacts';

    protected $fillable = [
        'store_id',
        'carrier_account_id',
        'registration_session_id',
        'carrier_api_event_id',
        'environment',
        'artifact_type',
        'scenario_key',
        'test_case_key',
        'label_format',
        'package_sequence',
        'artifact_role',
        'label',
        'original_filename',
        'mime_type',
        'file_size',
        'sha256',
        'scan_dpi',
        'file_path',
        'request_summary_json',
        'response_summary_json',
        'metadata_json',
        'fedex_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'request_summary_json' => 'array',
        'response_summary_json' => 'array',
        'metadata_json' => 'array',
        'package_sequence' => 'integer',
        'file_size' => 'integer',
        'scan_dpi' => 'integer',
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

    public function carrierApiEvent(): BelongsTo
    {
        return $this->belongsTo(CarrierApiEvent::class, 'carrier_api_event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function absolutePath(): ?string
    {
        if (! filled($this->file_path)) {
            return null;
        }

        return storage_path('app/'.$this->file_path);
    }
}
