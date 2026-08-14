<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUrlRedirect extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'product_import_id',
        'source_slug',
        'source_path',
        'destination_slug',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImport(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class);
    }
}
