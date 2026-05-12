<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'store_id',
        'email',
        'first_name',
        'last_name',
        'full_name',
        'phone',
        'password',
        'status',
        'blocked_at',
        'blocked_reason',
        'accepts_marketing',
        'marketing_consent',
        'marketing_consent_at',
        'marketing_consent_source',
        'email_verified_at',
        'last_order_at',
        'total_orders',
        'total_spent',
        'average_order_value',
        'date_of_birth',
        'gender',
        'preferred_currency',
        'preferred_locale',
        'source',
        'notes',
        'meta',
    ];

    protected $casts = [
        'accepts_marketing' => 'boolean',
        'marketing_consent' => 'boolean',
        'marketing_consent_at' => 'datetime',
        'blocked_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'last_order_at' => 'datetime',
        'date_of_birth' => 'date',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'meta' => 'array',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function checkouts()
    {
        return $this->hasMany(Checkout::class);
    }

    public function profileNotes()
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function tags()
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_customer_tag')
            ->withTimestamps();
    }
}
