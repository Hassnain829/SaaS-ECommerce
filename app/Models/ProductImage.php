<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /**
     * Short sentinel stored in image_path while a remote URL is waiting in source_url.
     */
    public const PENDING_DISK_PATH = '__import_image_pending__';

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_primary',
        'status',
        'processing_started_at',
        'processed_at',
        'failure_reason',
        'source_url',
        'product_import_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
        'processing_started_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ProductImage $image): void {
            if ($image->image_path === '' || $image->image_path === null) {
                return;
            }
            if ($image->image_path === self::PENDING_DISK_PATH) {
                return;
            }
            Storage::disk('public')->delete($image->image_path);
        });
    }

    public function isReady(): bool
    {
        return ($this->status ?? self::STATUS_READY) === self::STATUS_READY
            && $this->image_path !== null
            && $this->image_path !== ''
            && $this->image_path !== self::PENDING_DISK_PATH;
    }

    public function isPendingVisual(): bool
    {
        $s = (string) ($this->status ?? self::STATUS_READY);

        return $s === self::STATUS_QUEUED || $s === self::STATUS_PROCESSING;
    }

    public function isFailed(): bool
    {
        return ($this->status ?? '') === self::STATUS_FAILED;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productImport(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}
