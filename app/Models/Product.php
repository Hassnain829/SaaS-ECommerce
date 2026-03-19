<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'base_price',
        'sku',
        'product_type',
        'status',
        'meta',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'status' => 'boolean',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variationTypes(): HasMany
    {
        return $this->hasMany(ProductVariationType::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
