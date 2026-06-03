import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const D = 'corelabs-checkout-fields';

const OP_LABELS = {
	is: __( 'is', D ),
	is_not: __( 'is not', D ),
	contains: __( 'contains', D ),
	gt: __( 'greater than', D ),
	lt: __( 'less than', D ),
};

const SOURCES = ( window.CLCF_BUILDER && window.CLCF_BUILDER.sources ) || [];
const ALL_OPS = ( window.CLCF_BUILDER && window.CLCF_BUILDER.operators ) || [ 'is', 'is_not', 'contains', 'gt', 'lt' ];

const FIELD_SOURCE = '__field__';

function baseToken( ruleField ) {
	if ( ! ruleField || ruleField[ 0 ] !== '@' ) {
		return null;
	}
	const i = ruleField.indexOf( ':' );
	return i === -1 ? ruleField : ruleField.slice( 0, i );
}

function suffixId( ruleField ) {
	const i = ( ruleField || '' ).indexOf( ':' );
	return i === -1 ? '' : ruleField.slice( i + 1 );
}

function defFor( ruleField ) {
	return SOURCES.find( ( s ) => s.token === baseToken( ruleField ) ) || null;
}

function kindOf( ruleField ) {
	if ( ! ruleField || ruleField[ 0 ] !== '@' ) {
		return 'field';
	}
	const def = defFor( ruleField );
	return def ? def.input : 'select';
}

function opOptions( ops ) {
	return ops.map( ( o ) => ( { label: OP_LABELS[ o ] || o, value: o } ) );
}

function opsFor( kind ) {
	if ( kind === 'number' ) {
		return [ 'is', 'gt', 'lt' ];
	}
	if ( kind === 'select' ) {
		return [ 'is', 'is_not' ];
	}
	return ALL_OPS.length ? ALL_OPS : [ 'is', 'is_not' ];
}

function makeRule( sourceValue, siblings ) {
	if ( sourceValue === FIELD_SOURCE ) {
		const first = siblings[ 0 ];
		return { field: first ? first.id : '', operator: 'is', value: '' };
	}
	const def = SOURCES.find( ( s ) => s.token === sourceValue );
	if ( ! def ) {
		return { field: sourceValue, operator: 'is', value: '' };
	}
	if ( def.input === 'suffix' ) {
		const firstId = def.values && def.values[ 0 ] ? def.values[ 0 ].value : '';
		return { field: `${ sourceValue }:${ firstId }`, operator: 'is', value: 'yes' };
	}
	if ( def.input === 'number' ) {
		return { field: sourceValue, operator: 'gt', value: '' };
	}
	const firstVal = def.values && def.values[ 0 ] ? def.values[ 0 ].value : '';
	return { field: sourceValue, operator: 'is', value: firstVal };
}

function SiblingValue( { siblings, controllerId, value, onChange } ) {
	const ctrl = siblings.find( ( f ) => f.id === controllerId );
	const t = ctrl ? ctrl.type : 'text';
	if ( t === 'checkbox' ) {
		return <SelectControl label={ __( 'Value', D ) } value={ value } options={ [ { label: __( 'Checked', D ), value: 'yes' }, { label: __( 'Unchecked', D ), value: '' } ] } onChange={ onChange } />;
	}
	if ( t === 'select' || t === 'radio' ) {
		const opts = ( ctrl.options || [] ).map( ( o ) => ( { label: o, value: o } ) );
		return <SelectControl label={ __( 'Value', D ) } value={ value } options={ opts.length ? opts : [ { label: '—', value: '' } ] } onChange={ onChange } />;
	}
	return <TextControl label={ __( 'Value', D ) } value={ value } onChange={ onChange } />;
}

function RuleRow( { rule, siblings, onChange, onRemove, canRemove } ) {
	const kind = kindOf( rule.field );
	const current = ! rule.field || rule.field[ 0 ] !== '@' ? FIELD_SOURCE : baseToken( rule.field );
	const def = defFor( rule.field );

	const sourceOptions = [
		...( siblings.length ? [ { label: __( 'Another field', D ), value: FIELD_SOURCE } ] : [] ),
		...SOURCES.map( ( s ) => ( { label: s.label, value: s.token } ) ),
	];

	return (
		<div className="clcf-rule">
			<SelectControl
				label={ __( 'When', D ) }
				value={ current }
				options={ sourceOptions }
				onChange={ ( v ) => onChange( makeRule( v, siblings ) ) }
			/>

			{ kind === 'field' && (
				<>
					<SelectControl
						label={ __( 'Field', D ) }
						value={ rule.field }
						options={ siblings.map( ( o ) => ( { label: o.label || o.type, value: o.id } ) ) }
						onChange={ ( v ) => onChange( { ...rule, field: v, value: '' } ) }
					/>
					<SelectControl label={ __( 'Operator', D ) } value={ rule.operator } options={ opOptions( opsFor( 'field' ) ) } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<SiblingValue siblings={ siblings } controllerId={ rule.field } value={ rule.value } onChange={ ( v ) => onChange( { ...rule, value: v } ) } />
				</>
			) }

			{ kind === 'select' && (
				<>
					<SelectControl label={ __( 'Operator', D ) } value={ rule.operator } options={ opOptions( opsFor( 'select' ) ) } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<SelectControl label={ __( 'Value', D ) } value={ rule.value } options={ ( def && def.values ? def.values : [] ).map( ( x ) => ( { label: x.label, value: x.value } ) ) } onChange={ ( v ) => onChange( { ...rule, value: v } ) } />
				</>
			) }

			{ kind === 'number' && (
				<>
					<SelectControl label={ __( 'Operator', D ) } value={ rule.operator } options={ opOptions( opsFor( 'number' ) ) } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<TextControl type="number" label={ __( 'Value', D ) } value={ rule.value } onChange={ ( v ) => onChange( { ...rule, value: v } ) } />
				</>
			) }

			{ kind === 'suffix' && (
				<SelectControl
					label={ def ? def.label : __( 'Value', D ) }
					value={ suffixId( rule.field ) }
					options={ ( def && def.values ? def.values : [] ).map( ( x ) => ( { label: x.label, value: x.value } ) ) }
					onChange={ ( v ) => onChange( { field: `${ baseToken( rule.field ) }:${ v }`, operator: 'is', value: 'yes' } ) }
				/>
			) }

			{ canRemove && (
				<Button isDestructive variant="link" onClick={ onRemove }>{ __( 'Remove rule', D ) }</Button>
			) }
		</div>
	);
}

export default function ConditionEditor( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const siblings = fields.filter( ( f ) => f.id !== field.id );

	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	const enabled = rules.length > 0;

	const firstSource = SOURCES.length ? SOURCES[ 0 ].token : ( siblings.length ? FIELD_SOURCE : '' );

	const setRules = ( newRules ) =>
		updateField( field.id, {
			condition: null,
			conditions: newRules,
			conditionMatch: field.conditionMatch || 'all',
			conditionAction: field.conditionAction || 'show',
		} );

	const toggle = ( on ) =>
		on ? setRules( [ makeRule( firstSource, siblings ) ] ) : updateField( field.id, { conditions: [], condition: null } );

	const updateRule = ( i, newRule ) => setRules( rules.map( ( r, idx ) => ( idx === i ? newRule : r ) ) );
	const addRule = () => setRules( [ ...rules, makeRule( firstSource, siblings ) ] );
	const removeRule = ( i ) => setRules( rules.filter( ( _, idx ) => idx !== i ) );

	const actionOptions = [
		{ label: __( 'Show this field', D ), value: 'show' },
		{ label: __( 'Hide this field', D ), value: 'hide' },
		{ label: __( 'Require this field', D ), value: 'require' },
	];

	return (
		<div className="clcf-condition">
			<ToggleControl
				label={ __( 'Conditional logic', D ) }
				help={ __( 'Show, hide, or require this field based on cart, role, shipping, payment, or another field.', D ) }
				checked={ enabled }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<SelectControl
						label={ __( 'Match', D ) }
						value={ field.conditionMatch || 'all' }
						options={ [
							{ label: __( 'All rules (AND)', D ), value: 'all' },
							{ label: __( 'Any rule (OR)', D ), value: 'any' },
						] }
						onChange={ ( v ) => updateField( field.id, { conditionMatch: v } ) }
					/>
					{ rules.map( ( r, i ) => (
						<RuleRow
							key={ i }
							rule={ r }
							siblings={ siblings }
							onChange={ ( nr ) => updateRule( i, nr ) }
							onRemove={ () => removeRule( i ) }
							canRemove={ rules.length > 1 }
						/>
					) ) }
					<Button variant="secondary" onClick={ addRule }>{ __( '+ Add rule', D ) }</Button>
					<SelectControl
						label={ __( 'Then', D ) }
						value={ field.conditionAction || 'show' }
						options={ actionOptions }
						onChange={ ( v ) => updateField( field.id, { conditionAction: v } ) }
					/>
				</>
			) }
		</div>
	);
}
