<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Short-lived public catalog cache only. Checkout, confirmation, health, and
 * Stripe fields must never be stored here.
 */
final class Eco_Portal_Catalog_Cache
{
    public const TTL = 90;

    public const OPTION_VERSION = 'eco_portal_catalog_version';

    public const OPTION_GENERATION = 'eco_portal_cache_generation';

    public const OPTION_LAST_EVENT = 'eco_portal_last_event_id';

    public const OPTION_LAST_REBUILD = 'eco_portal_last_rebuild_at';

    public const OPTION_LAST_RECONCILE = 'eco_portal_last_reconcile_at';

    public const OPTION_SEEN_EVENTS = 'eco_portal_seen_event_ids';

    public static function get(string $bucket, string $key)
    {
        $cached = get_transient(self::transient_key($bucket, $key));

        return $cached === false ? null : $cached;
    }

    public static function put(string $bucket, string $key, mixed $value): void
    {
        if (! in_array($bucket, ['catalog', 'product', 'categories'], true)) {
            return;
        }

        set_transient(self::transient_key($bucket, $key), $value, self::TTL);
    }

    public static function bump(): int
    {
        $generation = (int) get_option(self::OPTION_GENERATION, 1) + 1;
        update_option(self::OPTION_GENERATION, $generation, false);

        return $generation;
    }

    public static function invalidate(?int $product_id = null, ?int $category_id = null): void
    {
        self::bump();
        if ($product_id !== null && $product_id > 0) {
            delete_transient(self::transient_key('product', (string) $product_id, (int) get_option(self::OPTION_GENERATION, 1) - 1));
        }
        unset($category_id);
    }

    public static function rebuild(): array
    {
        self::bump();
        $client = new Eco_Portal_Api_Client();
        $catalog = $client->get_catalog(['page' => 1, 'per_page' => 15], true);
        $version = '';
        if ($catalog['ok'] && is_array($catalog['data']['meta'] ?? null)) {
            $version = (string) ($catalog['data']['meta']['catalog_version'] ?? '');
        }
        if ($version === '') {
            $health = $client->get_health();
            $version = is_array($health['data']) ? (string) ($health['data']['catalog_version'] ?? '') : '';
        }
        self::remember_version($version);
        $rebuilt_at = gmdate('c');
        update_option(self::OPTION_LAST_REBUILD, $rebuilt_at, false);
        update_option(self::OPTION_LAST_RECONCILE, $rebuilt_at, false);

        return [
            'ok' => (bool) $catalog['ok'],
            'version' => $version,
            'catalog' => $catalog,
            'message' => $catalog['ok'] ? 'Catalog cache rebuilt from the merchant portal.' : ($catalog['message'] ?: 'Could not rebuild the catalog cache.'),
        ];
    }

    public static function reconcile(): void
    {
        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return;
        }

        $health = $client->get_health();
        $portal_version = is_array($health['data']) ? (string) ($health['data']['catalog_version'] ?? '') : '';
        $local_version = (string) get_option(self::OPTION_VERSION, '');
        $after = (string) get_option(self::OPTION_LAST_EVENT, '');
        $events = $client->get_catalog_events($after);
        if ($events['ok'] && is_array($events['data']['data'] ?? null)) {
            foreach ($events['data']['data'] as $event) {
                if (is_array($event)) {
                    Eco_Portal_Events::apply_event($event, true);
                }
            }
        }

        if ($portal_version !== '' && $portal_version !== $local_version) {
            self::bump();
            self::remember_version($portal_version);
        }

        update_option(self::OPTION_LAST_RECONCILE, gmdate('c'), false);
        $client->report_diagnostics(self::diagnostics_payload());
    }

    public static function remember_version(string $version): void
    {
        if ($version === '') {
            return;
        }
        update_option(self::OPTION_VERSION, $version, false);
    }

    public static function remember_event_id(string $event_id): void
    {
        if ($event_id === '') {
            return;
        }
        update_option(self::OPTION_LAST_EVENT, $event_id, false);
    }

    public static function snapshot(): array
    {
        return [
            'version' => (string) get_option(self::OPTION_VERSION, ''),
            'last_event_id' => (string) get_option(self::OPTION_LAST_EVENT, ''),
            'last_rebuild_at' => (string) get_option(self::OPTION_LAST_REBUILD, ''),
            'last_reconcile_at' => (string) get_option(self::OPTION_LAST_RECONCILE, ''),
        ];
    }

    public static function diagnostics_payload(): array
    {
        $conflicts = class_exists('Eco_Portal_Conflicts') ? Eco_Portal_Conflicts::report() : [
            'production_ready' => true,
            'conflicts' => [],
        ];

        return [
            'production_ready' => ! empty($conflicts['production_ready']),
            'conflicts' => $conflicts['conflicts'] ?? [],
            'catalog_cache' => self::snapshot(),
        ];
    }

    private static function transient_key(string $bucket, string $key, ?int $generation = null): string
    {
        $generation = $generation ?? (int) get_option(self::OPTION_GENERATION, 1);

        return 'eco_pc_'.$bucket.'_'.$generation.'_'.md5($key);
    }
}
