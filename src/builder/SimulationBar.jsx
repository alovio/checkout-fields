import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { CART_PRESETS } from './simulation';

const T = 'corelabs-checkout-fields';
const B = () => window.CLCF_BUILDER || {};

function srcValues( token ) {
	const def = ( B().sources || [] ).find( ( s ) => s.token === token );
	return ( def && def.values ) || [];
}

function Pick( { label, value, options, isActive, onChange } ) {
	const current = options.find( ( o ) => o.value === value );
	return (
		<span className={ `clcf-sim${ isActive ? ' is-active' : '' }` }>
			{ label }: { current ? current.label : __( 'Any', T ) } <span className="clcf-car"></span>
			<select value={ value } onChange={ ( e ) => onChange( e.target.value ) } aria-label={ label }>
				<option value="">{ __( 'Any', T ) }</option>
				{ options.map( ( o ) => (
					<option key={ o.value } value={ o.value }>{ o.label }</option>
				) ) }
			</select>
		</span>
	);
}

export default function SimulationBar() {
	const sim = useSelect( ( select ) => select( STORE ).getSim(), [] );
	const { setSim } = useDispatch( STORE );

	const roles = srcValues( '@user_role' );
	const payments = srcValues( '@payment_method' );
	const shippings = srcValues( '@shipping_method' );
	const countries = srcValues( '@billing_country' );
	const carts = CART_PRESETS.map( ( p ) => ( { value: p.key, label: p.label } ) );

	return (
		<div className="clcf-simbar">
			<span className="clcf-lbl"><span className="clcf-eye"></span>{ __( 'PREVIEW AS', T ) }</span>
			<Pick label={ __( 'Role', T ) } value={ sim.role } options={ roles } isActive={ sim.role && sim.role !== 'guest' } onChange={ ( v ) => setSim( { role: v || 'guest' } ) } />
			<Pick label={ __( 'Payment', T ) } value={ sim.payment } options={ payments } isActive={ !! sim.payment } onChange={ ( v ) => setSim( { payment: v } ) } />
			<Pick label={ __( 'Shipping', T ) } value={ sim.shipping } options={ shippings } isActive={ !! sim.shipping } onChange={ ( v ) => setSim( { shipping: v } ) } />
			<Pick label={ __( 'Cart', T ) } value={ sim.cartPreset } options={ carts } isActive={ sim.cartPreset && sim.cartPreset !== 'default' } onChange={ ( v ) => setSim( { cartPreset: v || 'default' } ) } />
			<Pick label={ __( 'Country', T ) } value={ sim.country } options={ countries } isActive={ !! sim.country } onChange={ ( v ) => setSim( { country: v } ) } />
			<span className="clcf-hint">{ __( 'Conditions evaluate live — exactly like your real checkout', T ) }</span>
		</div>
	);
}
