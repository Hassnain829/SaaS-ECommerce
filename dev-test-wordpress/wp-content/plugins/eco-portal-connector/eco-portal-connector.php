<?php
/**
 * Plugin Name: Eco Portal Connector
 * Description: WordPress storefront connector that displays Eco Commerce portal catalog, cart, and Stripe platform checkout.
 * Version: 1.7.1
 * Author: Eco Commerce
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: eco-portal-connector
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('ECO_PORTAL_CONNECTOR_VERSION', '1.7.1');
define('ECO_PORTAL_CONNECTOR_FILE', __FILE__);
define('ECO_PORTAL_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('ECO_PORTAL_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-api-client.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-checkout-attempt.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-catalog-cache.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-events.php';
require_once ECO_PORTAL_CONNECTOR_PATH.'includes/class-conflicts.php';
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
        Eco_Portal_Events::init();
        Eco_Portal_Storefront::init();

        register_activation_hook(ECO_PORTAL_CONNECTOR_FILE, [self::class, 'activate']);
    }

    public static function activate(): void
    {
        Eco_Portal_Storefront::ensure_pages();
        Eco_Portal_Events::ensure_cron();
        flush_rewrite_rules();
    }
}

Eco_Portal_Connector::instance();
