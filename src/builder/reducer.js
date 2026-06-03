/**
 * Pure builder state: reducer, action creators, selectors. No @wordpress imports
 * here so it is unit-testable with plain Jest. The @wordpress/data store wrapper
 * lives in store.js.
 *
 * Note: checkout fields carry no pricing (that is Pro and handled elsewhere).
 * Conditions use the multi-rule model (`conditions[]`) for everyone — multi is
 * free here — so there is no single-condition branch.
 */

const BASE = { required: false, options: [], location: 'after_order_notes' };

export const DEFAULTS = {
	text: { label: 'Text field', ...BASE },
	textarea: { label: 'Text area', ...BASE },
	number: { label: 'Number', ...BASE },
	checkbox: { label: 'Checkbox', ...BASE },
	radio: { label: 'Choose one', ...BASE, options: [ 'Option 1' ] },
	select: { label: 'Select', ...BASE, options: [ 'Option 1' ] },
	heading: { label: 'Section heading', ...BASE, description: '' },
	// Pro types (full impl is a Pro follow-up); kept so the palette doesn't break when Pro is on.
	date: { label: 'Pick a date', ...BASE, min: '', max: '' },
	file: { label: 'Upload a file', ...BASE },
};

let counter = 0;
export function makeId() {
	counter += 1;
	return `fld_${ counter }_${ Math.random().toString( 36 ).slice( 2, 7 ) }`;
}

export const initialState = { fields: [], selectedId: null };

export function reducer( state = initialState, action = {} ) {
	switch ( action.type ) {
		case 'ADD_FIELD': {
			const defaults = DEFAULTS[ action.fieldType ] || DEFAULTS.text;
			const field = { id: action.id, type: action.fieldType, ...defaults, options: [ ...defaults.options ] };
			return { ...state, fields: [ ...state.fields, field ], selectedId: field.id };
		}
		case 'UPDATE_FIELD':
			return {
				...state,
				fields: state.fields.map( ( f ) => ( f.id === action.id ? { ...f, ...action.patch } : f ) ),
			};
		case 'REMOVE_FIELD': {
			const fields = state.fields
				.filter( ( f ) => f.id !== action.id )
				.map( ( f ) => {
					if ( Array.isArray( f.conditions ) && f.conditions.some( ( r ) => r.field === action.id ) ) {
						return { ...f, conditions: f.conditions.filter( ( r ) => r.field !== action.id ) };
					}
					return f;
				} );
			return { ...state, fields, selectedId: state.selectedId === action.id ? null : state.selectedId };
		}
		case 'DUPLICATE_FIELD': {
			const idx = state.fields.findIndex( ( f ) => f.id === action.id );
			if ( idx === -1 ) {
				return state;
			}
			const copy = { ...JSON.parse( JSON.stringify( state.fields[ idx ] ) ), id: action.newId };
			if ( copy.label ) {
				copy.label += ' (copy)';
			}
			const fields = [ ...state.fields ];
			fields.splice( idx + 1, 0, copy );
			return { ...state, fields, selectedId: copy.id };
		}
		case 'REORDER': {
			const fields = [ ...state.fields ];
			if ( action.to < 0 || action.to >= fields.length ) {
				return state;
			}
			const [ moved ] = fields.splice( action.from, 1 );
			fields.splice( action.to, 0, moved );
			return { ...state, fields };
		}
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'HYDRATE':
			return { ...state, fields: Array.isArray( action.fields ) ? action.fields : [] };
		default:
			return state;
	}
}

export const actions = {
	addField: ( fieldType ) => ( { type: 'ADD_FIELD', fieldType, id: makeId() } ),
	updateField: ( id, patch ) => ( { type: 'UPDATE_FIELD', id, patch } ),
	removeField: ( id ) => ( { type: 'REMOVE_FIELD', id } ),
	duplicateField: ( id ) => ( { type: 'DUPLICATE_FIELD', id, newId: makeId() } ),
	reorder: ( from, to ) => ( { type: 'REORDER', from, to } ),
	selectField: ( id ) => ( { type: 'SELECT', id } ),
	hydrate: ( fields ) => ( { type: 'HYDRATE', fields } ),
};

export const selectors = {
	getFields: ( state ) => state.fields,
	getSelected: ( state ) => state.fields.find( ( f ) => f.id === state.selectedId ) || null,
	getSelectedId: ( state ) => state.selectedId,
};
