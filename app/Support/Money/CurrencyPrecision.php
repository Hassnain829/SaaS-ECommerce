<?php

namespace App\Support\Money;

use InvalidArgumentException;

final class CurrencyPrecision
{
    /** @var list<string> */
    private const ZERO_DECIMAL_CURRENCIES = [
        'bif',
        'clp',
        'djf',
        'gnf',
        'jpy',
        'kmf',
        'krw',
        'mga',
        'pyg',
        'rwf',
        'ugx',
        'vnd',
        'vuv',
        'xaf',
        'xof',
        'xpf',
    ];

    public static function exponent(string $currencyCode): int
    {
        return self::isZeroDecimal($currencyCode) ? 0 : 2;
    }

    public static function scale(string $currencyCode): int
    {
        return self::exponent($currencyCode);
    }

    /**
     * @return list<string>
     */
    public static function zeroDecimalCurrencies(): array
    {
        return self::ZERO_DECIMAL_CURRENCIES;
    }

    public static function isZeroDecimal(string $currencyCode): bool
    {
        return in_array(self::normalizeCurrencyCode($currencyCode), self::ZERO_DECIMAL_CURRENCIES, true);
    }

    public static function roundMajor(string|int|float $amount, string $currencyCode): string
    {
        $scale = self::scale($currencyCode);
        $value = self::normalizeAmount($amount);

        if ($scale === 0) {
            if (bccomp($value, '0', 16) >= 0) {
                return bcadd($value, '0.5', 0);
            }

            return bcsub($value, '0.5', 0);
        }

        $factor = bcpow('10', (string) $scale, 0);
        $scaled = bcmul($value, $factor, $scale + 2);

        if (bccomp($value, '0', 16) >= 0) {
            $roundedScaled = bcadd($scaled, '0.5', 0);
        } else {
            $roundedScaled = bcsub($scaled, '0.5', 0);
        }

        return bcdiv($roundedScaled, $factor, $scale);
    }

    public static function toMinorUnits(string|int|float $majorAmount, string $currencyCode): int
    {
        $rounded = self::roundMajor($majorAmount, $currencyCode);
        $exponent = self::exponent($currencyCode);
        $factor = bcpow('10', (string) $exponent, 0);
        $minorString = bcmul($rounded, $factor, 0);

        if (
            bccomp($minorString, (string) PHP_INT_MAX, 0) > 0
            || bccomp($minorString, (string) PHP_INT_MIN, 0) < 0
        ) {
            throw new InvalidArgumentException('Amount overflows minor-unit conversion.');
        }

        return (int) $minorString;
    }

    public static function fromMinorUnits(int $minorAmount, string $currencyCode): string
    {
        self::normalizeCurrencyCode($currencyCode);
        $exponent = self::exponent($currencyCode);

        if ($exponent === 0) {
            return (string) $minorAmount;
        }

        $factor = bcpow('10', (string) $exponent, 0);

        return bcdiv((string) $minorAmount, $factor, $exponent);
    }

    private static function normalizeCurrencyCode(string $currencyCode): string
    {
        $normalized = strtolower(trim($currencyCode));

        if ($normalized === '') {
            throw new InvalidArgumentException('Currency code is required.');
        }

        return $normalized;
    }

    private static function normalizeAmount(string|int|float $amount): string
    {
        return DecimalString::normalizeSigned($amount);
    }
}
