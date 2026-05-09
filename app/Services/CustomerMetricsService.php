<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use App\Support\OrderLifecycle;

class CustomerMetricsService
{
    public function recalculate(Customer $customer): void
    {
        $orders = $customer->orders()
            ->whereNotIn('status', [OrderLifecycle::ORDER_CANCELLED, OrderLifecycle::ORDER_REFUNDED])
            ->get(['id', 'grand_total', 'total', 'placed_at']);

        $totalOrders = $orders->count();
        $totalSpent = $orders->reduce(
            fn (string $carry, $order): string => bcadd($carry, (string) ($order->grand_total ?: $order->total), 2),
            '0'
        );

        $customer->forceFill([
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'average_order_value' => $totalOrders > 0 ? bcdiv($totalSpent, (string) $totalOrders, 2) : 0,
            'last_order_at' => $orders->max('placed_at'),
        ])->save();
    }

    public function recalculateForStore(Store $store): int
    {
        $count = 0;

        $store->customers()->chunkById(100, function ($customers) use (&$count): void {
            foreach ($customers as $customer) {
                $this->recalculate($customer);
                $count++;
            }
        });

        return $count;
    }
}
