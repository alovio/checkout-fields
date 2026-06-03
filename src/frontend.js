/**
 * Classic-checkout runtime: live show/hide/require. Reads the field group +
 * server-seeded token map emitted by ClassicRenderer (script.clcf-rules /
 * script.clcf-values), overlays the values that change on the page (payment /
 * shipping / country / our own field inputs), recomputes the active map (shared
 * ConditionalLogic), and toggles each field wrapper. Re-runs on input/change and
 * on WooCommerce's `updated_checkout` AJAX refresh.
 *
 * The Block checkout has its own runtime (src/block-checkout). Server-side
 * validation/visibility remain authoritative on submit.
 */
import { activeMap } from './frontend/conditional-logic';

function parsePayload() {
	const rules = document.querySelector( 'script.clcf-rules' );
	const seedNode = document.querySelector( 'script.clcf-values' );
	if ( ! rules ) {
		return null;
	}
	try {
		const group = JSON.parse( rules.textContent );
		const seed = seedNode ? JSON.parse( seedNode.textContent ) : {};
		return { group, seed };
	} catch ( e ) {
		return null;
	}
}

function liveTokens( root ) {
	const tokens = {};
	const pm = root.querySelector( 'input[name="payment_method"]:checked' );
	if ( pm ) {
		tokens[ '@payment_method' ] = pm.value;
	}
	const sm = root.querySelector( 'input[name^="shipping_method"]:checked' ) || root.querySelector( 'input[name^="shipping_method"][type="hidden"]' );
	if ( sm ) {
		tokens[ '@shipping_method' ] = sm.value;
	}
	const bc = root.querySelector( '#billing_country' );
	if ( bc ) {
		tokens[ '@billing_country' ] = bc.value;
	}
	const sc = root.querySelector( '#shipping_country' );
	if ( sc ) {
		tokens[ '@shipping_country' ] = sc.value;
	}
	return tokens;
}

function fieldValues( root, fields ) {
	const values = {};
	fields.forEach( ( f ) => {
		if ( f.type === 'heading' ) {
			return;
		}
		const name = `[name="clcf[${ f.id }]"]`;
		if ( f.type === 'radio' ) {
			const checked = root.querySelector( `${ name }:checked` );
			values[ f.id ] = checked ? checked.value : '';
		} else if ( f.type === 'checkbox' ) {
			const cb = root.querySelector( name );
			values[ f.id ] = cb && cb.checked ? ( cb.value || 'yes' ) : '';
		} else {
			const el = root.querySelector( name );
			values[ f.id ] = el ? el.value : '';
		}
	} );
	return values;
}

function apply( group, seed ) {
	const fields = group.fields || [];
	const root = document;
	const tokens = { ...seed, ...liveTokens( root ), ...fieldValues( root, fields ) };
	const map = activeMap( fields, tokens );

	fields.forEach( ( f ) => {
		const wrap = root.querySelector( `[data-clcf-field="${ f.id }"]` );
		if ( ! wrap ) {
			return;
		}
		const active = !! map[ f.id ];
		wrap.hidden = ! active;
		wrap.querySelectorAll( 'input, select, textarea' ).forEach( ( input ) => {
			input.required = active && !! f.required;
		} );
	} );
}

function init() {
	const payload = parsePayload();
	if ( ! payload ) {
		return;
	}
	const fields = payload.group.fields || [];
	const hasFees = fields.some( ( f ) => parseFloat( f.price ) > 0 );

	const run = () => apply( payload.group, payload.seed );
	document.addEventListener( 'change', run, true );
	document.addEventListener( 'input', run, true );
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'updated_checkout', run );

		// When a priced field changes, ask WooCommerce to recalculate the order
		// review so the fee (added server-side from the posted values) shows live.
		if ( hasFees ) {
			document.addEventListener( 'change', ( e ) => {
				const name = e.target && e.target.name ? e.target.name : '';
				if ( name.indexOf( 'clcf[' ) === 0 ) {
					window.jQuery( document.body ).trigger( 'update_checkout' );
				}
			}, true );
		}
	}
	run();
}

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
