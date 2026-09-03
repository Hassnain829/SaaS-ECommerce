<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers Elementor widgets that wrap Eco Portal section shortcodes.
 * Merchants drag widgets into Elementor; products still come from the portal.
 */
final class Eco_Portal_Elementor
{
    public static function init(): void
    {
        add_action('elementor/widgets/register', [self::class, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [self::class, 'register_category']);
    }

    public static function register_category($elements_manager): void
    {
        if (! is_object($elements_manager) || ! method_exists($elements_manager, 'add_category')) {
            return;
        }

        $elements_manager->add_category('eco-portal', [
            'title' => __('Eco Portal', 'eco-portal-connector'),
            'icon' => 'fa fa-shopping-bag',
        ]);
    }

    public static function register_widgets($widgets_manager): void
    {
        if (! class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        require_once ECO_PORTAL_CONNECTOR_PATH.'includes/elementor/class-widget-products.php';

        if (is_object($widgets_manager) && method_exists($widgets_manager, 'register')) {
            $widgets_manager->register(new Eco_Portal_Elementor_Products_Widget());
        }
    }
}
