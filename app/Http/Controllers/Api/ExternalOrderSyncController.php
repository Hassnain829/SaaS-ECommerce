<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ExternalOrderConflictException;
use App\Http\Controllers\Controller;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Store;
use App\Services\ExternalOrderSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExternalOrderSyncController extends Controller
{
    private const RAW_CARD_FIELDS = [
        'card_number',
        'cvc',
        'cvv',
        'expiry',
        'card_token',
    ];

    public function store(Request $request, ExternalOrderSyncService $syncService): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        if ($this->containsRawCardField($request->all())) {
            throw ValidationException::withMessages([
                'payment' => 'Raw payment card data must not be sent to this API.',
            ]);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        $payload = $this->validatedPayload($request);
        $this->assertExternalOrderIdentityPresent($payload);
        $requestHash = $this->requestHash($payload);

        if ($idempotencyKey !== '') {
            if (strlen($idempotencyKey) > 191) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Use an Idempotency-Key header shorter than 192 characters.',
                ]);
            }

            $existingKey = IdempotencyKey::query()
                ->where('store_id', $store->id)
                ->where('key', $idempotencyKey)
                ->first();

            if ($existingKey) {
                if (! hash_equals($existingKey->request_hash, $requestHash)) {
                    return response()->json([
                        'message' => 'This Idempotency-Key was already used for a different request.',
                    ], 409);
                }

                return response()->json($existingKey->response_body ?? [], $existingKey->response_code ?: 200);
            }
        }

        try {
            $result = $syncService->sync($store, $payload, $requestHash);
        } catch (ExternalOrderConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        $statusCode = $result['created'] ? 201 : 200;
        $body = $this->orderResponse($result['order'], $result['created']);

        if ($idempotencyKey !== '') {
            IdempotencyKey::query()->create([
                'store_id' => $store->id,
                'key' => $idempotencyKey,
                'request_method' => $request->method(),
                'request_path' => '/'.$request->path(),
                'request_hash' => $requestHash,
                'response_code' => $statusCode,
                'response_body' => $body,
                'resource_type' => Order::class,
                'resource_id' => $result['order']->id,
            ]);
        }

        return response()->json($body, $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'external_order_id' => ['nullable', 'string', 'max:191'],
            'external_order_number' => ['nullable', 'string', 'max:191'],
            'external_checkout_reference' => ['nullable', 'string', 'max:191'],
            'payment_status' => ['required', 'string', Rule::in([
                'paid',
                'pending',
                'authorized',
                'failed',
                'refunded',
                'partially_refunded',
                'cod_pending',
                'bank_transfer_pending',
            ])],
            'payment_gateway' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:191'],
            'placed_at' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'shipping_method_name' => ['nullable', 'string', 'max:120'],
            'shipping_carrier_name' => ['nullable', 'string', 'max:120'],
            'shipping_delivery_speed_label' => ['nullable', 'string', 'max:120'],
            'shipping' => ['nullable', 'array'],
            'shipping.source' => ['nullable', 'string', 'max:50'],
            'shipping.method_name' => ['nullable', 'string', 'max:120'],
            'shipping.carrier_name' => ['nullable', 'string', 'max:120'],
            'shipping.delivery_speed_label' => ['nullable', 'string', 'max:120'],
            'shipping.amount' => ['nullable', 'numeric', 'min:0'],
            'shipping.currency' => ['nullable', 'string', 'max:8'],
            'shipping.currency_code' => ['nullable', 'string', 'max:8'],
            'fulfillment' => ['nullable', 'array'],
            'fulfillment.managed_by' => ['nullable', 'string', 'max:50'],
            'fulfillment.status' => ['nullable', 'string', 'max:50'],
            'fulfillment.external_fulfillment_id' => ['nullable', 'string', 'max:191'],
            'fulfillment.external_shipment_id' => ['nullable', 'string', 'max:191'],
            'fulfillment.carrier_name' => ['nullable', 'string', 'max:120'],
            'fulfillment.tracking_number' => ['nullable', 'string', 'max:191'],
            'fulfillment.tracking_url' => ['nullable', 'url', 'max:500'],
            'fulfillment.shipped_at' => ['nullable', 'date'],
            'fulfillment.delivered_at' => ['nullable', 'date'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'discounts' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.first_name' => ['nullable', 'string', 'max:100'],
            'customer.last_name' => ['nullable', 'string', 'max:100'],
            'customer.full_name' => ['nullable', 'string', 'max:191'],
            'customer.name' => ['nullable', 'string', 'max:191'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'totals' => ['nullable', 'array'],
            'totals.subtotal' => ['nullable', 'numeric', 'min:0'],
            'totals.shipping' => ['nullable', 'numeric', 'min:0'],
            'totals.tax' => ['nullable', 'numeric', 'min:0'],
            'totals.discount' => ['nullable', 'numeric', 'min:0'],
            'totals.total' => ['nullable', 'numeric', 'min:0'],
            'totals.grand_total' => ['nullable', 'numeric', 'min:0'],
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
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.external_line_id' => ['nullable', 'string', 'max:191'],
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
     * @param  array<string, mixed>  $payload
     */
    private function assertExternalOrderIdentityPresent(array $payload): void
    {
        if (filled($payload['external_order_id'] ?? null) || filled($payload['external_order_number'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'external_order' => 'External order sync requires external_order_id or external_order_number. Idempotency-Key is supported as replay protection but cannot be the only order identity.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        $normalized = $this->sortRecursive($payload);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->sortRecursive($child);
        }

        return $value;
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

    /**
     * @return array<string, mixed>
     */
    private function orderResponse(Order $order, bool $created): array
    {
        $order->loadMissing(['items', 'addresses', 'customer']);

        return [
            'message' => $created ? 'External order synced.' : 'External order already synced.',
            'created' => $created,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'external_order_number' => $order->external_order_number,
                'external_order_id' => $order->external_order_id,
                'external_checkout_reference' => $order->external_checkout_reference,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_status' => $order->fulfillment_status,
                'order_source' => $order->order_source,
                'channel' => $order->channel,
                'currency_code' => $order->currency_code,
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'shipping' => number_format((float) $order->shipping, 2, '.', ''),
                'tax' => number_format((float) $order->tax, 2, '.', ''),
                'discount' => number_format((float) $order->discount, 2, '.', ''),
                'total' => number_format((float) ($order->grand_total ?: $order->total), 2, '.', ''),
                'payment_gateway' => $order->payment_gateway,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'customer_email' => $order->customer_email,
                'items' => $order->items
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
        ];
    }
}
