<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Applies field-driven fees (Pro) as a WooCommerce cart fee, so the surcharge
 * shows in the checkout total and flows onto the order automatically on BOTH
 * classic and Block checkout.
 *
 * Field values are captured into the session as the shopper edits the checkout
 * (classic: woocommerce_checkout_update_order_review; Block: a Store API update
 * callback driven by the client's extensionCartUpdate), then read back in
 * woocommerce_cart_calculate_fees. Inert in free — FeeCalculator returns 0 unless
 * `clcf_price_modes` is non-empty (Pro).
 */
final class FeesIntegration {

	private const SESSION_FIELDS = 'clcf_fee_fields';
	private const SESSION_CTX    = 'clcf_fee_ctx';

	private FieldRepository $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_classic' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_block_update' ) );
	}

	/** @param string $post_data serialized checkout form sent during the AJAX review refresh */
	public function capture_classic( $post_data ): void {
		$parsed = array();
		parse_str( (string) $post_data, $parsed );
		$this->store( is_array( $parsed ) ? $parsed : array() );
	}

	public function register_block_update(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'clcf',
				'callback'  => function ( $data ) {
					$fields = ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) ? $data['fields'] : array();
					$this->store( array( 'clcf' => $fields ) );
				},
			)
		);
	}

	/** @param array<string,mixed> $posted */
	private function store( array $posted ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$group        = $this->repo->get();
		$clcf         = ( isset( $posted['clcf'] ) && is_array( $posted['clcf'] ) ) ? $posted['clcf'] : array();
		$field_values = Sanitizer::sanitize( $group, $clcf );

		$ctx = array();
		foreach ( array( 'payment_method', 'billing_country', 'shipping_country' ) as $k ) {
			if ( isset( $posted[ $k ] ) ) {
				$ctx[ $k ] = sanitize_text_field( (string) $posted[ $k ] );
			}
		}

		WC()->session->set( self::SESSION_FIELDS, $field_values );
		WC()->session->set( self::SESSION_CTX, $ctx );
	}

	/** @param \WC_Cart $cart */
	public function add_fee( $cart ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$field_values = (array) WC()->session->get( self::SESSION_FIELDS, array() );
		if ( empty( $field_values ) ) {
			return;
		}
		$ctx    = (array) WC()->session->get( self::SESSION_CTX, array() );
		$group  = $this->repo->get();
		$values = CheckoutConditionData::build( $field_values, $ctx );
		$base   = (float) $cart->get_subtotal();
		$fee    = FeeCalculator::total( $group, $field_values, $values, $base );

		if ( $fee > 0 ) {
			$cart->add_fee( apply_filters( 'clcf_fee_label', __( 'Checkout options', 'corelabs-checkout-fields' ) ), $fee );
		}
	}
}
