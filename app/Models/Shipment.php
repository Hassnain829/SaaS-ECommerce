<?php

namespace App\Models;

use App\Support\OrderLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = OrderLifecycle::SHIPMENT_PENDING;

    public const STATUS_LABEL_CREATED = OrderLifecycle::SHIPMENT_LABEL_CREATED;

    public const STATUS_SHIPPED = OrderLifecycle::SHIPMENT_SHIPPED;

    public const STATUS_IN_TRANSIT = OrderLifecycle::SHIPMENT_IN_TRANSIT;

    public const STATUS_DELIVERED = OrderLifecycle::SHIPMENT_DELIVERED;

    public const STATUS_FAILED = OrderLifecycle::SHIPMENT_FAILED;

    public const STATUS_RETURNED = OrderLifecycle::SHIPMENT_RETURNED;

    public const STATUS_CANCELLED = OrderLifecycle::SHIPMENT_CANCELLED;

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_RETURN = 'return';

    /** Statuses that reduce remaining shippable quantity (prevent over-shipment). */
    public const STATUSES_RESERVED_AGAINST_ORDER = [
        self::STATUS_PENDING,
        self::STATUS_LABEL_CREATED,
        self::STATUS_SHIPPED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
    ];

    /** Statuses that count toward order fulfillment completion. */
    public const STATUSES_COUNTED_FOR_FULFILLMENT = [
        self::STATUS_SHIPPED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
    ];

    protected $fillable = [
        'store_id',
        'order_id',
        'order_return_id',
        'shipment_number',
        'origin_location_id',
        'carrier_account_id',
        'shipping_method_id',
        'status',
        'direction',
        'tracking_number',
        'tracking_url',
        'carrier_service',
        'package_count',
        'package_weight',
        'shipping_cost',
        'label_url',
        'shipped_at',
        'delivered_at',
        'shipped_by',
        'metadata',
    ];

    protected $casts = [
        'package_count' => 'integer',
        'package_weight' => 'decimal:3',
        'shipping_cost' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function carrierAccount(): BelongsTo
    {
        return $this->belongsTo(CarrierAccount::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function isReturn(): bool
    {
        return $this->direction === self::DIRECTION_RETURN
            || (bool) data_get($this->metadata, 'fedex.return_shipment');
    }

    public function isOutbound(): bool
    {
        return ! $this->isReturn();
    }

    /**
     * True when this shipment belongs to FedEx Model A ops (not merely "has a tracking number").
     */
    public function isFedExManagedShipment(?CarrierAccount $preferredAccount = null): bool
    {
        if (filled(data_get($this->metadata, 'fedex'))) {
            return true;
        }

        if ($preferredAccount instanceof CarrierAccount
            && (int) $this->carrier_account_id === (int) $preferredAccount->id
            && $preferredAccount->isFedEx()
            && $preferredAccount->usesFedExIntegratorProvider()) {
            return true;
        }

        $account = $this->relationLoaded('carrierAccount')
            ? $this->carrierAccount
            : $this->carrierAccount()->first();

        return $account instanceof CarrierAccount
            && $account->isFedEx()
            && $account->usesFedExIntegratorProvider();
    }

    /**
     * Ensure a customer-safe public tracking token exists for FedEx-managed shipments with tracking.
     */
    public function ensureFedExPublicTrackingToken(): ?string
    {
        if (! filled($this->tracking_number) || ! $this->isFedExManagedShipment()) {
            return data_get($this->metadata, 'fedex.public_tracking_token');
        }

        $existing = data_get($this->metadata, 'fedex.public_tracking_token');
        if (filled($existing)) {
            return (string) $existing;
        }

        $token = bin2hex(random_bytes(16));
        $meta = is_array($this->metadata) ? $this->metadata : [];
        $fedex = is_array($meta['fedex'] ?? null) ? $meta['fedex'] : [];
        $fedex['public_tracking_token'] = $token;
        $meta['fedex'] = $fedex;
        $this->forceFill(['metadata' => $meta])->save();

        return $token;
    }

    public function publicFedExTrackingUrl(?string $storeSlug): ?string
    {
        $token = data_get($this->metadata, 'fedex.public_tracking_token');
        if (! filled($token) || ! filled($storeSlug) || ! \Illuminate\Support\Facades\Route::has('public.fedex.tracking')) {
            return null;
        }

        return route('public.fedex.tracking', [
            'storeSlug' => $storeSlug,
            'token' => $token,
        ]);
    }
}
