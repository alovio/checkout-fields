import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { optionLabel } from '../../shared/options';

const T = 'corelabs-checkout-fields';

const OP_LABELS = {
	is: __( 'is', T ),
	is_not: __( 'is not', T ),
	contains: __( 'contains', T ),
	gt: __( 'greater than', T ),
	lt: __( 'less than', T ),
};

const SOURCES = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.sources ) || [];
const ALL_OPS = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.operators ) || [ 'is', 'is_not', 'contains', 'gt', 'lt' ];
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
	return SOURCES().find( ( s ) => s.token === baseToken( ruleField ) ) || null;
}
function kindOf( ruleField ) {
	if ( ! ruleField || ruleField[ 0 ] !== '@' ) {
		return 'field';
	}
	const def = defFor( ruleField );
	return def ? def.input : 'select';
}
function opsFor( kind ) {
	if ( kind === 'number' ) {
		return [ 'is', 'gt', 'lt' ];
	}
	if ( kind === 'select' ) {
		return [ 'is', 'is_not' ];
	}
	return ALL_OPS();
}
function makeRule( sourceValue, siblings ) {
	if ( sourceValue === FIELD_SOURCE ) {
		const first = siblings[ 0 ];
		return { field: first ? first.id : '', operator: 'is', value: '' };
	}
	const def = SOURCES().find( ( s ) => s.token === sourceValue );
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

/** A select disguised as a token chip. */
function TokSelect( { kind, value, valueLabel, options, onChange } ) {
	return (
		<span className={ `clcf-tok${ kind ? ` clcf-tok--${ kind }` : '' }` }>
			{ valueLabel } <span className="clcf-car"></span>
			<select value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
				{ options.map( ( o, i ) => (
					<option key={ i } value={ o.value }>{ o.label }</option>
				) ) }
			</select>
		</span>
	);
}

function SiblingValue( { siblings, controllerId, value, onChange } ) {
	const ctrl = siblings.find( ( f ) => f.id === controllerId );
	const t = ctrl ? ctrl.type : 'text';
	if ( t === 'checkbox' ) {
		const opts = [ { label: __( 'Checked', T ), value: 'yes' }, { label: __( 'Unchecked', T ), value: '' } ];
		return <TokSelect kind="val" value={ value } valueLabel={ value === 'yes' ? __( 'Checked', T ) : __( 'Unchecked', T ) } options={ opts } onChange={ onChange } />;
	}
	if ( t === 'select' || t === 'radio' ) {
		const opts = ( ctrl.options || [] ).map( ( o ) => ( { label: optionLabel( o ), value: optionLabel( o ) } ) );
		return <TokSelect kind="val" value={ value } valueLabel={ value || '—' } options={ opts.length ? opts : [ { label: '—', value: '' } ] } onChange={ onChange } />;
	}
	return (
		<span className="clcf-tok clcf-tok--val">
			<input value={ value } placeholder={ __( 'value…', T ) } onChange={ ( e ) => onChange( e.target.value ) } />
		</span>
	);
}

function RuleRow( { rule, siblings, onChange, onRemove, canRemove } ) {
	const kind = kindOf( rule.field );
	const current = ! rule.field || rule.field[ 0 ] !== '@' ? FIELD_SOURCE : baseToken( rule.field );
	const def = defFor( rule.field );

	const sourceOptions = [
		...( siblings.length ? [ { label: __( 'Another field', T ), value: FIELD_SOURCE } ] : [] ),
		...SOURCES().map( ( s ) => ( { label: s.label, value: s.token } ) ),
	];
	const currentSourceLabel = current === FIELD_SOURCE ? __( 'Another field', T ) : ( def ? def.label : current );

	const ops = opsFor( kind );
	const opOptions = ops.map( ( o ) => ( { label: OP_LABELS[ o ] || o, value: o } ) );

	return (
		<div className="clcf-sentence">
			<TokSelect kind="src" value={ current } valueLabel={ currentSourceLabel } options={ sourceOptions } onChange={ ( v ) => onChange( makeRule( v, siblings ) ) } />

			{ kind === 'field' && (
				<>
					<TokSelect
						kind="src"
						value={ rule.field }
						valueLabel={ ( siblings.find( ( s ) => s.id === rule.field ) || {} ).label || rule.field || '—' }
						options={ siblings.map( ( o ) => ( { label: o.label || o.type, value: o.id } ) ) }
						onChange={ ( v ) => onChange( { ...rule, field: v, value: '' } ) }
					/>
					<TokSelect value={ rule.operator } valueLabel={ OP_LABELS[ rule.operator ] || rule.operator } options={ opOptions } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<SiblingValue siblings={ siblings } controllerId={ rule.field } value={ rule.value } onChange={ ( v ) => onChange( { ...rule, value: v } ) } />
				</>
			) }

			{ kind === 'select' && (
				<>
					<TokSelect value={ rule.operator } valueLabel={ OP_LABELS[ rule.operator ] || rule.operator } options={ opOptions } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<TokSelect
						kind="val"
						value={ rule.value }
						valueLabel={ ( ( def && def.values ) || [] ).find( ( x ) => x.value === rule.value )?.label || rule.value || '—' }
						options={ ( ( def && def.values ) || [] ).map( ( x ) => ( { label: x.label, value: x.value } ) ) }
						onChange={ ( v ) => onChange( { ...rule, value: v } ) }
					/>
				</>
			) }

			{ kind === 'number' && (
				<>
					<TokSelect value={ rule.operator } valueLabel={ OP_LABELS[ rule.operator ] || rule.operator } options={ opOptions } onChange={ ( v ) => onChange( { ...rule, operator: v } ) } />
					<span className="clcf-tok clcf-tok--val">
						<input type="number" value={ rule.value } onChange={ ( e ) => onChange( { ...rule, value: e.target.value } ) } />
					</span>
				</>
			) }

			{ kind === 'suffix' && (
				<TokSelect
					kind="val"
					value={ suffixId( rule.field ) }
					valueLabel={ ( ( def && def.values ) || [] ).find( ( x ) => x.value === suffixId( rule.field ) )?.label || suffixId( rule.field ) || '—' }
					options={ ( ( def && def.values ) || [] ).map( ( x ) => ( { label: x.label, value: x.value } ) ) }
					onChange={ ( v ) => onChange( { field: `${ baseToken( rule.field ) }:${ v }`, operator: 'is', value: 'yes' } ) }
				/>
			) }

			{ canRemove && (
				<button className="clcf-rule-x" aria-label={ __( 'Remove rule', T ) } onClick={ onRemove }>✕</button>
			) }
		</div>
	);
}

export default function Logic( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const siblings = fields.filter( ( f ) => f.id !== field.id );

	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	const enabled = rules.length > 0;
	const firstSource = SOURCES().length ? SOURCES()[ 0 ].token : ( siblings.length ? FIELD_SOURCE : '' );

	const setRules = ( newRules ) =>
		updateField( field.id, {
			condition: null,
			conditions: newRules,
			conditionMatch: field.conditionMatch || 'all',
			conditionAction: field.conditionAction || 'show',
		} );

	const toggle = ( on ) =>
		on ? setRules( [ makeRule( firstSource, siblings ) ] ) : updateField( field.id, { conditions: [], condition: null } );

	return (
		<>
			<ToggleControl
				label={ __( 'Conditional logic', T ) }
				help={ __( 'Show, hide, or require this field based on cart, role, shipping, payment, or another field.', T ) }
				checked={ enabled }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<div className="clcf-rule-card">
						<div className="clcf-rule-when">{ __( 'When', T ) }</div>
						{ rules.map( ( r, i ) => (
							<div key={ i }>
								{ i > 0 && (
									<div className="clcf-andor">
										<button className={ ( field.conditionMatch || 'all' ) === 'all' ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'all' } ) }>{ __( 'AND', T ) }</button>
										<button className={ field.conditionMatch === 'any' ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'any' } ) }>{ __( 'OR', T ) }</button>
									</div>
								) }
								<RuleRow
									rule={ r }
									siblings={ siblings }
									onChange={ ( nr ) => setRules( rules.map( ( x, idx ) => ( idx === i ? nr : x ) ) ) }
									onRemove={ () => setRules( rules.filter( ( _, idx ) => idx !== i ) ) }
									canRemove={ rules.length > 1 }
								/>
							</div>
						) ) }
						<button className="clcf-addrule" onClick={ () => setRules( [ ...rules, makeRule( firstSource, siblings ) ] ) }>
							＋ { __( 'Add condition', T ) }
						</button>
					</div>
					<div className="clcf-then">
						<div className="clcf-then-lbl">{ __( 'Then', T ) }</div>
						<div className="clcf-seg">
							{ [ [ 'show', __( 'Show', T ) ], [ 'hide', __( 'Hide', T ) ], [ 'require', __( 'Require', T ) ] ].map( ( [ v, l ] ) => (
								<button key={ v } className={ ( field.conditionAction || 'show' ) === v ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionAction: v } ) }>{ l }</button>
							) ) }
						</div>
					</div>
				</>
			) }
		</>
	);
}
