<?php

declare(strict_types=1);

if (! defined('ABSPATH') && ! defined('ECO_PORTAL_CONNECTOR_TESTING')) {
    exit;
}

/**
 * Pure state transitions for one logical WordPress checkout attempt.
 * Persistence remains in the HttpOnly-cookie-bound WordPress transient.
 */
final class Eco_Portal_Checkout_Attempt
{
    public const ERROR_EXPIRED = 1001;

    public const ERROR_CHANGED = 1002;

    /**
     * @param  array<string, mixed>  $state
     * @param  callable(): string  $key_factory
     * @param  callable(): string  $token_factory
     * @return array<string, mixed>
     */
    public static function ensure(array $state, callable $key_factory, callable $token_factory, int $created_at): array
    {
        $key = trim((string) ($state['idempotency_key'] ?? ''));
        $token = trim((string) ($state['attempt_token'] ?? ''));

        if ($key !== '' && $token !== '') {
            return $state;
        }

        if ((int) ($state['checkout_id'] ?? 0) > 0) {
            throw new RuntimeException('An existing checkout is missing its attempt identity.', self::ERROR_EXPIRED);
        }

        $state['step'] = (string) ($state['step'] ?? 'address');
        $state['idempotency_key'] = $key !== '' ? $key : trim((string) $key_factory());
        $state['attempt_token'] = $token !== '' ? $token : trim((string) $token_factory());
        $state['attempt_created_at'] = (int) ($state['attempt_created_at'] ?? $created_at);

        if ($state['idempotency_key'] === '' || $state['attempt_token'] === '') {
            throw new RuntimeException('Checkout attempt identity could not be created.', self::ERROR_EXPIRED);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function begin(array $state, string $posted_token, string $request_fingerprint): array
    {
        $key = trim((string) ($state['idempotency_key'] ?? ''));
        $token = trim((string) ($state['attempt_token'] ?? ''));
        $posted_token = trim($posted_token);

        if ($key === '' || $token === '' || $posted_token === '' || ! hash_equals($token, $posted_token)) {
            throw new RuntimeException('The rendered checkout attempt is no longer available.', self::ERROR_EXPIRED);
        }

        $existing_fingerprint = trim((string) ($state['request_fingerprint'] ?? ''));
        if ($existing_fingerprint !== '' && ! hash_equals($existing_fingerprint, $request_fingerprint)) {
            throw new RuntimeException('Checkout details changed after this attempt was submitted.', self::ERROR_CHANGED);
        }

        $state['step'] = 'starting';
        $state['request_fingerprint'] = $request_fingerprint;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function idempotency_key(array $state): string
    {
        return trim((string) ($state['idempotency_key'] ?? ''));
    }
}
