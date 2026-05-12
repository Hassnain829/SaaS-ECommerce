<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\CheckoutEvent;

class CheckoutEventRecorder
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(
        Checkout $checkout,
        string $eventType,
        string $title,
        ?string $description = null,
        array $data = [],
    ): CheckoutEvent {
        return CheckoutEvent::query()->create([
            'store_id' => $checkout->store_id,
            'checkout_id' => $checkout->id,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'data' => $data === [] ? null : $data,
        ]);
    }
}
