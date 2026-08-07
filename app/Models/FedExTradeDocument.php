<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FedExTradeDocument extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_FAILED = 'failed';

    protected $table = 'fedex_trade_documents';

    protected $fillable = [
        'store_id',
        'order_id',
        'shipment_id',
        'carrier_account_id',
        'document_type',
        'fedex_document_id',
        'status',
        'origin_country_code',
        'destination_country_code',
        'storage_disk',
        'storage_path',
        'original_filename',
        'metadata',
        'uploaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }
}
