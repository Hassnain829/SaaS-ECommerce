<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';

    public const MEMBER_ROLES = [
        self::ROLE_OWNER,
        self::ROLE_MANAGER,
        self::ROLE_STAFF,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'logo',
        'address',
        'currency',
        'timezone',
        'category',
        'settings',
        'onboarding_completed',
    ];

    protected $casts = [
        'settings' => 'array',
        'onboarding_completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public static function memberRoles(): array
    {
        return self::MEMBER_ROLES;
    }

    public static function isValidMemberRole(string $role): bool
    {
        return in_array($role, self::MEMBER_ROLES, true);
    }

    public function roleForUser(User|int|null $user): ?string
    {
        $userId = $user instanceof User ? $user->id : $user;

        if (! $userId) {
            return null;
        }

        $loadedMember = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $userId)
            : null;

        if ($loadedMember) {
            return $loadedMember->pivot?->role;
        }

        return $this->members()
            ->where('users.id', $userId)
            ->first()?->pivot?->role;
    }

    public function userHasRole(User|int|null $user, string|array $roles): bool
    {
        $role = $this->roleForUser($user);

        if (! $role) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($role, $roles, true);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
