<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Services\Carriers\FedEx\Support\FedExConfig;

final class FedExComprehensiveRateAccessClassifier
{
    public const STATE_PASSED = 'passed';

    public const STATE_BLOCKED_ENTITLEMENT = 'blocked_entitlement';

    public const STATE_BLOCKED_ACCESS = 'blocked_access';

    public const STATE_FAILED_AUTHENTICATION = 'failed_authentication';

    public const STATE_FAILED_INVALID_REQUEST = 'failed_invalid_request';

    public const STATE_FAILED_RESPONSE_SCHEMA = 'failed_response_schema';

    public const STATE_FAILED_TRANSPORT = 'failed_transport';

    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return array{access_state: string, fedex_error_code: ?string, fedex_error_message: ?string}
     */
    public function classify(
        ?int $httpStatus,
        ?array $responseBody,
        string $endpoint,
        ?string $transportErrorCode = null,
    ): array {
        $normalizedEndpoint = '/'.ltrim($endpoint, '/');
        $errors = $this->extractErrors($responseBody);
        $primaryCode = $errors[0]['code'] ?? null;
        $primaryMessage = $errors[0]['message'] ?? null;

        if ($transportErrorCode !== null) {
            return [
                'access_state' => self::STATE_FAILED_TRANSPORT,
                'fedex_error_code' => $transportErrorCode,
                'fedex_error_message' => $primaryMessage,
            ];
        }

        if ($normalizedEndpoint !== FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH) {
            return [
                'access_state' => self::STATE_FAILED_INVALID_REQUEST,
                'fedex_error_code' => $primaryCode,
                'fedex_error_message' => 'Unexpected comprehensive rate endpoint.',
            ];
        }

        if ($httpStatus === 401) {
            return [
                'access_state' => self::STATE_FAILED_AUTHENTICATION,
                'fedex_error_code' => $primaryCode,
                'fedex_error_message' => $primaryMessage,
            ];
        }

        if ($httpStatus === 400 || $this->isInvalidRequestCode($primaryCode)) {
            return [
                'access_state' => self::STATE_FAILED_INVALID_REQUEST,
                'fedex_error_code' => $primaryCode,
                'fedex_error_message' => $primaryMessage,
            ];
        }

        if ($httpStatus === 403) {
            if ($this->isEntitlementError($primaryCode, $primaryMessage)) {
                return [
                    'access_state' => self::STATE_BLOCKED_ENTITLEMENT,
                    'fedex_error_code' => $primaryCode,
                    'fedex_error_message' => $primaryMessage,
                ];
            }

            return [
                'access_state' => self::STATE_BLOCKED_ACCESS,
                'fedex_error_code' => $primaryCode,
                'fedex_error_message' => $primaryMessage,
            ];
        }

        if ($httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300) {
            return [
                'access_state' => self::STATE_PASSED,
                'fedex_error_code' => null,
                'fedex_error_message' => null,
            ];
        }

        return [
            'access_state' => self::STATE_FAILED_RESPONSE_SCHEMA,
            'fedex_error_code' => $primaryCode,
            'fedex_error_message' => $primaryMessage,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return list<array{code: ?string, message: ?string}>
     */
    private function extractErrors(?array $responseBody): array
    {
        $errors = [];
        foreach ((array) data_get($responseBody, 'errors', []) as $error) {
            if (! is_array($error)) {
                continue;
            }

            $errors[] = [
                'code' => filled($error['code'] ?? null) ? (string) $error['code'] : null,
                'message' => filled($error['message'] ?? null) ? (string) $error['message'] : null,
            ];
        }

        return $errors;
    }

    private function isEntitlementError(?string $code, ?string $message): bool
    {
        $haystack = strtoupper(trim((string) $code.' '.(string) $message));

        foreach ([
            'FORBIDDEN',
            'NOT.AUTHORIZED',
            'NOT AUTHORIZED',
            'ENTITLEMENT',
            'PERMISSION',
            'SCOPE',
            'ACCESS DENIED',
            'PROJECT ACCESS',
            'API ACCESS',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isInvalidRequestCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $normalized = strtoupper($code);

        return str_contains($normalized, 'REQUEST.MISMATCH')
            || str_contains($normalized, 'INVALID')
            || str_contains($normalized, 'REQUIRED')
            || str_contains($normalized, 'MISSING');
    }
}
