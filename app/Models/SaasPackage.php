<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SaasPackage extends Model
{
    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';

    public const INTERVAL_ONE_TIME = 'one_time';

    public const BILLING_INTERVALS = [
        self::INTERVAL_MONTH,
        self::INTERVAL_YEAR,
        self::INTERVAL_ONE_TIME,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_cents',
        'billing_interval',
        'currency',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaasPackage $package): void {
            if (! filled($package->slug) && filled($package->name)) {
                $package->slug = static::uniqueSlugFromName($package->name, $package->id);
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(StoreSubscription::class, 'package_id');
    }

    public function formattedPrice(): string
    {
        $amount = number_format($this->price_cents / 100, 2);
        $currency = strtoupper((string) $this->currency);

        return $currency.' '.$amount;
    }

    public function billingIntervalLabel(): string
    {
        return match ($this->billing_interval) {
            self::INTERVAL_YEAR => 'per year',
            self::INTERVAL_ONE_TIME => 'one-time',
            default => 'per month',
        };
    }

    public static function uniqueSlugFromName(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'package';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
