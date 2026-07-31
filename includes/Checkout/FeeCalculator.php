<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

use CoreLabs\CheckoutFields\Fields\FieldOptions;
use CoreLabs\CheckoutFields\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the total checkout-field fee. A field adds a fee when it is active
 * (conditional logic) AND engaged (filled/checked/selected). Pure logic,
 * unit-tested. Free — no gating.
 *
 * Modes: fixed (flat), per_unit (number field: price × value), percent (of $base).
 */
final class FeeCalculator {

	/** @var string[] Supported pricing modes (extensibility point, not a lock). */
	public static function modes(): array {
		return (array) apply_filters( 'clcf_price_modes', array( 'fixed', 'per_unit', 'percent' ) );
	}

	/**
	 * Per-field fee rows — the single source of truth for what the checkout
	 * charges AND for what it shows. Rows are rounded here so the printed
	 * lines, the printed total and the charged total agree by construction.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $values       full token map (for visibility)
	 * @param float               $base         subtotal used for percentage fees
	 * @param int|null            $decimals     store decimals; resolved when null
	 * @return array<int,array{field_id:string,label:string,amount:float}>
	 */
	public static function breakdown( array $group, array $field_values, array $values, float $base = 0.0, ?int $decimals = null ): array {
		if ( null === $decimals ) {
			$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		}
		$modes  = self::modes();
		$active = ConditionalLogic::active_map( $group, $values );
		$rows   = array();

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			$val  = $field_values[ $id ] ?? null;
			// A priced option lifts a field that carries no price of its own.
			$price = FieldOptions::effective_price( $f, $val );
			if ( $price <= 0 || ! self::is_engaged( $type, $val ) ) {
				continue;
			}

			$mode = (string) ( $f['priceMode'] ?? 'fixed' );
			if ( ! in_array( $mode, $modes, true ) ) {
				$mode = 'fixed';
			}

			if ( 'per_unit' === $mode && 'number' === $type ) {
				$amount = $price * (float) $val;
			} elseif ( 'percent' === $mode ) {
				$amount = $base * $price / 100;
			} else {
				$amount = $price;
			}

			$amount = round( $amount, $decimals );
			if ( $amount <= 0 ) {
				continue; // never emit a 0.00 fee line.
			}

			$rows[] = array(
				'field_id' => $id,
				'label'    => self::row_label( $f, $val ),
				'amount'   => $amount,
			);
		}

		return $rows;
	}

	/**
	 * What an itemized fee line reads as. Choice fields carry the chosen value,
	 * because "Shipping speed £12.00" alone does not say which speed.
	 *
	 * @param array<string,mixed> $f
	 * @param mixed               $val
	 */
	private static function row_label( array $f, $val ): string {
		$label = (string) ( $f['label'] ?? '' );
		if ( '' === $label ) {
			$label = (string) ( $f['id'] ?? '' );
		}
		if ( in_array( (string) ( $f['type'] ?? '' ), FieldOptions::CHOICE_TYPES, true ) && is_scalar( $val ) && '' !== $val ) {
			return $label . ': ' . (string) $val;
		}
		return $label;
	}

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $field_values field id => submitted value
	 * @param array<string,mixed> $values       full token map (for visibility)
	 * @param float               $base         subtotal used for percentage fees
	 */
	public static function total( array $group, array $field_values, array $values, float $base = 0.0 ): float {
		$total = 0.0;
		foreach ( self::breakdown( $group, $field_values, $values, $base ) as $row ) {
			$total += $row['amount'];
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
