<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutConversionService;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentProviderManager $paymentProviderManager,
        CheckoutConversionService $conversionService,
        string $mode = 'test',
    ): JsonResponse {
        $mode = strtolower($mode);

        try {
            $result = $paymentProviderManager
                ->driver('stripe')
                ->verifyWebhook($request->getContent(), (string) $request->header('Stripe-Signature', ''), $mode);
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook verification failed', [
                'mode' => $mode,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe webhook.'], 400);
        }

        if ($result->mode !== null && $result->mode !== $mode) {
            return response()->json(['message' => 'Stripe webhook mode mismatch.'], 400);
        }

        match ($result->eventType) {
            'payment_intent.succeeded' => $conversionService->handleSucceededPayment($result),
            'payment_intent.payment_failed', 'payment_intent.canceled' => $conversionService->handleFailedPayment($result),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}
