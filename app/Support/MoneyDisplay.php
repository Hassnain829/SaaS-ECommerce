<?php

namespace App\Support;

/**
 * Currency display without the PHP intl extension (Number::currency requires intl).
 */
final class MoneyDisplay
{
    /**
     * @param  float|string|int|null  $amount
     */
    public static function format($amount, string $currency = 'USD'): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $code = strtoupper(trim($currency) !== '' ? $currency : 'USD');

        return match ($code) {
            'USD' => '$'.number_format($value, 2),
            'EUR' => number_format($value, 2, ',', '.').' €',
            'GBP' => '£'.number_format($value, 2),
            default => number_format($value, 2).' '.$code,
        };
    }
}
