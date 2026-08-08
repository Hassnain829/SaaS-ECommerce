<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingPackagePreset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'weight_value',
        'weight_unit',
        'length',
        'width',
        'height',
        'dimension_unit',
        'package_type',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'weight_value' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForStore(Builder $query, Store|int $store): Builder
    {
        $storeId = $store instanceof Store ? (int) $store->id : (int) $store;

        return $query->where('store_id', $storeId);
    }

    public function hasCompleteDimensions(): bool
    {
        return is_numeric($this->length) && (float) $this->length > 0
            && is_numeric($this->width) && (float) $this->width > 0
            && is_numeric($this->height) && (float) $this->height > 0;
    }
}
