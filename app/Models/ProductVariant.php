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
        'source_system',
        'source_site',
        'source_variation_id',
        'price',
        'compare_at_price',
        'product_image_id',
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

    /**
     * Selling price for this variant.
     *
     * A stored NULL means "inherit the product's base price", the same way a
     * blank shipping weight inherits the product or store fallback. Reading
     * `$variant->price` therefore always gives the price a shopper pays, while
     * `priceOverride()` exposes the raw value the merchant actually set.
     */
    public function getPriceAttribute(mixed $value): ?string
    {
        if ($value !== null && $value !== '') {
            return number_format((float) $value, 2, '.', '');
        }

        $basePrice = $this->resolveProductBasePrice();

        return $basePrice === null ? null : number_format($basePrice, 2, '.', '');
    }

    /**
     * The merchant's explicit price for this variant, or null when it inherits.
     */
    public function priceOverride(): ?string
    {
        $raw = $this->attributes['price'] ?? null;

        return ($raw === null || $raw === '') ? null : number_format((float) $raw, 2, '.', '');
    }

    public function hasPriceOverride(): bool
    {
        return $this->priceOverride() !== null;
    }

    private function resolveProductBasePrice(): ?float
    {
        if ($this->relationLoaded('product')) {
            return $this->product ? (float) $this->product->base_price : null;
        }

        if (! $this->product_id) {
            return null;
        }

        $basePrice = Product::query()->whereKey($this->product_id)->value('base_price');

        return $basePrice === null ? null : (float) $basePrice;
    }

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
     * Catalog image chosen for this variant. Many variants may share one product image.
     */
    public function linkedCatalogImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    /**
     * Ordered gallery photos assigned to this variant. The first row is also stored on product_image_id.
     */
    public function catalogImages(): BelongsToMany
    {
        return $this->belongsToMany(ProductImage::class, 'product_variant_images')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('product_images.id');
    }

    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class, 'variant_id');
    }
}
