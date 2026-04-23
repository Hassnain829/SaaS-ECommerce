<?php

namespace App\Support\Catalog;

/**
 * Maps internal validation / system messages to plain-language copy for merchants.
 * Technical strings still appear in logs; DB may store either form until re-saved.
 */
final class ProductImportMerchantMessages
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        'Mapping must include product name.' => 'Your import setup is missing the product name column. Go back to mapping and connect a column to Product name.',
        'Mapping must include SKU.' => 'Your import setup is missing the SKU column. Go back to mapping and connect a column to SKU.',
        'Product name is empty.' => 'This row is missing a product name. Add a name in your file for this product.',
        'SKU is empty.' => 'This row is missing a SKU (product code). Every product needs a unique SKU.',
        'Base price is not a valid number.' => 'The price on this row is not a valid number. Use a plain number (for example 19.99).',
        'Base price cannot be negative.' => 'The price on this row cannot be negative.',
        'Stock must be a whole number (spreadsheet formats like 1,200 or 10.0 are accepted).' => 'Stock must be a whole number. Remove letters or symbols from the quantity.',
        'Low stock threshold must be a whole number.' => 'Low stock alert must be a whole number.',
        'Compare-at price is not a valid number.' => 'Compare-at price is not a valid number.',
        'Cost price is not a valid number.' => 'Cost price is not a valid number.',
        'Duplicate SKU in import file.' => 'This SKU appears more than once in your file. Each SKU must be unique in a single import.',
        'This row could not be retried because saved row data was incomplete.' => 'This row could not be retried because saved data was incomplete. Run a new import from your file instead.',
    ];

    public static function humanizeRowError(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'This row could not be imported. Check the values and try again.';
        }

        if (isset(self::MAP[$message])) {
            return self::MAP[$message];
        }

        if (preg_match('/^Custom field (.+) value is too long\.$/', $message, $m)) {
            return 'The value for “'.$m[1].'” is too long. Shorten the text and try again.';
        }

        if (str_contains($message, 'max_allowed_packet') || str_contains($message, 'Got a packet bigger')) {
            return 'This row contained more data than the database could accept in one request. Very long text or image lists are trimmed when stored for debugging; shorten those cells in your file or ask your host to raise max_allowed_packet if needed.';
        }

        if (str_contains($message, 'SQLSTATE') || str_contains($message, 'Integrity constraint')) {
            return 'This row could not be saved because of a data conflict. Check for duplicate SKUs or invalid values.';
        }

        return self::truncateForStorage($message, 800);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function humanizeRowErrors(array $errors): string
    {
        $out = [];
        foreach ($errors as $e) {
            if (! is_string($e) || trim($e) === '') {
                continue;
            }
            $out[] = self::humanizeRowError($e);
        }

        return $out === [] ? self::humanizeRowError('') : implode(' ', array_unique($out));
    }

    public static function humanizeException(\Throwable $e): string
    {
        $raw = self::truncateForStorage($e->getMessage(), 2000);

        return self::humanizeRowError($raw);
    }

    /**
     * Short strings safe for product_import_rows.error_message and product_imports.failure_message.
     */
    public static function truncateForStorage(?string $message, int $maxChars = 2000): string
    {
        if ($message === null) {
            return '';
        }
        $message = trim($message);
        if ($message === '') {
            return '';
        }
        if (mb_strlen($message) <= $maxChars) {
            return $message;
        }

        return rtrim(mb_substr($message, 0, max(1, $maxChars - 16))).'… [truncated]';
    }

    /**
     * @param  list<array{row:int, message:string}>  $failures
     * @return list<array{row:int, message:string}>
     */
    public static function truncateFailureList(array $failures, int $messageMax = 1200): array
    {
        $out = [];
        foreach ($failures as $f) {
            if (! is_array($f) || ! isset($f['row'], $f['message'])) {
                continue;
            }
            $out[] = [
                'row' => (int) $f['row'],
                'message' => self::truncateForStorage((string) $f['message'], $messageMax),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $cells
     * @param  array<string, string>  $columnMapping
     */
    public static function describeRowForMerchant(array $headers, array $cells, array $columnMapping): string
    {
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $cells[$i] ?? '';
        }
        $nameKey = $columnMapping[\App\Catalog\ProductImportField::PRODUCT_NAME] ?? null;
        $skuKey = $columnMapping[\App\Catalog\ProductImportField::SKU] ?? null;
        $name = is_string($nameKey) ? trim((string) ($row[$nameKey] ?? '')) : '';
        $sku = is_string($skuKey) ? trim((string) ($row[$skuKey] ?? '')) : '';
        if ($name !== '' && $sku !== '') {
            return $name.' — SKU: '.$sku;
        }
        if ($sku !== '') {
            return 'SKU: '.$sku;
        }
        if ($name !== '') {
            return $name;
        }

        return 'Row in your file';
    }
}
