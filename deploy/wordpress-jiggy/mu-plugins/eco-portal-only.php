<?php
/**
 * Keep the original Elementor design, but send every add-to-cart / shop / checkout
 * action through Eco Portal instead of WooCommerce.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class Eco_Portal_Design_Bridge
{
    /** Woo product/variation ID => [portal product_id, variant_id] */
    private const MAP = [
        310 => [8, 9],
        319 => [9, 10],
        317 => [10, 11],
        315 => [11, 12],
        730 => [12, 13],
        1383 => [8, 9],
        1263 => [9, 10],
        1266 => [10, 11],
        1268 => [11, 12],
        1260 => [12, 13],
    ];

    private const PRODUCT_SLUGS = [
        'teriyaki-flavor' => 8,
        'garlic-flavor' => 9,
        'peppered-flavor' => 10,
        'giardinera-flavor' => 11,
        'giardiniera-flavor' => 11,
        'orange-habanero' => 12,
    ];

    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'intercept_woo_add_to_cart'], 0);
        // Only rewrite at widget level so we don't re-process the full page HTML.
        add_filter('elementor/widget/render_content', [self::class, 'rewrite_widget'], 10, 2);
        add_action('wp_footer', [self::class, 'rewrite_urls_script'], 99);
        add_filter('eco_portal_appearance_defaults', [self::class, 'jiggy_appearance_defaults']);
        add_action('eco_portal_enqueue_assets', [self::class, 'enqueue_brand_css']);
    }

    /**
     * Brand defaults only. Values saved in Settings → Eco Portal always win.
     *
     * @param  array<string, string>  $ui
     * @return array<string, string>
     */
    public static function jiggy_appearance_defaults(array $ui): array
    {
        $ui['accent'] = '#ffa302';
        $ui['accent_2'] = '#ffe402';
        $ui['button_text'] = '#111111';
        $ui['text'] = '#1a1100';
        $ui['background'] = '#f7f1e4';
        $ui['muted'] = '#6b5a3c';
        $ui['card'] = '#ffffff';
        $ui['border'] = '#e5d7b8';
        $ui['radius'] = '18px';
        $ui['heading_font'] = 'bebas';

        return $ui;
    }

    public static function enqueue_brand_css(): void
    {
        $path = __DIR__.'/jiggy-eco-brand.css';
        if (! is_readable($path)) {
            return;
        }

        wp_enqueue_style(
            'jiggy-eco-brand',
            content_url('mu-plugins/jiggy-eco-brand.css'),
            ['eco-portal-storefront'],
            (string) filemtime($path)
        );
    }

    public static function portal_url(string $slug): string
    {
        if (class_exists('Eco_Portal_Storefront')) {
            $url = Eco_Portal_Storefront::page_url($slug);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return home_url('/'.$slug.'/');
    }

    public static function product_url(int $productId): string
    {
        if (class_exists('Eco_Portal_Storefront')) {
            return Eco_Portal_Storefront::product_url($productId);
        }

        return add_query_arg('eco_product', $productId, self::portal_url('portal-shop'));
    }

    public static function intercept_woo_add_to_cart(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'eco_portal_add_to_cart') {
            return;
        }

        $wooId = (int) ($_POST['add-to-cart'] ?? $_POST['product_id'] ?? 0);
        $variationId = (int) ($_POST['variation_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));

        if ($wooId < 1 && $variationId < 1) {
            return;
        }

        $mapped = self::MAP[$variationId] ?? self::MAP[$wooId] ?? null;
        if ($mapped === null || ! class_exists('Eco_Portal_Storefront')) {
            return;
        }

        $_POST['action'] = 'eco_portal_add_to_cart';
        $_POST['product_id'] = (string) $mapped[0];
        $_POST['variant_id'] = (string) $mapped[1];
        $_POST['quantity'] = (string) $qty;
        $_POST['redirect_to'] = self::portal_url('portal-cart');
        $_REQUEST['_wpnonce'] = wp_create_nonce('eco_portal_add_to_cart');
        $_POST['_wpnonce'] = $_REQUEST['_wpnonce'];

        Eco_Portal_Storefront::handle_add_to_cart();
        exit;
    }

    public static function rewrite_widget(string $content, $widget): string
    {
        $name = is_object($widget) && method_exists($widget, 'get_name') ? (string) $widget->get_name() : '';

        if ($name === 'woocommerce-product-add-to-cart' || $name === 'wc-add-to-cart') {
            $settings = method_exists($widget, 'get_settings_for_display') ? $widget->get_settings_for_display() : [];
            $wooId = (int) ($settings['product_id'] ?? 310);
            $mapped = self::MAP[$wooId] ?? [8, 9];
            $action = esc_url(admin_url('admin-post.php'));
            $nonce = esc_attr(wp_create_nonce('eco_portal_add_to_cart'));
            $cart = esc_url(self::portal_url('portal-cart'));

            return '<form class="eco-bridge-wc-form" method="post" action="'.$action.'" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">'
                .'<input type="hidden" name="action" value="eco_portal_add_to_cart" />'
                .'<input type="hidden" name="_wpnonce" value="'.$nonce.'" />'
                .'<input type="hidden" name="product_id" value="'.esc_attr((string) $mapped[0]).'" />'
                .'<input type="hidden" name="variant_id" value="'.esc_attr((string) $mapped[1]).'" />'
                .'<input type="number" name="quantity" value="1" min="1" class="qty" style="width:4.5em;text-align:center" />'
                .'<button type="submit" class="elementor-button" style="background:linear-gradient(90deg,#FFA302,#FFE402);color:#000;border:0;border-radius:999px;padding:13px 53px;font-family:Bebas Neue,Impact,sans-serif;font-size:24px;cursor:pointer">Add to Cart</button>'
                .'<input type="hidden" name="redirect_to" value="'.$cart.'" />'
                .'</form>';
        }

        if ($name !== 'html' && $name !== 'text-editor' && $name !== 'button' && $name !== 'heading') {
            return $content;
        }

        return self::rewrite_fragment($content);
    }

    public static function rewrite_fragment(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        // Already converted — do not touch again.
        if (strpos($content, 'eco_portal_add_to_cart') !== false && strpos($content, 'name="add-to-cart"') === false) {
            return self::rewrite_links($content);
        }

        $content = preg_replace_callback(
            '~(<form\b[^>]*class="[^"]*custom-cart-form[^"]*"[^>]*)>(.*?)</form>~is',
            static function (array $m): string {
                $open = $m[1];
                $inner = $m[2];

                if (strpos($inner, 'eco_portal_add_to_cart') !== false) {
                    return $m[0];
                }

                $wooId = 310;
                if (preg_match('~name="(?:add-to-cart|product_id)"\s+value="(\d+)"~i', $inner, $idMatch)) {
                    $wooId = (int) $idMatch[1];
                }
                $mapped = self::MAP[$wooId] ?? [8, 9];

                // Remove Woo-only fields; keep quantity + designed button markup.
                $inner = preg_replace('~<input[^>]*name="(?:add-to-cart|variation_id|attribute_pa_size)"[^>]*>~i', '', $inner) ?? $inner;
                $inner = preg_replace('~<input[^>]*name="product_id"[^>]*>~i', '', $inner) ?? $inner;
                $inner = preg_replace('~<!--\s*Hidden inputs for WooCommerce\s*-->~i', '<!-- Eco Portal cart -->', $inner) ?? $inner;

                $action = esc_url(admin_url('admin-post.php'));
                $nonce = esc_attr(wp_create_nonce('eco_portal_add_to_cart'));
                $cart = esc_url(self::portal_url('portal-cart'));

                $open = preg_replace('~\saction=(["\']).*?\1~i', '', $open) ?? $open;
                if (stripos($open, 'action=') === false) {
                    $open .= ' action="'.$action.'"';
                }

                $fields = "\n  <input type=\"hidden\" name=\"action\" value=\"eco_portal_add_to_cart\" />"
                    ."\n  <input type=\"hidden\" name=\"_wpnonce\" value=\"{$nonce}\" />"
                    ."\n  <input type=\"hidden\" name=\"product_id\" value=\"".esc_attr((string) $mapped[0])."\" />"
                    ."\n  <input type=\"hidden\" name=\"variant_id\" value=\"".esc_attr((string) $mapped[1])."\" />"
                    ."\n  <input type=\"hidden\" name=\"redirect_to\" value=\"{$cart}\" />\n";

                return $open.'>'.$fields.$inner.'</form>';
            },
            $content
        ) ?? $content;

        return self::rewrite_links($content);
    }

    public static function rewrite_links(string $content): string
    {
        $shop = self::portal_url('portal-shop');
        $cart = self::portal_url('portal-cart');
        $checkout = self::portal_url('portal-checkout');

        $content = preg_replace_callback(
            '~https?://[^"\']+/(shop|cart|checkout)/?~i',
            static function (array $m) use ($shop, $cart, $checkout): string {
                $part = strtolower($m[1]);
                if ($part === 'checkout') {
                    return $checkout;
                }
                if ($part === 'cart') {
                    return $cart;
                }

                return $shop;
            },
            $content
        ) ?? $content;

        $content = preg_replace_callback(
            '~https?://[^"\']+/product/([a-z0-9\-]+)/?~i',
            static function (array $m): string {
                $slug = strtolower($m[1]);
                $id = self::PRODUCT_SLUGS[$slug] ?? 0;
                if ($id < 1) {
                    return self::portal_url('portal-shop');
                }

                return self::product_url($id);
            },
            $content
        ) ?? $content;

        return str_replace(
            [
                'https://jiggyjerky.com',
                'http://jiggyjerky.com',
            ],
            [
                untrailingslashit(home_url()),
                untrailingslashit(home_url()),
            ],
            $content
        );
    }

    public static function rewrite_urls_script(): void
    {
        $shop = esc_url(self::portal_url('portal-shop'));
        $cart = esc_url(self::portal_url('portal-cart'));
        $checkout = esc_url(self::portal_url('portal-checkout'));
        $map = [];
        foreach (self::PRODUCT_SLUGS as $slug => $id) {
            $map[$slug] = self::product_url($id);
        }
        echo '<script>(function(){var shop='.json_encode($shop).',cart='.json_encode($cart).',checkout='.json_encode($checkout).',products='.json_encode($map).';document.querySelectorAll("a[href]").forEach(function(a){var h=a.getAttribute("href")||"";var m=h.match(/\\/product\\/([a-z0-9\\-]+)/i);if(m&&products[m[1].toLowerCase()]){a.setAttribute("href",products[m[1].toLowerCase()]);return;}if(/checkout/i.test(h))a.setAttribute("href",checkout);else if(/\\/cart\\/?/i.test(h))a.setAttribute("href",cart);else if(/\\/shop\\/?/i.test(h))a.setAttribute("href",shop);else if(/^https?:\\/\\/(www\\.)?jiggyjerky\\.com\\/?$/i.test(h))a.setAttribute("href",'.json_encode(home_url('/')).');});})();</script>';
    }
}

Eco_Portal_Design_Bridge::init();
