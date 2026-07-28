<?php

namespace App\Support;

final class ExchangeLifecycle
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    public const EVENT_EXCHANGE_CREATED = 'exchange.created';

    public const EVENT_EXCHANGE_COMPLETED = 'exchange.completed';

    public const EVENT_EXCHANGE_CANCELLED = 'exchange.cancelled';

    private const STATUS_LABELS = [
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    private const EVENT_LABELS = [
        self::EVENT_EXCHANGE_CREATED => 'Exchange created',
        self::EVENT_EXCHANGE_COMPLETED => 'Exchange completed',
        self::EVENT_EXCHANGE_CANCELLED => 'Exchange cancelled',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function eventTypeLabel(?string $eventType): string
    {
        return self::EVENT_LABELS[$eventType ?? ''] ?? 'Exchange activity';
    }

    public static function canTransition(?string $from, string $to): bool
    {
        $allowed = [
            self::STATUS_REQUESTED => [self::STATUS_RESERVED, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
            self::STATUS_RESERVED => [self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED, self::STATUS_RESERVED, self::STATUS_REQUESTED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($to, $allowed[$from ?? ''] ?? [], true);
    }
}
