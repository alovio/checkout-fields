<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the classic-checkout runtime (live show/hide). Only on the classic
 * checkout — the Block checkout ships its own bundle via BlockIntegration.
 */
final class FrontendAssets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$asset_file = CLCF_PATH . 'build/frontend.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'clcf-frontend', CLCF_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'clcf-frontend', 'corelabs-checkout-fields', CLCF_PATH . 'languages' );

		if ( file_exists( CLCF_PATH . 'assets/css/frontend.css' ) ) {
			wp_enqueue_style( 'clcf-frontend', CLCF_URL . 'assets/css/frontend.css', array(), CLCF_VERSION );
		}
	}
}
