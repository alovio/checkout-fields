<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Validate submitted checkout-field values against a normalized field group.
 * Pure logic — returns a list of human-readable error strings.
 *
 * Visibility/required are resolved against the FULL token map ($values, from
 * CheckoutConditionData) so context-based conditions (role/cart/payment/...) are
 * honoured; presence/type checks use the bare-id $field_values.
 */
final class Validator {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $values       full token map for the engine
	 * @return string[]
	 */
	public static function validate( array $group, array $field_values, array $values ): array {
		$errors = array();
		$active = ConditionalLogic::active_map( $group, $values );

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue; // hidden fields are not validated.
			}

			$type = (string) ( $f['type'] ?? '' );
			if ( 'heading' === $type ) {
				continue;
			}

			$label = ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;
			$value = $field_values[ $id ] ?? null;

			$required = ! empty( $f['required'] ) || ConditionalLogic::requires( $f, $values );
			$is_empty = ( null === $value || '' === $value || array() === $value || false === $value );

			if ( $required && $is_empty ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” is required.', 'corelabs-checkout-fields' ), $label );
				continue;
			}
			if ( $is_empty ) {
				continue;
			}

			if ( 'number' === $type && ! is_numeric( $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a number.', 'corelabs-checkout-fields' ), $label );
			}

			if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $f['options'] ) ) {
				$valid = array_map( 'strval', (array) $f['options'] );
				if ( ! in_array( (string) $value, $valid, true ) ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” has an invalid selection.', 'corelabs-checkout-fields' ), $label );
				}
			}
		}

		return $errors;
	}
}
