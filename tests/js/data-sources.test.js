/**
 * JS/PHP parity: buildValues must match every case in the shared fixture
 * (the same file CheckoutConditionData::from_values is tested against).
 */
import { buildValues } from '../../src/checkout-conditions/data-sources';
import cases from '../fixtures/condition-data-cases.json';

describe( 'buildValues (parity with PHP CheckoutConditionData::from_values)', () => {
	cases.forEach( ( c ) => {
		it( c.name, () => {
			expect( buildValues( c.fieldValues, c.ctx ) ).toEqual( c.expected );
		} );
	} );

	it( 'coerces a non-scalar field value to an empty string', () => {
		const out = buildValues( { a: { x: 1 }, b: 'ok' }, { role: 'customer' } );
		expect( out.a ).toBe( '' );
		expect( out.b ).toBe( 'ok' );
		expect( out[ '@user_role' ] ).toBe( 'customer' );
	} );
} );
