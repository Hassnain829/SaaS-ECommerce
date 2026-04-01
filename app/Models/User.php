<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;
use App\Models\Store;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function memberStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleInStore(Store|int|null $store): ?string
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        if (! $storeId) {
            return null;
        }

        $loadedStore = $this->relationLoaded('memberStores')
            ? $this->memberStores->firstWhere('id', $storeId)
            : null;

        if ($loadedStore) {
            return $loadedStore->pivot?->role;
        }

        return $this->memberStores()
            ->where('stores.id', $storeId)
            ->first()?->pivot?->role;
    }

    public function belongsToStore(Store|int|null $store): bool
    {
        return $this->roleInStore($store) !== null;
    }

    public function hasStoreRole(Store|int|null $store, string|array $roles): bool
    {
        $role = $this->roleInStore($store);

        if (! $role) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($role, $roles, true);
    }
}
