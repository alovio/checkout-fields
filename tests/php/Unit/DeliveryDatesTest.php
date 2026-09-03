<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\DeliveryDates;

/**
 * The date-list logic is WordPress-independent — a fixed `$now` is injected so
 * the assertions are deterministic (no dependency on the real clock or timezone).
 */
class DeliveryDatesTest extends TestCase {

	private function now( string $s ): \DateTimeImmutable {
		return new \DateTimeImmutable( $s, new \DateTimeZone( 'UTC' ) );
	}

	public function test_excludes_disabled_weekdays_and_dates_and_honours_lead(): void {
		$f   = array(
			'minLeadDays'      => 1,
			'daysAhead'        => 14,
			'disabledWeekdays' => array( 0, 6 ), // no weekends
			'disabledDates'    => array( '2026-09-08' ),
			'cutoffTime'       => '',
		);
		$out = DeliveryDates::valid_dates( $f, $this->now( '2026-09-03 10:00' ) );
		$vals = array_column( $out, 'value' );

		$this->assertNotEmpty( $vals );
		foreach ( $vals as $v ) {
			$wd = (int) ( new \DateTimeImmutable( $v ) )->format( 'w' );
			$this->assertNotContains( $wd, array( 0, 6 ), "$v is a weekend" );
			$this->assertNotSame( '2026-09-08', $v, 'blocked date leaked' );
			$this->assertGreaterThanOrEqual( '2026-09-04', $v, 'earlier than lead time' ); // now + 1 day
		}
	}

	public function test_cutoff_pushes_the_earliest_date_to_the_next_day(): void {
		$f = array( 'minLeadDays' => 0, 'daysAhead' => 3, 'cutoffTime' => '14:00', 'disabledWeekdays' => array(), 'disabledDates' => array() );

		$before = DeliveryDates::valid_dates( $f, $this->now( '2026-09-03 10:00' ) ); // before cutoff
		$after  = DeliveryDates::valid_dates( $f, $this->now( '2026-09-03 15:00' ) ); // past cutoff

		$this->assertSame( '2026-09-03', $before[0]['value'] );
		$this->assertSame( '2026-09-04', $after[0]['value'] );
	}

	public function test_is_valid_matches_the_list(): void {
		$f = array( 'minLeadDays' => 0, 'daysAhead' => 3, 'cutoffTime' => '14:00', 'disabledWeekdays' => array(), 'disabledDates' => array() );

		$this->assertTrue( DeliveryDates::is_valid( $f, '2026-09-03', $this->now( '2026-09-03 10:00' ) ) );
		$this->assertFalse( DeliveryDates::is_valid( $f, '2026-09-03', $this->now( '2026-09-03 15:00' ) ) ); // past cutoff → today gone
		$this->assertFalse( DeliveryDates::is_valid( $f, 'not-a-date', $this->now( '2026-09-03 10:00' ) ) );
		$this->assertFalse( DeliveryDates::is_valid( array( 'daysAhead' => 3, 'disabledWeekdays' => array( 0, 1, 2, 3, 4, 5, 6 ) ), '2026-09-04', $this->now( '2026-09-03 10:00' ) ) );
	}
}
