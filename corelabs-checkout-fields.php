<?php
/**
 * Plugin Name: Alovio Checkout Fields for WooCommerce
 * Plugin URI: https://alovio.org/checkout-fields
 * Update URI: https://licenses.alovio.org/corelabs-checkout-fields
 * Description: Add custom WooCommerce checkout fields with a drag-and-drop builder and full conditional logic — on classic and Block checkout.
 * Version: 1.7.0
 * Author: Alovio
 * Author URI: https://alovio.org
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: corelabs-checkout-fields
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) || exit;

define( 'CLCF_VERSION', '1.7.0' );
define( 'CLCF_FILE', __FILE__ );
define( 'CLCF_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLCF_URL', plugin_dir_url( __FILE__ ) );

require_once CLCF_PATH . 'vendor/autoload.php';

// Updates come from our own server rather than the plugin directory. Registered
// outside the WooCommerce gate below on purpose: a site with WooCommerce
// switched off still has to be able to update this plugin.
( new \CoreLabs\CheckoutFields\Updater() )->register();

// Declare WooCommerce feature compatibility (HPOS + Cart/Checkout Blocks).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CLCF_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CLCF_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		\CoreLabs\CheckoutFields\Plugin::instance()->boot();
	}
);
