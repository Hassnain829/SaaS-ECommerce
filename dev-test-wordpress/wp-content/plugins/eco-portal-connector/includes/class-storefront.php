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
        add_action('admin_post_nopriv_eco_portal_start_checkout', [self::class, 'handle_start_checkout']);
        add_action('admin_post_eco_portal_start_checkout', [self::class, 'handle_start_checkout']);
        add_action('admin_post_nopriv_eco_portal_select_shipping', [self::class, 'handle_select_shipping']);
        add_action('admin_post_eco_portal_select_shipping', [self::class, 'handle_select_shipping']);
        add_action('admin_post_nopriv_eco_portal_confirm_checkout', [self::class, 'handle_confirm_checkout']);
        add_action('admin_post_eco_portal_confirm_checkout', [self::class, 'handle_confirm_checkout']);
        add_action('admin_post_nopriv_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
        add_action('admin_post_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
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

        if (has_shortcode($content, 'eco_portal_checkout')) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            wp_enqueue_script(
                'eco-portal-checkout',
                ECO_PORTAL_CONNECTOR_URL.'assets/js/checkout.js',
                ['stripe-js'],
                ECO_PORTAL_CONNECTOR_VERSION,
                true
            );
        }
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
        $currency = self::remember_store_currency((string) ($store['currency'] ?? ''));
        self::remember_checkout_settings($store);

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/catalog.php';

        return (string) ob_get_clean();
    }

    public static function render_cart(): string
    {
        $cart = self::get_cart();
        $checkout_url = self::page_url('portal-checkout');
        $shop_url = self::page_url('portal-shop');
        $currency = self::store_currency();

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/cart.php';

        return (string) ob_get_clean();
    }

    public static function render_checkout(): string
    {
        $cart = self::get_cart();
        $shop_url = self::page_url('portal-shop');
        $currency = self::store_currency();
        $client = new Eco_Portal_Api_Client();
        if ($client->is_configured()) {
            $catalog = $client->get_catalog();
            if ($catalog['ok'] && is_array($catalog['data']['store'] ?? null)) {
                $currency = self::remember_store_currency((string) ($catalog['data']['store']['currency'] ?? ''));
                self::remember_checkout_settings($catalog['data']['store']);
            }
        }
        $checkout_mode = self::checkout_mode();
        $platform_ready = self::platform_ready();
        $checkout_state = self::checkout_state();
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
            'currency_code' => self::store_currency(),
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

    public static function handle_start_checkout(): void
    {
        check_admin_referer('eco_portal_start_checkout');

        $cart = self::get_cart();
        if ($cart === []) {
            self::redirect_checkout_error('Cart is empty.');
        }

        $fields = self::posted_customer_address();
        if ($fields['customer_name'] === '' || $fields['customer_email'] === '' || $fields['address_line1'] === '' || $fields['city'] === '' || $fields['country'] === '') {
            self::redirect_checkout_error('Fill in customer name, email, and a complete shipping address.');
        }

        $client = new Eco_Portal_Api_Client();
        $catalog = $client->get_catalog();
        if ($catalog['ok'] && is_array($catalog['data']['store'] ?? null)) {
            self::remember_store_currency((string) ($catalog['data']['store']['currency'] ?? ''));
            self::remember_checkout_settings($catalog['data']['store']);
        }

        if (self::checkout_mode() !== 'platform_checkout') {
            self::redirect_checkout_error('This store is set to website payment. Switch to platform checkout in the merchant portal Payments page to load delivery rates from this portal.');
        }

        $items = [];
        foreach ($cart as $line) {
            $items[] = [
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            ];
        }

        $address = self::shipping_address_payload($fields);
        $payload = [
            'source_channel' => 'wordpress_storefront',
            'currency_code' => self::store_currency(),
            'customer' => [
                'full_name' => $fields['customer_name'],
                'email' => $fields['customer_email'],
                'phone' => $fields['customer_phone'] !== '' ? $fields['customer_phone'] : null,
            ],
            'shipping_address' => $address,
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => $items,
        ];

        $created = $client->create_checkout($payload);
        if (! $created['ok'] || ! is_array($created['data']['checkout'] ?? null)) {
            self::redirect_checkout_error($created['message'] !== '' ? $created['message'] : 'Could not start platform checkout. Connect Stripe and enable platform checkout in the merchant portal.');
        }

        $checkout = $created['data']['checkout'];
        $checkout_id = (int) ($checkout['id'] ?? 0);
        $options_result = $client->delivery_options($checkout_id, [
            'shipping_address' => $address,
        ]);
        $options = is_array($options_result['data']['delivery_options'] ?? null)
            ? $options_result['data']['delivery_options']
            : [];

        $warning = '';
        if (! $options_result['ok']) {
            $warning = $options_result['message'] !== '' ? $options_result['message'] : 'Could not load delivery rates.';
        } elseif ($options === []) {
            $warning = 'No delivery methods matched this address. In the merchant portal, check Delivery zones, checkout-enabled methods, and origin locations.';
        }

        self::save_checkout_state([
            'step' => 'rates',
            'checkout_id' => $checkout_id,
            'checkout' => $checkout,
            'delivery_options' => $options,
            'payment' => is_array($created['data']['payment'] ?? null) ? $created['data']['payment'] : [],
            'address' => $fields,
            'warning' => $warning,
        ]);

        wp_safe_redirect(self::page_url('portal-checkout'));
        exit;
    }

    public static function handle_select_shipping(): void
    {
        check_admin_referer('eco_portal_select_shipping');

        $state = self::checkout_state();
        $checkout_id = (int) ($state['checkout_id'] ?? 0);
        $method_id = (int) ($_POST['shipping_method_id'] ?? 0);
        $pickup_id = (int) ($_POST['pickup_location_id'] ?? 0);
        if ($checkout_id < 1 || $method_id < 1) {
            self::redirect_checkout_error('Choose a delivery option from this portal.');
        }

        $fields = is_array($state['address'] ?? null) ? $state['address'] : self::posted_customer_address();
        $client = new Eco_Portal_Api_Client();
        $selected = $client->select_shipping_method($checkout_id, [
            'shipping_method_id' => $method_id,
            'pickup_location_id' => $pickup_id > 0 ? $pickup_id : null,
            'shipping_address' => self::shipping_address_payload($fields),
        ]);

        if (! $selected['ok'] || ! is_array($selected['data']['checkout'] ?? null)) {
            self::redirect_checkout_error($selected['message'] !== '' ? $selected['message'] : 'Could not save the delivery option.');
        }

        $payment = is_array($selected['data']['payment'] ?? null) ? $selected['data']['payment'] : [];
        if (($payment['publishable_key'] ?? '') === '' || ($payment['client_secret'] ?? '') === '') {
            self::redirect_checkout_error('Delivery was saved, but Stripe is not ready. Connect Stripe in the merchant portal Payments page.');
        }

        $state['step'] = 'pay';
        $state['checkout'] = $selected['data']['checkout'];
        $state['payment'] = $payment;
        $state['shipping_method_id'] = $method_id;
        $state['warning'] = '';
        self::save_checkout_state($state);

        wp_safe_redirect(self::page_url('portal-checkout'));
        exit;
    }

    public static function handle_confirm_checkout(): void
    {
        check_admin_referer('eco_portal_confirm_checkout');

        $state = self::checkout_state();
        $checkout_id = (int) ($state['checkout_id'] ?? 0);
        if ($checkout_id < 1) {
            self::redirect_checkout_error('Checkout expired. Start again.');
        }

        $client = new Eco_Portal_Api_Client();
        $result = $client->confirm_checkout($checkout_id);
        if (! $result['ok'] || ! is_array($result['data']['order'] ?? null)) {
            self::redirect_checkout_error($result['message'] !== '' ? $result['message'] : 'Payment was taken, but the portal has not created the order yet. Open Orders in the merchant portal.');
        }

        self::save_cart([]);
        self::clear_checkout_state();

        $order = $result['data']['order'];
        $token = wp_generate_password(20, false, false);
        set_transient('eco_portal_order_'.$token, [
            'portal_order_number' => $order['order_number'] ?? '',
            'portal_order_id' => $order['id'] ?? '',
            'total' => $order['total'] ?? '',
            'payment_status' => $order['payment_status'] ?? 'paid',
            'status' => $order['status'] ?? '',
            'mode' => 'platform',
        ], 10 * MINUTE_IN_SECONDS);

        wp_safe_redirect(add_query_arg('eco_order', $token, self::page_url('portal-checkout')));
        exit;
    }

    public static function handle_reset_checkout(): void
    {
        check_admin_referer('eco_portal_reset_checkout');
        self::clear_checkout_state();
        wp_safe_redirect(self::page_url('portal-checkout'));
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

    /**
     * @return array<string, mixed>
     */
    private static function checkout_state(): array
    {
        $sid = isset($_COOKIE['eco_portal_checkout_sid']) ? sanitize_text_field((string) wp_unslash($_COOKIE['eco_portal_checkout_sid'])) : '';
        if ($sid === '') {
            return [];
        }

        $state = get_transient('eco_portal_co_'.$sid);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function save_checkout_state(array $state): void
    {
        $sid = isset($_COOKIE['eco_portal_checkout_sid']) ? sanitize_text_field((string) wp_unslash($_COOKIE['eco_portal_checkout_sid'])) : '';
        if ($sid === '') {
            $sid = wp_generate_password(20, false, false);
            setcookie('eco_portal_checkout_sid', $sid, [
                'expires' => time() + HOUR_IN_SECONDS,
                'path' => COOKIEPATH ? COOKIEPATH : '/',
                'domain' => COOKIE_DOMAIN ?: '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE['eco_portal_checkout_sid'] = $sid;
        }

        set_transient('eco_portal_co_'.$sid, $state, HOUR_IN_SECONDS);
    }

    private static function clear_checkout_state(): void
    {
        $sid = isset($_COOKIE['eco_portal_checkout_sid']) ? sanitize_text_field((string) wp_unslash($_COOKIE['eco_portal_checkout_sid'])) : '';
        if ($sid !== '') {
            delete_transient('eco_portal_co_'.$sid);
        }
        setcookie('eco_portal_checkout_sid', '', [
            'expires' => time() - 3600,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['eco_portal_checkout_sid']);
    }

    /**
     * @return array{customer_name:string,customer_email:string,customer_phone:string,address_line1:string,city:string,state:string,postal_code:string,country:string,country_code:string}
     */
    private static function posted_customer_address(): array
    {
        $country = sanitize_text_field((string) ($_POST['country'] ?? ''));
        $country_code = self::resolve_country_code($country, (string) ($_POST['country_code'] ?? ''));

        return [
            'customer_name' => sanitize_text_field((string) ($_POST['customer_name'] ?? '')),
            'customer_email' => sanitize_email((string) ($_POST['customer_email'] ?? '')),
            'customer_phone' => sanitize_text_field((string) ($_POST['customer_phone'] ?? '')),
            'address_line1' => sanitize_text_field((string) ($_POST['address_line1'] ?? '')),
            'city' => sanitize_text_field((string) ($_POST['city'] ?? '')),
            'state' => sanitize_text_field((string) ($_POST['state'] ?? '')),
            'postal_code' => sanitize_text_field((string) ($_POST['postal_code'] ?? '')),
            'country' => $country,
            'country_code' => $country_code,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string|null>
     */
    private static function shipping_address_payload(array $fields): array
    {
        return [
            'name' => $fields['customer_name'] ?? '',
            'address_line1' => $fields['address_line1'] ?? '',
            'city' => $fields['city'] ?? '',
            'state' => ($fields['state'] ?? '') !== '' ? $fields['state'] : null,
            'postal_code' => ($fields['postal_code'] ?? '') !== '' ? $fields['postal_code'] : null,
            'country' => $fields['country'] ?? '',
            'country_code' => self::resolve_country_code(
                (string) ($fields['country'] ?? ''),
                (string) ($fields['country_code'] ?? '')
            ),
            'phone' => ($fields['customer_phone'] ?? '') !== '' ? $fields['customer_phone'] : null,
        ];
    }

    private static function resolve_country_code(string $country, string $posted_code = ''): string
    {
        $mapped = self::country_code($country);
        $posted_code = strtoupper(trim($posted_code));
        $raw_country = strtoupper(trim($country));

        if ($mapped !== '' && strlen($raw_country) > 2) {
            return $mapped;
        }

        if (preg_match('/^[A-Z]{2}$/', $posted_code) === 1) {
            return $posted_code;
        }

        return $mapped !== '' ? $mapped : 'US';
    }

    private static function country_code(string $country): string
    {
        $country = strtoupper(trim($country));
        if ($country === '') {
            return '';
        }

        if (preg_match('/\(([A-Z]{2})\)\s*$/', $country, $matches) === 1) {
            return $matches[1];
        }

        $map = [
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'USA' => 'US',
            'U.S.' => 'US',
            'U.S.A.' => 'US',
            'PAKISTAN' => 'PK',
            'CANADA' => 'CA',
            'UNITED KINGDOM' => 'GB',
            'UK' => 'GB',
        ];

        if (isset($map[$country])) {
            return $map[$country];
        }

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : '';
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

    public static function remember_store_currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return self::store_currency(false);
        }

        update_option('eco_portal_store_currency', $currency, false);

        return $currency;
    }

    public static function store_currency(bool $refresh_from_catalog = true): string
    {
        $currency = strtoupper(trim((string) get_option('eco_portal_store_currency', '')));
        if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
            return $currency;
        }

        if (! $refresh_from_catalog) {
            return 'USD';
        }

        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return 'USD';
        }

        $result = $client->get_catalog();
        $from_catalog = is_array($result['data']['store'] ?? null)
            ? strtoupper(trim((string) ($result['data']['store']['currency'] ?? '')))
            : '';

        if ($result['ok'] && preg_match('/^[A-Z]{3}$/', $from_catalog) === 1) {
            update_option('eco_portal_store_currency', $from_catalog, false);

            return $from_catalog;
        }

        return 'USD';
    }

    /**
     * @param  array<string, mixed>  $store
     */
    public static function remember_checkout_settings(array $store): void
    {
        $mode = (string) ($store['checkout_mode'] ?? 'external_checkout');
        if (! in_array($mode, ['external_checkout', 'platform_checkout'], true)) {
            $mode = 'external_checkout';
        }
        update_option('eco_portal_checkout_mode', $mode, false);
        update_option('eco_portal_platform_ready', ! empty($store['platform_checkout']['ready']) ? '1' : '0', false);
    }

    public static function checkout_mode(): string
    {
        $mode = (string) get_option('eco_portal_checkout_mode', 'external_checkout');

        return in_array($mode, ['external_checkout', 'platform_checkout'], true)
            ? $mode
            : 'external_checkout';
    }

    public static function platform_ready(): bool
    {
        return (string) get_option('eco_portal_platform_ready', '0') === '1';
    }

    public static function format_money(string $value, string $currency): string
    {
        return $currency.' '.self::money($value);
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
