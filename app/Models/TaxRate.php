<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    protected $fillable = [
        'store_id',
        'country_code',
        'region_code',
        'name',
        'rate_percent',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:4',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function checkoutTaxLines(): HasMany
    {
        return $this->hasMany(CheckoutTaxLine::class);
    }

    public function orderTaxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    public function draftTaxLines(): HasMany
    {
        return $this->hasMany(DraftTaxLine::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeForJurisdiction($query, string $countryCode, ?string $regionCode = null)
    {
        return $query
            ->where('country_code', self::normalizeCountryCode($countryCode))
            ->where('region_code', self::normalizeRegionCode($regionCode));
    }

    public static function normalizeCountryCode(?string $countryCode): string
    {
        return mb_strtoupper(trim((string) $countryCode));
    }

    public static function normalizeRegionCode(mixed $regionCode): string
    {
        $region = trim((string) $regionCode);

        if ($region === '') {
            return '';
        }

        return mb_strtoupper($region);
    }

    public function setCountryCodeAttribute(?string $value): void
    {
        $this->attributes['country_code'] = self::normalizeCountryCode($value);
    }

    public function setRegionCodeAttribute(mixed $value): void
    {
        $this->attributes['region_code'] = self::normalizeRegionCode($value);
    }
}
