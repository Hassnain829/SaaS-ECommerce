<?php

namespace App\Support;

final class OrderLifecycle
{
    public const ORDER_PENDING = 'pending';
    public const ORDER_CONFIRMED = 'confirmed';
    public const ORDER_PROCESSING = 'processing';
    public const ORDER_COMPLETED = 'completed';
    public const ORDER_CANCELLED = 'cancelled';
    public const ORDER_REFUNDED = 'refunded';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_AUTHORIZED = 'authorized';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';

    public const FULFILLMENT_UNFULFILLED = 'unfulfilled';
    public const FULFILLMENT_PARTIAL = 'partial';
    public const FULFILLMENT_FULFILLED = 'fulfilled';
    public const FULFILLMENT_RETURNED = 'returned';

    public const SHIPMENT_PENDING = 'pending';
    public const SHIPMENT_LABEL_CREATED = 'label_created';
    public const SHIPMENT_PICKED_UP = 'picked_up';
    public const SHIPMENT_IN_TRANSIT = 'in_transit';
    public const SHIPMENT_DELIVERED = 'delivered';
    public const SHIPMENT_FAILED = 'failed';
    public const SHIPMENT_RETURNED = 'returned';

    public const EVENT_ORDER_CREATED = 'order.created';
    public const EVENT_ORDER_STATUS_CHANGED = 'order.status_changed';
    public const EVENT_PAYMENT_STATUS_CHANGED = 'payment.status_changed';
    public const EVENT_FULFILLMENT_STATUS_CHANGED = 'fulfillment.status_changed';
    public const EVENT_INVENTORY_DEDUCTED = 'inventory.deducted';
    public const EVENT_ORDER_NOTE_ADDED = 'order.note_added';
    public const EVENT_ORDER_CANCELLED = 'order.cancelled';
    public const EVENT_ORDER_COMPLETED = 'order.completed';
    public const EVENT_ORDER_REFUNDED = 'order.refunded';

    private const ORDER_STATUS_LABELS = [
        self::ORDER_PENDING => 'Pending',
        self::ORDER_CONFIRMED => 'Confirmed',
        self::ORDER_PROCESSING => 'Processing',
        self::ORDER_COMPLETED => 'Completed',
        self::ORDER_CANCELLED => 'Cancelled',
        self::ORDER_REFUNDED => 'Refunded',
    ];

    private const PAYMENT_STATUS_LABELS = [
        self::PAYMENT_PENDING => 'Pending',
        self::PAYMENT_AUTHORIZED => 'Authorized',
        self::PAYMENT_PAID => 'Paid',
        self::PAYMENT_FAILED => 'Failed',
        self::PAYMENT_REFUNDED => 'Refunded',
        self::PAYMENT_PARTIALLY_REFUNDED => 'Partially refunded',
    ];

    private const FULFILLMENT_STATUS_LABELS = [
        self::FULFILLMENT_UNFULFILLED => 'Unfulfilled',
        self::FULFILLMENT_PARTIAL => 'Partially fulfilled',
        self::FULFILLMENT_FULFILLED => 'Fulfilled',
        self::FULFILLMENT_RETURNED => 'Returned',
    ];

    private const SHIPMENT_STATUS_LABELS = [
        self::SHIPMENT_PENDING => 'Pending',
        self::SHIPMENT_LABEL_CREATED => 'Label created',
        self::SHIPMENT_PICKED_UP => 'Picked up',
        self::SHIPMENT_IN_TRANSIT => 'In transit',
        self::SHIPMENT_DELIVERED => 'Delivered',
        self::SHIPMENT_FAILED => 'Failed',
        self::SHIPMENT_RETURNED => 'Returned',
    ];

    private const EVENT_TYPE_LABELS = [
        self::EVENT_ORDER_CREATED => 'Order created',
        self::EVENT_ORDER_STATUS_CHANGED => 'Order status changed',
        self::EVENT_PAYMENT_STATUS_CHANGED => 'Payment status changed',
        self::EVENT_FULFILLMENT_STATUS_CHANGED => 'Fulfillment status changed',
        self::EVENT_INVENTORY_DEDUCTED => 'Inventory deducted',
        self::EVENT_ORDER_NOTE_ADDED => 'Order note added',
        self::EVENT_ORDER_CANCELLED => 'Order cancelled',
        self::EVENT_ORDER_COMPLETED => 'Order completed',
        self::EVENT_ORDER_REFUNDED => 'Order refunded',
    ];

    /**
     * @return list<string>
     */
    public static function orderStatuses(): array
    {
        return array_keys(self::ORDER_STATUS_LABELS);
    }

    /**
     * @return list<string>
     */
    public static function paymentStatuses(): array
    {
        return array_keys(self::PAYMENT_STATUS_LABELS);
    }

    /**
     * @return list<string>
     */
    public static function fulfillmentStatuses(): array
    {
        return array_keys(self::FULFILLMENT_STATUS_LABELS);
    }

    /**
     * @return list<string>
     */
    public static function shipmentStatuses(): array
    {
        return array_keys(self::SHIPMENT_STATUS_LABELS);
    }

    public static function orderStatusLabel(?string $status): string
    {
        return self::ORDER_STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function paymentStatusLabel(?string $status): string
    {
        return self::PAYMENT_STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function fulfillmentStatusLabel(?string $status): string
    {
        return self::FULFILLMENT_STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function shipmentStatusLabel(?string $status): string
    {
        return self::SHIPMENT_STATUS_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function eventTypeLabel(?string $eventType): string
    {
        return self::EVENT_TYPE_LABELS[$eventType ?? ''] ?? 'Order activity';
    }

    public static function canTransitionOrderStatus(?string $from, string $to): bool
    {
        if (! in_array($to, self::orderStatuses(), true)) {
            return false;
        }

        if ($from === null || $from === '') {
            return $to === self::ORDER_PENDING;
        }

        $allowed = [
            self::ORDER_PENDING => [self::ORDER_CONFIRMED, self::ORDER_CANCELLED],
            self::ORDER_CONFIRMED => [self::ORDER_PROCESSING, self::ORDER_COMPLETED, self::ORDER_CANCELLED],
            self::ORDER_PROCESSING => [self::ORDER_COMPLETED, self::ORDER_CANCELLED],
            self::ORDER_COMPLETED => [self::ORDER_REFUNDED],
            self::ORDER_CANCELLED => [],
            self::ORDER_REFUNDED => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    public static function orderStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::ORDER_CONFIRMED => 'bg-[#EFF6FF] text-[#1D4ED8]',
            self::ORDER_PROCESSING => 'bg-[#EEF2FF] text-[#4F46E5]',
            self::ORDER_COMPLETED => 'bg-[#ECFDF5] text-[#059669]',
            self::ORDER_CANCELLED => 'bg-[#FEF2F2] text-[#BA1A1A]',
            self::ORDER_REFUNDED => 'bg-[#F5F3FF] text-[#6D28D9]',
            default => 'bg-[#F8FAFC] text-[#475569]',
        };
    }

    public static function paymentStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::PAYMENT_PAID, self::PAYMENT_AUTHORIZED => 'bg-[#ECFDF5] text-[#059669]',
            self::PAYMENT_FAILED => 'bg-[#FEF2F2] text-[#BA1A1A]',
            self::PAYMENT_REFUNDED, self::PAYMENT_PARTIALLY_REFUNDED => 'bg-[#F5F3FF] text-[#6D28D9]',
            default => 'bg-[#FFF7ED] text-[#D97706]',
        };
    }

    public static function fulfillmentStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            self::FULFILLMENT_PARTIAL => 'bg-[#EEF2FF] text-[#4F46E5]',
            self::FULFILLMENT_FULFILLED => 'bg-[#ECFDF5] text-[#059669]',
            self::FULFILLMENT_RETURNED => 'bg-[#F5F3FF] text-[#6D28D9]',
            default => 'bg-[#F8FAFC] text-[#475569]',
        };
    }
}
