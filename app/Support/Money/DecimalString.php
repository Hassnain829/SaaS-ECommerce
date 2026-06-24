<?php

namespace App\Support\Money;

use InvalidArgumentException;

final class DecimalString
{
    private const PLAIN_DECIMAL_PATTERN = '/^(?:\+|-)?(?:\d+\.?\d*|\.\d+)$/';

    public static function normalizeSigned(string|int|float $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('Amount must be a valid decimal number.');
            }

            return self::normalizeSigned(rtrim(rtrim(sprintf('%.12F', $amount), '0'), '.'));
        }

        $trimmed = trim((string) $amount);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Amount must be a valid decimal number.');
        }

        if (stripos($trimmed, 'e') !== false) {
            throw new InvalidArgumentException('Amount must be a valid decimal number.');
        }

        if (! preg_match(self::PLAIN_DECIMAL_PATTERN, $trimmed)) {
            throw new InvalidArgumentException('Amount must be a valid decimal number.');
        }

        return self::canonicalize($trimmed);
    }

    public static function normalizeNonNegative(
        string|int|float $amount,
        string $message = 'Amount must be a non-negative decimal number.',
    ): string {
        $normalized = self::normalizeSigned($amount);

        if (bccomp($normalized, '0', 12) < 0) {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function canonicalize(string $value): string
    {
        if (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        if (str_starts_with($value, '.')) {
            $value = '0'.$value;
        }

        return $value;
    }
}
