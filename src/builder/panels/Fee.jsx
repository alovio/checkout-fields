import { useDispatch } from '@wordpress/data';
import { TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

const T = 'corelabs-checkout-fields';

/**
 * Field-driven fee: adds a cart fee when the field is active + engaged.
 * Free feature — fixed / per-unit (number fields) / percent of subtotal.
 */
export default function Fee( { field } ) {
	const { updateField } = useDispatch( STORE );
	const set = ( patch ) => updateField( field.id, patch );
	const mode = field.priceMode || 'fixed';
	const price = field.price || 0;

	const modeOptions = [
		{ label: __( 'Fixed amount', T ), value: 'fixed' },
		...( field.type === 'number' ? [ { label: __( 'Per unit (× number entered)', T ), value: 'per_unit' } ] : [] ),
		{ label: __( 'Percentage of subtotal', T ), value: 'percent' },
	];

	const amountLabel =
		mode === 'percent' ? __( 'Fee — percentage (%)', T )
			: mode === 'per_unit' ? __( 'Fee — price per unit', T )
				: __( 'Fee amount', T );

	return (
		<>
			<TextControl
				type="number"
				min={ 0 }
				step="0.01"
				label={ amountLabel }
				help={ __( 'Added to the order total when this field is filled in or selected. Leave 0 for no fee.', T ) }
				value={ price }
				onChange={ ( v ) => set( { price: Math.max( 0, parseFloat( v ) || 0 ) } ) }
			/>
			<SelectControl
				label={ __( 'Fee type', T ) }
				value={ mode }
				options={ modeOptions }
				onChange={ ( v ) => set( { priceMode: v } ) }
			/>
			{ price > 0 && (
				<p style={ { fontSize: 12, color: '#57606a' } }>
					{ mode === 'percent'
						? `${ __( 'Charges', T ) } ${ price }% ${ __( 'of the cart subtotal.', T ) }`
						: mode === 'per_unit'
							? `${ __( 'Charges', T ) } ${ price } ${ __( 'for each unit entered.', T ) }`
							: `${ __( 'Charges a flat', T ) } ${ price }.` }
				</p>
			) }
		</>
	);
}
