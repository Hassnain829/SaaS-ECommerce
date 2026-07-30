<?php

namespace App\Support;

final class ReturnLifecycle
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_MERCHANT = 'merchant';

    public const CONDITION_SELLABLE = 'sellable';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_DEFECTIVE = 'defective';

    public const CONDITION_NON_SELLABLE = 'non_sellable';

    public const NON_SELLABLE_CONDITIONS = [
        self::CONDITION_DAMAGED,
        self::CONDITION_DEFECTIVE,
        self::CONDITION_NON_SELLABLE,
    ];

    public const EVENT_RETURN_REQUESTED = 'return.requested';

    public const EVENT_RETURN_APPROVED = 'return.approved';

    public const EVENT_RETURN_REJECTED = 'return.rejected';

    public const EVENT_RETURN_RECEIVED = 'return.received';

    public const EVENT_RETURN_COMPLETED = 'return.completed';

    public const EVENT_RETURN_CANCELLED = 'return.cancelled';

    /** Statuses that still claim open returnable quantity (before physical receive). */
    public const OPEN_CLAIM_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
    ];

    private const STATUS_LABELS = [
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    private const EVENT_LABELS = [
        self::EVENT_RETURN_REQUESTED => 'Return requested',
        self::EVENT_RETURN_APPROVED => 'Return approved',
        self::EVENT_RETURN_REJECTED => 'Return rejected',
        self::EVENT_RETURN_RECEIVED => 'Return received',
        self::EVENT_RETURN_COMPLETED => 'Return completed',
        self::EVENT_RETURN_CANCELLED => 'Return cancelled',
    ];

    /**
     * @return list<string>
     */
    public static function conditions(): array
    {
        return [
            self::CONDITION_SELLABLE,
            self::CONDITION_DAMAGED,
            self::CONDITION_DEFECTIVE,
            self::CONDITION_NON_SELLABLE,
        ];
    }

    public static function isNonSellable(?string $condition): bool
    {
        return in_array((string) $condition, self::NON_SELLABLE_CONDITIONS, true);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function eventTypeLabel(?string $eventType): string
    {
        return self::EVENT_LABELS[$eventType ?? ''] ?? 'Return activity';
    }

    public static function canTransition(?string $from, string $to): bool
    {
        if (! in_array($to, self::statuses(), true)) {
            return false;
        }

        $allowed = [
            self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED => [self::STATUS_RECEIVED, self::STATUS_CANCELLED],
            self::STATUS_RECEIVED => [self::STATUS_COMPLETED],
            self::STATUS_REJECTED => [],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($to, $allowed[$from ?? ''] ?? [], true);
    }

    public static function isPhysicalProductType(?string $productType): bool
    {
        $type = strtolower(trim((string) $productType));

        if ($type === '' || $type === 'physical') {
            return true;
        }

        return ! in_array($type, ['digital', 'service', 'subscription'], true);
    }
}
