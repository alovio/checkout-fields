<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the list of selectable delivery dates for a "delivery" field from its
 * constraints (disabled weekdays/dates, minimum lead time, daily cutoff, window).
 *
 * The list is built here — server-side, in the site timezone — and both the
 * classic renderer and the Block config use it, so the two checkouts offer the
 * exact same dates without duplicating the date maths in JavaScript. The same
 * class is the authority the Validator re-checks against on submit.
 *
 * The date-membership logic is WordPress-independent (a `$now` can be injected)
 * so it is unit-testable; only the human label uses WP date formatting.
 */
final class DeliveryDates {

	private static function tz(): \DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
	}

	/** Earliest selectable date (today + lead, plus one more day once past the cutoff). */
	public static function earliest( array $f, ?\DateTimeImmutable $now = null ): \DateTimeImmutable {
		$now  = $now ?? new \DateTimeImmutable( 'now', self::tz() );
		$lead = max( 0, (int) ( $f['minLeadDays'] ?? 0 ) );

		$cutoff = (string) ( $f['cutoffTime'] ?? '' );
		if ( '' !== $cutoff && $now->format( 'H:i' ) >= $cutoff ) {
			++$lead;
		}
		return $now->setTime( 0, 0, 0 )->modify( '+' . $lead . ' days' );
	}

	/**
	 * Selectable dates as { value: Y-m-d, label: localized }.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public static function valid_dates( array $f, ?\DateTimeImmutable $now = null ): array {
		$start     = self::earliest( $f, $now );
		$daysAhead = min( 365, max( 1, (int) ( $f['daysAhead'] ?? 21 ) ) );
		$weekdays  = array_map( 'intval', (array) ( $f['disabledWeekdays'] ?? array() ) );
		$dates     = (array) ( $f['disabledDates'] ?? array() );

		$out    = array();
		$cursor = $start;
		for ( $i = 0; $i <= $daysAhead; $i++ ) {
			$ymd = $cursor->format( 'Y-m-d' );
			$wd  = (int) $cursor->format( 'w' ); // 0 (Sun) .. 6 (Sat)
			if ( ! in_array( $wd, $weekdays, true ) && ! in_array( $ymd, $dates, true ) ) {
				$out[] = array(
					'value' => $ymd,
					'label' => self::label( $cursor ),
				);
			}
			$cursor = $cursor->modify( '+1 day' );
		}
		return $out;
	}

	/** Is $date (Y-m-d) one of the currently selectable dates? The submit-time gate. */
	public static function is_valid( array $f, string $date, ?\DateTimeImmutable $now = null ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		foreach ( self::valid_dates( $f, $now ) as $d ) {
			if ( $d['value'] === $date ) {
				return true;
			}
		}
		return false;
	}

	private static function label( \DateTimeImmutable $d ): string {
		if ( function_exists( 'wp_date' ) && function_exists( 'get_option' ) ) {
			$fmt = (string) get_option( 'date_format' );
			return (string) wp_date( '' !== $fmt ? $fmt : 'M j, Y', $d->getTimestamp() );
		}
		return $d->format( 'M j, Y' );
	}
}
