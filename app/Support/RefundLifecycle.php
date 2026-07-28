<?php

namespace App\Support;

final class RefundLifecycle
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that still reserve refundable allocation. */
    public const ALLOCATING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_SUCCEEDED,
    ];

    public const METHOD_PROVIDER = 'provider';

    public const METHOD_EXTERNAL = 'external';

    public const METHOD_MANUAL = 'manual';

    public const ADJUSTMENT_SHIPPING = 'shipping';

    public const ADJUSTMENT_SHIPPING_TAX = 'shipping_tax';

    public const ADJUSTMENT_TAX = 'tax';

    public const ADJUSTMENT_OTHER = 'other';

    public const EVENT_REFUND_REQUESTED = 'refund.requested';

    public const EVENT_REFUND_SUCCEEDED = 'refund.succeeded';

    public const EVENT_REFUND_FAILED = 'refund.failed';

    public const EVENT_REFUND_MISMATCH = 'refund.provider_mismatch';

    private const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_SUCCEEDED => 'Succeeded',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    private const EVENT_LABELS = [
        self::EVENT_REFUND_REQUESTED => 'Refund requested',
        self::EVENT_REFUND_SUCCEEDED => 'Refund succeeded',
        self::EVENT_REFUND_FAILED => 'Refund failed',
        self::EVENT_REFUND_MISMATCH => 'Refund provider mismatch',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function eventTypeLabel(?string $eventType): string
    {
        return self::EVENT_LABELS[$eventType ?? ''] ?? 'Refund activity';
    }
}
