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
                return trim((string) $value);
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
        $token = (string) get_option('eco_portal_token', '');
        $test_message = isset($_GET['eco_test']) ? sanitize_text_field(wp_unslash((string) $_GET['eco_test'])) : '';
        $test_ok = isset($_GET['eco_ok']) && (string) $_GET['eco_ok'] === '1';

        $catalog_page = get_page_by_path('portal-shop');
        $cart_page = get_page_by_path('portal-cart');
        $checkout_page = get_page_by_path('portal-checkout');
        ?>
        <div class="wrap">
            <h1>Eco Portal Connector</h1>
            <p>Connect this WordPress site to your Eco Commerce merchant portal for integration testing.</p>

            <?php if ($test_message !== '') : ?>
                <div class="notice notice-<?php echo $test_ok ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($test_message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('eco_portal_connector'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="eco_portal_base_url">Portal base URL</label></th>
                        <td>
                            <input
                                name="eco_portal_base_url"
                                id="eco_portal_base_url"
                                type="url"
                                class="regular-text"
                                value="<?php echo esc_attr($base_url); ?>"
                                placeholder="https://your-portal.example.com"
                                required
                            />
                            <p class="description">
                                Public URL of the Laravel portal (no trailing slash). Example:
                                <code>https://portal.yourdomain.com</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="eco_portal_token">Developer storefront token</label></th>
                        <td>
                            <input
                                name="eco_portal_token"
                                id="eco_portal_token"
                                type="password"
                                class="regular-text"
                                value="<?php echo esc_attr($token); ?>"
                                autocomplete="off"
                                required
                            />
                            <p class="description">
                                From the merchant dashboard: <strong>Settings → Developer storefront</strong>.
                                Generate a token and paste it here. It is stored in WordPress and used only on the server.
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
            </ul>

            <h2>How orders flow</h2>
            <ol>
                <li>This site loads products from <code>GET /api/developer-storefront/catalog</code>.</li>
                <li>Checkout posts paid orders to <code>POST /api/v1/external/orders</code>.</li>
                <li>Orders appear in the merchant portal with source <code>external_checkout</code>.</li>
            </ol>
            <p><em>This is a test connector, not the final production WordPress/WooCommerce plugin.</em></p>
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
        $result = $client->get_catalog();

        if ($result['ok'] && is_array($result['data'])) {
            $store_name = (string) ($result['data']['store']['name'] ?? 'store');
            $count = is_array($result['data']['products'] ?? null) ? count($result['data']['products']) : 0;
            $message = "Connected to \"{$store_name}\" ({$count} products).";
            $ok = '1';
        } else {
            $message = $result['message'] !== '' ? $result['message'] : 'Connection failed.';
            $ok = '0';
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'eco-portal-connector',
            'eco_test' => $message,
            'eco_ok' => $ok,
        ], admin_url('options-general.php')));
        exit;
    }
}
