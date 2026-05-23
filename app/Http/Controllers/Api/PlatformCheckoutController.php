<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\PaymentIntent;
use App\Models\Store;
use App\Services\CheckoutConversionService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Shipping\CheckoutShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlatformCheckoutController extends Controller
{
    private const RAW_CARD_FIELDS = [
        'card_number',
        'cvc',
        'cvv',
        'expiry',
        'card_token',
    ];

    public function store(Request $request, CheckoutService $checkoutService): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        if ($this->containsRawCardField($request->all())) {
            throw ValidationException::withMessages([
                'payment' => 'Raw payment card data must not be sent to this API. Use Stripe.js in the browser instead.',
            ]);
        }

        try {
            $checkout = $checkoutService->create($store, $this->validatedPayload($request));
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Stripe')) {
                throw ValidationException::withMessages([
                    'payment' => 'Stripe is not configured for platform checkout. Connect Stripe in the SaaS dashboard or use External checkout sync.',
                ]);
            }

            throw $exception;
        }

        return response()->json($this->checkoutResponse($checkout), 201);
    }

    public function show(Request $request, Checkout $checkout): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store && (int) $checkout->store_id === (int) $store->id, 404);

        return response()->json($this->checkoutResponse(
            $checkout->load(['items', 'addresses', 'paymentIntents', 'convertedOrder'])
        ));
    }

    public function deliveryOptions(Request $request, Checkout $checkout, CheckoutShippingService $checkoutShippingService): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store && (int) $checkout->store_id === (int) $store->id, 404);

        $payload = $this->validatedDeliveryPayload($request);

        return response()->json([
            'delivery_options' => collect($checkoutShippingService->deliveryOptions($checkout, $payload['shipping_address'] ?? null))
                ->map(fn (array $option): array => $this->publicDeliveryOption($option))
                ->values()
                ->all(),
        ]);
    }

    public function selectShippingMethod(Request $request, Checkout $checkout, CheckoutShippingService $checkoutShippingService): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store && (int) $checkout->store_id === (int) $store->id, 404);

        $payload = $this->validatedShippingSelectionPayload($request);
        $checkout = $checkoutShippingService->selectShippingMethod(
            $checkout,
            (int) $payload['shipping_method_id'],
            $payload['shipping_address'] ?? null,
        );

        return response()->json($this->checkoutResponse($checkout));
    }

    public function confirm(
        Request $request,
        Checkout $checkout,
        PaymentProviderManager $paymentProviderManager,
        CheckoutConversionService $conversionService,
    ): JsonResponse {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store && (int) $checkout->store_id === (int) $store->id, 404);

        $checkout->loadMissing(['paymentIntents', 'convertedOrder']);
        /** @var PaymentIntent|null $paymentIntent */
        $paymentIntent = $checkout->paymentIntents->sortByDesc('id')->first();

        if (! $paymentIntent?->provider_intent_id) {
            throw ValidationException::withMessages([
                'payment' => 'No Stripe payment was found for this checkout.',
            ]);
        }

        $result = $paymentProviderManager
            ->driver($paymentIntent->provider)
            ->retrievePaymentIntent($paymentIntent->provider_intent_id);

        if ($result->status === 'succeeded') {
            $order = $conversionService->handleSucceededPayment($result);

            return response()->json([
                'message' => 'Platform checkout converted to an order.',
                'checkout' => $this->checkoutResponse($checkout->fresh(['items', 'addresses', 'paymentIntents', 'convertedOrder']))['checkout'],
                'order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total' => number_format((float) ($order->grand_total ?: $order->total), 2, '.', ''),
                    'currency_code' => $order->currency_code,
                ] : null,
            ]);
        }

        if (in_array($result->status, ['requires_payment_method', 'canceled'], true)) {
            $conversionService->handleFailedPayment($result);
        }

        return response()->json([
            'message' => 'Stripe has not confirmed payment for this checkout yet.',
            'payment_status' => $result->status,
        ], 409);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'source_channel' => ['nullable', 'string', 'max:64'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'shipping_method_id' => ['nullable', 'integer'],
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.first_name' => ['nullable', 'string', 'max:100'],
            'customer.last_name' => ['nullable', 'string', 'max:100'],
            'customer.full_name' => ['nullable', 'string', 'max:191'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['nullable', 'string', 'max:191'],
            'shipping_address.company' => ['nullable', 'string', 'max:191'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.state' => ['nullable', 'string', 'max:100'],
            'shipping_address.province_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.country' => ['required', 'string', 'max:100'],
            'shipping_address.country_code' => ['nullable', 'string', 'max:8'],
            'shipping_address.phone' => ['nullable', 'string', 'max:50'],
            'shipping_address.delivery_notes' => ['nullable', 'string', 'max:1000'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.same_as_shipping' => ['nullable', 'boolean'],
            'billing_address.name' => ['nullable', 'string', 'max:191'],
            'billing_address.company' => ['nullable', 'string', 'max:191'],
            'billing_address.address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address.address_line2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:100'],
            'billing_address.state' => ['nullable', 'string', 'max:100'],
            'billing_address.province_code' => ['nullable', 'string', 'max:32'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:32'],
            'billing_address.country' => ['nullable', 'string', 'max:100'],
            'billing_address.country_code' => ['nullable', 'string', 'max:8'],
            'billing_address.phone' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator): void {
            $billing = $validator->getData()['billing_address'] ?? null;
            if (! is_array($billing) || ($billing['same_as_shipping'] ?? true)) {
                return;
            }

            foreach (['address_line1', 'city', 'country'] as $field) {
                if (! filled($billing[$field] ?? null)) {
                    $validator->errors()->add("billing_address.{$field}", 'Add a complete billing address or mark it same as shipping.');
                }
            }
        });

        /** @var array<string, mixed> $payload */
        $payload = $validator->validate();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDeliveryPayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.address_line1' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.state' => ['nullable', 'string', 'max:100'],
            'shipping_address.province_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.country' => ['nullable', 'string', 'max:100'],
            'shipping_address.country_code' => ['nullable', 'string', 'max:8'],
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedShippingSelectionPayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'shipping_method_id' => ['required', 'integer'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.address_line1' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.state' => ['nullable', 'string', 'max:100'],
            'shipping_address.province_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_address.country' => ['nullable', 'string', 'max:100'],
            'shipping_address.country_code' => ['nullable', 'string', 'max:8'],
        ])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutResponse(?Checkout $checkout): array
    {
        abort_unless($checkout, 404);

        $checkout->loadMissing(['items', 'addresses', 'paymentIntents', 'convertedOrder']);
        $checkout->loadMissing('paymentProviderAccount');
        /** @var PaymentIntent|null $paymentIntent */
        $paymentIntent = $checkout->paymentIntents->sortByDesc('id')->first();
        $providerAccount = $checkout->paymentProviderAccount;

        return [
            'message' => 'Platform checkout created.',
            'checkout' => [
                'id' => $checkout->id,
                'checkout_number' => $checkout->checkout_number,
                'status' => $checkout->status,
                'source_channel' => $checkout->source_channel,
                'currency_code' => $checkout->currency_code,
                'subtotal' => number_format((float) $checkout->subtotal, 2, '.', ''),
                'shipping_total' => number_format((float) $checkout->shipping_total, 2, '.', ''),
                'shipping_method_id' => $checkout->shipping_method_id,
                'shipping_snapshot' => $checkout->shipping_snapshot,
                'tax_total' => number_format((float) $checkout->tax_total, 2, '.', ''),
                'discount_total' => number_format((float) $checkout->discount_total, 2, '.', ''),
                'grand_total' => number_format((float) $checkout->grand_total, 2, '.', ''),
                'converted_order_id' => $checkout->converted_order_id,
                'converted_order_number' => $checkout->convertedOrder?->order_number,
                'items' => $checkout->items
                    ->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'variant_id' => $item->product_variant_id,
                        'product_name' => $item->product_name,
                        'variant_label' => $item->variant_label,
                        'sku' => $item->sku_snapshot,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                        'total' => number_format((float) $item->total, 2, '.', ''),
                    ])
                    ->values()
                    ->all(),
            ],
            'payment' => [
                'provider' => $paymentIntent?->provider,
                'provider_intent_id' => $paymentIntent?->provider_intent_id,
                'provider_account_id' => $paymentIntent?->provider_account_id ?: $providerAccount?->provider_account_id,
                'connection_type' => $providerAccount?->connection_type,
                'connection_label' => $providerAccount?->connection_type === 'connect'
                    ? 'Connected Stripe account'
                    : 'Platform test mode',
                'status' => $paymentIntent?->status,
                'client_secret' => $paymentIntent?->client_secret,
                'publishable_key' => config('payments.stripe.key'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private function publicDeliveryOption(array $option): array
    {
        return [
            'id' => $option['id'],
            'shipping_method_id' => $option['shipping_method_id'],
            'name' => $option['name'],
            'description' => $option['description'],
            'delivery_speed_label' => $option['delivery_speed_label'],
            'amount' => $option['amount'],
            'amount_formatted' => $option['amount_formatted'],
            'currency_code' => $option['currency_code'],
            'estimated_min_days' => $option['estimated_min_days'],
            'estimated_max_days' => $option['estimated_max_days'],
            'carrier_name' => $option['carrier_name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function containsRawCardField(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::RAW_CARD_FIELDS, true)) {
                return true;
            }

            if (is_array($value) && $this->containsRawCardField($value)) {
                return true;
            }
        }

        return false;
    }
}
