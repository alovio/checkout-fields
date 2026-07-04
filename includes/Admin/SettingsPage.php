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
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/** Full-bleed layout flag for the builder page only. */
	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, self::SLUG ) ) {
			$classes .= ' clcf-builder-page';
		}
		return $classes;
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
		// The v2 app ships its own header — no wp-admin wrap/h1.
		echo '<div id="clcf-builder-root"></div>';
	}
}
