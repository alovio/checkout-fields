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

describe( 'undo history', () => {
	it( 'UNDO restores the previous fields state', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, actions.updateField( 'a', { label: 'Changed' } ) );
		expect( s.fields[ 0 ].label ).toBe( 'Changed' );
		s = reducer( s, actions.undo() );
		expect( s.fields[ 0 ].label ).not.toBe( 'Changed' );
	} );

	it( 'history is bounded at 25 snapshots', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		for ( let i = 0; i < 40; i++ ) {
			s = reducer( s, actions.updateField( 'a', { label: `L${ i }` } ) );
		}
		expect( s.past.length ).toBeLessThanOrEqual( 25 );
	} );

	it( 'HYDRATE clears history; UNDO with empty history is a no-op', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, actions.hydrate( [ { id: 'x', type: 'text' } ] ) );
		expect( s.past ).toHaveLength( 0 );
		const same = reducer( s, actions.undo() );
		expect( same.fields ).toEqual( s.fields );
	} );
} );

describe( 'simulation state', () => {
	it( 'SET_SIM merges patches', () => {
		let s = reducer( initialState, actions.setSim( { payment: 'cod' } ) );
		s = reducer( s, actions.setSim( { role: 'wholesale' } ) );
		expect( s.sim.payment ).toBe( 'cod' );
		expect( s.sim.role ).toBe( 'wholesale' );
	} );

	it( 'sim changes are not undoable', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		const before = s.past.length;
		s = reducer( s, actions.setSim( { payment: 'cod' } ) );
		expect( s.past.length ).toBe( before );
	} );
} );

describe( 'INSERT_FIELDS (templates)', () => {
	const recipe = [
		{ id: 'gate', type: 'checkbox', label: 'Gate?' },
		{
			id: 'note', type: 'textarea', label: 'Note',
			conditions: [ { field: 'gate', operator: 'is', value: 'yes' } ],
			conditionMatch: 'all', conditionAction: 'show',
		},
	];

	it( 'inserts with fresh unique ids and remapped sibling refs; @-tokens untouched', () => {
		let s = reducer( initialState, actions.insertFields( recipe ) );
		expect( s.fields ).toHaveLength( 2 );
		const [ f1, f2 ] = s.fields;
		expect( f1.id ).not.toBe( 'gate' );
		expect( f2.conditions[ 0 ].field ).toBe( f1.id );
		// insert again — ids must not collide
		s = reducer( s, actions.insertFields( recipe ) );
		const ids = s.fields.map( ( f ) => f.id );
		expect( new Set( ids ).size ).toBe( 4 );

		const tokenRecipe = [ {
			id: 'cod', type: 'checkbox', label: 'C',
			conditions: [ { field: '@payment_method', operator: 'is', value: 'cod' } ],
			conditionMatch: 'all', conditionAction: 'show',
		} ];
		s = reducer( s, actions.insertFields( tokenRecipe ) );
		expect( s.fields[ 4 ].conditions[ 0 ].field ).toBe( '@payment_method' );
	} );

	it( 'selects the first inserted field', () => {
		const s = reducer( initialState, actions.insertFields( recipe ) );
		expect( s.selectedId ).toBe( s.fields[ 0 ].id );
	} );
} );
