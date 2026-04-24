<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_INITIAL = 'initial';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const TYPE_EDIT_UPDATE = 'edit_update';

    public const TYPE_IMPORT = 'import';

    public const TYPE_ORDER_SALE = 'order_sale';

    /** Append-only: only created_at is maintained */
    public const UPDATED_AT = null;

    protected $fillable = [
        'store_id',
        'product_id',
        'variant_id',
        'previous_stock',
        'quantity_change',
        'new_stock',
        'movement_type',
        'reason',
        'source',
        'reference_id',
        'reference_type',
        'reference_code',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
