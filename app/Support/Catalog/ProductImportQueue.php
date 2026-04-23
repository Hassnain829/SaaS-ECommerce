<?php

namespace App\Support\Catalog;

/**
 * Resolves which queue connection catalog import jobs use.
 *
 * Order (matches product requirements):
 * 1. PRODUCT_IMPORT_QUEUE_CONNECTION when set to a non-empty string in config
 * 2. Otherwise Laravel's queue.default (from QUEUE_CONNECTION in .env)
 * 3. Otherwise "database"
 *
 * This is evaluated at runtime from config keys so it tracks queue.default
 * after AppServiceProvider merges values (and avoids relying on a single
 * pre-baked product_import.queue_connection when config was cached under
 * different .env values).
 */
final class ProductImportQueue
{
    public static function connection(): string
    {
        $explicit = config('product_import.explicit_queue_connection');
        if (is_string($explicit)) {
            $t = trim($explicit);
            if ($t !== '') {
                return $t;
            }
        }

        $default = config('queue.default', 'database');
        if (! is_string($default)) {
            return 'database';
        }

        $t = trim($default);

        return $t === '' ? 'database' : $t;
    }

    public static function runsInline(): bool
    {
        return self::connection() === 'sync';
    }

    /**
     * @return array<string, mixed>
     */
    public static function diagnostics(): array
    {
        return [
            'queue.default' => config('queue.default'),
            'product_import.explicit_queue_connection' => config('product_import.explicit_queue_connection'),
            'product_import.queue_connection_resolved' => self::connection(),
            'runs_inline' => self::runsInline(),
        ];
    }
}
