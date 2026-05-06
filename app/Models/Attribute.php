<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'display_type',
        'sort_order',
        'is_filterable',
        'is_visible',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(AttributeTerm::class);
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }
}
