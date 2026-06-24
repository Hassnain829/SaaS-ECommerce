<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxSetting extends Model
{
    public const CALCULATION_ADDRESS_SHIPPING = 'shipping';

    protected $fillable = [
        'store_id',
        'enabled',
        'prices_include_tax',
        'default_product_taxable',
        'shipping_taxable',
        'calculation_address',
        'settings_version',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'prices_include_tax' => 'boolean',
        'default_product_taxable' => 'boolean',
        'shipping_taxable' => 'boolean',
        'settings_version' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
