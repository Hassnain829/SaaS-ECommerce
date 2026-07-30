<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\PaymentIntent as LocalPaymentIntent;
use App\Models\PaymentProviderAccount;
use App\Support\Money\CurrencyPrecision;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePlatformPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly StripeConfig $stripeConfig,
    ) {}

    public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
    {
        $providerAccount = $options['provider_account'] ?? $checkout->paymentProviderAccount;
        $providerAccount = $providerAccount instanceof PaymentProviderAccount ? $providerAccount : null;
        $mode = (string) ($options['mode'] ?? $providerAccount?->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        if ($providerAccount && $providerAccount->mode !== $mode) {
            throw new \RuntimeException('The selected Stripe account does not match the checkout payment mode.');
        }

        $amountMinor = CurrencyPrecision::toMinorUnits((string) $checkout->grand_total, (string) $checkout->currency_code);
        $client = new StripeClient($secret);
        $requestOptions = $providerAccount ? $this->requestOptionsForAccount($providerAccount) : [];
        $intent = $client->paymentIntents->create([
            'amount' => $amountMinor,
            'currency' => strtolower((string) $checkout->currency_code),
            'payment_method_types' => ['card'],
            'metadata' => [
                'store_id' => (string) $checkout->store_id,
                'checkout_id' => (string) $checkout->id,
                'checkout_number' => (string) $checkout->checkout_number,
                'source_channel' => (string) $checkout->source_channel,
                'payment_provider_account_id' => (string) ($providerAccount?->id ?? ''),
                'connected_account_id' => (string) ($providerAccount?->provider_account_id ?? ''),
                'payment_mode' => $mode,
            ],
            'description' => 'Checkout '.$checkout->checkout_number,
        ], $requestOptions);

        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;

        return new PaymentIntentResult(
            provider: 'stripe',
            providerIntentId: (string) $intent->id,
            clientSecret: $intent->client_secret ?? null,
            status: (string) $intent->status,
            amount: CurrencyPrecision::roundMajor((string) $checkout->grand_total, (string) $checkout->currency_code),
            currencyCode: strtoupper((string) $checkout->currency_code),
            raw: $raw,
            providerAccountId: $providerAccount?->provider_account_id,
            mode: $mode,
        );
    }

    public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
    {
        $secret = $this->stripeConfig->stripeWebhookSecret($mode);
        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured for '.$mode.' mode.');
        }

        $event = Webhook::constructEvent($payload, $signature, $secret);
        $object = $event->data->object;
        $rawObject = method_exists($object, 'toArray') ? $object->toArray() : (array) $object;
        $failure = $rawObject['last_payment_error'] ?? [];

        return new PaymentWebhookResult(
            eventType: (string) $event->type,
            providerIntentId: (string) ($rawObject['id'] ?? ''),
            status: (string) ($rawObject['status'] ?? ''),
            amount: isset($rawObject['amount']) ? CurrencyPrecision::fromMinorUnits((int) $rawObject['amount'], (string) ($rawObject['currency'] ?? 'usd')) : null,
            currencyCode: isset($rawObject['currency']) ? strtoupper((string) $rawObject['currency']) : null,
            failureCode: is_array($failure) ? ($failure['code'] ?? null) : null,
            failureMessage: is_array($failure) ? ($failure['message'] ?? null) : null,
            raw: [
                'id' => $event->id,
                'type' => $event->type,
                'object' => $rawObject,
            ],
            providerAccountId: isset($event->account) ? (string) $event->account : null,
            mode: $mode,
        );
    }

    public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
    {
        $localPaymentIntent = LocalPaymentIntent::query()
            ->with('paymentProviderAccount')
            ->where('provider', 'stripe')
            ->where('provider_intent_id', $providerIntentId)
            ->latest('id')
            ->first();

        $providerAccount = $options['provider_account'] ?? $localPaymentIntent?->paymentProviderAccount;
        $providerAccount = $providerAccount instanceof PaymentProviderAccount ? $providerAccount : null;
        $mode = (string) ($options['mode'] ?? $providerAccount?->mode ?? $localPaymentIntent?->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        $client = new StripeClient($secret);
        $intent = $client->paymentIntents->cancel(
            $providerIntentId,
            [],
            $providerAccount ? $this->requestOptionsForAccount($providerAccount) : []
        );
        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;

        return new PaymentWebhookResult(
            eventType: 'payment_intent.canceled',
            providerIntentId: (string) ($raw['id'] ?? $providerIntentId),
            status: (string) ($raw['status'] ?? 'canceled'),
            amount: isset($raw['amount']) ? CurrencyPrecision::fromMinorUnits((int) $raw['amount'], (string) ($raw['currency'] ?? 'usd')) : null,
            currencyCode: isset($raw['currency']) ? strtoupper((string) $raw['currency']) : null,
            raw: [
                'id' => 'cancel_'.$providerIntentId,
                'type' => 'payment_intent.canceled',
                'object' => $raw,
            ],
            providerAccountId: $providerAccount?->provider_account_id,
            mode: $mode,
        );
    }

    public function updatePaymentIntentAmount(
        string $providerIntentId,
        int $amountMinor,
        string $currencyCode,
        array $options = [],
    ): PaymentIntentUpdateResult {
        $localPaymentIntent = LocalPaymentIntent::query()
            ->with('paymentProviderAccount')
            ->where('provider', 'stripe')
            ->where('provider_intent_id', $providerIntentId)
            ->latest('id')
            ->first();

        $providerAccount = $options['provider_account'] ?? $localPaymentIntent?->paymentProviderAccount;
        $providerAccount = $providerAccount instanceof PaymentProviderAccount ? $providerAccount : null;
        $mode = (string) ($options['mode'] ?? $providerAccount?->mode ?? $localPaymentIntent?->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        $client = new StripeClient($secret);
        $intent = $client->paymentIntents->update(
            $providerIntentId,
            [
                'amount' => $amountMinor,
            ],
            $providerAccount ? $this->requestOptionsForAccount($providerAccount) : []
        );
        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;

        return $this->paymentIntentUpdateResultFromStripeObject($raw, $mode);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function paymentIntentUpdateResultFromStripeObject(array $raw, ?string $mode = null): PaymentIntentUpdateResult
    {
        $providerIntentId = (string) ($raw['id'] ?? '');
        $providerAmountMinor = (int) ($raw['amount'] ?? 0);
        $providerCurrency = strtoupper((string) ($raw['currency'] ?? ''));

        return new PaymentIntentUpdateResult(
            providerIntentId: $providerIntentId,
            amountMinor: $providerAmountMinor,
            currencyCode: $providerCurrency,
            status: (string) ($raw['status'] ?? ''),
            clientSecret: isset($raw['client_secret']) ? (string) $raw['client_secret'] : null,
            raw: $raw,
            mode: $mode,
        );
    }

    public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
    {
        $localPaymentIntent = LocalPaymentIntent::query()
            ->with('paymentProviderAccount')
            ->where('provider', 'stripe')
            ->where('provider_intent_id', $providerIntentId)
            ->latest('id')
            ->first();

        $mode = $mode ?? (string) ($localPaymentIntent?->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        $client = new StripeClient($secret);
        $providerAccount = $localPaymentIntent?->paymentProviderAccount;
        $requestOptions = $providerAccount instanceof PaymentProviderAccount
            ? $this->requestOptionsForAccount($providerAccount)
            : [];

        $intent = $client->paymentIntents->retrieve($providerIntentId, [], $requestOptions);
        $raw = method_exists($intent, 'toArray') ? $intent->toArray() : (array) $intent;
        $failure = $raw['last_payment_error'] ?? [];

        return new PaymentWebhookResult(
            eventType: $this->eventTypeForStatus((string) ($raw['status'] ?? '')),
            providerIntentId: (string) ($raw['id'] ?? $providerIntentId),
            status: (string) ($raw['status'] ?? ''),
            amount: isset($raw['amount']) ? CurrencyPrecision::fromMinorUnits((int) $raw['amount'], (string) ($raw['currency'] ?? 'usd')) : null,
            currencyCode: isset($raw['currency']) ? strtoupper((string) $raw['currency']) : null,
            failureCode: is_array($failure) ? ($failure['code'] ?? null) : null,
            failureMessage: is_array($failure) ? ($failure['message'] ?? null) : null,
            raw: [
                'id' => 'client_confirm_'.$providerIntentId,
                'type' => $this->eventTypeForStatus((string) ($raw['status'] ?? '')),
                'object' => $raw,
            ],
            providerAccountId: $providerAccount?->provider_account_id,
            mode: $mode,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createRefund(
        LocalPaymentIntent $paymentIntent,
        int $amountMinor,
        string $currencyCode,
        array $options = [],
    ): PaymentRefundResult {
        $paymentIntent->loadMissing('paymentProviderAccount');

        $providerAccount = $options['provider_account'] ?? $paymentIntent->paymentProviderAccount;
        $providerAccount = $providerAccount instanceof PaymentProviderAccount ? $providerAccount : null;
        $mode = (string) ($options['mode'] ?? $providerAccount?->mode ?? $paymentIntent->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        $client = new StripeClient($secret);
        $params = [
            'payment_intent' => (string) $paymentIntent->provider_intent_id,
            'amount' => $amountMinor,
            'reason' => $options['reason'] ?? 'requested_by_customer',
        ];

        if (! empty($options['idempotency_key'])) {
            $requestOptions = array_merge(
                $providerAccount ? $this->requestOptionsForAccount($providerAccount) : [],
                ['idempotency_key' => (string) $options['idempotency_key']]
            );
        } else {
            $requestOptions = $providerAccount ? $this->requestOptionsForAccount($providerAccount) : [];
        }

        $refund = $client->refunds->create($params, $requestOptions);
        $raw = method_exists($refund, 'toArray') ? $refund->toArray() : (array) $refund;

        return $this->paymentRefundResultFromStripeObject(
            $raw,
            $amountMinor,
            $currencyCode,
            $providerAccount?->provider_account_id ?? $paymentIntent->provider_account_id,
            $mode,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function retrieveRefund(
        string $providerRefundId,
        LocalPaymentIntent $paymentIntent,
        array $options = [],
    ): PaymentRefundResult {
        $paymentIntent->loadMissing('paymentProviderAccount');

        $providerAccount = $options['provider_account'] ?? $paymentIntent->paymentProviderAccount;
        $providerAccount = $providerAccount instanceof PaymentProviderAccount ? $providerAccount : null;
        $mode = (string) ($options['mode'] ?? $providerAccount?->mode ?? $paymentIntent->mode ?? $this->stripeConfig->defaultMode());
        $secret = $this->stripeConfig->stripeSecretKey($mode);

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('Stripe is not configured for '.$mode.' mode.');
        }

        $client = new StripeClient($secret);
        $requestOptions = $providerAccount ? $this->requestOptionsForAccount($providerAccount) : [];
        $refund = $client->refunds->retrieve($providerRefundId, [], $requestOptions);
        $raw = method_exists($refund, 'toArray') ? $refund->toArray() : (array) $refund;

        return $this->paymentRefundResultFromStripeObject(
            $raw,
            (int) ($raw['amount'] ?? 0),
            (string) ($raw['currency'] ?? $paymentIntent->currency_code),
            $providerAccount?->provider_account_id ?? $paymentIntent->provider_account_id,
            $mode,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function paymentRefundResultFromStripeObject(
        array $raw,
        int $fallbackAmountMinor,
        string $fallbackCurrency,
        ?string $providerAccountId,
        string $mode,
    ): PaymentRefundResult {
        $currency = isset($raw['currency']) ? strtoupper((string) $raw['currency']) : strtoupper($fallbackCurrency);
        $amountMinor = isset($raw['amount']) ? (int) $raw['amount'] : $fallbackAmountMinor;

        return new PaymentRefundResult(
            providerRefundId: (string) ($raw['id'] ?? ''),
            status: (string) ($raw['status'] ?? 'pending'),
            amount: CurrencyPrecision::fromMinorUnits($amountMinor, $currency),
            amountMinor: $amountMinor,
            currencyCode: $currency,
            failureCode: isset($raw['failure_reason']) ? (string) $raw['failure_reason'] : null,
            failureMessage: isset($raw['failure_reason']) ? (string) $raw['failure_reason'] : null,
            raw: $raw,
            providerAccountId: $providerAccountId,
            mode: $mode,
        );
    }

    /**
     * @return array<string, string>
     */
    private function requestOptionsForAccount(PaymentProviderAccount $account): array
    {
        if ($account->connection_type !== PaymentProviderAccount::CONNECTION_CONNECT || ! filled($account->provider_account_id)) {
            return [];
        }

        return ['stripe_account' => (string) $account->provider_account_id];
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
