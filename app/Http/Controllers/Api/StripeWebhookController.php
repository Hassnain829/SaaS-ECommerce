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
    ): JsonResponse {
        try {
            $result = $paymentProviderManager
                ->driver('stripe')
                ->verifyWebhook($request->getContent(), (string) $request->header('Stripe-Signature', ''));
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook verification failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe webhook.'], 400);
        }

        match ($result->eventType) {
            'payment_intent.succeeded' => $conversionService->handleSucceededPayment($result),
            'payment_intent.payment_failed', 'payment_intent.canceled' => $conversionService->handleFailedPayment($result),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}
