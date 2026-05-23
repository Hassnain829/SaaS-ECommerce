<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdempotencyKey;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\ExternalShipmentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExternalShipmentSyncController extends Controller
{
    public function store(Request $request, ExternalShipmentSyncService $syncService): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $payload = $this->validatedPayload($request);
        $requestHash = $this->requestHash($payload);
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

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

        $result = $syncService->sync($store, $payload);
        $statusCode = $result['created'] ? 201 : 200;
        $body = $this->shipmentResponse($result['shipment'], $result['order'], $result['created']);

        if ($idempotencyKey !== '') {
            IdempotencyKey::query()->create([
                'store_id' => $store->id,
                'key' => $idempotencyKey,
                'request_method' => $request->method(),
                'request_path' => '/'.$request->path(),
                'request_hash' => $requestHash,
                'response_code' => $statusCode,
                'response_body' => $body,
                'resource_type' => Shipment::class,
                'resource_id' => $result['shipment']->id,
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
            'external_order_number' => ['required', 'string', 'max:191'],
            'external_shipment_id' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', Rule::in([
                'pending',
                'open',
                'label_created',
                'processing',
                'shipped',
                'in_transit',
                'delivered',
                'failed',
                'returned',
                'cancelled',
                'canceled',
            ])],
            'carrier_name' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:191'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'shipped_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.sku' => ['required_with:items', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $validator->validate();

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        return hash('sha256', json_encode($this->sortRecursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
     * @return array<string, mixed>
     */
    private function shipmentResponse(Shipment $shipment, \App\Models\Order $order, bool $created): array
    {
        return [
            'message' => $created ? 'External shipment synced.' : 'External shipment updated.',
            'created' => $created,
            'shipment' => [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'external_shipment_id' => data_get($shipment->metadata, 'external_shipment_id'),
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'external_order_number' => $order->external_order_number,
                'fulfillment_status' => $order->fulfillment_status,
            ],
        ];
    }
}
