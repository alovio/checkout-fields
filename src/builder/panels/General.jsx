import { useDispatch } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { optionLabel } from '../../shared/options';

const T = 'corelabs-checkout-fields';
const HAS_OPTIONS = [ 'radio', 'select' ];
const HAS_PLACEHOLDER = [ 'text', 'textarea', 'number', 'email', 'phone', 'url' ];
const HAS_MAXLEN = [ 'text', 'textarea' ];

function DefaultControl( { field, set } ) {
	const t = field.type;
	if ( HAS_PLACEHOLDER.includes( t ) ) {
		return <TextControl label={ __( 'Default value', T ) } value={ field.default || '' } onChange={ ( v ) => set( { default: v } ) } />;
	}
	if ( t === 'checkbox' ) {
		return <ToggleControl label={ __( 'Checked by default', T ) } checked={ field.default === 'yes' } onChange={ ( on ) => set( { default: on ? 'yes' : '' } ) } />;
	}
	if ( HAS_OPTIONS.includes( t ) ) {
		const opts = [
			{ label: __( '— none —', T ), value: '' },
			...( field.options || [] ).map( ( o ) => ( { label: optionLabel( o ), value: optionLabel( o ) } ) ),
		];
		return <SelectControl label={ __( 'Default selection', T ) } value={ field.default || '' } options={ opts } onChange={ ( v ) => set( { default: v } ) } />;
	}
	return null;
}

export default function General( { field } ) {
	const { updateField } = useDispatch( STORE );
	const set = ( patch ) => updateField( field.id, patch );

	if ( field.type === 'heading' ) {
		return (
			<>
				<TextControl label={ __( 'Heading text', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Description', T ) } value={ field.description || '' } onChange={ ( v ) => set( { description: v } ) } />
			</>
		);
	}

	const optionsEmpty = HAS_OPTIONS.includes( field.type ) && ! ( field.options || [] ).length;

	return (
		<>
			<TextControl label={ __( 'Label', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
			<TextControl label={ __( 'Description / help text', T ) } value={ field.description || '' } onChange={ ( v ) => set( { description: v } ) } />
			<ToggleControl label={ __( 'Required', T ) } checked={ !! field.required } onChange={ ( required ) => set( { required } ) } />

			{ HAS_PLACEHOLDER.includes( field.type ) && (
				<TextControl label={ __( 'Placeholder', T ) } value={ field.placeholder || '' } onChange={ ( v ) => set( { placeholder: v } ) } />
			) }

			<DefaultControl field={ field } set={ set } />

			{ field.type === 'number' && (
				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8 } }>
					<TextControl type="number" label={ __( 'Min', T ) } value={ field.min || '' } onChange={ ( v ) => set( { min: v } ) } />
					<TextControl type="number" label={ __( 'Max', T ) } value={ field.max || '' } onChange={ ( v ) => set( { max: v } ) } />
					<TextControl type="number" label={ __( 'Step', T ) } value={ field.step || '' } onChange={ ( v ) => set( { step: v } ) } />
				</div>
			) }

			{ field.type === 'date' && (
				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 } }>
					<TextControl type="date" label={ __( 'Earliest date', T ) } value={ field.min || '' } onChange={ ( v ) => set( { min: v } ) } />
					<TextControl type="date" label={ __( 'Latest date', T ) } value={ field.max || '' } onChange={ ( v ) => set( { max: v } ) } />
				</div>
			) }

			{ HAS_MAXLEN.includes( field.type ) && (
				<TextControl type="number" min={ 0 } label={ __( 'Max length (characters)', T ) } value={ field.maxLength || '' } onChange={ ( v ) => set( { maxLength: parseInt( v, 10 ) || 0 } ) } />
			) }

			{ optionsEmpty && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }
		</>
	);
}
