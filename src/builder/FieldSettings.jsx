import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';

const T = 'corelabs-checkout-fields';
const HAS_OPTIONS = [ 'radio', 'select' ];
const HAS_PLACEHOLDER = [ 'text', 'textarea', 'number' ];
const HAS_MAXLEN = [ 'text', 'textarea' ];

const LOCATIONS = ( window.CLCF_BUILDER && window.CLCF_BUILDER.locations ) || [
	{ value: 'after_order_notes', label: 'After order notes' },
];

function DefaultControl( { field, set } ) {
	const t = field.type;
	if ( HAS_PLACEHOLDER.includes( t ) ) {
		return <TextControl label={ __( 'Default value', T ) } value={ field.default || '' } onChange={ ( v ) => set( { default: v } ) } />;
	}
	if ( t === 'checkbox' ) {
		return <ToggleControl label={ __( 'Checked by default', T ) } checked={ field.default === 'yes' } onChange={ ( on ) => set( { default: on ? 'yes' : '' } ) } />;
	}
	if ( HAS_OPTIONS.includes( t ) ) {
		const opts = [ { label: __( '— none —', T ), value: '' }, ...( field.options || [] ).map( ( o ) => ( { label: o, value: o } ) ) ];
		return <SelectControl label={ __( 'Default selection', T ) } value={ field.default || '' } options={ opts } onChange={ ( v ) => set( { default: v } ) } />;
	}
	return null;
}

export default function FieldSettings() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const { updateField } = useDispatch( STORE );

	if ( ! field ) {
		return <div className="clcf-settings clcf-settings--empty">{ __( 'Select a field to edit its settings.', T ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );

	const locationControl = (
		<SelectControl
			label={ __( 'Show in checkout region', T ) }
			value={ field.location || 'after_order_notes' }
			options={ LOCATIONS }
			onChange={ ( v ) => set( { location: v } ) }
		/>
	);

	// Heading is display-only: label, description, location, and optional condition.
	if ( field.type === 'heading' ) {
		return (
			<div className="clcf-settings">
				<TextControl label={ __( 'Heading text', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Description', T ) } value={ field.description || '' } onChange={ ( v ) => set( { description: v } ) } />
				{ locationControl }
				<ConditionEditor field={ field } />
			</div>
		);
	}

	const optionsEmpty = HAS_OPTIONS.includes( field.type ) && ! ( field.options || [] ).length;

	return (
		<div className="clcf-settings">
			<TextControl label={ __( 'Label', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />

			<TextControl
				label={ __( 'Description / help text', T ) }
				value={ field.description || '' }
				onChange={ ( v ) => set( { description: v } ) }
			/>

			<ToggleControl label={ __( 'Required', T ) } checked={ !! field.required } onChange={ ( required ) => set( { required } ) } />

			{ locationControl }

			{ HAS_PLACEHOLDER.includes( field.type ) && (
				<TextControl label={ __( 'Placeholder', T ) } value={ field.placeholder || '' } onChange={ ( v ) => set( { placeholder: v } ) } />
			) }

			<DefaultControl field={ field } set={ set } />

			{ field.type === 'number' && (
				<div className="clcf-row3">
					<TextControl type="number" label={ __( 'Min', T ) } value={ field.min || '' } onChange={ ( v ) => set( { min: v } ) } />
					<TextControl type="number" label={ __( 'Max', T ) } value={ field.max || '' } onChange={ ( v ) => set( { max: v } ) } />
					<TextControl type="number" label={ __( 'Step', T ) } value={ field.step || '' } onChange={ ( v ) => set( { step: v } ) } />
				</div>
			) }

			{ HAS_MAXLEN.includes( field.type ) && (
				<TextControl type="number" min={ 0 } label={ __( 'Max length (characters)', T ) } value={ field.maxLength || '' } onChange={ ( v ) => set( { maxLength: parseInt( v, 10 ) || 0 } ) } />
			) }

			{ HAS_OPTIONS.includes( field.type ) && (
				<TextareaControl
					label={ __( 'Options (one per line)', T ) }
					value={ ( field.options || [] ).join( '\n' ) }
					onChange={ ( v ) => set( { options: v.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ) } ) }
				/>
			) }
			{ optionsEmpty && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }

			<ConditionEditor field={ field } />
		</div>
	);
}
