import { useDispatch } from '@wordpress/data';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

const T = 'corelabs-checkout-fields';
const REGIONS = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.locations ) || [ { value: 'after_order_notes', label: 'After order notes' } ];

export default function Layout( { field } ) {
	const { updateField, setRegion } = useDispatch( STORE );
	const width = field.width === 'half' ? 'half' : 'full';

	return (
		<>
			<div className="clcf-then-lbl">{ __( 'Field width', T ) }</div>
			<div className="clcf-widthpick">
				<button className={ width === 'full' ? 'is-on' : '' } onClick={ () => updateField( field.id, { width: 'full' } ) }>
					<span className="clcf-diagram"><span className="is-hl"></span></span>
					{ __( 'Full width', T ) }
				</button>
				<button className={ width === 'half' ? 'is-on' : '' } onClick={ () => updateField( field.id, { width: 'half' } ) }>
					<span className="clcf-diagram"><span className="is-hl"></span><span></span></span>
					{ __( 'Half width', T ) }
				</button>
			</div>

			<SelectControl
				label={ __( 'Checkout region', T ) }
				value={ field.location || 'after_order_notes' }
				options={ REGIONS().map( ( r ) => ( { label: r.label, value: r.value } ) ) }
				onChange={ ( v ) => {
					updateField( field.id, { location: v } );
					setRegion( v ); // canvas follows the field
				} }
				help={ __( 'Where this field appears on the checkout page.', T ) }
			/>
		</>
	);
}
