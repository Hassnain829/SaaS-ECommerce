<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLevel extends Model
{
    protected $fillable = [
        'store_id',
        'inventory_item_id',
        'location_id',
        'available',
        'reserved',
        'committed',
        'incoming',
    ];

    protected $casts = [
        'available' => 'integer',
        'reserved' => 'integer',
        'committed' => 'integer',
        'incoming' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
