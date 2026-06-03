/**
 * Pure reducer tests (no @wordpress/data needed).
 */
import { reducer, actions, initialState, DEFAULTS } from '../../src/builder/reducer';

describe( 'builder reducer', () => {
	it( 'adds a field with type defaults and selects it', () => {
		const s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ] ).toMatchObject( { id: 'a', type: 'text', location: 'after_order_notes' } );
		expect( s.selectedId ).toBe( 'a' );
	} );

	it( 'updates a field by id', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, actions.updateField( 'a', { label: 'VAT', required: true } ) );
		expect( s.fields[ 0 ].label ).toBe( 'VAT' );
		expect( s.fields[ 0 ].required ).toBe( true );
	} );

	it( 'removes a field and strips rules that referenced it', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'checkbox', id: 'biz' } );
		s = reducer( s, { type: 'ADD_FIELD', fieldType: 'text', id: 'vat' } );
		s = reducer( s, actions.updateField( 'vat', {
			conditions: [ { field: 'biz', operator: 'is', value: 'yes' } ],
			conditionMatch: 'all',
			conditionAction: 'show',
		} ) );
		s = reducer( s, actions.removeField( 'biz' ) );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ].conditions ).toHaveLength( 0 );
	} );

	it( 'keeps context-token rules when an unrelated field is removed', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'vat' } );
		s = reducer( s, { type: 'ADD_FIELD', fieldType: 'text', id: 'tmp' } );
		s = reducer( s, actions.updateField( 'vat', {
			conditions: [ { field: '@user_role', operator: 'is', value: 'wholesale' } ],
			conditionMatch: 'all',
			conditionAction: 'show',
		} ) );
		s = reducer( s, actions.removeField( 'tmp' ) );
		expect( s.fields[ 0 ].conditions ).toEqual( [ { field: '@user_role', operator: 'is', value: 'wholesale' } ] );
	} );

	it( 'reorders fields', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, { type: 'ADD_FIELD', fieldType: 'text', id: 'b' } );
		s = reducer( s, actions.reorder( 0, 1 ) );
		expect( s.fields.map( ( f ) => f.id ) ).toEqual( [ 'b', 'a' ] );
	} );

	it( 'hydrates from saved fields', () => {
		const s = reducer( initialState, actions.hydrate( [ { id: 'x', type: 'text' } ] ) );
		expect( s.fields ).toEqual( [ { id: 'x', type: 'text' } ] );
	} );

	it( 'exposes defaults for every free type', () => {
		[ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' ].forEach( ( t ) => {
			expect( DEFAULTS[ t ] ).toBeDefined();
		} );
	} );
} );
