<?php

namespace App\Support;

final class ConnectedSiteEventSignature
{
    public static function sign(string $secret, string $timestamp, string $eventId, string $rawBody): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.$rawBody, $secret);
    }

    public static function verify(string $secret, string $timestamp, string $eventId, string $rawBody, string $header): bool
    {
        $header = trim($header);
        if ($header === '' || $secret === '') {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $eventId, $rawBody), $header);
    }
}
