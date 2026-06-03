<?php
declare( strict_types=1 );

namespace CoreLabs\CheckoutFields\Tests\Unit;

use CoreLabs\CheckoutFields\Fields\FieldSchema;

class FieldSchemaTest extends TestCase {

	public function test_sets_version_and_drops_unknown_types(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'note', 'type' => 'text', 'label' => 'Note' ),
					array( 'id' => 'x', 'type' => 'rocket', 'label' => 'nope' ),
				),
			)
		);
		$this->assertSame( 1, $out['version'] );
		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'note', $out['fields'][0]['id'] );
		$this->assertSame( 'Note', $out['fields'][0]['label'] );
	}

	public function test_drops_duplicate_and_empty_ids(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'text' ),
					array( 'id' => 'a', 'type' => 'text' ),
					array( 'id' => '', 'type' => 'text' ),
				),
			)
		);
		$this->assertCount( 1, $out['fields'] );
	}

	public function test_multi_condition_is_kept_in_free_tier(): void {
		// The wedge: AND/OR multi-conditions are FREE here (gated in the flagship).
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'biz', 'type' => 'checkbox' ),
					array(
						'id'              => 'vat',
						'type'            => 'text',
						'conditions'      => array(
							array( 'field' => 'biz', 'operator' => 'is', 'value' => 'yes' ),
							array( 'field' => '@user_role', 'operator' => 'is', 'value' => 'wholesale' ),
						),
						'conditionMatch'  => 'all',
						'conditionAction' => 'show',
					),
				),
			)
		);
		$vat = $out['fields'][1];
		$this->assertCount( 2, $vat['conditions'] );
		$this->assertSame( 'all', $vat['conditionMatch'] );
		$this->assertSame( 'show', $vat['conditionAction'] );
		$this->assertNull( $vat['condition'] );
	}

	public function test_context_source_token_condition_is_kept(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'        => 'cod_note',
						'type'      => 'textarea',
						'condition' => array( 'field' => '@payment_method', 'operator' => 'is', 'value' => 'cod', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertSame( '@payment_method', $out['fields'][0]['condition']['field'] );
		$this->assertSame( 'cod', $out['fields'][0]['condition']['value'] );
	}

	public function test_sibling_reference_to_missing_field_is_dropped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'        => 'vat',
						'type'      => 'text',
						'condition' => array( 'field' => 'ghost', 'operator' => 'is', 'value' => 'x', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertNull( $out['fields'][0]['condition'] );
	}

	public function test_self_reference_is_dropped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'        => 'vat',
						'type'      => 'text',
						'condition' => array( 'field' => 'vat', 'operator' => 'is', 'value' => 'x', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertNull( $out['fields'][0]['condition'] );
	}

	public function test_location_defaults_and_validates(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'text', 'location' => 'before_payment' ),
					array( 'id' => 'b', 'type' => 'text', 'location' => 'bogus' ),
					array( 'id' => 'c', 'type' => 'text' ),
				),
			)
		);
		$this->assertSame( 'before_payment', $out['fields'][0]['location'] );
		$this->assertSame( 'after_order_notes', $out['fields'][1]['location'] );
		$this->assertSame( 'after_order_notes', $out['fields'][2]['location'] );
	}

	public function test_number_constraints_normalized(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'n', 'type' => 'number', 'min' => '1', 'max' => '10', 'step' => '0.5' ),
				),
			)
		);
		$this->assertSame( '1', $out['fields'][0]['min'] );
		$this->assertSame( '10', $out['fields'][0]['max'] );
		$this->assertSame( '0.5', $out['fields'][0]['step'] );
	}
}
