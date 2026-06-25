<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftTaxLine extends Model
{
    public const APPLIES_TO_ITEMS = 'items';

    public const APPLIES_TO_SHIPPING = 'shipping';

    protected $fillable = [
        'store_id',
        'draft_order_id',
        'tax_rate_id',
        'jurisdiction_country_code',
        'jurisdiction_region_code',
        'rate_percent',
        'taxable_amount',
        'tax_amount',
        'applies_to',
        'settings_version',
        'calculated_at',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:4',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'settings_version' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
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
