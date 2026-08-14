<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_eco_portal_test_connection', [self::class, 'handle_test_connection']);
        add_action('admin_post_eco_portal_rebuild_catalog', [self::class, 'handle_rebuild_catalog']);
    }

    public static function register_menu(): void
    {
        add_options_page(
            'Eco Portal Connector',
            'Eco Portal',
            'manage_options',
            'eco-portal-connector',
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('eco_portal_connector', 'eco_portal_base_url', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = esc_url_raw(trim((string) $value));

                return rtrim($value, '/');
            },
            'default' => '',
        ]);

        register_setting('eco_portal_connector', 'eco_portal_token', [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = trim((string) $value);
                if ($value === '') {
                    return trim((string) get_option('eco_portal_token', ''));
                }

                return $value;
            },
            'default' => '',
        ]);
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $base_url = (string) get_option('eco_portal_base_url', '');
        $has_token = trim((string) get_option('eco_portal_token', '')) !== '';
        $test_message = isset($_GET['eco_test']) ? sanitize_text_field(wp_unslash((string) $_GET['eco_test'])) : '';
        $test_ok = isset($_GET['eco_ok']) && (string) $_GET['eco_ok'] === '1';
        $last_health = get_option('eco_portal_last_health', []);
        if (! is_array($last_health)) {
            $last_health = [];
        }

        $catalog_page = get_page_by_path('portal-shop');
        $cart_page = get_page_by_path('portal-cart');
        $checkout_page = get_page_by_path('portal-checkout');
        $order_page = get_page_by_path('portal-order');
        $conflicts = Eco_Portal_Conflicts::report();
        $production_ready = ! empty($conflicts['production_ready'])
            && ! empty($last_health['ok'])
            && ! empty($last_health['stripe']);
        $cache = Eco_Portal_Catalog_Cache::snapshot();
        ?>
        <div class="wrap">
            <h1>Eco Portal Connector</h1>
            <p>Sell products from your merchant portal on this WordPress site. You do not need WooCommerce. This plugin never turns other plugins off.</p>

            <?php if (! $production_ready) : ?>
                <div class="notice notice-warning">
                    <p><strong>This website is not ready for live shoppers.</strong> You can still test Portal Shop while you follow the steps below. Eco Portal will not deactivate anything for you.</p>
                </div>
            <?php endif; ?>

            <?php if ($test_message !== '') : ?>
                <div class="notice notice-<?php echo $test_ok ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($test_message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('eco_portal_connector'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="eco_portal_base_url">Portal website address</label></th>
                        <td>
                            <input
                                name="eco_portal_base_url"
                                id="eco_portal_base_url"
                                type="url"
                                class="regular-text"
                                value="<?php echo esc_attr($base_url); ?>"
                                placeholder="http://127.0.0.1:8000"
                                required
                            />
                            <p class="description">
                                Address of your merchant portal, with no trailing slash. Local example:
                                <code>http://127.0.0.1:8000</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="eco_portal_token">Connection key</label></th>
                        <td>
                            <input
                                name="eco_portal_token"
                                id="eco_portal_token"
                                type="password"
                                class="regular-text"
                                value=""
                                autocomplete="new-password"
                                <?php echo $has_token ? '' : 'required'; ?>
                                placeholder="<?php echo $has_token ? 'A key is already saved on this server' : ''; ?>"
                            />
                            <p class="description">
                                From the merchant portal: <strong>Website → Connect your website</strong>.
                                <?php if ($has_token) : ?>
                                    A connection key is already saved on this WordPress server. Leave this blank to keep it, or paste a new key to replace it.
                                <?php else : ?>
                                    Create a connection key and paste it here. It stays on this WordPress server and is never shown to shoppers.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save connection'); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="eco_portal_test_connection" />
                <?php wp_nonce_field('eco_portal_test_connection'); ?>
                <?php submit_button('Test connection', 'secondary', 'submit', false); ?>
            </form>

            <h2>Product list cache</h2>
            <p>This site keeps a short copy of public products and categories from the merchant portal. Checkout, orders, and payment stay live from the portal and are not cached here.</p>
            <ul>
                <li>Portal product list version: <?php echo $cache['version'] !== '' ? esc_html($cache['version']) : 'Not stored yet'; ?></li>
                <li>Last catalog event: <?php echo $cache['last_event_id'] !== '' ? esc_html($cache['last_event_id']) : 'None yet'; ?></li>
                <li>Last automatic check: <?php echo $cache['last_reconcile_at'] !== '' ? esc_html($cache['last_reconcile_at']) : 'Not run yet'; ?></li>
                <li>Last full rebuild: <?php echo $cache['last_rebuild_at'] !== '' ? esc_html($cache['last_rebuild_at']) : 'Not run yet'; ?></li>
            </ul>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="eco_portal_rebuild_catalog" />
                <?php wp_nonce_field('eco_portal_rebuild_catalog'); ?>
                <?php submit_button('Rebuild catalog cache', 'secondary', 'submit', false); ?>
            </form>

            <h2>Live-shopper readiness</h2>
            <ul>
                <li>Portal connection: <?php echo ! empty($last_health['ok']) ? 'Connected' : 'Not connected — save the key and click Test connection'; ?></li>
                <li>Stripe in the merchant portal: <?php echo ! empty($last_health['stripe']) ? 'Ready' : 'Not connected — open Payments in the merchant portal'; ?></li>
                <li>Fulfillment location: <?php echo ! empty($last_health['location']) ? 'Ready' : 'Missing — add a location in the merchant portal'; ?></li>
                <li>Catalog: <?php echo ! empty($last_health['catalog']) ? 'Products available' : 'Empty'; ?></li>
                <li>WordPress conflicts: <?php echo empty($conflicts['conflicts']) ? 'None found' : 'Needs attention'; ?></li>
            </ul>
            <?php if (! empty($conflicts['conflicts'])) : ?>
                <ol>
                    <?php foreach ($conflicts['conflicts'] as $conflict) : ?>
                        <li>
                            <strong><?php echo esc_html((string) ($conflict['title'] ?? 'Conflict')); ?></strong>
                            — <?php echo esc_html((string) ($conflict['instruction'] ?? '')); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($last_health !== []) : ?>
                <h2>Last connection check</h2>
                <ul>
                    <li>Store: <?php echo esc_html((string) ($last_health['store_name'] ?? 'Unknown')); ?></li>
                    <li>Website address: <?php echo self::match_label($last_health['url_match'] ?? null); ?></li>
                    <li>Plugin version: <?php echo esc_html((string) ($last_health['plugin_version'] ?? ECO_PORTAL_CONNECTOR_VERSION)); ?></li>
                    <li>Product list version: <?php echo esc_html((string) ($last_health['catalog_version'] ?? 'Not stored yet')); ?></li>
                </ul>
                <?php if (! empty($last_health['messages']) && is_array($last_health['messages'])) : ?>
                    <ul>
                        <?php foreach ($last_health['messages'] as $message) : ?>
                            <li><?php echo esc_html((string) $message); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <hr />
            <h2>Storefront pages</h2>
            <ul>
                <li>
                    Shop:
                    <?php if ($catalog_page) : ?>
                        <a href="<?php echo esc_url(get_permalink($catalog_page)); ?>" target="_blank" rel="noopener">Portal Shop</a>
                    <?php else : ?>
                        missing — deactivate/reactivate the plugin
                    <?php endif; ?>
                </li>
                <li>
                    Cart:
                    <?php if ($cart_page) : ?>
                        <a href="<?php echo esc_url(get_permalink($cart_page)); ?>" target="_blank" rel="noopener">Portal Cart</a>
                    <?php else : ?>
                        missing
                    <?php endif; ?>
                </li>
                <li>
                    Checkout:
                    <?php if ($checkout_page) : ?>
                        <a href="<?php echo esc_url(get_permalink($checkout_page)); ?>" target="_blank" rel="noopener">Portal Checkout</a>
                    <?php else : ?>
                        missing
                    <?php endif; ?>
                </li>
                <li>
                    Order status:
                    <?php if ($order_page) : ?>
                        <a href="<?php echo esc_url(get_permalink($order_page)); ?>" target="_blank" rel="noopener">Portal order status</a>
                    <?php else : ?>
                        missing — deactivate/reactivate the plugin
                    <?php endif; ?>
                </li>
            </ul>

            <h2>How this works</h2>
            <ol>
                <li>This site loads products from your merchant portal.</li>
                <li>Checkout loads delivery rates and Stripe payment from the portal.</li>
                <li>When payment succeeds, the order, customer, and stock activity appear in the merchant portal. Shipping labels stay there.</li>
            </ol>
        </div>
        <?php
    }

    public static function handle_test_connection(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer('eco_portal_test_connection');

        $client = new Eco_Portal_Api_Client();
        $config = $client->get_event_config();
        if ($config['ok'] && is_array($config['data']) && ! empty($config['data']['signing_secret'])) {
            update_option('eco_portal_event_secret', (string) $config['data']['signing_secret'], false);
        }

        $rebuild = Eco_Portal_Catalog_Cache::rebuild();
        $diagnostics = Eco_Portal_Catalog_Cache::diagnostics_payload();
        $health = $client->report_diagnostics($diagnostics);
        if (! $health['ok']) {
            $health = $client->get_health();
        }
        $catalog = is_array($rebuild['catalog'] ?? null) ? $rebuild['catalog'] : $client->get_catalog([], true);

        $ok = $health['ok'] && $catalog['ok'];
        $health_data = is_array($health['data']) ? $health['data'] : [];
        $catalog_data = is_array($catalog['data']) ? $catalog['data'] : [];
        $store = is_array($health_data['store'] ?? null) ? $health_data['store'] : (is_array($catalog_data['store'] ?? null) ? $catalog_data['store'] : []);
        $store_name = (string) ($store['name'] ?? 'store');
        $currency = Eco_Portal_Storefront::remember_store_currency((string) ($store['currency'] ?? ''));
        $count = (int) ($catalog_data['meta']['total'] ?? (is_array($catalog_data['products'] ?? null) ? count($catalog_data['products']) : 0));
        $readiness = is_array($health_data['readiness'] ?? null) ? $health_data['readiness'] : [];
        $site = is_array($health_data['site'] ?? null) ? $health_data['site'] : [];
        $plugin = is_array($health_data['plugin'] ?? null) ? $health_data['plugin'] : [];
        $messages = is_array($health_data['messages'] ?? null) ? $health_data['messages'] : [];

        update_option('eco_portal_last_health', [
            'ok' => $ok,
            'store_name' => $store_name,
            'url_match' => $site['url_match'] ?? null,
            'stripe' => ! empty($readiness['stripe']),
            'location' => ! empty($readiness['location']),
            'catalog' => ! empty($readiness['catalog']),
            'production_ready' => ! empty($readiness['production']),
            'conflicts' => $diagnostics['conflicts'],
            'plugin_version' => $plugin['reported_version'] ?? ECO_PORTAL_CONNECTOR_VERSION,
            'catalog_version' => $diagnostics['catalog_cache']['version'] ?? '',
            'messages' => $messages,
        ], false);

        if ($ok && ! empty($diagnostics['production_ready'])) {
            $url_label = self::match_plain($site['url_match'] ?? null);
            $message = "Connected to \"{$store_name}\" ({$count} products, {$currency}). Website address {$url_label}. Ready for live shoppers after Stripe and delivery are connected in the merchant portal.";
            $flag = '1';
        } elseif ($ok) {
            $message = "Connected to \"{$store_name}\", but this website is not ready for live shoppers. Follow the readiness steps on this page. Eco Portal did not deactivate any plugins.";
            $flag = '1';
        } else {
            $message = $health['message'] !== '' ? $health['message'] : ($catalog['message'] !== '' ? $catalog['message'] : 'Connection failed.');
            if ($messages !== []) {
                $message .= ' '.implode(' ', array_map('strval', $messages));
            }
            $flag = '0';
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'eco-portal-connector',
            'eco_test' => $message,
            'eco_ok' => $flag,
        ], admin_url('options-general.php')));
        exit;
    }

    public static function handle_rebuild_catalog(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }

        check_admin_referer('eco_portal_rebuild_catalog');

        $rebuild = Eco_Portal_Catalog_Cache::rebuild();
        $client = new Eco_Portal_Api_Client();
        $client->report_diagnostics(Eco_Portal_Catalog_Cache::diagnostics_payload());

        wp_safe_redirect(add_query_arg([
            'page' => 'eco-portal-connector',
            'eco_test' => $rebuild['message'],
            'eco_ok' => $rebuild['ok'] ? '1' : '0',
        ], admin_url('options-general.php')));
        exit;
    }

    private static function match_label(mixed $match): string
    {
        if ($match === true) {
            return 'Matches the portal';
        }
        if ($match === false) {
            return 'Does not match the portal';
        }

        return 'Not checked yet';
    }

    private static function match_plain(mixed $match): string
    {
        if ($match === true) {
            return 'matches';
        }
        if ($match === false) {
            return 'does not match';
        }

        return 'was not compared';
    }
}
