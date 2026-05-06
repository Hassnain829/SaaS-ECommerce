<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;

class OrderEventRecorder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(
        Order $order,
        string $eventType,
        string $title,
        ?string $description = null,
        array $data = [],
        ?User $actor = null,
        ?\DateTimeInterface $createdAt = null,
    ): OrderEvent {
        return OrderEvent::query()->create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'data' => $data === [] ? null : $data,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
