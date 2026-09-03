<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Section shortcodes for Elementor / page builders.
 * Same portal catalog, filtered into homepage slots by tag or product IDs.
 *
 * Examples:
 * [eco_portal_products tag="homepage-hero" layout="hero" limit="5"]
 * [eco_portal_products tag="featured" layout="cards" limit="6"]
 * [eco_portal_products ids="12,15,18" layout="cards"]
 * [eco_portal_products tag="top-seller" layout="topsellers" limit="2"]
 */
final class Eco_Portal_Sections
{
    public static function init(): void
    {
        add_shortcode('eco_portal_products', [self::class, 'render_products']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
    }

    public static function register_assets(): void
    {
        wp_register_style(
            'eco-portal-sections',
            ECO_PORTAL_CONNECTOR_URL.'assets/css/sections.css',
            [],
            ECO_PORTAL_CONNECTOR_VERSION
        );
    }

    /**
     * @param  array<string, mixed>|string  $atts
     */
    public static function render_products($atts = []): string
    {
        $atts = shortcode_atts([
            'tag' => '',
            'ids' => '',
            'layout' => 'cards',
            'limit' => '12',
            'title' => '',
            'empty' => 'No products match this section yet. Tag products in the merchant portal.',
        ], is_array($atts) ? $atts : [], 'eco_portal_products');

        $connection = Eco_Portal_Storefront::connection_state();
        if (! $connection['ok'] && in_array($connection['code'], ['not_configured', 'unauthorized', 'unreachable'], true)) {
            return Eco_Portal_Storefront::reconnect_notice($connection);
        }

        wp_enqueue_style('eco-portal-sections');
        if (wp_style_is('eco-portal-storefront', 'registered') || wp_style_is('eco-portal-storefront', 'enqueued')) {
            Eco_Portal_Templates::enqueue_appearance_css('eco-portal-storefront');
        } else {
            Eco_Portal_Templates::enqueue_appearance_css('eco-portal-sections');
        }

        $products = self::resolve_products(
            sanitize_text_field((string) $atts['tag']),
            sanitize_text_field((string) $atts['ids']),
            max(1, min(50, (int) $atts['limit']))
        );

        $layout = sanitize_key((string) $atts['layout']);
        if (! in_array($layout, ['cards', 'hero', 'topsellers', 'grid'], true)) {
            $layout = 'cards';
        }

        $title = sanitize_text_field((string) $atts['title']);
        $empty = sanitize_text_field((string) $atts['empty']);
        $currency = Eco_Portal_Storefront::store_currency();
        $cart_url = Eco_Portal_Storefront::page_url('portal-cart');

        return Eco_Portal_Templates::render('sections/'.$layout.'.php', compact(
            'products',
            'title',
            'empty',
            'currency',
            'cart_url'
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function resolve_products(string $tag, string $ids, int $limit): array
    {
        $idList = array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $ids) ?: [])));
        $client = new Eco_Portal_Api_Client();
        $result = $client->get_products([
            'per_page' => 50,
            'page' => 1,
        ]);

        if (! $result['ok'] || ! is_array($result['data'])) {
            return [];
        }

        $rows = is_array($result['data']['data'] ?? null)
            ? $result['data']['data']
            : (is_array($result['data']['products'] ?? null) ? $result['data']['products'] : []);

        if ($idList !== []) {
            $wanted = array_fill_keys($idList, true);
            $rows = array_values(array_filter($rows, static function ($row) use ($wanted): bool {
                return isset($wanted[(int) ($row['id'] ?? 0)]);
            }));
        } elseif ($tag !== '') {
            $needle = strtolower($tag);
            $filtered = array_values(array_filter($rows, static function ($row) use ($needle): bool {
                $tags = is_array($row['tags'] ?? null) ? $row['tags'] : [];
                foreach ($tags as $item) {
                    $slug = strtolower((string) ($item['slug'] ?? ''));
                    $name = strtolower((string) ($item['name'] ?? ''));
                    if ($slug === $needle || $name === $needle) {
                        return true;
                    }
                }

                return false;
            }));

            // Demo-friendly fallback: if no products carry the section tag yet, show the first published products.
            $rows = $filtered !== [] ? $filtered : $rows;
        }

        return array_slice($rows, 0, $limit);
    }
}
