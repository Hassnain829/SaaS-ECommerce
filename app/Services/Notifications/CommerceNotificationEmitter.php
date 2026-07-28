<?php

namespace App\Services\Notifications;

use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Support\NotificationEvent;

/**
 * Domain-facing helpers that keep emit payloads consistent across services.
 *
 * All public methods schedule work via NotificationCommitBoundary so commerce
 * transactions are never rolled back by notification failures.
 */
class CommerceNotificationEmitter
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationCommitBoundary $boundary,
    ) {}

    public function orderCreated(Order $order, ?User $actor = null): void
    {
        $orderId = (int) $order->id;
        $actorId = $actor?->id;

        $this->boundary->run('order.created', function () use ($orderId, $actorId): void {
            $order = Order::query()->with(['store', 'customer'])->find($orderId);
            $store = $order?->store;
            if (! $order || ! $store) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;
            $number = $order->order_number ?: ('#'.$order->id);
            $total = $order->grand_total ?? $order->total;
            $customerName = $order->customer?->full_name ?: ($order->customer_email ?: 'a customer');
            $title = 'New order '.$number;
            $body = sprintf(
                'Order %s for %s %s from %s.',
                $number,
                $order->currency_code ?: '',
                $total,
                $customerName
            );
            $merchantData = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'action_url' => route('orderViewDetails', $order),
                'action_label' => 'View order',
            ];
            $dedupe = 'order.created:'.$order->id;

            $this->dispatcher->notifyStore(
                $store,
                NotificationEvent::ORDER_CREATED,
                $title,
                $body,
                $dedupe,
                $merchantData,
                $actor
            );

            if (filled($order->customer_email)) {
                $this->dispatcher->notifyCustomer(
                    $store,
                    (string) $order->customer_email,
                    NotificationEvent::ORDER_CREATED,
                    'Order confirmation '.$number,
                    sprintf('Thanks for your order %s. Total: %s %s.', $number, $order->currency_code ?: '', $total),
                    $dedupe,
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    $actor
                );
            }
        }, ['order_id' => $orderId, 'store_id' => $order->store_id ?? null]);
    }

    public function paymentFailed(Store $store, string $reference, string $message, ?int $checkoutId = null, ?User $actor = null): void
    {
        $storeId = (int) $store->id;
        $actorId = $actor?->id;

        $this->boundary->run('payment.failed', function () use ($storeId, $reference, $message, $checkoutId, $actorId): void {
            $store = Store::query()->find($storeId);
            if (! $store) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;

            $this->dispatcher->notifyStore(
                $store,
                NotificationEvent::PAYMENT_FAILED,
                'Payment failed',
                $message,
                'payment.failed:'.($checkoutId ?: $reference),
                [
                    'checkout_id' => $checkoutId,
                    'reference' => $reference,
                    'action_url' => route('orders'),
                    'action_label' => 'Review orders',
                ],
                $actor
            );
        }, ['store_id' => $storeId, 'checkout_id' => $checkoutId]);
    }

    public function importFinished(ProductImport $import, bool $failed = false): void
    {
        $importId = (int) $import->id;

        $this->boundary->run($failed ? 'import.failed' : 'import.completed', function () use ($importId, $failed): void {
            $import = ProductImport::query()->with('store')->find($importId);
            $store = $import?->store;
            if (! $import || ! $store) {
                return;
            }

            $event = $failed ? NotificationEvent::IMPORT_FAILED : NotificationEvent::IMPORT_COMPLETED;
            $title = $failed ? 'Product import failed' : 'Product import completed';
            $summary = is_array($import->result_summary) ? $import->result_summary : [];
            $body = $failed
                ? ('Import #'.$import->id.' could not finish. Check the import report for details.')
                : sprintf(
                    'Import #%d finished. %s row(s) processed.',
                    $import->id,
                    $summary['processed_rows'] ?? $summary['total_rows'] ?? '—'
                );

            $this->dispatcher->notifyStore(
                $store,
                $event,
                $title,
                $body,
                ($failed ? 'import.failed:' : 'import.completed:').$import->id,
                [
                    'import_id' => $import->id,
                    'action_url' => route('products.import.result', $import),
                    'action_label' => 'View import',
                ]
            );
        }, ['import_id' => $importId, 'store_id' => $import->store_id ?? null]);
    }

    public function returnStatus(OrderReturn $return, string $eventType, string $title, ?User $actor = null): void
    {
        $returnId = (int) $return->id;
        $actorId = $actor?->id;

        $this->boundary->run($eventType, function () use ($returnId, $eventType, $title, $actorId): void {
            $return = OrderReturn::query()->with(['order.store', 'order'])->find($returnId);
            $order = $return?->order;
            $store = $order?->store;
            if (! $return || ! $store || ! $order) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;
            $body = sprintf(
                'Return %s for order %s is now %s.',
                $return->return_number,
                $order->order_number,
                str_replace('return.', '', $eventType)
            );
            $merchantData = [
                'return_id' => $return->id,
                'order_id' => $order->id,
                'action_url' => route('orderViewDetails', $order),
                'action_label' => 'View order',
            ];
            $dedupe = $eventType.':'.$return->id;

            $this->dispatcher->notifyStore($store, $eventType, $title, $body, $dedupe, $merchantData, $actor);

            if (filled($order->customer_email) && NotificationEvent::isCustomerTransactional($eventType)) {
                $this->dispatcher->notifyCustomer(
                    $store,
                    (string) $order->customer_email,
                    $eventType,
                    $title,
                    $body,
                    $dedupe,
                    [
                        'return_id' => $return->id,
                        'order_id' => $order->id,
                    ],
                    $actor
                );
            }
        }, ['return_id' => $returnId]);
    }

    public function refundFinished(Refund $refund, bool $failed = false, ?User $actor = null): void
    {
        $refundId = (int) $refund->id;
        $actorId = $actor?->id;

        $this->boundary->run($failed ? 'refund.failed' : 'refund.completed', function () use ($refundId, $failed, $actorId): void {
            $refund = Refund::query()->with(['order.store', 'order'])->find($refundId);
            $order = $refund?->order;
            $store = $order?->store;
            if (! $refund || ! $store || ! $order) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;
            $event = $failed ? NotificationEvent::REFUND_FAILED : NotificationEvent::REFUND_COMPLETED;
            $title = $failed ? 'Refund failed' : 'Refund completed';
            $body = sprintf(
                'Refund %s for order %s %s (%s %s).',
                $refund->refund_number ?? ('#'.$refund->id),
                $order->order_number,
                $failed ? 'failed' : 'was completed',
                $refund->currency_code ?? $order->currency_code,
                $refund->amount ?? ''
            );
            $merchantData = [
                'refund_id' => $refund->id,
                'order_id' => $order->id,
                'action_url' => route('orderViewDetails', $order),
                'action_label' => 'View order',
            ];
            $dedupe = $event.':'.$refund->id;

            $this->dispatcher->notifyStore($store, $event, $title, $body, $dedupe, $merchantData, $actor);

            if (! $failed && filled($order->customer_email)) {
                $this->dispatcher->notifyCustomer(
                    $store,
                    (string) $order->customer_email,
                    NotificationEvent::REFUND_COMPLETED,
                    'Refund confirmation',
                    sprintf('Your refund for order %s has been processed.', $order->order_number),
                    $dedupe,
                    [
                        'refund_id' => $refund->id,
                        'order_id' => $order->id,
                    ],
                    $actor
                );
            }
        }, ['refund_id' => $refundId]);
    }

    public function exchangeEvent(Exchange $exchange, string $eventType, string $title, ?User $actor = null): void
    {
        $exchangeId = (int) $exchange->id;
        $actorId = $actor?->id;

        $this->boundary->run($eventType, function () use ($exchangeId, $eventType, $title, $actorId): void {
            $exchange = Exchange::query()->with(['order.store', 'order', 'store'])->find($exchangeId);
            $order = $exchange?->order;
            $store = $order?->store ?? $exchange?->store;
            if (! $exchange || ! $store || ! $order) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;
            $body = sprintf(
                'Exchange %s for order %s.',
                $exchange->exchange_number,
                $order->order_number
            );

            $this->dispatcher->notifyStore(
                $store,
                $eventType,
                $title,
                $body,
                $eventType.':'.$exchange->id,
                [
                    'exchange_id' => $exchange->id,
                    'order_id' => $order->id,
                    'action_url' => route('orderViewDetails', $order),
                    'action_label' => 'View order',
                ],
                $actor
            );
        }, ['exchange_id' => $exchangeId]);
    }

    public function shipmentEvent(Shipment $shipment, string $eventType, string $title, ?User $actor = null): void
    {
        $shipmentId = (int) $shipment->id;
        $actorId = $actor?->id;

        $this->boundary->run($eventType, function () use ($shipmentId, $eventType, $title, $actorId): void {
            $shipment = Shipment::query()->with(['order.store', 'order'])->find($shipmentId);
            $order = $shipment?->order;
            $store = $order?->store;
            if (! $shipment || ! $store || ! $order) {
                return;
            }

            $actor = $actorId ? User::query()->find($actorId) : null;
            $tracking = $shipment->tracking_number;
            $body = $tracking
                ? sprintf('Shipment for order %s — tracking %s.', $order->order_number, $tracking)
                : sprintf('Shipment for order %s was updated.', $order->order_number);

            $merchantData = [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'tracking_number' => $tracking,
                'action_url' => route('orderViewDetails', $order),
                'action_label' => 'View order',
            ];
            $dedupe = $eventType.':'.$shipment->id.($tracking ? ':'.$tracking : '');

            $this->dispatcher->notifyStore($store, $eventType, $title, $body, $dedupe, $merchantData, $actor);

            if (filled($order->customer_email) && NotificationEvent::isCustomerTransactional($eventType)) {
                $customerData = [
                    'shipment_id' => $shipment->id,
                    'order_id' => $order->id,
                    'tracking_number' => $tracking,
                ];

                $trackingUrl = is_string($shipment->tracking_url) ? trim($shipment->tracking_url) : '';
                if ($trackingUrl !== '' && preg_match('#^https?://#i', $trackingUrl) === 1) {
                    $customerData['action_url'] = $trackingUrl;
                    $customerData['action_label'] = 'Track shipment';
                }

                $this->dispatcher->notifyCustomer(
                    $store,
                    (string) $order->customer_email,
                    $eventType,
                    $title,
                    $body,
                    $dedupe,
                    $customerData,
                    $actor
                );
            }
        }, ['shipment_id' => $shipmentId]);
    }

    public function securityNewLogin(User $user, Store $store, string $summary, ?string $sessionKey = null): void
    {
        $userId = (int) $user->id;
        $storeId = (int) $store->id;

        $this->boundary->run('security.login_new_device', function () use ($userId, $storeId, $summary, $sessionKey): void {
            $user = User::query()->find($userId);
            $store = Store::query()->find($storeId);
            if (! $user || ! $store) {
                return;
            }

            $this->dispatcher->notifyUser(
                $store,
                $user,
                NotificationEvent::SECURITY_LOGIN_NEW_DEVICE,
                'New sign-in detected',
                $summary,
                'security.login:'.$user->id.':'.($sessionKey ?: now()->format('YmdHi')),
                [
                    'action_url' => route('security'),
                    'action_label' => 'Review security',
                ]
            );
        }, ['user_id' => $userId, 'store_id' => $storeId]);
    }
}
