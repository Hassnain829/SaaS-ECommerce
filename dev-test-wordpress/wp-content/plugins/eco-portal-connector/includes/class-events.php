<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Events
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_filter('cron_schedules', [self::class, 'schedules']);
        add_action('init', [self::class, 'ensure_cron']);
        add_action('eco_portal_reconcile_catalog', [Eco_Portal_Catalog_Cache::class, 'reconcile']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $schedules
     * @return array<string, array<string, mixed>>
     */
    public static function schedules(array $schedules): array
    {
        $schedules['eco_portal_five_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 minutes',
        ];

        return $schedules;
    }

    public static function ensure_cron(): void
    {
        if (! wp_next_scheduled('eco_portal_reconcile_catalog')) {
            wp_schedule_event(time() + 300, 'eco_portal_five_minutes', 'eco_portal_reconcile_catalog');
        }
    }

    public static function register_routes(): void
    {
        register_rest_route('eco-portal/v1', '/events', [
            'methods' => 'POST',
            'callback' => [self::class, 'receive'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function receive(\WP_REST_Request $request): \WP_REST_Response
    {
        $raw = $request->get_body();
        $event_id = trim((string) $request->get_header('x-eco-event-id'));
        $timestamp = trim((string) $request->get_header('x-eco-timestamp'));
        $signature = trim((string) $request->get_header('x-eco-signature'));
        $secret = trim((string) get_option('eco_portal_event_secret', ''));

        if ($secret === '' || $event_id === '' || $timestamp === '' || $signature === '') {
            return new \WP_REST_Response(['message' => 'Missing catalog event signature.'], 401);
        }

        if (! ctype_digit($timestamp)) {
            return new \WP_REST_Response(['message' => 'Invalid catalog event timestamp.'], 401);
        }

        $age = abs(time() - (int) $timestamp);
        if ($age > 300) {
            return new \WP_REST_Response(['message' => 'Catalog event timestamp is outside the allowed window.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$event_id.'.'.$raw, $secret);
        if (! hash_equals($expected, $signature)) {
            return new \WP_REST_Response(['message' => 'Catalog event signature is invalid.'], 401);
        }

        if (self::already_seen($event_id)) {
            return new \WP_REST_Response(['ok' => true, 'duplicate' => true], 200);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return new \WP_REST_Response(['message' => 'Catalog event body is invalid.'], 400);
        }

        self::mark_seen($event_id);
        self::apply_event($payload, false);

        return new \WP_REST_Response(['ok' => true, 'duplicate' => false], 200);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function apply_event(array $event, bool $from_poll): void
    {
        $event_id = trim((string) ($event['id'] ?? ''));
        if ($event_id !== '') {
            if ($from_poll && self::already_seen($event_id)) {
                return;
            }
            self::mark_seen($event_id);
            Eco_Portal_Catalog_Cache::remember_event_id($event_id);
        }

        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $product_id = isset($resource['product_id']) ? (int) $resource['product_id'] : null;
        $category_id = isset($resource['category_id']) ? (int) $resource['category_id'] : null;
        Eco_Portal_Catalog_Cache::invalidate($product_id ?: null, $category_id ?: null);

        $version = trim((string) ($event['catalog_version'] ?? ''));
        Eco_Portal_Catalog_Cache::remember_version($version);
    }

    public static function already_seen(string $event_id): bool
    {
        $seen = get_option(Eco_Portal_Catalog_Cache::OPTION_SEEN_EVENTS, []);
        if (! is_array($seen)) {
            $seen = [];
        }

        return isset($seen[$event_id]);
    }

    public static function mark_seen(string $event_id): void
    {
        $seen = get_option(Eco_Portal_Catalog_Cache::OPTION_SEEN_EVENTS, []);
        if (! is_array($seen)) {
            $seen = [];
        }
        $seen[$event_id] = time();
        $cutoff = time() - 604800;
        foreach ($seen as $id => $at) {
            if ((int) $at < $cutoff) {
                unset($seen[$id]);
            }
        }
        if (count($seen) > 500) {
            asort($seen);
            $seen = array_slice($seen, -500, 500, true);
        }
        update_option(Eco_Portal_Catalog_Cache::OPTION_SEEN_EVENTS, $seen, false);
    }
}
