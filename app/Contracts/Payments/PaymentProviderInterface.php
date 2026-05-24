<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;

interface PaymentProviderInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult;

    public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult;

    public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult;
}
