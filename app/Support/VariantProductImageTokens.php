<?php

namespace App\Support;

/**
 * Parse merchant-submitted variant photo tokens (numeric id, new:N, existing:path).
 */
final class VariantProductImageTokens
{
    public const TOKEN_PATTERN = '/^(?:[1-9]\d*|new:\d+|existing:.+)$/';

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function collectRaw(array $row): array
    {
        $tokens = [];
        if (isset($row['product_image_ids']) && is_array($row['product_image_ids'])) {
            foreach ($row['product_image_ids'] as $value) {
                $token = self::normalizeRaw($value);
                if ($token !== null) {
                    $tokens[] = $token;
                }
            }
        }

        if ($tokens === []) {
            $single = self::normalizeRaw($row['product_image_id'] ?? null);
            if ($single !== null) {
                $tokens[] = $single;
            }
        }

        $seen = [];
        $unique = [];
        foreach ($tokens as $token) {
            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;
            $unique[] = $token;
        }

        return $unique;
    }

    public static function normalizeRaw(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '' || str_contains($raw, '..')) {
            return null;
        }

        if (preg_match(self::TOKEN_PATTERN, $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    /**
     * @param  list<string>  $tokens
     * @return array{ok: bool, error: string|null, parsed: list<array{kind: string, id?: int, index?: int, path?: string}>}
     */
    public static function parse(array $tokens, int $rowIndex): array
    {
        $parsed = [];
        foreach ($tokens as $token) {
            if (str_starts_with($token, 'new:')) {
                $index = substr($token, 4);
                if ($index === '' || ! ctype_digit($index)) {
                    return [
                        'ok' => false,
                        'error' => sprintf('Variant %d has an invalid newly uploaded image.', $rowIndex + 1),
                        'parsed' => [],
                    ];
                }
                $parsed[] = ['kind' => 'new', 'index' => (int) $index];

                continue;
            }

            if (str_starts_with($token, 'existing:')) {
                $path = str_replace('\\', '/', substr($token, strlen('existing:')));
                if ($path === '' || str_contains($path, '..')) {
                    return [
                        'ok' => false,
                        'error' => sprintf('Variant %d has an invalid catalog image.', $rowIndex + 1),
                        'parsed' => [],
                    ];
                }
                $parsed[] = ['kind' => 'existing', 'path' => $path];

                continue;
            }

            $id = (int) $token;
            if ($id < 1) {
                return [
                    'ok' => false,
                    'error' => sprintf('Variant %d has an invalid catalog image.', $rowIndex + 1),
                    'parsed' => [],
                ];
            }
            $parsed[] = ['kind' => 'id', 'id' => $id];
        }

        return ['ok' => true, 'error' => null, 'parsed' => $parsed];
    }
}
