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
            ->where('status', '!=', OrderLifecycle::ORDER_CANCELLED)
            ->get(['id', 'status', 'grand_total', 'total', 'refunded_total', 'placed_at']);

        // Fully refunded orders still count toward order history, but contribute $0 net spend.
        $totalOrders = $orders->count();
        $totalSpent = $orders->reduce(function (string $carry, $order): string {
            $gross = (string) ($order->grand_total ?: $order->total ?: '0');
            $refunded = (string) ($order->refunded_total ?: '0');
            $net = bcsub($gross, $refunded, 2);
            if (bccomp($net, '0', 2) < 0) {
                $net = '0.00';
            }

            return bcadd($carry, $net, 2);
        }, '0');

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
