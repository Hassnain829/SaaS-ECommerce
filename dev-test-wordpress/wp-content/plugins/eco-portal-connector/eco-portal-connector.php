<?php
/**
 * Plugin Name: Eco Portal Connector
 * Description: Sample WordPress storefront that pulls catalog from the Eco Commerce SaaS portal and syncs orders back for integration testing.
 * Version: 1.0.0
 * Author: Eco Commerce
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: eco-portal-connector
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ECO_PORTAL_CONNECTOR_VERSION', '1.0.0');
define('ECO_PORTAL_CONNECTOR_FILE', __FILE__);
define('ECO_PORTAL_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('ECO_PORTAL_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-api-client.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-admin.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-storefront.php';

final class Eco_Portal_Connector
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        Eco_Portal_Admin::init();
        Eco_Portal_Storefront::init();

        register_activation_hook(ECO_PORTAL_CONNECTOR_FILE, [self::class, 'activate']);
    }

    public static function activate(): void
    {
        Eco_Portal_Storefront::ensure_pages();
        flush_rewrite_rules();
    }
}

Eco_Portal_Connector::instance();
