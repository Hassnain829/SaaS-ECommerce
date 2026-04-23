<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImport extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'created_by',
        'original_filename',
        'stored_disk',
        'stored_path',
        'mime_type',
        'file_extension',
        'status',
        'headers',
        'column_mapping',
        'custom_field_mappings',
        'preview_summary',
        'result_summary',
        'failure_message',
        'started_at',
        'queued_at',
        'completed_at',
        'last_processed_row',
        'total_rows',
        'import_state',
    ];

    protected $casts = [
        'headers' => 'array',
        'column_mapping' => 'array',
        'custom_field_mappings' => 'array',
        'preview_summary' => 'array',
        'result_summary' => 'array',
        'started_at' => 'datetime',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
        'import_state' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class, 'product_import_id');
    }
}
