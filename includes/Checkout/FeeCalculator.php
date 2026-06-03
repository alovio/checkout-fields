<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the total checkout-field fee (Pro). A field adds a fee when it is
 * active (conditional logic) AND engaged (filled/checked/selected). Pure logic,
 * unit-tested. Returns 0 unless `clcf_price_modes` is non-empty (i.e. Pro on).
 *
 * Modes: fixed (flat), per_unit (number field: price × value), percent (of $base).
 */
final class FeeCalculator {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $values       full token map (for visibility)
	 * @param float               $base         subtotal used for percentage fees
	 */
	public static function total( array $group, array $field_values, array $values, float $base = 0.0 ): float {
		$modes = (array) apply_filters( 'clcf_price_modes', array() );
		if ( empty( $modes ) ) {
			return 0.0;
		}

		$active = ConditionalLogic::active_map( $group, $values );
		$total  = 0.0;

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue;
			}
			$price = (float) ( $f['price'] ?? 0 );
			if ( $price <= 0 ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			$val  = $field_values[ $id ] ?? null;
			if ( ! self::is_engaged( $type, $val ) ) {
				continue;
			}

			$mode = (string) ( $f['priceMode'] ?? 'fixed' );
			if ( ! in_array( $mode, $modes, true ) ) {
				$mode = 'fixed';
			}

			if ( 'per_unit' === $mode && 'number' === $type ) {
				$total += $price * (float) $val;
			} elseif ( 'percent' === $mode ) {
				$total += $base * $price / 100;
			} else {
				$total += $price;
			}
		}

		return (float) apply_filters( 'clcf_fee_total', $total, $group, $field_values, $base );
	}

	/**
	 * @param mixed $val
	 */
	private static function is_engaged( string $type, $val ): bool {
		if ( null === $val || '' === $val ) {
			return false;
		}
		if ( 'checkbox' === $type ) {
			return 'yes' === $val || true === $val || '1' === $val;
		}
		if ( 'number' === $type ) {
			return is_numeric( $val ) && 0.0 !== (float) $val;
		}
		return true;
	}
}
