<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'product_id',
        'sku',
        'price',
        'compare_at_price',
        'stock',
        'stock_alert',
        'image',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant): void {
            if ($variant->store_id) {
                return;
            }

            if ($variant->relationLoaded('product') && $variant->product) {
                $variant->store_id = $variant->product->store_id;

                return;
            }

            if ($variant->product_id) {
                $variant->store_id = Product::query()
                    ->whereKey($variant->product_id)
                    ->value('store_id');
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariationOption::class, 'product_variant_options', 'variant_id', 'option_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    /**
     * Catalog image row linked to this variant (normalized media; Day 15).
     */
    public function linkedCatalogImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_variant_id');
    }
}
