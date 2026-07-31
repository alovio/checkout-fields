/**
 * Choice-option shape helpers — the JS twin of PHP FieldOptions.
 *
 * An option is either a plain string ("Express") or an object carrying a
 * price: {label, price}. Both forms are valid everywhere, so both renderers
 * and the fee gates read them through here rather than assuming a string.
 */

/** Field types whose value is one of a fixed list of options (PHP: FieldOptions::CHOICE_TYPES). */
export const CHOICE_TYPES = [ 'select', 'radio' ];

export function optionLabel( option ) {
	return option && typeof option === 'object' ? String( option.label || '' ) : String( option ?? '' );
}

/** The option's own fee; 0 means "use the field price". */
export function optionPrice( option ) {
	if ( ! option || typeof option !== 'object' ) {
		return 0;
	}
	// Gate on a full numeric match, exactly as PHP's is_numeric() does, so
	// "10abc" is not half-read as 10 on one side of the wire only.
	const raw = option.price;
	if ( typeof raw !== 'number' && ! ( typeof raw === 'string' && raw.trim() !== '' && ! isNaN( Number( raw ) ) ) ) {
		return 0;
	}
	const price = Number( raw );
	return isNaN( price ) || price <= 0 ? 0 : price;
}

export function optionLabels( field ) {
	return ( ( field && field.options ) || [] ).map( optionLabel );
}

/** Price attached to the option matching this value, else 0. */
export function optionPriceForValue( field, value ) {
	if ( value === undefined || value === null || value === '' || typeof value === 'object' ) {
		return 0;
	}
	if ( ! CHOICE_TYPES.includes( ( field && field.type ) || '' ) ) {
		return 0; // only a real choice field can price by option.
	}
	const match = ( ( field && field.options ) || [] ).find( ( o ) => optionLabel( o ) === String( value ) );
	return match === undefined ? 0 : optionPrice( match );
}

export function hasPricedOptions( field ) {
	return ( ( field && field.options ) || [] ).some( ( o ) => optionPrice( o ) > 0 );
}

/**
 * What a field charges for this value: the picked option's own price when it
 * has one, else the field-level price. Mirrors FieldOptions::effective_price.
 */
export function effectivePrice( field, value ) {
	const own = optionPriceForValue( field, value );
	return own > 0 ? own : parseFloat( field && field.price ) || 0;
}

/** Min/max of what a choice field can charge (PHP: FieldOptions::price_range). */
export function priceRange( field ) {
	const prices = ( ( field && field.options ) || [] )
		.map( ( o ) => effectivePrice( field, optionLabel( o ) ) )
		.filter( ( p ) => p > 0 );
	return prices.length ? [ Math.min( ...prices ), Math.max( ...prices ) ] : [ 0, 0 ];
}
