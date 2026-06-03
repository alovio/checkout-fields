<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the data the builder's condition editor needs: the list of render
 * locations and the catalogue of condition "sources" (context tokens + their
 * selectable values). All lookups guard against WooCommerce being unavailable so
 * the page never fatals.
 *
 * Source token contract (matches CheckoutConditionData):
 *  - simple select sources carry a fixed `token` (e.g. `@user_role`) and the
 *    chosen option is the rule's `value`;
 *  - `suffix` sources (cart_has_category / cart_has_product) append the chosen
 *    id to the token (e.g. `@cart_has_category:12`) and the rule's value is `yes`;
 *  - `number` sources (cart_qty / cart_subtotal) take a free numeric value.
 */
final class SourceCatalog {

	/** @return array<int,array{value:string,label:string}> */
	public static function locations(): array {
		return array(
			array( 'value' => 'after_order_notes', 'label' => __( 'After order notes', 'corelabs-checkout-fields' ) ),
			array( 'value' => 'before_order_notes', 'label' => __( 'Before order notes', 'corelabs-checkout-fields' ) ),
			array( 'value' => 'after_customer_details', 'label' => __( 'After customer details', 'corelabs-checkout-fields' ) ),
			array( 'value' => 'before_payment', 'label' => __( 'Before payment', 'corelabs-checkout-fields' ) ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function sources(): array {
		return array(
			array( 'token' => '@user_role', 'label' => __( 'User role', 'corelabs-checkout-fields' ), 'input' => 'select', 'values' => self::roles() ),
			array( 'token' => '@payment_method', 'label' => __( 'Payment method', 'corelabs-checkout-fields' ), 'input' => 'select', 'values' => self::gateways() ),
			array( 'token' => '@shipping_method', 'label' => __( 'Shipping method', 'corelabs-checkout-fields' ), 'input' => 'select', 'values' => self::shipping_methods() ),
			array( 'token' => '@billing_country', 'label' => __( 'Billing country', 'corelabs-checkout-fields' ), 'input' => 'select', 'values' => self::countries() ),
			array( 'token' => '@shipping_country', 'label' => __( 'Shipping country', 'corelabs-checkout-fields' ), 'input' => 'select', 'values' => self::countries() ),
			array( 'token' => '@cart_has_category', 'label' => __( 'Cart has category', 'corelabs-checkout-fields' ), 'input' => 'suffix', 'values' => self::categories() ),
			array( 'token' => '@cart_qty', 'label' => __( 'Cart item count', 'corelabs-checkout-fields' ), 'input' => 'number' ),
			array( 'token' => '@cart_subtotal', 'label' => __( 'Cart subtotal', 'corelabs-checkout-fields' ), 'input' => 'number' ),
		);
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function roles(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}
		$out = array( array( 'value' => 'guest', 'label' => __( 'Guest (not logged in)', 'corelabs-checkout-fields' ) ) );
		foreach ( wp_roles()->get_names() as $key => $name ) {
			$out[] = array( 'value' => (string) $key, 'label' => translate_user_role( $name ) );
		}
		return $out;
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function gateways(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}
		$out = array();
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$out[] = array( 'value' => (string) $id, 'label' => $gateway->get_method_title() ? $gateway->get_method_title() : (string) $id );
		}
		return $out;
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function shipping_methods(): array {
		if ( ! class_exists( '\WC_Shipping_Zones' ) ) {
			return array();
		}
		$out   = array();
		$zones = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'shipping_methods' => \WC_Shipping_Zones::get_zone( 0 )->get_shipping_methods() );
		foreach ( $zones as $zone ) {
			foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
				$rate_id = $method->id . ':' . $method->instance_id;
				$out[ $rate_id ] = array( 'value' => $rate_id, 'label' => $method->get_title() ? $method->get_title() : $method->id );
			}
		}
		return array_values( $out );
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function countries(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}
		$out = array();
		foreach ( WC()->countries->get_countries() as $code => $name ) {
			$out[] = array( 'value' => (string) $code, 'label' => (string) $name );
		}
		return $out;
	}

	/** @return array<int,array{value:string,label:string}> */
	private static function categories(): array {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array( 'value' => (string) $term->term_id, 'label' => (string) $term->name );
		}
		return $out;
	}
}
