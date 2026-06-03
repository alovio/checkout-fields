<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Pro;

defined( 'ABSPATH' ) || exit;

/**
 * Pro gate. When Pro is active (the `clcf_is_pro` filter, flipped by the separate
 * Pro add-on plugin), it lifts the free restrictions.
 *
 * IMPORTANT — the free/Pro line differs from the Product Options flagship:
 * the FULL conditional logic engine (multi-condition AND/OR + every operator) is
 * FREE here — that is the entire wedge. Pro only unlocks adjacent power:
 * extra field types (file, date) and pricing modes (per_unit, percent).
 *
 * Building Pro behind this gate decouples it from any license wiring — Pro can be
 * developed/tested now by returning true from `clcf_is_pro`.
 */
final class ProModule {

	public static function is_pro(): bool {
		return (bool) apply_filters( 'clcf_is_pro', false );
	}

	public function register(): void {
		if ( ! self::is_pro() ) {
			return;
		}
		add_filter( 'clcf_field_types', array( $this, 'field_types' ) );
		add_filter( 'clcf_price_modes', array( $this, 'price_modes' ) );
	}

	/**
	 * @param string[] $types
	 * @return string[]
	 */
	public function field_types( $types ): array {
		return array_merge( (array) $types, array( 'file', 'date' ) );
	}

	/**
	 * @param string[] $modes
	 * @return string[]
	 */
	public function price_modes( $modes ): array {
		return array( 'fixed', 'per_unit', 'percent' );
	}
}
