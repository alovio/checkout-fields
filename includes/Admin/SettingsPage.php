<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Checkout Fields" admin page (WooCommerce submenu) that mounts
 * the React builder. The field group is store-wide, so this is a single central
 * page rather than per-product meta boxes.
 */
final class SettingsPage {

	public const SLUG = 'clcf-checkout-fields';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Checkout Fields', 'corelabs-checkout-fields' ),
			__( 'Checkout Fields', 'corelabs-checkout-fields' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		echo '<div class="wrap clcf-wrap"><h1>' . esc_html__( 'Checkout Fields', 'corelabs-checkout-fields' ) . '</h1>';
		echo '<div id="clcf-builder-root"></div></div>';
	}
}
