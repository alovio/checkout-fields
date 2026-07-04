<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Type-aware sanitization of submitted checkout-field values, keyed by bare field
 * id. Used by both classic and Block submit paths.
 */
final class Sanitizer {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $raw  field id => raw submitted value
	 * @return array<string,mixed> field id => sanitized value (only non-empty/valid)
	 */
	public static function sanitize( array $group, array $raw ): array {
		$out = array();

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			$val  = $raw[ $id ] ?? null;

			switch ( $type ) {
				case 'text':
				case 'phone':
				case 'date':
				case 'time':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = sanitize_text_field( (string) $val );
					}
					break;
				case 'email':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = sanitize_email( trim( (string) $val ) );
					}
					break;
				case 'url':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = esc_url_raw( trim( (string) $val ) );
					}
					break;
				case 'textarea':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = sanitize_textarea_field( (string) $val );
					}
					break;
				case 'number':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = (float) $val;
					}
					break;
				case 'checkbox':
					if ( ! empty( $val ) ) {
						$out[ $id ] = 'yes';
					}
					break;
				case 'select':
				case 'radio':
					$opts = array_map( 'strval', (array) ( $f['options'] ?? array() ) );
					if ( null !== $val && in_array( (string) $val, $opts, true ) ) {
						$out[ $id ] = (string) $val;
					}
					break;
				// 'heading' is display-only; no value.
			}
		}

		return $out;
	}
}
