/**
 * Simulation drives the SAME engine as the live checkout: simToCtx + buildValues
 * + activeMap must live-toggle conditional fields exactly like the frontend.
 */
import { simToCtx, previewFieldValues, CART_PRESETS } from '../../src/builder/simulation';
import { buildValues } from '../../src/checkout-conditions/data-sources';
import { activeMap } from '../../src/frontend/conditional-logic';
import { describeCondition, conditionAction } from '../../src/builder/describe';

const FIELDS = [
	{
		id: 'cod_ack', type: 'checkbox', label: 'Cash ready',
		conditions: [ { field: '@payment_method', operator: 'is', value: 'cod' } ],
		conditionMatch: 'all', conditionAction: 'show',
	},
	{
		id: 'vat', type: 'text', label: 'VAT number',
		conditions: [ { field: '@user_role', operator: 'is', value: 'wholesale' } ],
		conditionMatch: 'all', conditionAction: 'show',
	},
	{ id: 'note', type: 'textarea', label: 'Note' },
];

function mapFor( sim ) {
	const values = buildValues( previewFieldValues( FIELDS ), simToCtx( sim ) );
	return activeMap( FIELDS, values );
}

describe( 'simulation → engine', () => {
	it( 'payment=cod shows the COD field, guest hides the VAT field', () => {
		const map = mapFor( { payment: 'cod', role: 'guest' } );
		expect( map.cod_ack ).toBe( true );
		expect( map.vat ).toBe( false );
		expect( map.note ).toBe( true );
	} );

	it( 'switching payment hides COD; wholesale shows VAT', () => {
		const map = mapFor( { payment: 'bacs', role: 'wholesale' } );
		expect( map.cod_ack ).toBe( false );
		expect( map.vat ).toBe( true );
	} );

	it( 'cart presets feed qty/subtotal tokens', () => {
		const ctx = simToCtx( { cartPreset: 'big' } );
		expect( ctx.cartQty ).toBe( 5 );
		expect( ctx.cartSubtotal ).toBe( 180 );
		expect( CART_PRESETS.map( ( p ) => p.key ) ).toContain( 'empty' );
	} );
} );

describe( 'describeCondition', () => {
	const SOURCES = [
		{ token: '@payment_method', label: 'Payment method', input: 'select', values: [ { value: 'cod', label: 'Cash on delivery' } ] },
		{ token: '@cart_has_category', label: 'Cart has category', input: 'suffix', values: [ { value: '12', label: 'Hoodies' } ] },
	];

	it( 'labels context source and value', () => {
		expect( describeCondition( FIELDS[ 0 ], SOURCES, FIELDS ) ).toBe( 'Payment method is Cash on delivery' );
		expect( conditionAction( FIELDS[ 0 ] ) ).toBe( 'SHOW' );
	} );

	it( 'labels suffix tokens by their catalog entry', () => {
		const f = { conditions: [ { field: '@cart_has_category:12', operator: 'is', value: 'yes' } ], conditionAction: 'show' };
		expect( describeCondition( f, SOURCES, [] ) ).toBe( 'Cart has category is Hoodies' );
	} );

	it( 'labels sibling fields and counts extra rules', () => {
		const f = {
			conditions: [
				{ field: 'note', operator: 'contains', value: 'gate' },
				{ field: '@payment_method', operator: 'is', value: 'cod' },
			],
			conditionMatch: 'any',
		};
		expect( describeCondition( f, SOURCES, FIELDS ) ).toBe( 'Note contains gate OR +1' );
	} );

	it( 'empty for unconditional fields', () => {
		expect( describeCondition( FIELDS[ 2 ], SOURCES, FIELDS ) ).toBe( '' );
	} );
} );
