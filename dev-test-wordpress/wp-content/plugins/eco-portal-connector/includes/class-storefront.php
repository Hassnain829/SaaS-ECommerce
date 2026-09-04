<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Storefront
{
    private const CART_COOKIE = 'eco_portal_cart';
    private const ORDERS_COOKIE = 'eco_portal_orders';
    private const CHECKOUT_SESSION_COOKIE = 'eco_portal_checkout_sid';

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
        add_action('wp_ajax_nopriv_eco_portal_checkout_status', [self::class, 'handle_checkout_status']);
        add_action('wp_ajax_eco_portal_checkout_status', [self::class, 'handle_checkout_status']);
        add_action('admin_post_nopriv_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
        add_action('admin_post_eco_portal_reset_checkout', [self::class, 'handle_reset_checkout']);
        add_action('admin_post_nopriv_eco_portal_lookup_order', [self::class, 'handle_lookup_order']);
        add_action('admin_post_eco_portal_lookup_order', [self::class, 'handle_lookup_order']);
    }

    public static function ensure_pages(): void
    {
        $pages = [
            'portal-shop' => [
                'title' => 'Shop',
                'content' => '[eco_portal_catalog]',
            ],
            'portal-cart' => [
                'title' => 'Cart',
                'content' => '[eco_portal_cart]',
            ],
            'portal-checkout' => [
                'title' => 'Checkout',
                'content' => '[eco_portal_checkout]',
            ],
            'portal-order' => [
                'title' => 'Order status',
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

        // Allocate the browser-bound logical attempt before the theme starts
        // rendering. Both the cookie and the form token therefore exist before
        // any initial checkout submission can be sent.
        if (self::is_checkout_page()) {
            self::ensure_checkout_attempt();
        }
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

        // Homepage Elementor sections also need section CSS when Portal Products is used.
        wp_enqueue_style(
            'eco-portal-sections',
            ECO_PORTAL_CONNECTOR_URL.'assets/css/sections.css',
            ['eco-portal-storefront'],
            ECO_PORTAL_CONNECTOR_VERSION
        );

        $theme_css = trailingslashit(get_stylesheet_directory()).'eco-portal/storefront.css';
        if (is_readable($theme_css)) {
            wp_enqueue_style(
                'eco-portal-theme',
                trailingslashit(get_stylesheet_directory_uri()).'eco-portal/storefront.css',
                ['eco-portal-storefront'],
                (string) filemtime($theme_css)
            );
        }

        Eco_Portal_Templates::enqueue_appearance_css('eco-portal-storefront');

        /**
         * Let themes / site plugins enqueue brand CSS after the base storefront.
         *
         * @param bool $is_commerce_page
         */
        do_action('eco_portal_enqueue_assets', true);

        global $post;
        $content = $post instanceof WP_Post ? (string) $post->post_content : '';
        $needsCheckout = has_shortcode($content, 'eco_portal_checkout')
            || ($post instanceof WP_Post && self::elementor_uses_portal($post->ID) && str_contains((string) get_post_meta($post->ID, '_elementor_data', true), 'eco_portal_checkout'));
        if ($needsCheckout || self::is_checkout_page()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
            wp_enqueue_script(
                'eco-portal-checkout',
                ECO_PORTAL_CONNECTOR_URL.'assets/js/checkout.js',
                ['stripe-js'],
                ECO_PORTAL_CONNECTOR_VERSION,
                true
            );
            wp_localize_script('eco-portal-checkout', 'ecoPortalCheckout', [
                'statusUrl' => admin_url('admin-ajax.php'),
                'statusNonce' => wp_create_nonce('eco_portal_checkout_status'),
                'returnUrl' => add_query_arg('eco_payment_return', '1', self::page_url('portal-checkout')),
            ]);
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

        return Eco_Portal_Templates::render('catalog.php', compact(
            'store',
            'products',
            'categories',
            'catalog_meta',
            'active_category',
            'cart_url',
            'currency'
        ));
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

        return Eco_Portal_Templates::render('product.php', compact(
            'product',
            'cart_url',
            'shop_url',
            'currency'
        ));
    }

    public static function render_cart(): string
    {
        $connection = self::connection_state();
        $cart = self::hydrate_cart();
        $checkout_url = self::page_url('portal-checkout');
        $shop_url = self::page_url('portal-shop');
        $currency = self::store_currency();
        $catalog_subtotal = self::cart_subtotal($cart);

        return Eco_Portal_Templates::render('cart.php', compact(
            'connection',
            'cart',
            'checkout_url',
            'shop_url',
            'currency',
            'catalog_subtotal'
        ));
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
        $checkout_state = self::ensure_checkout_attempt();
        $order_result = null;
        $error = '';
        $conflict_notice = self::storefront_conflict_notice();

        if (isset($_GET['eco_error'])) {
            $error = sanitize_text_field((string) wp_unslash($_GET['eco_error']));
        }

        return Eco_Portal_Templates::render('checkout.php', compact(
            'connection',
            'cart',
            'shop_url',
            'order_url',
            'currency',
            'checkout_mode',
            'platform_ready',
            'checkout_blocked',
            'checkout_state',
            'order_result',
            'error',
            'conflict_notice'
        ));
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

        return Eco_Portal_Templates::render('order.php', compact(
            'connection',
            'order_result',
            'error',
            'token',
            'recent',
            'shop_url'
        ));
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

        $payload_fingerprint = hash('sha256', (string) wp_json_encode($payload));
        $attempt_state = self::checkout_state();
        $posted_attempt_token = sanitize_text_field((string) wp_unslash($_POST['checkout_attempt_token'] ?? ''));

        try {
            $attempt_state = Eco_Portal_Checkout_Attempt::begin(
                $attempt_state,
                $posted_attempt_token,
                $payload_fingerprint
            );
        } catch (RuntimeException $exception) {
            $message = $exception->getCode() === Eco_Portal_Checkout_Attempt::ERROR_CHANGED
                ? 'Checkout details changed after this attempt was submitted. Choose Start over before beginning a different checkout.'
                : 'This checkout form has expired. Reload the checkout page before trying again.';
            self::redirect_checkout_error($message);
        }

        $idempotency_key = Eco_Portal_Checkout_Attempt::idempotency_key($attempt_state);

        // Persist the logical attempt before the network request so timeouts and
        // concurrent browser submissions reuse the preallocated key.
        $attempt_state['address'] = $fields;
        self::save_checkout_state($attempt_state);

        $created = $client->create_checkout($payload, $idempotency_key);
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

        $attempt_state['step'] = 'rates';
        $attempt_state['checkout_id'] = $checkout_id;
        $attempt_state['checkout'] = $checkout;
        $attempt_state['delivery_options'] = $options;
        $attempt_state['payment'] = is_array($created['data']['payment'] ?? null) ? $created['data']['payment'] : [];
        $attempt_state['warning'] = $warning;
        self::save_checkout_state($attempt_state);

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

    public static function handle_checkout_status(): void
    {
        check_ajax_referer('eco_portal_checkout_status', 'nonce');

        $mode = sanitize_key((string) wp_unslash($_POST['mode'] ?? 'poll'));
        if (! in_array($mode, ['begin', 'poll', 'payment_error', 'complete'], true)) {
            wp_send_json_error([
                'state' => 'processing',
                'message' => 'That checkout status action is not available.',
                'recoverable' => true,
            ], 400);
        }

        $state = self::checkout_state();
        $checkout_id = (int) ($state['checkout_id'] ?? 0);
        if ($checkout_id < 1) {
            wp_send_json_success([
                'state' => 'expired',
                'message' => 'This checkout session has expired. Return to your cart to start a new checkout.',
                'recoverable' => false,
            ]);
        }

        if ($mode === 'begin') {
            if (($state['step'] ?? '') === 'pay') {
                $state['step'] = 'confirming';
                $state['confirmation_started_at'] = time();
                self::save_checkout_state($state);
            }

            if (($state['step'] ?? '') === 'completed') {
                wp_send_json_success(self::completed_checkout_state($state));
            }

            wp_send_json_success(self::processing_checkout_state(
                'Stripe is confirming the payment. Order confirmation will continue automatically.'
            ));
        }

        if ($mode === 'payment_error') {
            if (in_array((string) ($state['step'] ?? ''), ['confirming', 'processing'], true)) {
                $state['step'] = 'pay';
                unset($state['confirmation_started_at'], $state['last_status_checked_at']);
                self::save_checkout_state($state);
            }

            wp_send_json_success([
                'state' => 'failed',
                'message' => 'Stripe did not complete this payment. Review the payment details and try again.',
                'recoverable' => true,
            ]);
        }

        if ($mode === 'complete') {
            if (($state['step'] ?? '') !== 'completed' || empty($state['confirmation_redirect_url'])) {
                wp_send_json_error([
                    'state' => 'processing',
                    'message' => 'Order confirmation is not ready yet.',
                    'recoverable' => true,
                ], 409);
            }

            $completed = self::completed_checkout_state($state);
            self::clear_checkout_state();
            wp_send_json_success($completed);
        }

        if (($state['step'] ?? '') === 'completed') {
            wp_send_json_success(self::completed_checkout_state($state));
        }

        if (! in_array((string) ($state['step'] ?? ''), ['confirming', 'processing'], true)) {
            wp_send_json_error([
                'state' => 'processing',
                'message' => 'Payment has not been submitted for this checkout.',
                'recoverable' => true,
            ], 409);
        }

        $client = new Eco_Portal_Api_Client();
        $result = $client->confirm_checkout($checkout_id);
        $status = (int) ($result['status'] ?? 0);

        if ($status === 200 && is_array($result['data']['order'] ?? null)) {
            $order = $result['data']['order'];
            $confirmation_token = sanitize_text_field((string) ($order['confirmation_token'] ?? ''));
            $confirmation_reference = $confirmation_token !== ''
                ? $confirmation_token
                : sanitize_text_field((string) ($order['order_number'] ?? ''));
            $redirect_url = add_query_arg('eco_confirm', $confirmation_reference, self::page_url('portal-order'));

            self::save_cart([]);
            $state['step'] = 'completed';
            $state['confirmation_token'] = $confirmation_token;
            $state['confirmation_redirect_url'] = $redirect_url;
            $state['completion_recorded_at'] = time();
            unset($state['payment']);
            if ($confirmation_token !== '') {
                self::remember_order_token($confirmation_token);
            }
            self::save_checkout_state($state);

            wp_send_json_success(self::completed_checkout_state($state));
        }

        if ($status === 410) {
            $state['step'] = 'expired';
            unset($state['payment']);
            self::save_checkout_state($state);

            wp_send_json_success([
                'state' => 'expired',
                'message' => 'This checkout expired before an order was confirmed. Return to your cart to start a new checkout.',
                'recoverable' => false,
            ]);
        }

        if ($status === 422) {
            $state['step'] = 'failed';
            unset($state['payment']);
            self::save_checkout_state($state);

            wp_send_json_success([
                'state' => 'failed',
                'message' => 'Stripe reported that this payment did not complete. Return to your cart when you are ready to try again.',
                'recoverable' => false,
            ]);
        }

        $state['step'] = 'processing';
        $state['last_status_checked_at'] = time();
        self::save_checkout_state($state);

        $message = in_array($status, [401, 403], true)
            ? 'Order confirmation is still pending, but this website connection needs attention. Do not pay again.'
            : 'Payment confirmation is still processing. Do not pay again; this page will keep checking safely.';
        $retry_after = max(1, min(10, (int) ($result['data']['retry_after_seconds'] ?? 2)));

        wp_send_json_success(self::processing_checkout_state($message, $retry_after));
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
        $sid = self::checkout_session_id();
        if ($sid === '') {
            return [];
        }

        $state = get_transient('eco_portal_co_'.$sid);

        return is_array($state) ? $state : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function ensure_checkout_attempt(): array
    {
        $state = self::checkout_state();

        // Established checkouts from an earlier plugin version do not need a
        // form token because their initial address form is no longer rendered.
        if ((int) ($state['checkout_id'] ?? 0) > 0) {
            return $state;
        }

        $ensured = Eco_Portal_Checkout_Attempt::ensure(
            $state,
            static fn (): string => 'wp_checkout_'.(function_exists('wp_generate_uuid4')
                ? wp_generate_uuid4()
                : bin2hex(random_bytes(16))),
            static fn (): string => wp_generate_password(32, false, false),
            time()
        );

        if ($ensured !== $state) {
            self::save_checkout_state($ensured);
        }

        return $ensured;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function save_checkout_state(array $state): void
    {
        $sid = self::checkout_session_id();
        if ($sid === '') {
            $sid = wp_generate_password(20, false, false);
            setcookie(self::CHECKOUT_SESSION_COOKIE, $sid, [
                'expires' => time() + HOUR_IN_SECONDS,
                'path' => COOKIEPATH ? COOKIEPATH : '/',
                'domain' => COOKIE_DOMAIN ?: '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[self::CHECKOUT_SESSION_COOKIE] = $sid;
        }

        set_transient('eco_portal_co_'.$sid, $state, HOUR_IN_SECONDS);
    }

    private static function clear_checkout_state(): void
    {
        $sid = self::checkout_session_id();
        if ($sid !== '') {
            delete_transient('eco_portal_co_'.$sid);
        }
        setcookie(self::CHECKOUT_SESSION_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => COOKIEPATH ? COOKIEPATH : '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::CHECKOUT_SESSION_COOKIE]);
    }

    private static function checkout_session_id(): string
    {
        $sid = isset($_COOKIE[self::CHECKOUT_SESSION_COOKIE])
            ? sanitize_text_field((string) wp_unslash($_COOKIE[self::CHECKOUT_SESSION_COOKIE]))
            : '';

        return preg_match('/^[A-Za-z0-9]{20}$/', $sid) === 1 ? $sid : '';
    }

    /**
     * @return array{state:string,message:string,retry_after_seconds:int,recoverable:bool}
     */
    private static function processing_checkout_state(string $message, int $retry_after = 2): array
    {
        return [
            'state' => 'processing',
            'message' => sanitize_text_field($message),
            'retry_after_seconds' => max(1, min(10, $retry_after)),
            'recoverable' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{state:string,message:string,redirect_url:string,recoverable:bool}
     */
    private static function completed_checkout_state(array $state): array
    {
        return [
            'state' => 'completed',
            'message' => 'Your order is confirmed.',
            'redirect_url' => esc_url_raw((string) ($state['confirmation_redirect_url'] ?? '')),
            'recoverable' => false,
        ];
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
    public static function reconnect_notice(array $connection): string
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
        $cached = strtoupper(trim((string) get_option('eco_portal_store_currency', '')));
        $cachedValid = preg_match('/^[A-Z]{3}$/', $cached) === 1;

        if (! $refresh_from_catalog) {
            return $cachedValid ? $cached : 'USD';
        }

        $client = new Eco_Portal_Api_Client();
        if (! $client->is_configured()) {
            return $cachedValid ? $cached : 'USD';
        }

        $result = $client->get_health();
        $from_health = is_array($result['data']['store'] ?? null)
            ? strtoupper(trim((string) ($result['data']['store']['currency'] ?? '')))
            : '';

        if ($result['ok'] && preg_match('/^[A-Z]{3}$/', $from_health) === 1) {
            update_option('eco_portal_store_currency', $from_health, false);

            return $from_health;
        }

        return $cachedValid ? $cached : 'USD';
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

    public static function is_commerce_page(): bool
    {
        if (! is_singular()) {
            return false;
        }

        global $post;
        if (! $post instanceof WP_Post) {
            return false;
        }

        return self::content_uses_portal((string) $post->post_content)
            || self::elementor_uses_portal($post->ID);
    }

    private static function is_checkout_page(): bool
    {
        if (! is_singular('page')) {
            return false;
        }

        global $post;
        if (! $post instanceof WP_Post) {
            return false;
        }

        if (has_shortcode((string) $post->post_content, 'eco_portal_checkout')) {
            return true;
        }

        $data = (string) get_post_meta($post->ID, '_elementor_data', true);

        return $data !== '' && (
            str_contains($data, 'eco_portal_checkout')
            || str_contains($data, '[eco_portal_checkout')
        );
    }

    public static function content_uses_portal(string $content): bool
    {
        return has_shortcode($content, 'eco_portal_catalog')
            || has_shortcode($content, 'eco_portal_product')
            || has_shortcode($content, 'eco_portal_products')
            || has_shortcode($content, 'eco_portal_cart')
            || has_shortcode($content, 'eco_portal_checkout')
            || has_shortcode($content, 'eco_portal_order');
    }

    public static function elementor_uses_portal(int $post_id): bool
    {
        if ($post_id < 1) {
            return false;
        }

        $data = (string) get_post_meta($post_id, '_elementor_data', true);
        if ($data === '') {
            return false;
        }

        return str_contains($data, 'eco_portal_products')
            || str_contains($data, 'eco_portal_catalog')
            || str_contains($data, 'eco_portal_product')
            || str_contains($data, 'eco_portal_cart')
            || str_contains($data, 'eco_portal_checkout')
            || str_contains($data, 'eco_portal_order')
            || str_contains($data, '[eco_portal_');
    }
}
