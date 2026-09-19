<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Support\StorePermission;
use App\Support\StorePermissionResolver;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'must_set_password',
        'role_id',
        'phone',
        'avatar',
        'is_active',
        'last_login_at',
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
            'must_set_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
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

    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function memberStores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->using(StoreUser::class)
            ->withPivot(['role', 'job_title', 'access_preset', 'location_ids', 'status'])
            ->withTimestamps();
    }

    public function activeMemberStores(): BelongsToMany
    {
        return $this->memberStores()
            ->where(function ($query): void {
                $query->where('store_user.role', Store::ROLE_OWNER)
                    ->orWhereNull('store_user.status')
                    ->orWhere('store_user.status', StoreUser::STATUS_ACTIVE);
            });
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

    /**
     * @return list<string>
     */
    public function storePermissions(Store|int|null $store): array
    {
        return StorePermissionResolver::permissionsFor($this, $store);
    }

    public function hasStorePermission(Store|int|null $store, string $permission): bool
    {
        return StorePermissionResolver::userCan($this, $store, $permission);
    }

    public function hasAnyStorePermission(Store|int|null $store, array $permissions): bool
    {
        return StorePermissionResolver::userCanAny($this, $store, $permissions);
    }

    public function canManageCatalog(Store|int|null $store): bool
    {
        return $this->hasAnyStorePermission($store, ['products.edit', StorePermission::CATALOG_MANAGE]);
    }

    public function canManageOrders(Store|int|null $store): bool
    {
        return $this->hasAnyStorePermission($store, ['orders.edit', StorePermission::ORDERS_MANAGE]);
    }

    public function canManageCustomers(Store|int|null $store): bool
    {
        return $this->hasAnyStorePermission($store, ['customers.edit', StorePermission::CUSTOMERS_MANAGE]);
    }

    public function canManageSettings(Store|int|null $store): bool
    {
        return $this->hasStorePermission($store, StorePermission::SETTINGS_MANAGE);
    }

    /**
     * Right to open a new store.
     *
     * Store owners can create additional stores. A brand-new merchant with
     * no memberships can create their first store. Anyone who is a team
     * member of any store cannot create a store, even if they also own
     * another workspace or a leftover Create new stores grant exists.
     */
    public function canCreateStores(?Store $store = null): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->memberStores()->wherePivot('role', '!=', Store::ROLE_OWNER)->exists()) {
            return false;
        }

        if ($store instanceof Store) {
            return $this->roleInStore($store) === Store::ROLE_OWNER;
        }

        if ($this->memberStores()->wherePivot('role', Store::ROLE_OWNER)->exists()) {
            return true;
        }

        if ($this->stores()->exists()) {
            return true;
        }

        return ! $this->memberStores()->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        // Auth mail must not depend on a queue worker being online.
        $this->notifyNow(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->must_set_password) {
            return;
        }

        // Auth mail must not depend on a queue worker being online.
        $this->notifyNow(new QueuedResetPassword($token));
    }
}
