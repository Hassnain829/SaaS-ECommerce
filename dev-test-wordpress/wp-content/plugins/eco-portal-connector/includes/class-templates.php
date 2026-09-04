<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared template loading and appearance helpers.
 *
 * Theme override path (highest priority):
 *   wp-content/themes/{active-theme}/eco-portal/{template}.php
 *   wp-content/themes/{active-theme}/eco-portal/sections/{layout}.php
 *
 * Filters:
 *   eco_portal_locate_template
 *   eco_portal_appearance
 *   eco_portal_enqueue_assets
 */
final class Eco_Portal_Templates
{
    public static function init(): void
    {
        add_filter('body_class', [self::class, 'body_class']);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_front'], 30);
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    public static function body_class(array $classes): array
    {
        if (class_exists('Eco_Portal_Storefront') && Eco_Portal_Storefront::is_commerce_page()) {
            $classes[] = 'eco-portal-commerce';
        }

        return $classes;
    }

    public static function maybe_enqueue_front(): void
    {
        // Storefront::enqueue_assets already handles commerce pages.
        // This is a safety net when only section CSS was enqueued by shortcode render.
        if (! class_exists('Eco_Portal_Storefront') || ! Eco_Portal_Storefront::is_commerce_page()) {
            return;
        }

        if (wp_style_is('eco-portal-storefront', 'enqueued')) {
            self::enqueue_appearance_css('eco-portal-storefront');
        } elseif (wp_style_is('eco-portal-sections', 'enqueued')) {
            self::enqueue_appearance_css('eco-portal-sections');
        }
    }

    /**
     * Resolve a plugin template, allowing theme overrides.
     *
     * @param  array<string, mixed>  $vars
     */
    public static function render(string $relative, array $vars = []): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $path = self::locate($relative);

        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped template locals only.
        extract($vars, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    public static function locate(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $candidates = [
            trailingslashit(get_stylesheet_directory()).'eco-portal/'.$relative,
            trailingslashit(get_template_directory()).'eco-portal/'.$relative,
            ECO_PORTAL_CONNECTOR_PATH.'templates/'.$relative,
        ];

        $found = '';
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                $found = $candidate;
                break;
            }
        }

        /**
         * @param string $found
         * @param string $relative
         * @param list<string> $candidates
         */
        return (string) apply_filters('eco_portal_locate_template', $found, $relative, $candidates);
    }

    /**
     * @return array{
     *   accent: string,
     *   accent_2: string,
     *   text: string,
     *   background: string,
     *   muted: string,
     *   card: string,
     *   border: string,
     *   button_text: string,
     *   radius: string,
     *   heading_font: string,
     *   custom_css: string
     * }
     */
    public static function appearance(): array
    {
        $defaults = [
            'accent' => '#0f6cbd',
            'accent_2' => '#3b8fd9',
            'text' => '#142033',
            'background' => '#f6f8fb',
            'muted' => '#5b6b82',
            'card' => '#ffffff',
            'border' => '#d9e1ec',
            'button_text' => '#ffffff',
            'radius' => '14px',
            'heading_font' => 'inherit',
            'custom_css' => '',
        ];

        /** Brand packs should use this filter (defaults only). Saved admin settings always win. */
        $defaults = (array) apply_filters('eco_portal_appearance_defaults', $defaults);

        $optionMap = [
            'accent' => 'eco_portal_ui_accent',
            'accent_2' => 'eco_portal_ui_accent_2',
            'text' => 'eco_portal_ui_text',
            'background' => 'eco_portal_ui_background',
            'muted' => 'eco_portal_ui_muted',
            'card' => 'eco_portal_ui_card',
            'border' => 'eco_portal_ui_border',
            'button_text' => 'eco_portal_ui_button_text',
            'radius' => 'eco_portal_ui_radius',
            'heading_font' => 'eco_portal_ui_heading_font',
            'custom_css' => 'eco_portal_ui_custom_css',
        ];

        $ui = $defaults;
        foreach ($optionMap as $key => $optionName) {
            $stored = get_option($optionName, null);
            if (! is_string($stored) || $stored === '') {
                continue;
            }
            $ui[$key] = $stored;
        }

        foreach (['accent', 'accent_2', 'text', 'background', 'muted', 'card', 'border', 'button_text'] as $colorKey) {
            $ui[$colorKey] = self::sanitize_hex((string) $ui[$colorKey], (string) $defaults[$colorKey]);
        }

        $radius = trim((string) $ui['radius']);
        if ($radius === '' || ! preg_match('/^\d+(\.\d+)?(px|rem|em)?$/', $radius)) {
            $ui['radius'] = (string) $defaults['radius'];
        } elseif (ctype_digit($radius)) {
            $ui['radius'] = $radius.'px';
        }

        $allowedFonts = ['inherit', 'system', 'bebas', 'serif'];
        if (! in_array((string) $ui['heading_font'], $allowedFonts, true)) {
            $ui['heading_font'] = (string) $defaults['heading_font'];
        }

        $ui['custom_css'] = self::sanitize_custom_css((string) ($ui['custom_css'] ?? ''));

        /**
         * Final appearance array. Prefer eco_portal_appearance_defaults for brand packs
         * so Settings → Eco Portal values are not overwritten.
         *
         * @param array<string, string> $ui
         */
        return (array) apply_filters('eco_portal_appearance', $ui);
    }

    public static function enqueue_appearance_css(string $handle): void
    {
        static $done = [];
        if (isset($done[$handle])) {
            return;
        }
        $done[$handle] = true;

        if (! wp_style_is($handle, 'enqueued') && ! wp_style_is($handle, 'registered')) {
            return;
        }

        $ui = self::appearance();
        $heading = match ($ui['heading_font']) {
            'system' => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
            'bebas' => '"Bebas Neue", Impact, sans-serif',
            'serif' => 'Georgia, "Times New Roman", serif',
            default => 'inherit',
        };

        if ($ui['heading_font'] === 'bebas') {
            wp_enqueue_style(
                'eco-portal-font-bebas',
                'https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap',
                [],
                null
            );
            if (wp_style_is($handle, 'registered') || wp_style_is($handle, 'enqueued')) {
                // Ensure font loads before brand CSS when possible.
                $obj = wp_styles();
                if (isset($obj->registered[$handle])) {
                    $obj->registered[$handle]->deps[] = 'eco-portal-font-bebas';
                }
            }
        }

        $css = '.eco-portal,.eco-section{'
            . '--eco-accent:'.$ui['accent'].';'
            . '--eco-accent-2:'.$ui['accent_2'].';'
            . '--eco-accent-dark:'.$ui['accent'].';'
            . '--eco-text:'.$ui['text'].';'
            . '--eco-bg:'.$ui['background'].';'
            . '--eco-muted:'.$ui['muted'].';'
            . '--eco-card:'.$ui['card'].';'
            . '--eco-border:'.$ui['border'].';'
            . '--eco-radius:'.$ui['radius'].';'
            . '--eco-heading-font:'.$heading.';'
            . '--eco-button-text:'.$ui['button_text'].';'
            . '}'
            . '.eco-portal a.eco-portal__button,'
            . '.eco-portal button.eco-portal__button,'
            . '.eco-portal .eco-portal__button,'
            . '.eco-portal .eco-portal__button--cta,'
            . '.eco-section .eco-section__btn,'
            . '.eco-section button.eco-section__btn{'
            . 'color:'.$ui['button_text'].';'
            . '}';

        if ($ui['custom_css'] !== '') {
            $css .= "\n/* Eco Portal Custom CSS (this site) */\n".$ui['custom_css'];
        }

        wp_add_inline_style($handle, $css);
    }

    public static function sanitize_custom_css(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        // Remove HTML/script while keeping CSS syntax (including >, *, etc.).
        $css = preg_replace('/<[^>]*>/', '', $css) ?? $css;
        $css = str_ireplace(['</style', '<script', 'javascript:'], '', $css);

        return trim($css);
    }

    private static function sanitize_hex(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value) === 1) {
            return strtolower($value);
        }

        return $fallback;
    }
}
