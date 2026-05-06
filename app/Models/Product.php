<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Gallery files live on disk; paths and ordering live in {@see ProductImage} (source of truth).
 * `meta` holds non-image product data only (e.g. default_stock, stock_alert); do not use it for images.
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'base_price',
        'sku',
        'product_type',
        'requires_shipping',
        'track_inventory',
        'status',
        'meta',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'requires_shipping' => 'boolean',
        'track_inventory' => 'boolean',
        'status' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            // DB cascade removes product_images rows without model events; delete files here.
            $paths = $product->images()->pluck('image_path');
            foreach ($paths as $path) {
                if ($path && $path !== ProductImage::PENDING_DISK_PATH) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
            ->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Primary gallery image if flagged; otherwise first ordered row.
     */
    public function primaryImage(): ?ProductImage
    {
        $primary = $this->images()->where('is_primary', true)->orderBy('sort_order')->first();

        if ($primary) {
            return $primary;
        }

        return $this->images()->orderBy('sort_order')->orderBy('id')->first();
    }

    public function variationTypes(): HasMany
    {
        return $this->hasMany(ProductVariationType::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withPivot(['is_variation', 'is_visible', 'sort_order'])
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
