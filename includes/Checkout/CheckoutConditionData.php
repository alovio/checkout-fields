<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the flat `values` map that ConditionalLogic evaluates against, from the
 * checkout context. Two layers:
 *  - `from_values()` is PURE (no WooCommerce calls) and unit-tested against
 *    tests/fixtures/condition-data-cases.json, in lockstep with the JS mirror
 *    (src/checkout-conditions/data-sources.js `buildValues`).
 *  - `build()` gathers the context from WooCommerce (cart/customer/session) plus
 *    the posted request, then delegates to `from_values()`.
 *
 * Token scheme (see FieldSchema): submitted field values are keyed by their BARE
 * field id (so transitive active_map resolution works); context sources are
 * keyed by `@`-prefixed tokens that cannot collide with a field id.
 */
final class CheckoutConditionData {

	/**
	 * Pure: map submitted field values + a plain context array to engine values.
	 *
	 * @param array<string,mixed> $field_values  field id => submitted value
	 * @param array<string,mixed> $ctx           role, cartCategories[], cartProducts[], cartQty,
	 *                                            cartSubtotal, shippingMethod, paymentMethod,
	 *                                            billingCountry, shippingCountry
	 * @return array<string,string>
	 */
	public static function from_values( array $field_values, array $ctx ): array {
		$values = array();

		foreach ( $field_values as $id => $val ) {
			$values[ (string) $id ] = is_scalar( $val ) ? (string) $val : '';
		}

		$role                 = isset( $ctx['role'] ) ? (string) $ctx['role'] : '';
		$values['@user_role'] = '' !== $role ? $role : 'guest';

		foreach ( (array) ( $ctx['cartCategories'] ?? array() ) as $tid ) {
			$values[ '@cart_has_category:' . (int) $tid ] = 'yes';
		}
		foreach ( (array) ( $ctx['cartProducts'] ?? array() ) as $pid ) {
			$values[ '@cart_has_product:' . (int) $pid ] = 'yes';
		}

		$values['@cart_qty']        = (string) (int) ( $ctx['cartQty'] ?? 0 );
		$values['@cart_subtotal']   = (string) ( ( $ctx['cartSubtotal'] ?? 0 ) + 0 );
		$values['@shipping_method'] = (string) ( $ctx['shippingMethod'] ?? '' );
		$values['@payment_method']  = (string) ( $ctx['paymentMethod'] ?? '' );
		$values['@billing_country']  = (string) ( $ctx['billingCountry'] ?? '' );
		$values['@shipping_country'] = (string) ( $ctx['shippingCountry'] ?? '' );

		return (array) apply_filters( 'clcf_condition_values', $values, $field_values, $ctx );
	}

	/**
	 * Gather context from WooCommerce + the posted request, then map to values.
	 *
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $posted       raw posted data (payment_method, *_country overrides)
	 * @return array<string,string>
	 */
	public static function build( array $field_values = array(), array $posted = array() ): array {
		$ctx = self::context();

		if ( isset( $posted['payment_method'] ) ) {
			$ctx['paymentMethod'] = (string) $posted['payment_method'];
		}
		if ( isset( $posted['billing_country'] ) && '' !== (string) $posted['billing_country'] ) {
			$ctx['billingCountry'] = (string) $posted['billing_country'];
		}
		if ( isset( $posted['shipping_country'] ) && '' !== (string) $posted['shipping_country'] ) {
			$ctx['shippingCountry'] = (string) $posted['shipping_country'];
		}

		return self::from_values( $field_values, $ctx );
	}

	/**
	 * Gather the raw checkout context from WooCommerce (cart/session/customer).
	 * Returns the `ctx` shape that from_values()/buildValues() consume; also sent
	 * to the Block client so it can resolve conditions live.
	 *
	 * @return array<string,mixed>
	 */
	public static function context(): array {
		$ctx = array(
			'role'            => self::current_role(),
			'cartCategories'  => array(),
			'cartProducts'    => array(),
			'cartQty'         => 0,
			'cartSubtotal'    => 0,
			'shippingMethod'  => '',
			'paymentMethod'   => '',
			'billingCountry'  => '',
			'shippingCountry' => '',
		);

		// Guard: the cart/session/customer are only safe to read after wp_loaded.
		// (Block types register on `init`, before the cart exists — touching it
		// there triggers wc_doing_it_wrong.)
		if ( ! function_exists( 'WC' ) || ! did_action( 'wp_loaded' ) ) {
			return $ctx;
		}

		if ( WC()->cart ) {
			$cats  = array();
			$prods = array();
			foreach ( WC()->cart->get_cart() as $item ) {
				$pid = (int) ( $item['product_id'] ?? 0 );
				if ( ! $pid ) {
					continue;
				}
				$prods[ $pid ] = true;
				foreach ( wc_get_product_term_ids( $pid, 'product_cat' ) as $tid ) {
					$cats[ (int) $tid ] = true;
				}
			}
			$ctx['cartCategories'] = array_keys( $cats );
			$ctx['cartProducts']   = array_keys( $prods );
			$ctx['cartQty']        = (int) WC()->cart->get_cart_contents_count();
			$ctx['cartSubtotal']   = (float) WC()->cart->get_subtotal();
		}

		if ( WC()->session ) {
			$chosen                = (array) WC()->session->get( 'chosen_shipping_methods', array() );
			$ctx['shippingMethod'] = isset( $chosen[0] ) ? (string) $chosen[0] : '';
			$ctx['paymentMethod']  = (string) WC()->session->get( 'chosen_payment_method', '' );
		}

		if ( WC()->customer ) {
			$ctx['billingCountry']  = (string) WC()->customer->get_billing_country();
			$ctx['shippingCountry'] = (string) WC()->customer->get_shipping_country();
		}

		return $ctx;
	}

	private static function current_role(): string {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return 'guest';
		}
		$user = wp_get_current_user();
		return ( $user && ! empty( $user->roles ) ) ? (string) $user->roles[0] : 'guest';
	}
}
