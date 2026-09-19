<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StoreUser extends Pivot
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'store_user';

    protected $fillable = [
        'store_id',
        'user_id',
        'role',
        'job_title',
        'access_preset',
        'location_ids',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'location_ids' => 'array',
        ];
    }
}
