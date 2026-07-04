/**
 * Recipe integrity: every template inserts cleanly through INSERT_FIELDS and
 * produces valid free-tier field shapes.
 */
import { TEMPLATES } from '../../src/builder/templates';
import { reducer, actions, initialState } from '../../src/builder/reducer';

const FREE_TYPES = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' ];
const REGIONS = [ 'after_order_notes', 'before_order_notes', 'after_customer_details', 'before_payment' ];

describe( 'templates', () => {
	it( 'has the four launch recipes with unique keys', () => {
		const keys = TEMPLATES.map( ( t ) => t.key );
		expect( keys ).toEqual( [ 'cod_confirmation', 'gift_message', 'vat_b2b', 'delivery_notes' ] );
		expect( new Set( keys ).size ).toBe( keys.length );
	} );

	TEMPLATES.forEach( ( tpl ) => {
		it( `"${ tpl.key }" inserts valid fields`, () => {
			const s = reducer( initialState, actions.insertFields( tpl.fields ) );
			expect( s.fields ).toHaveLength( tpl.fields.length );

			const ids = s.fields.map( ( f ) => f.id );
			expect( new Set( ids ).size ).toBe( ids.length );

			s.fields.forEach( ( f ) => {
				expect( FREE_TYPES ).toContain( f.type );
				expect( REGIONS ).toContain( f.location );
				expect( [ 'full', 'half' ] ).toContain( f.width );
				( f.conditions || [] ).forEach( ( r ) => {
					const isToken = r.field[ 0 ] === '@';
					const isSibling = ids.includes( r.field );
					expect( isToken || isSibling ).toBe( true );
				} );
			} );
		} );
	} );
} );
