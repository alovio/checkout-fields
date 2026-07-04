<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported checkout-field types — all free. The `clcf_field_types`
 * filter remains as an extensibility point only, never a lock.
 */
final class FieldTypes {

	/** @var string[] Built-in field types (everything is free). */
	public const FREE = array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading', 'email', 'phone', 'url', 'date', 'time' );

	/** @return string[] */
	public static function all(): array {
		return (array) apply_filters( 'clcf_field_types', self::FREE );
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
