<?php

namespace App\Support;

final class NotificationEvent
{
    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_EMAIL = 'email';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_READ = 'read';

    public const ORDER_CREATED = 'order.created';

    public const PAYMENT_FAILED = 'payment.failed';

    public const INVENTORY_LOW = 'inventory.low';

    public const IMPORT_COMPLETED = 'import.completed';

    public const IMPORT_FAILED = 'import.failed';

    public const RETURN_REQUESTED = 'return.requested';

    public const RETURN_APPROVED = 'return.approved';

    public const RETURN_REJECTED = 'return.rejected';

    public const RETURN_RECEIVED = 'return.received';

    public const RETURN_COMPLETED = 'return.completed';

    public const REFUND_COMPLETED = 'refund.completed';

    public const REFUND_FAILED = 'refund.failed';

    public const EXCHANGE_CREATED = 'exchange.created';

    public const EXCHANGE_COMPLETED = 'exchange.completed';

    public const SHIPMENT_SHIPPED = 'shipment.shipped';

    public const SHIPMENT_DELIVERED = 'shipment.delivered';

    public const SHIPMENT_TRACKING_UPDATED = 'shipment.tracking_updated';

    public const SECURITY_LOGIN_NEW_DEVICE = 'security.login_new_device';

    public const WEBHOOK_FAILED = 'webhook.failed';

    public const BILLING_ISSUE = 'billing.issue';

    /**
     * Merchant-facing preference toggles (includes reserved Phase 9/10 types).
     *
     * @return list<string>
     */
    public static function merchantPreferenceEvents(): array
    {
        return [
            self::ORDER_CREATED,
            self::PAYMENT_FAILED,
            self::INVENTORY_LOW,
            self::IMPORT_COMPLETED,
            self::IMPORT_FAILED,
            self::RETURN_REQUESTED,
            self::RETURN_APPROVED,
            self::RETURN_REJECTED,
            self::RETURN_RECEIVED,
            self::RETURN_COMPLETED,
            self::REFUND_COMPLETED,
            self::REFUND_FAILED,
            self::EXCHANGE_CREATED,
            self::EXCHANGE_COMPLETED,
            self::SHIPMENT_SHIPPED,
            self::SHIPMENT_DELIVERED,
            self::SHIPMENT_TRACKING_UPDATED,
            self::SECURITY_LOGIN_NEW_DEVICE,
            self::WEBHOOK_FAILED,
            self::BILLING_ISSUE,
        ];
    }

    /**
     * Events that bypass quiet hours for email.
     *
     * @return list<string>
     */
    public static function criticalEvents(): array
    {
        return [
            self::PAYMENT_FAILED,
            self::INVENTORY_LOW,
            self::SECURITY_LOGIN_NEW_DEVICE,
            self::WEBHOOK_FAILED,
            self::BILLING_ISSUE,
            self::REFUND_FAILED,
            self::IMPORT_FAILED,
        ];
    }

    /**
     * Customer transactional events (not preference-gated like merchant alerts).
     *
     * @return list<string>
     */
    public static function customerTransactionalEvents(): array
    {
        return [
            self::ORDER_CREATED,
            self::REFUND_COMPLETED,
            self::RETURN_REQUESTED,
            self::RETURN_APPROVED,
            self::RETURN_REJECTED,
            self::RETURN_RECEIVED,
            self::RETURN_COMPLETED,
            self::SHIPMENT_SHIPPED,
            self::SHIPMENT_TRACKING_UPDATED,
            self::SHIPMENT_DELIVERED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ORDER_CREATED => 'New orders',
            self::PAYMENT_FAILED => 'Payment failures',
            self::INVENTORY_LOW => 'Low stock alerts',
            self::IMPORT_COMPLETED => 'Import completed',
            self::IMPORT_FAILED => 'Import failed',
            self::RETURN_REQUESTED => 'Return requested',
            self::RETURN_APPROVED => 'Return approved',
            self::RETURN_REJECTED => 'Return rejected',
            self::RETURN_RECEIVED => 'Return received',
            self::RETURN_COMPLETED => 'Return completed',
            self::REFUND_COMPLETED => 'Refund completed',
            self::REFUND_FAILED => 'Refund failed',
            self::EXCHANGE_CREATED => 'Exchange created',
            self::EXCHANGE_COMPLETED => 'Exchange completed',
            self::SHIPMENT_SHIPPED => 'Shipment shipped',
            self::SHIPMENT_DELIVERED => 'Shipment delivered',
            self::SHIPMENT_TRACKING_UPDATED => 'Tracking updates',
            self::SECURITY_LOGIN_NEW_DEVICE => 'Security alerts',
            self::WEBHOOK_FAILED => 'Webhook failures',
            self::BILLING_ISSUE => 'Billing issues',
        ];
    }

    public static function label(string $event): string
    {
        return self::labels()[$event] ?? str_replace(['.', '_'], ' ', $event);
    }

    /**
     * Default preference map: all merchant events enabled.
     *
     * @return array<string, bool>
     */
    public static function defaultEventTypes(): array
    {
        $defaults = [];
        foreach (self::merchantPreferenceEvents() as $event) {
            $defaults[$event] = true;
        }

        return $defaults;
    }

    /**
     * Visual accent for the notification center.
     *
     * @return array{tone: string, icon: string}
     */
    public static function presentation(string $event): array
    {
        return match ($event) {
            self::PAYMENT_FAILED, self::REFUND_FAILED, self::IMPORT_FAILED, self::WEBHOOK_FAILED, self::BILLING_ISSUE => [
                'tone' => 'danger',
                'icon' => 'alert',
            ],
            self::INVENTORY_LOW, self::SECURITY_LOGIN_NEW_DEVICE => [
                'tone' => 'warning',
                'icon' => 'shield',
            ],
            self::ORDER_CREATED, self::REFUND_COMPLETED, self::SHIPMENT_DELIVERED, self::IMPORT_COMPLETED => [
                'tone' => 'success',
                'icon' => 'check',
            ],
            self::RETURN_REQUESTED, self::RETURN_APPROVED, self::RETURN_REJECTED, self::RETURN_RECEIVED, self::RETURN_COMPLETED,
            self::EXCHANGE_CREATED, self::EXCHANGE_COMPLETED => [
                'tone' => 'info',
                'icon' => 'return',
            ],
            self::SHIPMENT_SHIPPED, self::SHIPMENT_TRACKING_UPDATED => [
                'tone' => 'info',
                'icon' => 'truck',
            ],
            default => [
                'tone' => 'neutral',
                'icon' => 'bell',
            ],
        };
    }

    public static function isCritical(string $event): bool
    {
        return in_array($event, self::criticalEvents(), true);
    }

    public static function isCustomerTransactional(string $event): bool
    {
        return in_array($event, self::customerTransactionalEvents(), true);
    }

    /**
     * UI filter / preference groups for the notification center.
     *
     * @return array<string, array{label: string, description: string, events: list<string>}>
     */
    public static function uiGroups(): array
    {
        return [
            'orders' => [
                'label' => 'Sales & Orders',
                'description' => 'Immediate order notifications',
                'events' => [
                    self::ORDER_CREATED,
                    self::PAYMENT_FAILED,
                    self::RETURN_REQUESTED,
                    self::RETURN_APPROVED,
                    self::RETURN_REJECTED,
                    self::RETURN_RECEIVED,
                    self::RETURN_COMPLETED,
                    self::REFUND_COMPLETED,
                    self::REFUND_FAILED,
                    self::EXCHANGE_CREATED,
                    self::EXCHANGE_COMPLETED,
                    self::SHIPMENT_SHIPPED,
                    self::SHIPMENT_DELIVERED,
                    self::SHIPMENT_TRACKING_UPDATED,
                ],
            ],
            'inventory' => [
                'label' => 'Inventory Status',
                'description' => 'Stock updates and low levels',
                'events' => [
                    self::INVENTORY_LOW,
                    self::IMPORT_COMPLETED,
                    self::IMPORT_FAILED,
                ],
            ],
            'system' => [
                'label' => 'System Logs',
                'description' => 'Security and platform alerts',
                'events' => [
                    self::SECURITY_LOGIN_NEW_DEVICE,
                    self::WEBHOOK_FAILED,
                    self::BILLING_ISSUE,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function eventsForCategory(string $category): array
    {
        return self::uiGroups()[$category]['events'] ?? [];
    }

    public static function categoryForEvent(string $event): string
    {
        foreach (self::uiGroups() as $key => $group) {
            if (in_array($event, $group['events'], true)) {
                return $key;
            }
        }

        return 'system';
    }

    /**
     * @return array{tone: string, icon: string, bg: string, fg: string}
     */
    public static function uiIcon(string $event): array
    {
        return match (true) {
            $event === self::INVENTORY_LOW => [
                'tone' => 'warning',
                'icon' => 'inventory_2',
                'bg' => 'bg-orange-50',
                'fg' => 'text-orange-600',
            ],
            in_array($event, [self::IMPORT_COMPLETED, self::REFUND_COMPLETED, self::SHIPMENT_DELIVERED], true) => [
                'tone' => 'success',
                'icon' => 'check_circle',
                'bg' => 'bg-emerald-50',
                'fg' => 'text-emerald-600',
            ],
            str_starts_with($event, 'order.') || str_starts_with($event, 'payment.')
                || str_starts_with($event, 'return.') || str_starts_with($event, 'refund.')
                || str_starts_with($event, 'exchange.') || str_starts_with($event, 'shipment.') => [
                    'tone' => 'info',
                    'icon' => 'shopping_bag',
                    'bg' => 'bg-[#EFF6FF]',
                    'fg' => 'text-[#0052CC]',
                ],
            default => [
                'tone' => 'neutral',
                'icon' => 'sync',
                'bg' => 'bg-stone-100',
                'fg' => 'text-stone-600',
            ],
        };
    }
}
