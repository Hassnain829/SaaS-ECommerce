<?php

namespace App\Services\Checkout;

use App\Models\IdempotencyKey;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutIdempotencyService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function replayOrStart(Store $store, Request $request, array $payload): ?JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return null;
        }

        $hash = hash('sha256', json_encode([
            'path' => $request->path(),
            'method' => strtoupper($request->method()),
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES));

        $existing = IdempotencyKey::query()
            ->where('store_id', $store->id)
            ->where('key', $key)
            ->first();

        if ($existing === null) {
            $request->attributes->set('checkoutIdempotencyKey', $key);
            $request->attributes->set('checkoutIdempotencyHash', $hash);

            return null;
        }

        if (! hash_equals((string) $existing->request_hash, $hash)) {
            throw new HttpException(409, 'This Idempotency-Key was already used with a different checkout request.');
        }

        if (is_array($existing->response_body) && $existing->response_code) {
            return response()->json($existing->response_body, (int) $existing->response_code);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function remember(Store $store, Request $request, array $body, int $code, int $resourceId): void
    {
        $key = (string) $request->attributes->get('checkoutIdempotencyKey', '');
        $hash = (string) $request->attributes->get('checkoutIdempotencyHash', '');
        if ($key === '' || $hash === '') {
            return;
        }

        IdempotencyKey::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'key' => $key,
            ],
            [
                'request_method' => strtoupper($request->method()),
                'request_path' => $request->path(),
                'request_hash' => $hash,
                'response_code' => $code,
                'response_body' => $body,
                'resource_type' => 'checkout',
                'resource_id' => $resourceId,
            ]
        );
    }
}
