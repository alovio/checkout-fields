<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write the store-wide checkout field-group definition (stored as JSON in a
 * single option). Checkout fields are global to the store — not per-product — so
 * an option is the correct home. Always normalized through FieldSchema in and out.
 */
final class FieldRepository {

	private const OPTION = 'clcf_field_group';

	/** @return array<string,mixed> */
	public function get(): array {
		$raw = get_option( self::OPTION, '' );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = array();
		}
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		return FieldSchema::normalize( $decoded );
	}

	/**
	 * @param array<string,mixed> $group
	 * @return array<string,mixed> The normalized group that was stored.
	 */
	public function save( array $group ): array {
		$normalized = FieldSchema::normalize( $group );
		update_option( self::OPTION, wp_json_encode( $normalized ) );

		return $normalized;
	}
}
