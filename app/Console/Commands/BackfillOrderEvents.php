<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderEventRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Console\Command;

class BackfillOrderEvents extends Command
{
    protected $signature = 'orders:backfill-events {--store= : Limit the backfill to one store ID}';

    protected $description = 'Create initial order activity events for orders that do not have a timeline yet.';

    public function handle(OrderEventRecorder $recorder): int
    {
        $created = 0;

        $query = Order::query()
            ->whereDoesntHave('events')
            ->with('items')
            ->orderBy('id');

        if ($this->option('store')) {
            $query->where('store_id', (int) $this->option('store'));
        }

        $query->chunkById(100, function ($orders) use ($recorder, &$created): void {
            foreach ($orders as $order) {
                $placedAt = $order->placed_at ?? $order->created_at ?? now();

                $recorder->record(
                    $order,
                    OrderLifecycle::EVENT_ORDER_CREATED,
                    'Order placed',
                    'Order activity was backfilled from the existing order record.',
                    [
                        'source' => $order->order_source ?? 'existing_order',
                        'channel' => $order->channel,
                        'order_number' => $order->order_number,
                    ],
                    createdAt: $placedAt,
                );
                $created++;

                if ($order->payment_status && $order->payment_status !== OrderLifecycle::PAYMENT_PENDING) {
                    $recorder->record(
                        $order,
                        OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
                        'Payment status recorded',
                        'Payment status was recorded from the existing order.',
                        ['payment_status' => $order->payment_status],
                        createdAt: $placedAt->copy()->addMinute(),
                    );
                    $created++;
                }

                if ($order->fulfillment_status && $order->fulfillment_status !== OrderLifecycle::FULFILLMENT_UNFULFILLED) {
                    $recorder->record(
                        $order,
                        OrderLifecycle::EVENT_FULFILLMENT_STATUS_CHANGED,
                        'Fulfillment status recorded',
                        'Fulfillment status was recorded from the existing order.',
                        ['fulfillment_status' => $order->fulfillment_status],
                        createdAt: $placedAt->copy()->addMinutes(2),
                    );
                    $created++;
                }

                if (in_array($order->status, [
                    OrderLifecycle::ORDER_PROCESSING,
                    OrderLifecycle::ORDER_COMPLETED,
                    OrderLifecycle::ORDER_CANCELLED,
                    OrderLifecycle::ORDER_REFUNDED,
                ], true)) {
                    $recorder->record(
                        $order,
                        OrderLifecycle::EVENT_ORDER_STATUS_CHANGED,
                        'Order status recorded',
                        'Order status was recorded from the existing order.',
                        ['status' => $order->status],
                        createdAt: $placedAt->copy()->addMinutes(3),
                    );
                    $created++;
                }
            }
        });

        $this->info("Backfilled {$created} order events.");

        return self::SUCCESS;
    }
}
