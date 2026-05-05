<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'name',
        'email',
        'company',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'province_code',
        'postal_code',
        'country',
        'country_code',
        'phone',
        'delivery_notes',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
