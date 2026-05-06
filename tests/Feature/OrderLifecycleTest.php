<?php

namespace Tests\Feature;

use App\Support\OrderLifecycle;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    public function test_order_payment_and_fulfillment_statuses_are_separate(): void
    {
        $this->assertSame([
            'pending',
            'confirmed',
            'processing',
            'completed',
            'cancelled',
            'refunded',
        ], OrderLifecycle::orderStatuses());

        $this->assertSame([
            'pending',
            'authorized',
            'paid',
            'failed',
            'refunded',
            'partially_refunded',
        ], OrderLifecycle::paymentStatuses());

        $this->assertSame([
            'unfulfilled',
            'partial',
            'fulfilled',
            'returned',
        ], OrderLifecycle::fulfillmentStatuses());
    }

    public function test_shipping_like_states_are_not_order_statuses(): void
    {
        $this->assertNotContains('shipped', OrderLifecycle::orderStatuses());
        $this->assertNotContains('delivered', OrderLifecycle::orderStatuses());

        $this->assertContains('delivered', OrderLifecycle::shipmentStatuses());
    }

    public function test_order_status_transition_rules_are_explicit(): void
    {
        $this->assertTrue(OrderLifecycle::canTransitionOrderStatus('pending', 'confirmed'));
        $this->assertTrue(OrderLifecycle::canTransitionOrderStatus('confirmed', 'processing'));
        $this->assertTrue(OrderLifecycle::canTransitionOrderStatus('processing', 'completed'));
        $this->assertTrue(OrderLifecycle::canTransitionOrderStatus('completed', 'refunded'));

        $this->assertFalse(OrderLifecycle::canTransitionOrderStatus('pending', 'completed'));
        $this->assertFalse(OrderLifecycle::canTransitionOrderStatus('completed', 'processing'));
        $this->assertFalse(OrderLifecycle::canTransitionOrderStatus('cancelled', 'confirmed'));
        $this->assertFalse(OrderLifecycle::canTransitionOrderStatus('confirmed', 'shipped'));
    }
}
