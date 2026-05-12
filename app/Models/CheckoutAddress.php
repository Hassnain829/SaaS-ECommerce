<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutAddress extends Model
{
    protected $fillable = [
        'checkout_id',
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
    ];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }
}
