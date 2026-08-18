<?php

namespace App\Services\Checkout;

use App\Models\IdempotencyKey;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckoutIdempotencyService
{
    private const CLAIM_ID_ATTRIBUTE = 'checkoutIdempotencyClaimId';

    private const CLAIM_TOKEN_ATTRIBUTE = 'checkoutIdempotencyClaimToken';

    /**
     * Atomically creates the store/key claim before checkout side effects begin.
     *
     * @param  array<string, mixed>  $payload
     */
    public function replayOrStart(Store $store, Request $request, array $payload): ?JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Send an Idempotency-Key for each checkout attempt and reuse it when retrying that attempt.',
            ]);
        }

        if (mb_strlen($key) > 255) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The Idempotency-Key must not exceed 255 characters.',
            ]);
        }

        $hash = hash('sha256', json_encode([
            'path' => $request->path(),
            'method' => strtoupper($request->method()),
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $claimToken = (string) Str::uuid();

        try {
            $claim = IdempotencyKey::query()->create([
                'store_id' => $store->id,
                'key' => $key,
                'request_method' => strtoupper($request->method()),
                'request_path' => $request->path(),
                'request_hash' => $hash,
                'claim_token' => $claimToken,
            ]);

            $request->attributes->set(self::CLAIM_ID_ATTRIBUTE, (int) $claim->id);
            $request->attributes->set(self::CLAIM_TOKEN_ATTRIBUTE, $claimToken);

            return null;
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        $existing = IdempotencyKey::query()
            ->where('store_id', $store->id)
            ->where('key', $key)
            ->first();

        if (! $existing) {
            throw new \RuntimeException('The checkout idempotency claim could not be read after a concurrent insert.');
        }

        if (! hash_equals((string) $existing->request_hash, $hash)) {
            throw new HttpException(409, 'This Idempotency-Key was already used with a different checkout request.');
        }

        if (is_array($existing->response_body) && $existing->response_code) {
            return response()->json($existing->response_body, (int) $existing->response_code);
        }

        return response()->json([
            'message' => 'This checkout attempt is still processing. Retry with the same Idempotency-Key.',
            'code' => 'idempotency_in_progress',
            'retry_with_same_key' => true,
        ], 409)->header('Retry-After', '1');
    }

    /**
     * Stores the response only on the exact claim owned by this request.
     *
     * @param  array<string, mixed>  $body
     */
    public function remember(Store $store, Request $request, array $body, int $code, int $resourceId): void
    {
        $claimId = (int) $request->attributes->get(self::CLAIM_ID_ATTRIBUTE, 0);
        $claimToken = (string) $request->attributes->get(self::CLAIM_TOKEN_ATTRIBUTE, '');
        if ($claimId < 1 || $claimToken === '') {
            return;
        }

        $updated = IdempotencyKey::query()
            ->whereKey($claimId)
            ->where('store_id', $store->id)
            ->where('claim_token', $claimToken)
            ->whereNull('response_code')
            ->update([
                'response_code' => $code,
                'response_body' => $body,
                'resource_type' => 'checkout',
                'resource_id' => $resourceId,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new \RuntimeException('The checkout response could not be stored against its idempotency claim.');
        }
    }

    public function releaseOwnUnfinishedClaim(Store $store, Request $request): void
    {
        $claimId = (int) $request->attributes->get(self::CLAIM_ID_ATTRIBUTE, 0);
        $claimToken = (string) $request->attributes->get(self::CLAIM_TOKEN_ATTRIBUTE, '');
        if ($claimId < 1 || $claimToken === '') {
            return;
        }

        IdempotencyKey::query()
            ->whereKey($claimId)
            ->where('store_id', $store->id)
            ->where('claim_token', $claimToken)
            ->whereNull('response_code')
            ->delete();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return $sqlState === '23505'
            || $driverCode === '1062'
            || str_contains($message, 'idempotency_keys_store_key_unique')
            || ($driverCode === '19' && str_contains($message, 'unique constraint failed'));
    }
}
