<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldOptions;

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
				case 'file':
					// Value is a list of upload tokens, never a raw file. A
					// multi-file field posts several, comma-joined.
					$tokens = FileUploads::parse_tokens( $val );
					$tokens = array_slice( $tokens, 0, max( 1, (int) ( $f['maxFiles'] ?? 1 ) ) );
					if ( array() !== $tokens ) {
						$out[ $id ] = implode( ',', $tokens );
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
					// Options may be bare strings or {label, price}.
					$opts = FieldOptions::labels( $f );
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
