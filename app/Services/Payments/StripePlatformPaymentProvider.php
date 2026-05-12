<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePlatformPaymentProvider implements PaymentProviderInterface
{
    public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
    {
        $secret = (string) config('payments.stripe.secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe sandbox secret is not configured.');
        }

        $amountMinor = $this->amountMinor((float) $checkout->grand_total, (string) $checkout->currency_code);
        $client = new StripeClient($secret);
        $intent = $client->paymentIntents->create([
            'amount' => $amountMinor,
            'currency' => strtolower((string) $checkout->currency_code),
            'payment_method_types' => ['card'],
            'metadata' => [
                'store_id' => (string) $checkout->store_id,
                'checkout_id' => (string) $checkout->id,
                'checkout_number' => (string) $checkout->checkout_number,
                'source_channel' => (string) $checkout->source_channel,
            ],
            'description' => 'Checkout '.$checkout->checkout_number,
        ]);

        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;

        return new PaymentIntentResult(
            provider: 'stripe',
            providerIntentId: (string) $intent->id,
            clientSecret: $intent->client_secret ?? null,
            status: (string) $intent->status,
            amount: (float) $checkout->grand_total,
            currencyCode: strtoupper((string) $checkout->currency_code),
            raw: $raw,
        );
    }

    public function verifyWebhook(string $payload, string $signature): PaymentWebhookResult
    {
        $secret = (string) config('payments.stripe.webhook_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        $event = Webhook::constructEvent($payload, $signature, $secret);
        $object = $event->data->object;
        $rawObject = method_exists($object, 'toArray') ? $object->toArray() : (array) $object;
        $failure = $rawObject['last_payment_error'] ?? [];

        return new PaymentWebhookResult(
            eventType: (string) $event->type,
            providerIntentId: (string) ($rawObject['id'] ?? ''),
            status: (string) ($rawObject['status'] ?? ''),
            amount: isset($rawObject['amount']) ? $this->fromMinor((int) $rawObject['amount'], (string) ($rawObject['currency'] ?? 'usd')) : null,
            currencyCode: isset($rawObject['currency']) ? strtoupper((string) $rawObject['currency']) : null,
            failureCode: is_array($failure) ? ($failure['code'] ?? null) : null,
            failureMessage: is_array($failure) ? ($failure['message'] ?? null) : null,
            raw: [
                'id' => $event->id,
                'type' => $event->type,
                'object' => $rawObject,
            ],
        );
    }

    public function retrievePaymentIntent(string $providerIntentId): PaymentWebhookResult
    {
        $secret = (string) config('payments.stripe.secret', '');
        if ($secret === '') {
            throw new \RuntimeException('Stripe sandbox secret is not configured.');
        }

        $client = new StripeClient($secret);
        $intent = $client->paymentIntents->retrieve($providerIntentId, []);
        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;
        $failure = $raw['last_payment_error'] ?? [];

        return new PaymentWebhookResult(
            eventType: $this->eventTypeForStatus((string) ($raw['status'] ?? '')),
            providerIntentId: (string) ($raw['id'] ?? $providerIntentId),
            status: (string) ($raw['status'] ?? ''),
            amount: isset($raw['amount']) ? $this->fromMinor((int) $raw['amount'], (string) ($raw['currency'] ?? 'usd')) : null,
            currencyCode: isset($raw['currency']) ? strtoupper((string) $raw['currency']) : null,
            failureCode: is_array($failure) ? ($failure['code'] ?? null) : null,
            failureMessage: is_array($failure) ? ($failure['message'] ?? null) : null,
            raw: [
                'id' => 'client_confirm_'.$providerIntentId,
                'type' => $this->eventTypeForStatus((string) ($raw['status'] ?? '')),
                'object' => $raw,
            ],
        );
    }

    private function amountMinor(float $amount, string $currency): int
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return (int) round($amount * ($zeroDecimal ? 1 : 100));
    }

    private function fromMinor(int $amount, string $currency): float
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return round($amount / ($zeroDecimal ? 1 : 100), 2);
    }

    private function eventTypeForStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'payment_intent.succeeded',
            'canceled' => 'payment_intent.canceled',
            'requires_payment_method' => 'payment_intent.payment_failed',
            default => 'payment_intent.updated',
        };
    }
}
