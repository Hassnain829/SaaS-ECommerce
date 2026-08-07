<?php

namespace App\Services\Carriers\FedEx\Operations;

/**
 * Maps FedEx / transport failures to merchant-safe messages without leaking secrets or raw payloads.
 */
final class FedExSafeExceptionMapper
{
    public static function merchantMessage(?string $code, ?string $upstreamMessage = null, ?int $httpStatus = null): string
    {
        $code = strtoupper(trim((string) $code));
        $upstream = trim((string) $upstreamMessage);

        return match (true) {
            $code === 'ACCOUNT_RATE_UNAVAILABLE' => 'FedEx did not return negotiated account rates for this shipment.',
            $code === 'TRANSPORT_ERROR', $httpStatus === null && $code === '' => 'Unable to reach FedEx right now. Please try again.',
            $code === 'OAUTH_FAILED', $code === 'MISSING_ACCESS_TOKEN', $httpStatus === 401 => 'FedEx authentication failed. Verify or reconnect your FedEx account.',
            $httpStatus === 403, str_contains($code, 'AUTHORIZATION'), str_contains($code, 'FORBIDDEN') => 'FedEx blocked this request for the connected account. Contact FedEx if the problem continues.',
            $httpStatus === 429, str_contains($code, 'RATE.LIMIT') => 'FedEx is temporarily limiting requests. Wait a moment and try again.',
            $httpStatus !== null && $httpStatus >= 500 => 'FedEx is temporarily unavailable. Please try again.',
            $code === 'ORIGIN_NOT_READY' => $upstream !== '' ? $upstream : 'Ship-from address is not ready for FedEx.',
            $code === 'MISSING_ACCOUNT_NUMBER' => 'FedEx account number is required before continuing.',
            $code === 'MISSING_ADDRESS' => 'Enter a complete address before continuing.',
            $upstream !== '' && strlen($upstream) <= 240 && ! self::looksSensitive($upstream) => $upstream,
            default => 'FedEx could not complete this request. Review the details and try again.',
        };
    }

    private static function looksSensitive(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'bearer ')
            || str_contains($lower, 'client_secret')
            || str_contains($lower, 'customer_password')
            || str_contains($lower, 'access_token')
            || preg_match('/\b\d{9}\b/', $message) === 1;
    }
}
