<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detects WordPress commerce conflicts that would create a second checkout authority.
 * This class never deactivates plugins. It only reports exact merchant instructions.
 */
final class Eco_Portal_Conflicts
{
    /**
     * @return array{production_ready:bool,conflicts:list<array{code:string,severity:string,title:string,instruction:string}>}
     */
    public static function report(): array
    {
        $conflicts = array_values(array_filter([
            self::woocommerce_plugin(),
            self::woocommerce_checkout_page(),
            self::woocommerce_cart_page(),
            ...self::woocommerce_payment_plugins(),
            ...self::woocommerce_shipping_plugins(),
            ...self::conflicting_shortcodes(),
            self::unsafe_checkout_cache(),
        ]));

        return [
            'production_ready' => $conflicts === [],
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @return array{code:string,severity:string,title:string,instruction:string}|null
     */
    private static function woocommerce_plugin(): ?array
    {
        if (! self::plugin_active('woocommerce/woocommerce.php')) {
            return null;
        }

        return [
            'code' => 'woocommerce_active',
            'severity' => 'block',
            'title' => 'WooCommerce is still active',
            'instruction' => 'In WordPress: Plugins → installed plugins → Deactivate WooCommerce. Do not delete it until you have a backup. Eco Portal does not use WooCommerce for catalog, cart, or checkout.',
        ];
    }

    /**
     * @return array{code:string,severity:string,title:string,instruction:string}|null
     */
    private static function woocommerce_checkout_page(): ?array
    {
        $page_id = (int) get_option('woocommerce_checkout_page_id', 0);
        $page = $page_id > 0 ? get_post($page_id) : null;
        if (! $page instanceof WP_Post || $page->post_status !== 'publish') {
            return null;
        }

        return [
            'code' => 'woocommerce_checkout_page',
            'severity' => 'block',
            'title' => 'WooCommerce still has a checkout page assigned',
            'instruction' => 'In WordPress: WooCommerce → Settings → Advanced → Page setup. Clear the Checkout page assignment, or deactivate WooCommerce. Shoppers should use Portal Checkout, not the WooCommerce checkout page ('.self::page_title($page).').',
        ];
    }

    /**
     * @return array{code:string,severity:string,title:string,instruction:string}|null
     */
    private static function woocommerce_cart_page(): ?array
    {
        $page_id = (int) get_option('woocommerce_cart_page_id', 0);
        $page = $page_id > 0 ? get_post($page_id) : null;
        if (! $page instanceof WP_Post || $page->post_status !== 'publish') {
            return null;
        }

        return [
            'code' => 'woocommerce_cart_page',
            'severity' => 'block',
            'title' => 'WooCommerce still has a cart page assigned',
            'instruction' => 'In WordPress: WooCommerce → Settings → Advanced → Page setup. Clear the Cart page assignment, or deactivate WooCommerce. Shoppers should use Portal Cart ('.self::page_title($page).' is still a WooCommerce cart).',
        ];
    }

    /**
     * @return list<array{code:string,severity:string,title:string,instruction:string}>
     */
    private static function woocommerce_payment_plugins(): array
    {
        $known = [
            'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' => 'WooCommerce Stripe',
            'woocommerce-payments/woocommerce-payments.php' => 'WooPayments',
            'woocommerce-paypal-payments/woocommerce-paypal-payments.php' => 'WooCommerce PayPal Payments',
            'woocommerce-gateway-paypal-express-checkout/woocommerce-gateway-paypal-express-checkout.php' => 'PayPal Express Checkout',
            'klarna-payments-for-woocommerce/klarna-payments-for-woocommerce.php' => 'Klarna Payments for WooCommerce',
            'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php' => 'Mollie Payments for WooCommerce',
            'woocommerce-gateway-stripe/stripe.php' => 'WooCommerce Stripe',
            'payment-plugins-for-stripe-woocommerce/payment-plugins-for-stripe-woocommerce.php' => 'Payment Plugins for Stripe WooCommerce',
        ];

        $found = [];
        foreach ($known as $file => $label) {
            if (! self::plugin_active($file)) {
                continue;
            }
            $found[] = [
                'code' => 'woo_payment_plugin',
                'severity' => 'block',
                'title' => $label.' is still active',
                'instruction' => 'In WordPress: Plugins → Deactivate '.$label.'. Connect Stripe in the merchant portal Payments page instead. Do not leave a WordPress payment plugin taking cards.',
            ];
        }

        foreach (self::active_plugin_files() as $file) {
            if (isset($known[$file])) {
                continue;
            }
            if (
                str_contains($file, 'woocommerce')
                && (
                    str_contains($file, 'stripe')
                    || str_contains($file, 'paypal')
                    || str_contains($file, 'gateway')
                    || str_contains($file, 'payments')
                )
            ) {
                $found[] = [
                    'code' => 'woo_payment_plugin',
                    'severity' => 'block',
                    'title' => 'A WooCommerce payment plugin is still active',
                    'instruction' => 'In WordPress: Plugins → Deactivate '.$file.'. Card payments must go through the merchant portal Stripe connection, not a WordPress gateway.',
                ];
            }
        }

        return $found;
    }

    /**
     * @return list<array{code:string,severity:string,title:string,instruction:string}>
     */
    private static function woocommerce_shipping_plugins(): array
    {
        $found = [];
        foreach (self::active_plugin_files() as $file) {
            if (
                str_contains($file, 'woocommerce')
                && (
                    str_contains($file, 'shipping')
                    || str_contains($file, 'table-rate')
                    || $file === 'woocommerce-services/woocommerce-services.php'
                    || str_contains($file, 'flexible-shipping')
                )
            ) {
                $found[] = [
                    'code' => 'woo_shipping_plugin',
                    'severity' => 'block',
                    'title' => 'A WooCommerce shipping plugin is still active',
                    'instruction' => 'In WordPress: Plugins → Deactivate '.$file.'. Delivery rates come from the merchant portal. The website must not calculate shipping locally.',
                ];
            }
        }

        return $found;
    }

    /**
     * @return list<array{code:string,severity:string,title:string,instruction:string}>
     */
    private static function conflicting_shortcodes(): array
    {
        $shortcodes = [
            'woocommerce_checkout' => 'WooCommerce checkout',
            'woocommerce_cart' => 'WooCommerce cart',
            'woocommerce_my_account' => 'WooCommerce account',
            'woocommerce' => 'WooCommerce shop',
        ];
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 100,
            'suppress_filters' => true,
        ]);
        $found = [];
        foreach ($pages as $page) {
            if (! $page instanceof WP_Post) {
                continue;
            }
            foreach ($shortcodes as $code => $label) {
                if (! has_shortcode((string) $page->post_content, $code)) {
                    continue;
                }
                $found[] = [
                    'code' => 'conflicting_shortcode',
                    'severity' => 'block',
                    'title' => $label.' shortcode is still on a published page',
                    'instruction' => 'Edit the WordPress page "'.self::page_title($page).'" and remove ['.$code.']. Use Portal Shop, Portal Cart, and Portal Checkout instead.',
                ];
            }
        }

        return $found;
    }

    /**
     * @return array{code:string,severity:string,title:string,instruction:string}|null
     */
    private static function unsafe_checkout_cache(): ?array
    {
        $cachePlugins = [
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wp-rocket/wp-rocket.php' => 'WP Rocket',
            'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
            'sg-cachepress/sg-cachepress.php' => 'SiteGround Speed Optimizer',
            'nitropack/main.php' => 'NitroPack',
            'breeze/breeze.php' => 'Breeze',
            'cache-enabler/cache-enabler.php' => 'Cache Enabler',
            'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
        ];

        $active = [];
        foreach ($cachePlugins as $file => $label) {
            if (self::plugin_active($file)) {
                $active[] = $label;
            }
        }

        if ($active === [] && ! (defined('WP_CACHE') && WP_CACHE)) {
            return null;
        }

        $names = $active !== [] ? implode(', ', $active) : 'a page cache';

        return [
            'code' => 'unsafe_checkout_cache',
            'severity' => 'block',
            'title' => 'Checkout pages may be cached',
            'instruction' => 'In '.$names.': exclude Portal Cart, Portal Checkout, and Portal order status from page cache. Never cache pages that contain [eco_portal_checkout] or [eco_portal_cart]. Cached checkout can show a stale Stripe form or an old cart.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function active_plugin_files(): array
    {
        $plugins = (array) get_option('active_plugins', []);
        if (function_exists('is_multisite') && is_multisite()) {
            $plugins = array_merge($plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }

        return array_values(array_unique(array_map('strval', $plugins)));
    }

    private static function plugin_active(string $file): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active($file);
        }

        return in_array($file, self::active_plugin_files(), true);
    }

    private static function page_title(WP_Post $page): string
    {
        $title = trim((string) $page->post_title);

        return $title !== '' ? $title : (string) $page->post_name;
    }
}
