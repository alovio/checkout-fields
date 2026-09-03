import { useDispatch } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { optionLabel } from '../../shared/options';

const T = 'corelabs-checkout-fields';
const WEEKDAYS = () => [
	__( 'Sun', T ), __( 'Mon', T ), __( 'Tue', T ), __( 'Wed', T ), __( 'Thu', T ), __( 'Fri', T ), __( 'Sat', T ),
];

/** Constraints editor for the Delivery date field. */
function DeliveryControls( { field, set } ) {
	const disabled = field.disabledWeekdays || [];
	const dates = field.disabledDates || [];
	const slots = field.timeSlots || [];

	const toggleWeekday = ( i, on ) => {
		const s = new Set( disabled );
		if ( on ) { s.add( i ); } else { s.delete( i ); }
		set( { disabledWeekdays: [ ...s ].sort( ( a, b ) => a - b ) } );
	};
	const patchList = ( key, list ) => set( { [ key ]: list } );

	return (
		<div className="clcf-delivery-cfg">
			<p className="clcf-cfg-label">{ __( 'Unavailable weekdays', T ) }</p>
			<div className="clcf-weekdays">
				{ WEEKDAYS().map( ( name, i ) => (
					<label key={ i } className="clcf-weekday">
						<input type="checkbox" checked={ disabled.includes( i ) } onChange={ ( e ) => toggleWeekday( i, e.target.checked ) } /> { name }
					</label>
				) ) }
			</div>

			<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 } }>
				<TextControl type="number" min={ 0 } label={ __( 'Earliest in (days)', T ) } help={ __( 'Lead time before the first date.', T ) } value={ field.minLeadDays ?? 1 } onChange={ ( v ) => set( { minLeadDays: Math.max( 0, parseInt( v, 10 ) || 0 ) } ) } />
				<TextControl type="number" min={ 1 } max={ 365 } label={ __( 'Offer up to (days)', T ) } help={ __( 'How far ahead to list.', T ) } value={ field.daysAhead ?? 21 } onChange={ ( v ) => set( { daysAhead: Math.min( 365, Math.max( 1, parseInt( v, 10 ) || 1 ) ) } ) } />
			</div>

			<TextControl type="time" label={ __( 'Daily cutoff', T ) } help={ __( 'After this time, the lead time counts from the next day.', T ) } value={ field.cutoffTime || '' } onChange={ ( v ) => set( { cutoffTime: v } ) } />

			<p className="clcf-cfg-label">{ __( 'Blocked dates (holidays)', T ) }</p>
			{ dates.map( ( d, i ) => (
				<div key={ i } className="clcf-cfg-row">
					<input type="date" value={ d } onChange={ ( e ) => patchList( 'disabledDates', dates.map( ( x, j ) => ( j === i ? e.target.value : x ) ) ) } />
					<Button isDestructive variant="tertiary" aria-label={ __( 'Remove date', T ) } onClick={ () => patchList( 'disabledDates', dates.filter( ( _, j ) => j !== i ) ) }>✕</Button>
				</div>
			) ) }
			<Button variant="secondary" onClick={ () => patchList( 'disabledDates', [ ...dates, '' ] ) }>＋ { __( 'Add blocked date', T ) }</Button>

			<p className="clcf-cfg-label">{ __( 'Time slots (optional)', T ) }</p>
			{ slots.map( ( s, i ) => (
				<div key={ i } className="clcf-cfg-row">
					<TextControl hideLabelFromVision label={ __( 'Time slot', T ) } placeholder={ __( 'e.g. 9:00–12:00', T ) } value={ s } onChange={ ( v ) => patchList( 'timeSlots', slots.map( ( x, j ) => ( j === i ? v : x ) ) ) } />
					<Button isDestructive variant="tertiary" aria-label={ __( 'Remove slot', T ) } onClick={ () => patchList( 'timeSlots', slots.filter( ( _, j ) => j !== i ) ) }>✕</Button>
				</div>
			) ) }
			<Button variant="secondary" onClick={ () => patchList( 'timeSlots', [ ...slots, '' ] ) }>＋ { __( 'Add time slot', T ) }</Button>
		</div>
	);
}
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

			{ field.type === 'delivery' && <DeliveryControls field={ field } set={ set } /> }

			{ field.type === 'file' && (
				<TextControl
					type="number"
					min={ 1 }
					max={ 10 }
					label={ __( 'Max files', T ) }
					help={ __( 'Shoppers can attach up to this many files (1–10).', T ) }
					value={ field.maxFiles || 1 }
					onChange={ ( v ) => set( { maxFiles: Math.min( 10, Math.max( 1, parseInt( v, 10 ) || 1 ) ) } ) }
				/>
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
