<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Admin;

use CoreLabs\CheckoutFields\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the React builder bundle on the Checkout Fields admin page and injects
 * the CLCF_BUILDER global (REST creds + the data the condition editor needs).
 */
final class BuilderAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_' . SettingsPage::SLUG !== $hook ) {
			return;
		}

		$asset_file = CLCF_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'clcf-builder', CLCF_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'clcf-builder', 'corelabs-checkout-fields', CLCF_PATH . 'languages' );

		if ( file_exists( CLCF_PATH . 'build/index.css' ) ) {
			wp_enqueue_style( 'clcf-builder', CLCF_URL . 'build/index.css', array(), $asset['version'] );
			wp_style_add_data( 'clcf-builder', 'rtl', 'replace' );
		}

		wp_localize_script(
			'clcf-builder',
			'CLCF_BUILDER',
			array(
				'root'       => esc_url_raw( rest_url( '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'fieldTypes' => FieldTypes::all(),
				'operators'  => (array) apply_filters( 'clcf_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) ),
				'actions'    => (array) apply_filters( 'clcf_allowed_actions', array( 'show', 'hide', 'require' ) ),
				'locations'  => SourceCatalog::locations(),
				'sources'    => SourceCatalog::sources(),
			)
		);
	}
}
