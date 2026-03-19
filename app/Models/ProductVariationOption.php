<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariationOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'variation_type_id',
        'value',
        'sort_order',
    ];

    public function variationType(): BelongsTo
    {
        return $this->belongsTo(ProductVariationType::class, 'variation_type_id');
    }
}
