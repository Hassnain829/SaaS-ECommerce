<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'is_variation',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_variation' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(AttributeTerm::class, 'product_attribute_terms', 'product_attribute_id', 'term_id')
            ->withTimestamps();
    }
}
