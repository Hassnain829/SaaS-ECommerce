<?php

namespace App\Http\Controllers\Api;

use App\Data\Payments\PaymentWebhookResult;
use App\Http\Controllers\Controller;
use App\Models\PaymentProviderAccount;
use App\Models\SecurityLog;
use App\Services\CheckoutConversionService;
use App\Services\Payments\StripeConfig;
use App\Services\Payments\StripeConnectService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeConnectWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeConnectService $connectService,
        CheckoutConversionService $conversionService,
        SecurityLogRecorder $securityLogRecorder,
        StripeConfig $stripeConfig,
        string $mode = 'test',
    ): JsonResponse {
        $mode = strtolower($mode);
        $secret = $stripeConfig->stripeConnectWebhookSecret($mode);

        if ($secret === null || $secret === '') {
            Log::warning('Stripe Connect webhook secret is not configured.', ['mode' => $mode]);

            return response()->json(['message' => 'Stripe Connect webhook is not configured.'], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (\Throwable $exception) {
            Log::warning('Stripe Connect webhook verification failed', [
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe Connect webhook.'], 400);
        }

        $object = $event->data->object;
        $rawObject = method_exists($object, 'toArray') ? $object->toArray() : (array) $object;
        $providerAccountId = $this->providerAccountId($event, $rawObject);

        match ((string) $event->type) {
            'account.updated' => $this->handleAccountUpdated($providerAccountId, $rawObject, $mode, $connectService, $securityLogRecorder),
            'payment_intent.succeeded' => $conversionService->handleSucceededPayment($this->paymentResult($event, $rawObject, $providerAccountId, $mode)),
            'payment_intent.payment_failed', 'payment_intent.canceled' => $conversionService->handleFailedPayment($this->paymentResult($event, $rawObject, $providerAccountId, $mode)),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $rawObject
     */
    private function handleAccountUpdated(
        ?string $providerAccountId,
        array $rawObject,
        string $mode,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): void {
        if (! filled($providerAccountId)) {
            return;
        }

        $account = PaymentProviderAccount::query()
            ->stripe()
            ->connect()
            ->mode($mode)
            ->where('provider_account_id', $providerAccountId)
            ->first();

        if (! $account) {
            return;
        }

        $account = $connectService->applyAccountStatus($account, $rawObject);

        $securityLogRecorder->record(
            null,
            'stripe_connect_webhook_account_updated',
            SecurityLog::SEVERITY_INFO,
            store: $account->store,
            metadata: [
                'payment_provider_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'status' => $account->status,
                'charges_enabled' => $account->charges_enabled,
                'mode' => $mode,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $rawObject
     */
    private function paymentResult(object $event, array $rawObject, ?string $providerAccountId, string $mode): PaymentWebhookResult
    {
        $failure = $rawObject['last_payment_error'] ?? [];

        return new PaymentWebhookResult(
            eventType: (string) $event->type,
            providerIntentId: (string) ($rawObject['id'] ?? ''),
            status: (string) ($rawObject['status'] ?? ''),
            amount: isset($rawObject['amount'])
                ? $this->fromMinor((int) $rawObject['amount'], (string) ($rawObject['currency'] ?? 'usd'))
                : null,
            currencyCode: isset($rawObject['currency']) ? strtoupper((string) $rawObject['currency']) : null,
            failureCode: is_array($failure) ? ($failure['code'] ?? null) : null,
            failureMessage: is_array($failure) ? ($failure['message'] ?? null) : null,
            raw: [
                'id' => $event->id,
                'type' => $event->type,
                'account' => $providerAccountId,
                'object' => $rawObject,
            ],
            providerAccountId: $providerAccountId,
            mode: $mode,
        );
    }

    /**
     * @param  array<string, mixed>  $rawObject
     */
    private function providerAccountId(object $event, array $rawObject): ?string
    {
        if (isset($event->account) && filled($event->account)) {
            return (string) $event->account;
        }

        if (($rawObject['object'] ?? null) === 'account' && filled($rawObject['id'] ?? null)) {
            return (string) $rawObject['id'];
        }

        return null;
    }

    private function fromMinor(int $amount, string $currency): float
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return round($amount / ($zeroDecimal ? 1 : 100), 2);
    }
}
