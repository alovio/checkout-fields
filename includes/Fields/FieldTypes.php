<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported checkout-field types. The free set is fixed; Pro extends
 * it via the `clcf_field_types` filter (adds file, date).
 */
final class FieldTypes {

	/** @var string[] Free-tier field types. */
	public const FREE = array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' );

	/** @return string[] */
	public static function all(): array {
		return (array) apply_filters( 'clcf_field_types', self::FREE );
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
