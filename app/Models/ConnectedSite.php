<?php

namespace App\Models;

use App\Support\ConnectedSiteScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectedSite extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'store_id',
        'public_id',
        'site_url',
        'site_url_normalized',
        'active_site_url_key',
        'credential_hash',
        'event_signing_secret',
        'status',
        'is_primary',
        'scopes',
        'plugin_version',
        'last_seen_at',
        'last_seen_ip',
        'last_health_at',
        'last_health',
        'credential_created_at',
        'credential_rotated_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'event_signing_secret' => 'encrypted',
        'scopes' => 'array',
        'last_health' => 'array',
        'last_seen_at' => 'datetime',
        'last_health_at' => 'datetime',
        'credential_created_at' => 'datetime',
        'credential_rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'credential_hash',
        'event_signing_secret',
    ];

    protected static function booted(): void
    {
        static::saving(function (ConnectedSite $site): void {
            $site->active_site_url_key = $site->status === self::STATUS_ACTIVE
                && filled($site->site_url_normalized)
                    ? $site->site_url_normalized
                    : null;
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function eventDeliveries(): HasMany
    {
        return $this->hasMany(ConnectedSiteEventDelivery::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return in_array($scope, $scopes, true);
    }

    /**
     * @return list<string>
     */
    public function grantedScopes(): array
    {
        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return array_values(array_intersect(ConnectedSiteScope::ALL, $scopes));
    }
}
