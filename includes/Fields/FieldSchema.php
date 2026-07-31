<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Validate and normalize a decoded checkout field-group definition. Pure logic:
 * drops unknown types, de-duplicates ids, and validates each conditional rule's
 * controller.
 *
 * Controller tokens (a rule's `field`):
 *  - a sibling field id (bare slug) — must exist and not be the field itself;
 *  - a context source token, prefixed `@` (e.g. `@user_role`,
 *    `@cart_has_category:12`, `@payment_method`) — always accepted. The `@`
 *    prefix cannot collide with a sanitize_key'd field id, and such tokens are
 *    absent from the field map so ConditionalLogic::active_map treats them as
 *    always-present (correct for context sources) while sibling ids stay
 *    transitively resolvable.
 *
 * Unlike the Product Options flagship, multi-condition AND/OR and all operators
 * are available in the FREE tier here — that is the plugin's wedge.
 */
final class FieldSchema {

	/** @var string[] Allowed checkout render regions. */
	private const LOCATIONS = array( 'after_order_notes', 'before_order_notes', 'after_customer_details', 'before_payment' );

	public static function normalize( array $raw ): array {
		$fields = ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) ? $raw['fields'] : array();

		$ops     = (array) apply_filters( 'clcf_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) );
		$actions = (array) apply_filters( 'clcf_allowed_actions', array( 'show', 'hide', 'require' ) );

		// First pass: keep valid-typed fields with unique, non-empty ids.
		$kept = array();
		$seen = array();
		foreach ( $fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type = isset( $f['type'] ) ? (string) $f['type'] : '';
			if ( ! FieldTypes::is_valid( $type ) ) {
				continue;
			}
			$id = isset( $f['id'] ) ? sanitize_key( (string) $f['id'] ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$f['id']     = $id;
			$kept[]      = $f;
		}
		$ids = array_keys( $seen );

		// Second pass: normalize each kept field.
		$out = array();
		foreach ( $kept as $f ) {
			$entry = array(
				'id'          => (string) $f['id'],
				'type'        => (string) $f['type'],
				'label'       => isset( $f['label'] ) ? sanitize_text_field( (string) $f['label'] ) : '',
				'required'    => ! empty( $f['required'] ),
				'placeholder' => isset( $f['placeholder'] ) ? sanitize_text_field( (string) $f['placeholder'] ) : '',
				'description' => isset( $f['description'] ) ? sanitize_text_field( (string) $f['description'] ) : '',
				'default'     => isset( $f['default'] ) ? sanitize_text_field( (string) $f['default'] ) : '',
				'location'    => self::normalize_location( $f ),
				'width'       => in_array( $f['width'] ?? '', array( 'full', 'half' ), true ) ? (string) $f['width'] : 'full',
				'price'       => max( 0.0, (float) ( $f['price'] ?? 0 ) ),
				'priceMode'   => in_array( $f['priceMode'] ?? '', array( 'fixed', 'per_unit', 'percent' ), true ) ? (string) $f['priceMode'] : 'fixed',
				'options'     => self::normalize_options( $f ),
			);
			$out[] = array_merge(
				$entry,
				self::normalize_conditional( $f, $ids, (string) $f['id'], $ops, $actions ),
				self::normalize_constraints( $f )
			);
		}

		return array(
			'version' => 1,
			'fields'  => $out,
		);
	}

	private static function normalize_location( array $f ): string {
		$loc = isset( $f['location'] ) ? (string) $f['location'] : '';
		return in_array( $loc, self::LOCATIONS, true ) ? $loc : 'after_order_notes';
	}

	/**
	 * A rule controller is valid if it is a context source token (`@…`) or an
	 * existing sibling field id that is not the field referencing it.
	 *
	 * @param string[] $ids
	 */
	private static function valid_controller( string $field, array $ids, string $self_id ): bool {
		if ( '' === $field ) {
			return false;
		}
		if ( '@' === $field[0] ) {
			return true;
		}
		return $field !== $self_id && in_array( $field, $ids, true );
	}

	/**
	 * Build the conditional keys: a `conditions` array (AND/OR) when present and
	 * valid, otherwise a single `condition` (or null). Multi is always allowed.
	 *
	 * @param array<string,mixed> $f
	 * @param string[]            $ids
	 * @param string[]            $ops
	 * @param string[]            $actions
	 * @return array<string,mixed>
	 */
	private static function normalize_conditional( array $f, array $ids, string $self_id, array $ops, array $actions ): array {
		if ( ! empty( $f['conditions'] ) && is_array( $f['conditions'] ) ) {
			$rules = array();
			foreach ( $f['conditions'] as $rule ) {
				$nr = self::normalize_rule( is_array( $rule ) ? $rule : array(), $ids, $self_id, $ops );
				if ( null !== $nr ) {
					$rules[] = $nr;
				}
			}
			if ( ! empty( $rules ) ) {
				$action = isset( $f['conditionAction'] ) ? (string) $f['conditionAction'] : 'show';
				if ( ! in_array( $action, $actions, true ) ) {
					$action = $actions[0] ?? 'show';
				}
				return array(
					'condition'       => null,
					'conditions'      => $rules,
					'conditionMatch'  => ( 'any' === ( $f['conditionMatch'] ?? 'all' ) ) ? 'any' : 'all',
					'conditionAction' => $action,
				);
			}
		}
		return array( 'condition' => self::normalize_condition( $f['condition'] ?? null, $ids, $self_id, $ops, $actions ) );
	}

	/**
	 * @param mixed    $cond
	 * @param string[] $ids
	 * @param string[] $ops
	 * @param string[] $actions
	 * @return array<string,mixed>|null
	 */
	private static function normalize_condition( $cond, array $ids, string $self_id, array $ops, array $actions ) {
		if ( ! is_array( $cond ) ) {
			return null;
		}
		$field = isset( $cond['field'] ) ? (string) $cond['field'] : '';
		if ( ! self::valid_controller( $field, $ids, $self_id ) ) {
			return null;
		}
		$op = isset( $cond['operator'] ) ? (string) $cond['operator'] : '';
		if ( ! in_array( $op, $ops, true ) ) {
			$op = $ops[0] ?? 'is';
		}
		$action = isset( $cond['action'] ) ? (string) $cond['action'] : '';
		if ( ! in_array( $action, $actions, true ) ) {
			$action = $actions[0] ?? 'show';
		}
		return array(
			'field'    => $field,
			'operator' => $op,
			'value'    => isset( $cond['value'] ) ? sanitize_text_field( (string) $cond['value'] ) : '',
			'action'   => $action,
		);
	}

	/**
	 * @param array<string,mixed> $rule
	 * @param string[]            $ids
	 * @param string[]            $ops
	 * @return array<string,mixed>|null
	 */
	private static function normalize_rule( array $rule, array $ids, string $self_id, array $ops ): ?array {
		$field = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		if ( ! self::valid_controller( $field, $ids, $self_id ) ) {
			return null;
		}
		$op = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		if ( ! in_array( $op, $ops, true ) ) {
			$op = $ops[0] ?? 'is';
		}
		return array(
			'field'    => $field,
			'operator' => $op,
			'value'    => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
		);
	}

	/**
	 * Options keep the leanest shape that carries their data: a plain string
	 * when a choice is just a label, an object once it needs a price. Field
	 * groups saved before per-option pricing therefore normalize to exactly
	 * what they already were — no migration.
	 *
	 * This method is the gate every option passes through: FieldRepository
	 * normalizes on both read and write, so anything dropped here never
	 * reaches the database.
	 *
	 * @param array<string,mixed> $f
	 * @return array<int,mixed>
	 */
	private static function normalize_options( array $f ): array {
		$raw = ( isset( $f['options'] ) && is_array( $f['options'] ) ) ? $f['options'] : array();
		$out = array();

		foreach ( $raw as $o ) {
			$label = trim( sanitize_text_field( FieldOptions::label( $o ) ) );
			if ( '' === $label ) {
				continue; // an unlabelled choice cannot be selected.
			}
			$price = FieldOptions::price( $o );
			$out[] = $price > 0 ? array( 'label' => $label, 'price' => $price ) : $label;
		}

		return $out;
	}

	/**
	 * Type-specific constraints.
	 *
	 * @param array<string,mixed> $f
	 * @return array<string,mixed>
	 */
	private static function normalize_constraints( array $f ): array {
		$type = (string) ( $f['type'] ?? '' );

		if ( 'number' === $type ) {
			$num = static fn( $k ) => ( isset( $f[ $k ] ) && '' !== $f[ $k ] && is_numeric( $f[ $k ] ) ) ? (string) ( $f[ $k ] + 0 ) : '';
			return array(
				'min'  => $num( 'min' ),
				'max'  => $num( 'max' ),
				'step' => $num( 'step' ),
			);
		}

		if ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
			$maxlen = ( isset( $f['maxLength'] ) && is_numeric( $f['maxLength'] ) ) ? (int) $f['maxLength'] : 0;
			return $maxlen > 0 ? array( 'maxLength' => $maxlen ) : array();
		}

		// Pro date type: optional bounds (kept for forward-compat; date dropped in free).
		if ( 'date' === $type ) {
			return array(
				'min' => sanitize_text_field( (string) ( $f['min'] ?? '' ) ),
				'max' => sanitize_text_field( (string) ( $f['max'] ?? '' ) ),
			);
		}

		if ( 'file' === $type ) {
			$max = ( isset( $f['maxFiles'] ) && is_numeric( $f['maxFiles'] ) ) ? (int) $f['maxFiles'] : 1;
			return array( 'maxFiles' => min( 10, max( 1, $max ) ) );
		}

		return array();
	}
}
