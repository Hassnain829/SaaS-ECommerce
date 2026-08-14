<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Storefront
{
    private const CART_COOKIE = 'eco_portal_cart';
    private const ORDERS_COOKIE = 'eco_portal_orders';

    public static function init(): void
    {
        add_shortcode('eco_portal_catalog', [self::class, 'render_catalog']);
        add_shortcode('eco_portal_product', [self::class, 'render_product_shortcode']);
        add_shortcode('eco_portal_cart', [self::class, 'render_cart']);
        add_shortcode('eco_portal_checkout', [self::class, 'render_checkout']);
        add_shortcode('eco_portal_order', [self::class, 'render_order']);

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('template_redirect', [self::class, 'send_nocache_headers']);
        add_action('admin_post_nopriv_eco_portal_add_to_cart', [self::class, 'handle_add_to_cart']);
        add_action('admin_post_eco_portal_add_to_cart', [self::class, 'handle_add_to_cart']);
        add_action('admin_post_nopriv_eco_portal_update_cart', [self::class, 'handle_update_cart']);
        add_action('admin_post_eco_portal_update_cart', [self::class, 'handle_update_cart']);
        add_action('admin_post_nopriv_eco_portal_start_checkout', [self::class, 'handle_start_checkout']);
        add_action('admin_post_eco_portal_start_checkout', [self::class, 'handle_start_checkout']);
        add_action('admin_post_nopriv_eco_portal_select_shipping', [self::class, 'handle_select_shipping']);
        add_action('admin_post_eco_portal_select_shipping', [self::class, 'handle_select_shipping']);
        add_action('admin_post_nopriv_eco_portal_confirm_checkout', [self::class, 'handle_confirm_checkout']);
        add_action('admin_post_eco_portal_confirm_checkout', [self::class, 'handle_confirm_checkout']);
        add_action('admin_post_nopriv_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
        add_action('admin_post_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
        add_action('admin_post_nopriv_eco_portal_lookup_order', [self::class, 'handle_lookup_order']);
        add_action('admin_post_eco_portal_lookup_order', [self::class, 'handle_lookup_order']);
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
            'portal-order' => [
                'title' => 'Portal order status',
                'content' => '[eco_portal_order]',
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

    public static function send_nocache_headers(): void
    {
        if (! self::is_commerce_page()) {
            return;
        }

        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }

    public static function enqueue_assets(): void
    {
        if (! self::is_commerce_page()) {
            return;
        }

        wp_enqueue_style(
            'eco-portal-storefront',
            ECO_PORTAL_CONNECTOR_URL.'assets/css/storefront.css',
            [],
            ECO_PORTAL_CONNECTOR_VERSION
        );

        global $post;
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
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
        $product_id = (int) ($_GET['eco_product'] ?? 0);
        if ($product_id > 0) {
            return self::render_product($product_id);
        }

        $connection = self::connection_state();
        if (! $connection['ok'] && in_array($connection['code'], ['not_configured', 'unauthorized', 'unreachable'], true)) {
            return self::reconnect_notice($connection);
        }

        $client = new Eco_Portal_Api_Client();
        $page = max(1, (int) ($_GET['eco_page'] ?? 1));
        $category = sanitize_text_field((string) ($_GET['eco_category'] ?? ''));
        $result = $client->get_catalog([
            'page' => $page,
            'per_page' => 15,
            'category' => $category,
        ]);
        if (! $result['ok'] || ! is_array($result['data'])) {
            return self::reconnect_notice([
                'ok' => false,
                'code' => 'catalog_failed',
                'message' => 'Could not load catalog: '.($result['message'] ?: 'unknown error'),
                'reconnect' => $result['status'] === 401,
            ]);
        }

        $store = is_array($result['data']['store'] ?? null) ? $result['data']['store'] : [];
        $products = is_array($result['data']['products'] ?? null) ? $result['data']['products'] : [];
        $categories = is_array($result['data']['categories'] ?? null) ? $result['data']['categories'] : [];
        $catalog_meta = is_array($result['data']['meta'] ?? null) ? $result['data']['meta'] : [];
        $active_category = $category;
        $cart_url = self::page_url('portal-cart');
        $currency = self::remember_store_currency((string) ($store['currency'] ?? ''));
        self::remember_checkout_settings($store);

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/catalog.php';

        return (string) ob_get_clean();
    }

    public static function render_product_shortcode($atts = []): string
    {
        $atts = is_array($atts) ? $atts : [];
        $id = (int) ($atts['id'] ?? ($_GET['eco_product'] ?? 0));

        return $id > 0 ? self::render_product($id) : self::notice('Add a product id to this page, or open a product from Portal Shop.', 'info');
    }

    public static function render_product(int $product_id): string
    {
        $connection = self::connection_state();
        if (! $connection['ok'] && in_array($connection['code'], ['not_configured', 'unauthorized', 'unreachable'], true)) {
            return self::reconnect_notice($connection);
        }

        $client = new Eco_Portal_Api_Client();
        $result = $client->get_product($product_id);
        if (! $result['ok'] || ! is_array($result['data']['data'] ?? null)) {
            return self::notice('This product is not available from the merchant portal.', 'info');
        }

        $product = $result['data']['data'];
        $store = is_array($result['data']['meta']['store'] ?? null) ? $result['data']['meta']['store'] : [];
        $cart_url = self::page_url('portal-cart');
        $shop_url = self::page_url('portal-shop');
        $currency = self::remember_store_currency((string) ($store['currency'] ?? self::store_currency(false)));

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/product.php';

        return (string) ob_get_clean();
    }

    public static function render_cart(): string
    {
        $connection = self::connection_state();
        $cart = self::hydrate_cart();
        $checkout_url = self::page_url('portal-checkout');
        $shop_url = self::page_url('portal-shop');
        $currency = self::store_currency();
        $catalog_subtotal = self::cart_subtotal($cart);

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/cart.php';

        return (string) ob_get_clean();
    }

    public static function render_checkout(): string
    {
        $connection = self::connection_state();
        $cart = self::hydrate_cart();
        $shop_url = self::page_url('portal-shop');
        $order_url = self::page_url('portal-order');
        $currency = self::store_currency();
        $checkout_mode = 'platform_checkout';
        $platform_ready = ! empty($connection['stripe']);
        $checkout_blocked = ! $connection['ok'] || ! $platform_ready;
        $checkout_state = self::checkout_state();
        $order_result = null;
        $error = '';
        $conflict_notice = self::storefront_conflict_notice();

        if (isset($_GET['eco_error'])) {
            $error = sanitize_text_field((string) wp_unslash($_GET['eco_error']));
        }

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/checkout.php';

        return (string) ob_get_clean();
    }

    public static function render_order(): string
    {
        $connection = self::connection_state();
        $order_result = null;
        $error = '';
        $token = sanitize_text_field((string) wp_unslash($_GET['eco_confirm'] ?? ''));
        if ($token === '') {
            $token = sanitize_text_field((string) wp_unslash($_GET['eco_order'] ?? ''));
        }

        if ($token !== '' && ($connection['ok'] || $connection['code'] === 'stripe')) {
            $client = new Eco_Portal_Api_Client();
            $live = $client->get_order_confirmation($token);
            if ($live['ok'] && is_array($live['data']['order'] ?? null)) {
                $order_result = $live['data']['order'];
                $order_result['confirmation_token'] = $token;
            } else {
                $error = $live['status'] === 401
                    ? 'The connection key is missing or was removed. Create a new key in the merchant portal: Website → Connect your website.'
                    : 'No order was found for that confirmation code.';
            }
        }

        $recent = self::recent_order_tokens();
        $shop_url = self::page_url('portal-shop');

        ob_start();
        include ECO_PORTAL_CONNECTOR_PATH.'templates/order.php';

        return (string) ob_get_clean();
    }

    public static function handle_add_to_cart(): void
    {
        check_admin_referer('eco_portal_add_to_cart');

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $variant_id = (int) ($_POST['variant_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        if ($product_id < 1 || $variant_id < 1) {
            wp_safe_redirect(self::page_url('portal-shop'));
            exit;
        }

        $cart = self::intent_cart();
        $key = $product_id.'-'.$variant_id;
        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $quantity;
        } else {
            $cart[$key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
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
        $cart = self::intent_cart();

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
                $cart[$key]['product_id'] = (int) ($cart[$key]['product_id'] ?? 0);
                $cart[$key]['variant_id'] = (int) ($cart[$key]['variant_id'] ?? 0);
            }
        }

        self::save_cart($cart);
        wp_safe_redirect(self::page_url('portal-cart'));
        exit;
    }

    public static function handle_start_checkout(): void
    {
        check_admin_referer('eco_portal_start_checkout');

        $connection = self::connection_state();
        if (! $connection['ok']) {
            self::redirect_checkout_error($connection['message']);
        }
        if (empty($connection['stripe'])) {
            self::redirect_checkout_error('Checkout is blocked until Stripe is connected in the merchant portal Payments page. This site will not take payment locally.');
        }

        $cart = self::intent_cart();
        if ($cart === []) {
            self::redirect_checkout_error('Cart is empty.');
        }

        $fields = self::posted_customer_address();
        if ($fields['customer_name'] === '' || $fields['customer_email'] === '' || $fields['address_line1'] === '' || $fields['city'] === '' || $fields['country'] === '') {
            self::redirect_checkout_error('Fill in customer name, email, and a complete shipping address.');
        }

        $client = new Eco_Portal_Api_Client();
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
        if ($created['status'] === 401) {
            self::redirect_checkout_error('The connection key is missing or was removed. Create a new key in the merchant portal: Website → Connect your website.');
        }
        if (! $created['ok'] || ! is_array($created['data']['checkout'] ?? null)) {
            self::redirect_checkout_error($created['message'] !== '' ? $created['message'] : 'Could not start platform checkout. Connect Stripe in the merchant portal.');
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

        $connection = self::connection_state();
        if (! $connection['ok'] || empty($connection['stripe'])) {
            self::redirect_checkout_error($connection['ok']
                ? 'Checkout is blocked until Stripe is connected in the merchant portal Payments page.'
                : $connection['message']);
        }

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

        if ($selected['status'] === 401) {
            self::redirect_checkout_error('The connection key is missing or was removed. Create a new key in the merchant portal: Website → Connect your website.');
        }
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

        $connection = self::connection_state();
        if (! $connection['ok']) {
            self::redirect_checkout_error($connection['message']);
        }

        $state = self::checkout_state();
        $checkout_id = (int) ($state['checkout_id'] ?? 0);
        if ($checkout_id < 1) {
            self::redirect_checkout_error('Checkout expired. Start again.');
        }

        $client = new Eco_Portal_Api_Client();
        $result = $client->confirm_checkout($checkout_id);
        if ($result['status'] === 401) {
            self::redirect_checkout_error('The connection key is missing or was removed. Create a new key in the merchant portal: Website → Connect your website.');
        }
        if (! $result['ok'] || ! is_array($result['data']['order'] ?? null)) {
            self::redirect_checkout_error($result['message'] !== '' ? $result['message'] : 'Payment was taken, but the portal has not created the order yet. Open Orders in the merchant portal.');
        }

        self::save_cart([]);
        self::clear_checkout_state();

        $order = $result['data']['order'];
        $confirmation_token = (string) ($order['confirmation_token'] ?? '');
        if ($confirmation_token !== '') {
            self::remember_order_token($confirmation_token);
        }

        wp_safe_redirect(add_query_arg(
            'eco_confirm',
            $confirmation_token !== '' ? $confirmation_token : (string) ($order['order_number'] ?? ''),
            self::page_url('portal-order')
        ));
        exit;
    }

    public static function handle_reset_checkout(): void
    {
        check_admin_referer('eco_portal_reset_checkout');
        self::clear_checkout_state();
        wp_safe_redirect(self::page_url('portal-checkout'));
        exit;
    }

    public static function handle_lookup_order(): void
    {
        check_admin_referer('eco_portal_lookup_order');
        $token = sanitize_text_field((string) ($_POST['confirmation_token'] ?? ''));
        wp_safe_redirect(add_query_arg('eco_confirm', $token, self::page_url('portal-order')));
        exit;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function intent_cart(): array
    {
        $raw = self::get_cart_raw();
        $intent = [];
        foreach ($raw as $key => $line) {
            if (! is_array($line)) {
                continue;
            }
            $product_id = (int) ($line['product_id'] ?? 0);
            $variant_id = (int) ($line['variant_id'] ?? 0);
            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            if ($product_id < 1 || $variant_id < 1) {
                continue;
            }
            $intent[(string) $key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => $quantity,
            ];
        }

        return $intent;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function hydrate_cart(): array
    {
        $intent = self::intent_cart();
        if ($intent === []) {
            return [];
        }

        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return $intent;
        }

        $hydrated = [];
        foreach ($intent as $key => $line) {
            $product_id = (int) $line['product_id'];
            $variant_id = (int) $line['variant_id'];
            $result = $client->get_product($product_id);
            $product = is_array($result['data']['data'] ?? null) ? $result['data']['data'] : [];
            $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
            $variant = null;
            foreach ($variants as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) === $variant_id) {
                    $variant = $row;
                    break;
                }
            }

            $hydrated[$key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => (int) $line['quantity'],
                'product_name' => (string) ($product['name'] ?? 'Product'),
                'variant_label' => $variant ? self::variant_label($variant) : 'Unavailable',
                'unit_price' => (string) ($variant['price'] ?? ''),
                'sku' => (string) ($variant['sku'] ?? ''),
                'stock' => (int) ($variant['stock'] ?? 0),
                'available' => $result['ok'] && is_array($variant),
                'primary_image_url' => (string) ($product['primary_image_url'] ?? ''),
            ];
        }

        return $hydrated;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function get_cart_raw(): array
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
        $intent = [];
        foreach ($cart as $key => $line) {
            if (! is_array($line)) {
                continue;
            }
            $product_id = (int) ($line['product_id'] ?? 0);
            $variant_id = (int) ($line['variant_id'] ?? 0);
            if ($product_id < 1 || $variant_id < 1) {
                continue;
            }
            $intent[(string) $key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            ];
        }

        $json = (string) wp_json_encode($intent);
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
     * @return array{ok:bool,code:string,message:string,reconnect:bool,stripe:bool,health:array<string,mixed>}
     */
    public static function connection_state(): array
    {
        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return [
                'ok' => false,
                'code' => 'not_configured',
                'message' => 'This website is not connected to the merchant portal. Open WordPress admin → Settings → Eco Portal, paste a connection key from Website → Connect your website, then click Test connection.',
                'reconnect' => true,
                'stripe' => false,
                'health' => [],
            ];
        }

        $health = $client->get_health();
        if ($health['status'] === 401) {
            return [
                'ok' => false,
                'code' => 'unauthorized',
                'message' => 'The connection key is missing or was removed. In the merchant portal open Website → Connect your website, create a new key, then paste it in WordPress: Settings → Eco Portal.',
                'reconnect' => true,
                'stripe' => false,
                'health' => [],
            ];
        }
        if (! $health['ok'] || ! is_array($health['data'])) {
            return [
                'ok' => false,
                'code' => 'unreachable',
                'message' => $health['message'] !== '' ? $health['message'] : 'This website cannot reach the merchant portal. Check the portal website address, then click Test connection.',
                'reconnect' => true,
                'stripe' => false,
                'health' => [],
            ];
        }

        $data = $health['data'];
        $store = is_array($data['store'] ?? null) ? $data['store'] : [];
        $store['platform_checkout'] = [
            'ready' => ! empty($data['readiness']['stripe']),
        ];
        self::remember_store_currency((string) ($store['currency'] ?? ''));
        self::remember_checkout_settings($store);

        $stripe = ! empty($data['readiness']['stripe']);
        if (! $stripe) {
            return [
                'ok' => false,
                'code' => 'stripe',
                'message' => 'Checkout is blocked until Stripe is connected in the merchant portal Payments page. This website will not take payment itself.',
                'reconnect' => false,
                'stripe' => false,
                'health' => $data,
            ];
        }

        return [
            'ok' => true,
            'code' => 'ready',
            'message' => '',
            'reconnect' => false,
            'stripe' => true,
            'health' => $data,
        ];
    }

    /**
     * @param  array{ok?:bool,code?:string,message?:string,reconnect?:bool}  $connection
     */
    private static function reconnect_notice(array $connection): string
    {
        $message = (string) ($connection['message'] ?? 'This website is not connected to the merchant portal.');
        $html = self::notice($message, 'error');
        if (! empty($connection['reconnect'])) {
            $html .= '<p class="eco-portal__meta">Reconnect: merchant portal → Website → Connect your website → create a key → WordPress Settings → Eco Portal → Save → Test connection.</p>';
        }

        return $html;
    }

    private static function storefront_conflict_notice(): string
    {
        $report = Eco_Portal_Conflicts::report();
        if (! empty($report['production_ready'])) {
            return '';
        }

        $first = $report['conflicts'][0] ?? null;
        if (! is_array($first)) {
            return '';
        }

        return (string) ($first['title'] ?? '').'. Portal checkout can still be tested, but this website is not ready for live shoppers until the WordPress conflicts are fixed.';
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

    public static function page_url(string $slug): string
    {
        $page = get_page_by_path($slug);

        return $page ? (string) get_permalink($page) : home_url('/');
    }

    public static function product_url(int $product_id): string
    {
        return add_query_arg('eco_product', $product_id, self::page_url('portal-shop'));
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

        $result = $client->get_health();
        $from_health = is_array($result['data']['store'] ?? null)
            ? strtoupper(trim((string) ($result['data']['store']['currency'] ?? '')))
            : '';

        if ($result['ok'] && preg_match('/^[A-Z]{3}$/', $from_health) === 1) {
            update_option('eco_portal_store_currency', $from_health, false);

            return $from_health;
        }

        return 'USD';
    }

    /**
     * @param  array<string, mixed>  $store
     */
    public static function remember_checkout_settings(array $store): void
    {
        update_option('eco_portal_checkout_mode', 'platform_checkout', false);
        update_option('eco_portal_platform_ready', ! empty($store['platform_checkout']['ready']) ? '1' : '0', false);
    }

    public static function checkout_mode(): string
    {
        return 'platform_checkout';
    }

    public static function platform_ready(): bool
    {
        return (string) get_option('eco_portal_platform_ready', '0') === '1';
    }

    public static function format_money(string $value, string $currency): string
    {
        if ($value === '') {
            return $currency;
        }

        return $currency.' '.self::money($value);
    }

    public static function cart_subtotal(array $cart): string
    {
        $total = 0.0;
        foreach ($cart as $line) {
            if (empty($line['available'])) {
                continue;
            }
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
            $type = (string) ($option['group'] ?? $option['type'] ?? 'Option');
            $value = (string) ($option['value'] ?? '');
            $parts[] = $type.': '.$value;
        }

        return $parts === [] ? 'Default' : implode(', ', $parts);
    }

    /**
     * @return list<string>
     */
    private static function recent_order_tokens(): array
    {
        if (! isset($_COOKIE[self::ORDERS_COOKIE])) {
            return [];
        }
        $decoded = json_decode((string) wp_unslash((string) $_COOKIE[self::ORDERS_COOKIE]), true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), static fn (string $token): bool => str_starts_with($token, 'ordconf_')));
    }

    private static function remember_order_token(string $token): void
    {
        if (! str_starts_with($token, 'ordconf_')) {
            return;
        }
        $tokens = self::recent_order_tokens();
        array_unshift($tokens, $token);
        $tokens = array_values(array_unique($tokens));
        $tokens = array_slice($tokens, 0, 10);
        $json = (string) wp_json_encode($tokens);
        setcookie(self::ORDERS_COOKIE, $json, [
            'expires' => time() + (30 * DAY_IN_SECONDS),
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::ORDERS_COOKIE] = $json;
    }

    private static function is_commerce_page(): bool
    {
        if (! is_singular('page')) {
            return false;
        }
        global $post;
        if (! $post instanceof WP_Post) {
            return false;
        }
        $content = (string) $post->post_content;

        return has_shortcode($content, 'eco_portal_catalog')
            || has_shortcode($content, 'eco_portal_product')
            || has_shortcode($content, 'eco_portal_cart')
            || has_shortcode($content, 'eco_portal_checkout')
            || has_shortcode($content, 'eco_portal_order');
    }
}
