/**
 * Human summaries of a field's conditional rules, for the canvas IF-flags and
 * the ghost-section reasons. Pure — testable without the DOM.
 */

function sourceLabel( token, sources, fields ) {
	if ( ! token ) {
		return '';
	}
	if ( token[ 0 ] !== '@' ) {
		const f = ( fields || [] ).find( ( x ) => x.id === token );
		return f ? ( f.label || f.type ) : token;
	}
	const base = token.includes( ':' ) ? token.slice( 0, token.indexOf( ':' ) ) : token;
	const def = ( sources || [] ).find( ( s ) => s.token === base );
	return def ? def.label : base.slice( 1 ).replace( /_/g, ' ' );
}

function valueLabel( rule, sources ) {
	const token = rule.field || '';
	if ( token[ 0 ] === '@' ) {
		const base = token.includes( ':' ) ? token.slice( 0, token.indexOf( ':' ) ) : token;
		const def = ( sources || [] ).find( ( s ) => s.token === base );
		if ( def && def.input === 'suffix' ) {
			const id = token.slice( token.indexOf( ':' ) + 1 );
			const v = ( def.values || [] ).find( ( x ) => x.value === id );
			return v ? v.label : id;
		}
		if ( def && def.values ) {
			const v = def.values.find( ( x ) => x.value === rule.value );
			if ( v ) {
				return v.label;
			}
		}
	}
	return String( rule.value ?? '' );
}

const OPS = { is: 'is', is_not: 'is not', contains: 'contains', gt: '>', lt: '<' };

/**
 * One-line summary, e.g. "Payment method is Cash on delivery" (+ " +1 more").
 *
 * @param {Object} field
 * @param {Array}  sources CLCF_BUILDER.sources
 * @param {Array}  fields  all fields (for sibling labels)
 * @return {string} empty when the field has no rules
 */
export function describeCondition( field, sources, fields ) {
	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	if ( ! rules.length ) {
		return '';
	}
	const r = rules[ 0 ];
	let txt = `${ sourceLabel( r.field, sources, fields ) } ${ OPS[ r.operator ] || r.operator } ${ valueLabel( r, sources ) }`.trim();
	if ( rules.length > 1 ) {
		txt += ` ${ field.conditionMatch === 'any' ? 'OR' : 'AND' } +${ rules.length - 1 }`;
	}
	return txt;
}

/** Action word for the flag chip: SHOW | HIDE | REQUIRE. */
export function conditionAction( field ) {
	return ( field.conditionAction || 'show' ).toUpperCase();
}
