<?php

namespace App\Services\Carriers\FedEx;

use App\Services\Carriers\DTO\CarrierApiResult;

class FedExAuthorizationClassifier
{
    public static function isAuthorizationBlocked(CarrierApiResult $result, string $path): bool
    {
        if ($result->success) {
            return false;
        }

        $httpStatus = (int) data_get($result->responseSummary, 'http_status');

        if ($httpStatus !== 403) {
            return false;
        }

        $normalized = '/'.ltrim($path, '/');

        return str_contains($normalized, '/rate/v1/')
            || str_contains($normalized, '/ship/v1/');
    }

    public static function blockedErrorCode(CarrierApiResult $result, string $path): ?string
    {
        return self::isAuthorizationBlocked($result, $path) ? 'fedex_authorization_blocked' : null;
    }

    public static function applyBlockedClassification(CarrierApiResult $result, string $path): CarrierApiResult
    {
        $blockedCode = self::blockedErrorCode($result, $path);

        if ($blockedCode === null) {
            return $result;
        }

        return CarrierApiResult::failure(
            message: $result->errorMessage ?? 'FedEx authorization blocked for this capability in the current sandbox environment.',
            code: $blockedCode,
            requestId: $result->requestId,
            durationMs: $result->durationMs,
            requestSummary: $result->requestSummary,
            responseSummary: array_merge($result->responseSummary ?? [], [
                'authorization_blocked' => true,
            ]),
        );
    }
}
