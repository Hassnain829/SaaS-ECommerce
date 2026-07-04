<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Storefront
{
    private const CART_COOKIE = 'eco_portal_cart';

    public static function init(): void
    {
        add_shortcode('eco_portal_catalog', [self::class, 'render_catalog']);
        add_shortcode('eco_portal_cart', [self::class, 'render_cart']);
        add_shortcode('eco_portal_checkout', [self::class, 'render_checkout']);

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_post_nopriv_eco_portal_add_to_cart', [self::class, 'handle_add_to_cart']);
        add_action('admin_post_eco_portal_add_to_cart', [self::class, 'handle_add_to_cart']);
        add_action('admin_post_nopriv_eco_portal_update_cart', [self::class, 'handle_update_cart']);
        add_action('admin_post_eco_portal_update_cart', [self::class, 'handle_update_cart']);
        add_action('admin_post_nopriv_eco_portal_place_order', [self::class, 'handle_place_order']);
        add_action('admin_post_eco_portal_place_order', [self::class, 'handle_place_order']);
    }

    public static function ensure_pages(): void
    {
        $pages = [
            'portal-shop' => [
                'title' => 'Portal Shop',
                'content' => '[eco_portal_catalog]',
            ],
            'portal-cart' => [
                'title' => 'Portal Cart',
                'content' => '[eco_portal_cart]',
            ],
            'portal-checkout' => [
                'title' => 'Portal Checkout',
                'content' => '[eco_portal_checkout]',
            ],
        ];

        foreach ($pages as $slug => $page) {
            $existing = get_page_by_path($slug);
            if ($existing) {
                continue;
            }

            wp_insert_post([
                'post_title' => $page['title'],
                'post_name' => $slug,
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
        }
    }

    public static function enqueue_assets(): void
    {
        if (! is_singular('page')) {
            return;
        }

        global $post;
        if (! $post instanceof WP_Post) {
            return;
        }

        $content = (string) $post->post_content;
        if (
            ! has_shortcode($content, 'eco_portal_catalog')
            && ! has_shortcode($content, 'eco_portal_cart')
            && ! has_shortcode($content, 'eco_portal_checkout')
        ) {
            return;
        }

        wp_enqueue_style(
            'eco-portal-storefront',
            ECO_PORTAL_CONNECTOR_URL.'assets/css/storefront.css',
            [],
            ECO_PORTAL_CONNECTOR_VERSION
        );
    }

    public static function render_catalog(): string
    {
        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return self::notice('Configure the portal connection under Settings → Eco Portal.', 'error');
        }

        $result = $client->get_catalog();
        if (! $result['ok'] || ! is_array($result['data'])) {
            return self::notice('Could not load catalog: '.($result['message'] ?: 'unknown error'), 'error');
        }

        $store = is_array($result['data']['store'] ?? null) ? $result['data']['store'] : [];
        $products = is_array($result['data']['products'] ?? null) ? $result['data']['products'] : [];
        $cart_url = self::page_url('portal-cart');
        $currency = (string) ($store['currency'] ?? 'USD');

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/catalog.php';

        return (string) ob_get_clean();
    }

    public static function render_cart(): string
    {
        $cart = self::get_cart();
        $checkout_url = self::page_url('portal-checkout');
        $shop_url = self::page_url('portal-shop');

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/cart.php';

        return (string) ob_get_clean();
    }

    public static function render_checkout(): string
    {
        $cart = self::get_cart();
        $shop_url = self::page_url('portal-shop');
        $order_result = null;
        $error = '';

        if (isset($_GET['eco_order'])) {
            $token = sanitize_text_field((string) wp_unslash($_GET['eco_order']));
            $stored = get_transient('eco_portal_order_'.$token);
            if (is_array($stored)) {
                $order_result = $stored;
                delete_transient('eco_portal_order_'.$token);
            }
        }

        if (isset($_GET['eco_error'])) {
            $error = sanitize_text_field((string) wp_unslash($_GET['eco_error']));
        }

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/checkout.php';

        return (string) ob_get_clean();
    }

    public static function handle_add_to_cart(): void
    {
        check_admin_referer('eco_portal_add_to_cart');

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $name = sanitize_text_field((string) ($_POST['product_name'] ?? 'Product'));
        $variant_label = sanitize_text_field((string) ($_POST['variant_label'] ?? 'Default'));
        $unit_price = self::money((string) ($_POST['unit_price'] ?? '0'));
        $sku = sanitize_text_field((string) ($_POST['sku'] ?? ''));

        if ($product_id < 1 || $variant_id < 1) {
            wp_safe_redirect(self::page_url('portal-shop'));
            exit;
        }

        $cart = self::get_cart();
        $key = $product_id.'-'.$variant_id;

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $quantity;
        } else {
            $cart[$key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'product_name' => $name,
                'variant_label' => $variant_label,
                'unit_price' => $unit_price,
                'sku' => $sku,
                'quantity' => $quantity,
            ];
        }

        self::save_cart($cart);
        wp_safe_redirect(self::page_url('portal-cart'));
        exit;
    }

    public static function handle_update_cart(): void
    {
        check_admin_referer('eco_portal_update_cart');

        $action = sanitize_text_field((string) ($_POST['cart_action'] ?? 'update'));
        $cart = self::get_cart();

        if ($action === 'clear') {
            self::save_cart([]);
            wp_safe_redirect(self::page_url('portal-cart'));
            exit;
        }

        $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
        foreach ($quantities as $key => $qty) {
            $key = sanitize_text_field((string) $key);
            if (! isset($cart[$key])) {
                continue;
            }
            $qty = (int) $qty;
            if ($qty < 1) {
                unset($cart[$key]);
            } else {
                $cart[$key]['quantity'] = min(999, $qty);
            }
        }

        self::save_cart($cart);
        wp_safe_redirect(self::page_url('portal-cart'));
        exit;
    }

    public static function handle_place_order(): void
    {
        check_admin_referer('eco_portal_place_order');

        $cart = self::get_cart();
        if ($cart === []) {
            self::redirect_checkout_error('Cart is empty.');
        }

        $customer_name = sanitize_text_field((string) ($_POST['customer_name'] ?? ''));
        $customer_email = sanitize_email((string) ($_POST['customer_email'] ?? ''));
        $customer_phone = sanitize_text_field((string) ($_POST['customer_phone'] ?? ''));
        $address_line1 = sanitize_text_field((string) ($_POST['address_line1'] ?? ''));
        $city = sanitize_text_field((string) ($_POST['city'] ?? ''));
        $state = sanitize_text_field((string) ($_POST['state'] ?? ''));
        $postal_code = sanitize_text_field((string) ($_POST['postal_code'] ?? ''));
        $country = sanitize_text_field((string) ($_POST['country'] ?? ''));
        $shipping_total = self::money((string) ($_POST['shipping_total'] ?? '0'));
        $tax_total = self::money((string) ($_POST['tax_total'] ?? '0'));
        $discount_total = self::money((string) ($_POST['discount_total'] ?? '0'));
        $payment_status = sanitize_text_field((string) ($_POST['payment_status'] ?? 'paid'));

        if ($customer_name === '' || $customer_email === '' || $address_line1 === '' || $city === '' || $postal_code === '' || $country === '') {
            self::redirect_checkout_error('Fill in customer name, email, and full shipping address.');
        }

        $allowed_payment = ['paid', 'pending', 'cod_pending', 'bank_transfer_pending'];
        if (! in_array($payment_status, $allowed_payment, true)) {
            $payment_status = 'paid';
        }

        $items = [];
        $subtotal = '0.00';
        $line_index = 1;
        foreach ($cart as $line) {
            $qty = (int) ($line['quantity'] ?? 1);
            $unit = self::money((string) ($line['unit_price'] ?? '0'));
            $line_total = self::money((string) ((float) $unit * $qty));
            $subtotal = self::money((string) ((float) $subtotal + (float) $line_total));

            $items[] = [
                'variant_id' => (int) $line['variant_id'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'external_line_id' => 'wp-line-'.$line_index,
            ];
            $line_index++;
        }

        $external_order_id = 'wp-'.wp_generate_uuid4();
        $external_order_number = 'WP-'.strtoupper(wp_generate_password(8, false, false));
        $idempotency_key = 'wp-order-'.$external_order_id;

        $payload = [
            'external_order_id' => $external_order_id,
            'external_order_number' => $external_order_number,
            'external_checkout_reference' => 'wp-checkout-'.$external_order_id,
            'payment_status' => $payment_status,
            'payment_gateway' => 'wordpress_test',
            'payment_method' => 'test_checkout',
            'payment_reference' => 'wp-pay-'.$external_order_id,
            'placed_at' => gmdate('c'),
            'currency_code' => sanitize_text_field((string) ($_POST['currency_code'] ?? 'USD')),
            'shipping_total' => $shipping_total,
            'tax_total' => $tax_total,
            'discount_total' => $discount_total,
            'notes' => 'Synced from sample WordPress site (Eco Portal Connector).',
            'customer' => [
                'full_name' => $customer_name,
                'email' => $customer_email,
                'phone' => $customer_phone !== '' ? $customer_phone : null,
            ],
            'shipping_address' => [
                'name' => $customer_name,
                'address_line1' => $address_line1,
                'city' => $city,
                'state' => $state !== '' ? $state : null,
                'postal_code' => $postal_code,
                'country' => $country,
                'phone' => $customer_phone !== '' ? $customer_phone : null,
            ],
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => $items,
        ];

        $client = new Eco_Portal_Api_Client();
        $result = $client->sync_external_order($payload, $idempotency_key);

        if (! $result['ok'] || ! is_array($result['data']['order'] ?? null)) {
            self::redirect_checkout_error($result['message'] !== '' ? $result['message'] : 'Order sync failed.');
        }

        self::save_cart([]);

        $order = $result['data']['order'];
        $token = wp_generate_password(20, false, false);
        set_transient('eco_portal_order_'.$token, [
            'portal_order_number' => $order['order_number'] ?? '',
            'portal_order_id' => $order['id'] ?? '',
            'external_order_number' => $order['external_order_number'] ?? $external_order_number,
            'external_order_id' => $order['external_order_id'] ?? $external_order_id,
            'total' => $order['total'] ?? $order['grand_total'] ?? '',
            'payment_status' => $order['payment_status'] ?? $payment_status,
            'status' => $order['status'] ?? '',
        ], 10 * MINUTE_IN_SECONDS);

        wp_safe_redirect(add_query_arg('eco_order', $token, self::page_url('portal-checkout')));
        exit;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function get_cart(): array
    {
        if (! isset($_COOKIE[self::CART_COOKIE])) {
            return [];
        }

        $raw = wp_unslash((string) $_COOKIE[self::CART_COOKIE]);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $cart
     */
    private static function save_cart(array $cart): void
    {
        $json = (string) wp_json_encode($cart);
        setcookie(self::CART_COOKIE, $json, [
            'expires' => time() + WEEK_IN_SECONDS,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::CART_COOKIE] = $json;
    }

    private static function page_url(string $slug): string
    {
        $page = get_page_by_path($slug);

        return $page ? (string) get_permalink($page) : home_url('/');
    }

    private static function money(string $value): string
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        if ($number < 0) {
            $number = 0.0;
        }

        return number_format($number, 2, '.', '');
    }

    private static function notice(string $message, string $type = 'info'): string
    {
        return '<div class="eco-portal-notice eco-portal-notice--'.$type.'">'.esc_html($message).'</div>';
    }

    private static function redirect_checkout_error(string $message): void
    {
        wp_safe_redirect(add_query_arg('eco_error', $message, self::page_url('portal-checkout')));
        exit;
    }

    public static function cart_subtotal(array $cart): string
    {
        $total = 0.0;
        foreach ($cart as $line) {
            $total += ((float) ($line['unit_price'] ?? 0)) * ((int) ($line['quantity'] ?? 1));
        }

        return number_format($total, 2, '.', '');
    }

    public static function variant_label(array $variant): string
    {
        $options = is_array($variant['options'] ?? null) ? $variant['options'] : [];
        if ($options === []) {
            return 'Default';
        }

        $parts = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $type = (string) ($option['type'] ?? 'Option');
            $value = (string) ($option['value'] ?? '');
            $parts[] = $type.': '.$value;
        }

        return $parts === [] ? 'Default' : implode(', ', $parts);
    }
}
