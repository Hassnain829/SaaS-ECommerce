<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\PaymentIntent;

interface PaymentProviderInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function updatePaymentIntentAmount(
        string $providerIntentId,
        int $amountMinor,
        string $currencyCode,
        array $options = [],
    ): PaymentIntentUpdateResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function createRefund(
        PaymentIntent $paymentIntent,
        int $amountMinor,
        string $currencyCode,
        array $options = [],
    ): PaymentRefundResult;

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieveRefund(
        string $providerRefundId,
        PaymentIntent $paymentIntent,
        array $options = [],
    ): PaymentRefundResult;

    public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult;

    public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult;
}
