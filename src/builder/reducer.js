/**
 * Pure builder state: reducer, action creators, selectors. No @wordpress imports
 * here so it is unit-testable with plain Jest. The @wordpress/data store wrapper
 * lives in store.js.
 *
 * Conditions use the multi-rule model (`conditions[]`) for everyone, and every
 * field can carry a fee (price/priceMode) — everything is free.
 */

const BASE = { required: false, options: [], location: 'after_order_notes', width: 'full', price: 0, priceMode: 'fixed' };

export const DEFAULTS = {
	text: { label: 'Text field', ...BASE },
	textarea: { label: 'Text area', ...BASE },
	number: { label: 'Number', ...BASE },
	checkbox: { label: 'Checkbox', ...BASE },
	radio: { label: 'Choose one', ...BASE, options: [ 'Option 1' ] },
	select: { label: 'Select', ...BASE, options: [ 'Option 1' ] },
	heading: { label: 'Section heading', ...BASE, description: '' },
	email: { label: 'Email address', ...BASE },
	phone: { label: 'Phone number', ...BASE },
	url: { label: 'Website / link', ...BASE },
	date: { label: 'Pick a date', ...BASE, min: '', max: '' },
	time: { label: 'Pick a time', ...BASE },
	file: { label: 'Upload a file', ...BASE },
};

let counter = 0;
export function makeId() {
	counter += 1;
	return `fld_${ counter }_${ Math.random().toString( 36 ).slice( 2, 7 ) }`;
}

export const HISTORY_LIMIT = 25;

export const initialSim = { role: 'guest', payment: '', shipping: '', country: '', cartPreset: 'default' };

export const initialState = { fields: [], selectedId: null, sim: initialSim, past: [], activeRegion: 'after_order_notes' };

/** Push the current fields snapshot before a mutating action (bounded). */
function remember( state ) {
	const past = [ ...state.past, state.fields ];
	if ( past.length > HISTORY_LIMIT ) {
		past.shift();
	}
	return past;
}

export function reducer( state = initialState, action = {} ) {
	switch ( action.type ) {
		case 'UNDO': {
			if ( ! state.past.length ) {
				return state;
			}
			const past = [ ...state.past ];
			const fields = past.pop();
			return { ...state, fields, past, selectedId: fields.some( ( f ) => f.id === state.selectedId ) ? state.selectedId : null };
		}
		case 'SET_SIM':
			return { ...state, sim: { ...state.sim, ...action.patch } };
		case 'SET_REGION':
			return { ...state, activeRegion: action.region };
		case 'INSERT_FIELDS': {
			const fields = [ ...state.fields, ...action.fields ];
			return { ...state, past: remember( state ), fields, selectedId: action.fields.length ? action.fields[ 0 ].id : state.selectedId };
		}
		case 'ADD_FIELD': {
			const defaults = DEFAULTS[ action.fieldType ] || DEFAULTS.text;
			const field = { id: action.id, type: action.fieldType, ...defaults, options: [ ...defaults.options ] };
			return { ...state, past: remember( state ), fields: [ ...state.fields, field ], selectedId: field.id };
		}
		case 'UPDATE_FIELD':
			return {
				...state,
				past: remember( state ),
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
			return { ...state, past: remember( state ), fields, selectedId: state.selectedId === action.id ? null : state.selectedId };
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
			return { ...state, past: remember( state ), fields, selectedId: copy.id };
		}
		case 'REORDER': {
			const fields = [ ...state.fields ];
			if ( action.to < 0 || action.to >= fields.length ) {
				return state;
			}
			const [ moved ] = fields.splice( action.from, 1 );
			fields.splice( action.to, 0, moved );
			return { ...state, past: remember( state ), fields };
		}
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'HYDRATE':
			return { ...state, past: [], fields: Array.isArray( action.fields ) ? action.fields : [] };
		default:
			return state;
	}
}

/**
 * Remap a template's local ids to fresh unique ids, rewriting sibling condition
 * references. Context tokens (`@…`) are left untouched.
 *
 * @param {Array} templateFields
 * @return {Array} fields ready for INSERT_FIELDS
 */
export function remapTemplate( templateFields ) {
	const idMap = {};
	const fields = ( templateFields || [] ).map( ( f ) => {
		const copy = JSON.parse( JSON.stringify( f ) );
		idMap[ copy.id ] = makeId();
		copy.id = idMap[ copy.id ];
		return copy;
	} );
	fields.forEach( ( f ) => {
		if ( Array.isArray( f.conditions ) ) {
			f.conditions = f.conditions.map( ( r ) =>
				r.field && r.field[ 0 ] !== '@' && idMap[ r.field ] ? { ...r, field: idMap[ r.field ] } : r
			);
		}
	} );
	return fields;
}

export const actions = {
	addField: ( fieldType ) => ( { type: 'ADD_FIELD', fieldType, id: makeId() } ),
	updateField: ( id, patch ) => ( { type: 'UPDATE_FIELD', id, patch } ),
	removeField: ( id ) => ( { type: 'REMOVE_FIELD', id } ),
	duplicateField: ( id ) => ( { type: 'DUPLICATE_FIELD', id, newId: makeId() } ),
	reorder: ( from, to ) => ( { type: 'REORDER', from, to } ),
	selectField: ( id ) => ( { type: 'SELECT', id } ),
	hydrate: ( fields ) => ( { type: 'HYDRATE', fields } ),
	undo: () => ( { type: 'UNDO' } ),
	setSim: ( patch ) => ( { type: 'SET_SIM', patch } ),
	setRegion: ( region ) => ( { type: 'SET_REGION', region } ),
	insertFields: ( templateFields ) => ( { type: 'INSERT_FIELDS', fields: remapTemplate( templateFields ) } ),
};

export const selectors = {
	getFields: ( state ) => state.fields,
	getSelected: ( state ) => state.fields.find( ( f ) => f.id === state.selectedId ) || null,
	getSelectedId: ( state ) => state.selectedId,
	getSim: ( state ) => state.sim,
	canUndo: ( state ) => state.past.length > 0,
	getActiveRegion: ( state ) => state.activeRegion,
};
