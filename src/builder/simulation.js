/**
 * Simulation → engine input. Pure — the SimulationBar UI writes `sim` to the
 * store; this converts it to the ctx that buildValues() consumes, so the canvas
 * preview runs the exact engine the live checkout runs.
 */

export const CART_PRESETS = [
	{ key: 'empty', label: 'Empty cart', qty: 0, subtotal: 0 },
	{ key: 'default', label: '2 items · $48', qty: 2, subtotal: 48 },
	{ key: 'big', label: '5 items · $180', qty: 5, subtotal: 180 },
];

/**
 * @param {Object} sim { role, payment, shipping, country, cartPreset }
 * @return {Object} ctx for buildValues()
 */
export function simToCtx( sim ) {
	const preset = CART_PRESETS.find( ( p ) => p.key === ( sim.cartPreset || 'default' ) ) || CART_PRESETS[ 1 ];
	return {
		role: sim.role || 'guest',
		cartCategories: [],
		cartProducts: [],
		cartQty: preset.qty,
		cartSubtotal: preset.subtotal,
		shippingMethod: sim.shipping || '',
		paymentMethod: sim.payment || '',
		billingCountry: sim.country || '',
		shippingCountry: sim.country || '',
	};
}

/**
 * Default submitted values for preview: each field's default (checkbox default
 * 'yes' when set), so conditions on sibling fields evaluate sensibly.
 *
 * @param {Array} fields
 * @return {Object} field id -> value
 */
export function previewFieldValues( fields ) {
	const out = {};
	( fields || [] ).forEach( ( f ) => {
		if ( f.type === 'heading' ) {
			return;
		}
		out[ f.id ] = f.default !== undefined && f.default !== null ? String( f.default ) : '';
	} );
	return out;
}
