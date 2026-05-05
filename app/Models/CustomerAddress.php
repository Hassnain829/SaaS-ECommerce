<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'name',
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
        'is_default',
        'is_residential',
        'delivery_instructions',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_residential' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
