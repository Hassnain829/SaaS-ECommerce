<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Merchant-readable presentation for product detail workspace (custom fields, import extras).
 */
final class ProductDetailPresenter
{
    /**
     * @param array<string, mixed>|null $associative
     * @return list<array{label: string, value_display: string}>
     */
    public static function associativeRows(?array $associative): array
    {
        if ($associative === null || $associative === []) {
            return [];
        }

        $rows = [];
        foreach ($associative as $key => $value) {
            if (ProductCustomFieldHelper::isEmptyStoredValue($value)) {
                continue;
            }
            $display = self::stringifyValue($value);
            if (trim($display) === '') {
                continue;
            }
            $rows[] = [
                'label' => self::humanizeKey((string) $key),
                'value_display' => $display,
            ];
        }

        return $rows;
    }

    public static function isLong(string $value): bool
    {
        return strlen($value) > 140;
    }

    public static function humanizeKey(string $key): string
    {
        $key = str_replace(['_', '-'], ' ', $key);

        return Str::title(trim($key));
    }

    public static function formatScalarForDisplay(mixed $value): string
    {
        return trim(self::stringifyValue($value));
    }

    private static function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return is_float($value)
                ? rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.')
                : (string) $value;
        }

        if (is_array($value)) {
            if ($value === []) {
                return '';
            }
            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                $parts = [];
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $parts[] = trim((string) $item);
                    }
                }
                $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));

                return $parts === [] ? '' : implode(', ', $parts);
            }

            $lines = [];
            foreach ($value as $k => $v) {
                if (ProductCustomFieldHelper::isEmptyStoredValue($v)) {
                    continue;
                }
                $lines[] = self::humanizeKey((string) $k).': '.self::stringifyValue($v);
            }

            return implode("\n", $lines);
        }

        return (string) $value;
    }

    /**
     * Same as {@see associativeRows} but keeps the original import/meta key for workspace actions.
     *
     * @return list<array{raw_key: string, label: string, value_display: string}>
     */
    public static function associativeRowsWithRawKeys(?array $associative): array
    {
        if ($associative === null || $associative === []) {
            return [];
        }

        $rows = [];
        foreach ($associative as $key => $value) {
            if (ProductCustomFieldHelper::isEmptyStoredValue($value)) {
                continue;
            }
            $display = self::stringifyValue($value);
            if (trim($display) === '') {
                continue;
            }
            $rows[] = [
                'raw_key' => (string) $key,
                'label' => self::humanizeKey((string) $key),
                'value_display' => $display,
            ];
        }

        return $rows;
    }
}
