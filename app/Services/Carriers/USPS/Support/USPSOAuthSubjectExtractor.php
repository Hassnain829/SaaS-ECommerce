<?php

namespace App\Services\Carriers\USPS\Support;

/**
 * Extracts the USPS OAuth merchant subject identifier (sub / sub_id).
 *
 * Never treat CRID, MID, or EPA as OAuth subject identifiers.
 */
final class USPSOAuthSubjectExtractor
{
    /**
     * @param  array<string, mixed>  $tokenResponse
     */
    public function extractFromTokenResponse(array $tokenResponse): ?string
    {
        $subject = $this->extractFromPayload($tokenResponse);

        if ($subject !== null) {
            return $subject;
        }

        $idToken = (string) ($tokenResponse['id_token'] ?? '');

        return $this->extractFromIdToken($idToken);
    }

    /**
     * @param  array<string, mixed>  $userinfo
     */
    public function extractFromUserInfo(array $userinfo): ?string
    {
        return $this->extractFromPayload($userinfo);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractFromPayload(array $payload): ?string
    {
        foreach (['sub', 'sub_id', 'subject'] as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) || is_numeric($value)) {
                $normalized = trim((string) $value);

                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function extractFromIdToken(string $idToken): ?string
    {
        $idToken = trim($idToken);

        if ($idToken === '') {
            return null;
        }

        $parts = explode('.', $idToken);

        if (count($parts) < 2) {
            return null;
        }

        $payloadSegment = strtr($parts[1], '-_', '+/');
        $padding = strlen($payloadSegment) % 4;

        if ($padding > 0) {
            $payloadSegment .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($payloadSegment, true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $this->extractFromPayload($payload) : null;
    }
}
