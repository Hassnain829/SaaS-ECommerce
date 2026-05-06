<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'name',
        'slug',
        'swatch_value',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function productAttributes(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttribute::class, 'product_attribute_terms', 'term_id', 'product_attribute_id')
            ->withTimestamps();
    }
}
