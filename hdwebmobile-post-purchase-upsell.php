<?php

/**
 * Plugin Name: HDWebmobile Post-Purchase Upsell
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-post-purchase-upsell/
 * Description: A one-click upsell on the order-received page -- the original order is always verified by its real key, the price always computed server-side.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-post-purchase-upsell
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdppu;

if (!defined('ABSPATH')) {
    exit;
}

define('HDPPU_VERSION', '1.0.0');
define('HDPPU_PLUGIN_FILE', __FILE__);
define('HDPPU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDPPU_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDPPU_PLUGIN_DIR . 'includes/class-hdppu-activator.php';

register_activation_hook(__FILE__, array(HDPPU_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDPPU_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDPPU_PLUGIN_DIR . 'includes/class-hdppu-core.php';
    HDPPU_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-post-purchase-upsell') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
