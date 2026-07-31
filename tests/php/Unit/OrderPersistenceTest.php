<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Checkout\OrderPersistence;
use Brain\Monkey;

class OrderPersistenceTest extends TestCase {

	private function fakeOrder() {
		return new class() {
			/** @var array<int,array{key:string,value:mixed}> */
			public array $meta = array();
			public function add_meta_data( $key, $value, $unique = false ) {
				$this->meta[] = array( 'key' => (string) $key, 'value' => $value );
			}
		};
	}

	public function test_save_persists_active_fields_and_skips_hidden(): void {
		$order = $this->fakeOrder();
		$group = array(
			'fields' => array(
				array( 'id' => 'note', 'type' => 'text', 'label' => 'Delivery note' ),
				array( 'id' => 'gift', 'type' => 'checkbox', 'label' => 'Gift wrap' ),
				array(
					'id'              => 'vat',
					'type'            => 'text',
					'label'           => 'VAT',
					'conditions'      => array( array( 'field' => '@user_role', 'operator' => 'is', 'value' => 'wholesale' ) ),
					'conditionMatch'  => 'all',
					'conditionAction' => 'show',
				),
			),
		);
		$field_values = array( 'note' => 'Leave at door', 'gift' => 'yes', 'vat' => 'SHOULD-NOT-SAVE' );
		$values       = array_merge( $field_values, array( '@user_role' => 'guest' ) ); // vat inactive for guest

		( new OrderPersistence() )->save( $order, $group, $field_values, $values );

		$byKey = array();
		foreach ( $order->meta as $m ) {
			$byKey[ $m['key'] ] = $m['value'];
		}

		// Human-readable per-field meta for the two active fields.
		$this->assertSame( 'Leave at door', $byKey['Delivery note'] );
		$this->assertSame( 'Yes', $byKey['Gift wrap'] );
		$this->assertArrayNotHasKey( 'VAT', $byKey ); // hidden field not persisted

		// Machine-readable bundle excludes the hidden field.
		$this->assertArrayHasKey( '_clcf_fields', $byKey );
		$decoded = json_decode( (string) $byKey['_clcf_fields'], true );
		$ids     = array_column( $decoded, 'id' );
		$this->assertContains( 'note', $ids );
		$this->assertContains( 'gift', $ids );
		$this->assertNotContains( 'vat', $ids );
	}

	public function test_save_writes_nothing_when_no_active_values(): void {
		$order = $this->fakeOrder();
		$group = array( 'fields' => array( array( 'id' => 'note', 'type' => 'text', 'label' => 'Note' ) ) );
		( new OrderPersistence() )->save( $order, $group, array(), array() );
		$this->assertSame( array(), $order->meta );
	}

	public function test_one_dead_token_does_not_discard_the_other_files(): void {
		$alive = str_repeat( 'a1', 16 );
		$dead  = str_repeat( 'd0', 16 );

		// consume() reads clcf_upload_<token>; the dead one resolves to nothing.
		Monkey\Functions\when( 'get_option' )->alias(
			static function ( $name ) use ( $alive ) {
				return 'clcf_upload_' . $alive === $name
					? array( 'url' => 'https://example.test/a.png', 'name' => 'a.png' )
					: false;
			}
		);
		Monkey\Functions\when( 'delete_option' )->justReturn( true );

		$order = $this->fakeOrder();
		$group = array( 'fields' => array( array( 'id' => 'art', 'type' => 'file', 'label' => 'Artwork' ) ) );
		$vals  = array( 'art' => $dead . ',' . $alive );

		( new OrderPersistence() )->save( $order, $group, $vals, $vals );

		$rows = array_values(
			array_filter( $order->meta, static fn( $m ) => 'Artwork' === $m['key'] )
		);
		$this->assertCount( 1, $rows, 'the surviving file must still be recorded' );
		$this->assertStringContainsString( 'a.png', (string) $rows[0]['value'] );
	}

	public function test_several_files_are_each_recorded(): void {
		$one = str_repeat( 'a1', 16 );
		$two = str_repeat( 'b2', 16 );
		Monkey\Functions\when( 'get_option' )->alias(
			static function ( $name ) use ( $one, $two ) {
				if ( 'clcf_upload_' . $one === $name ) {
					return array( 'url' => 'https://example.test/one.png', 'name' => 'one.png' );
				}
				if ( 'clcf_upload_' . $two === $name ) {
					return array( 'url' => 'https://example.test/two.webp', 'name' => 'two.webp' );
				}
				return false;
			}
		);
		Monkey\Functions\when( 'delete_option' )->justReturn( true );

		$order = $this->fakeOrder();
		$group = array( 'fields' => array( array( 'id' => 'art', 'type' => 'file', 'label' => 'Artwork' ) ) );
		$vals  = array( 'art' => $one . ',' . $two );

		( new OrderPersistence() )->save( $order, $group, $vals, $vals );

		$meta = array_values( array_filter( $order->meta, static fn( $m ) => 'Artwork' === $m['key'] ) );
		$this->assertCount( 1, $meta );
		$this->assertStringContainsString( 'one.png', (string) $meta[0]['value'] );
		$this->assertStringContainsString( 'two.webp', (string) $meta[0]['value'] );
	}
}
