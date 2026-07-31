import {
	optionLabel,
	optionPrice,
	optionLabels,
	optionPriceForValue,
	hasPricedOptions,
	effectivePrice,
	priceRange,
} from '../../src/shared/options';

const SPEEDS = {
	id: 'speed',
	type: 'select',
	price: 0,
	options: [ 'Standard', { label: 'Express', price: 10 } ],
};

describe( 'option shape helpers', () => {
	it( 'reads labels from both shapes', () => {
		expect( optionLabel( 'Standard' ) ).toBe( 'Standard' );
		expect( optionLabel( { label: 'Express', price: 10 } ) ).toBe( 'Express' );
		expect( optionLabel( null ) ).toBe( '' );
	} );

	it( 'only counts positive numeric prices', () => {
		expect( optionPrice( { label: 'a', price: 10 } ) ).toBe( 10 );
		expect( optionPrice( 'a' ) ).toBe( 0 );
		expect( optionPrice( { label: 'a' } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: -5 } ) ).toBe( 0 );
	} );

	it( 'ignores a half-numeric price exactly as PHP is_numeric does', () => {
		expect( optionPrice( { label: 'a', price: '10abc' } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: '10' } ) ).toBe( 10 );
		expect( optionPrice( { label: 'a', price: ' ' } ) ).toBe( 0 );
	} );

	it( 'lists labels across mixed shapes', () => {
		expect( optionLabels( SPEEDS ) ).toEqual( [ 'Standard', 'Express' ] );
	} );

	it( 'prefers the option price over the field price', () => {
		const f = { ...SPEEDS, price: 4 };
		expect( effectivePrice( f, 'Standard' ) ).toBe( 4 );
		expect( effectivePrice( f, 'Express' ) ).toBe( 10 );
		expect( effectivePrice( f, 'Unknown' ) ).toBe( 4 );
	} );

	it( 'never lets options price a non-choice field', () => {
		const f = { type: 'text', price: 5, options: [ { label: 'gold', price: 500 } ] };
		expect( optionPriceForValue( f, 'gold' ) ).toBe( 0 );
		expect( effectivePrice( f, 'gold' ) ).toBe( 5 );
	} );

	it( 'resolves duplicate labels to the first match', () => {
		const f = { type: 'select', options: [ { label: 'A', price: 3 }, { label: 'A', price: 9 } ] };
		expect( effectivePrice( f, 'A' ) ).toBe( 3 );
	} );

	it( 'detects priced options and spans the field-price fallback', () => {
		expect( hasPricedOptions( SPEEDS ) ).toBe( true );
		expect( hasPricedOptions( { type: 'select', options: [ 'A', 'B' ] } ) ).toBe( false );
		expect( priceRange( { ...SPEEDS, price: 4 } ) ).toEqual( [ 4, 10 ] );
		expect( priceRange( { type: 'select', options: [ 'A' ] } ) ).toEqual( [ 0, 0 ] );
	} );
} );
