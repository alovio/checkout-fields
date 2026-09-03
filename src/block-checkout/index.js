/**
 * Block-checkout integration (Store API). Renders the configured checkout fields
 * in the Block checkout, resolves conditional visibility live from the wc/store/*
 * data stores, and submits values via setExtensionData so the server persists them.
 *
 * Config + initial context come from window.CLCF_BLOCK (inlined by
 * BlockFieldsIntegration). The server (BlockIntegration) re-validates/persists
 * authoritatively. See docs/superpowers/notes/block-spike-findings.md.
 */
import { registerPlugin } from '@wordpress/plugins';
import { hasPricedOptions, optionLabel, effectivePrice } from '../shared/options';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { activeMap } from '../frontend/conditional-logic';
import { buildValues } from '../checkout-conditions/data-sources';
import './style.css';

const { ExperimentalOrderMeta, extensionCartUpdate } = window.wc.blocksCheckout;

const CONFIG = window.CLCF_BLOCK || { fields: [], ctx: {}, sources: [] };
// A field priced only through its options is fee-bearing too — miss that and
// the cart update never fires, so the fee is never captured or charged.
/**
 * A choice reads as its label plus what picking it costs — the same rule the
 * classic renderer applies, so both checkouts advertise identical amounts.
 * Fields that do not price their options keep bare labels.
 */
function optionText( field, option ) {
	const label = optionLabel( option );
	if ( ! hasPricedOptions( field ) ) {
		return label;
	}
	const price = effectivePrice( field, label );
	if ( price <= 0 ) {
		return label;
	}
	const suffix = field.priceMode === 'percent' ? `+${ price }%` : `+${ formatPrice( price ) }`;
	return `${ label } (${ suffix })`;
}

/** Store-formatted money, falling back to two decimals. */
function formatPrice( amount ) {
	const cfg = ( window.wcSettings && window.wcSettings.currency ) || {};
	const decimals = typeof cfg.minorUnit === 'number' ? cfg.minorUnit : 2;
	const symbol = cfg.symbol || '';
	const body = Number( amount ).toFixed( decimals );
	return cfg.symbolPosition === 'right' ? `${ body }${ symbol }` : `${ symbol }${ body }`;
}

// Uploaded file names, kept per field so the list can show them. The token
// list in the field value is the source of truth; this is display only.
const FILE_NAMES = {};

const HAS_FEES = ( CONFIG.fields || [] ).some( ( f ) => parseFloat( f.price ) > 0 || hasPricedOptions( f ) );

function selectedShippingRate( cartData ) {
	const packages = ( cartData && cartData.shippingRates ) || [];
	for ( const pkg of packages ) {
		for ( const rate of pkg.shipping_rates || [] ) {
			if ( rate.selected ) {
				return rate.rate_id;
			}
		}
	}
	return '';
}

function initialValues( fields ) {
	const out = {};
	fields.forEach( ( f ) => {
		if ( f.type === 'heading' ) {
			return;
		}
		out[ f.id ] = f.default !== undefined && f.default !== null ? String( f.default ) : '';
	} );
	return out;
}

function Field( { field, value, onChange } ) {
	const id = `clcf_block_${ field.id }`;
	const label = field.label || field.id;

	if ( field.type === 'heading' ) {
		return (
			<div className={ `clcf-block-field clcf-block-field--heading clcf-w-${ field.width === 'half' ? 'half' : 'full' }` }>
				<strong>{ label }</strong>
				{ field.description ? <p className="clcf-block-field__desc">{ field.description }</p> : null }
			</div>
		);
	}

	const common = {
		id,
		value,
		required: !! field.required,
		onChange: ( e ) => onChange( field.type === 'checkbox' ? ( e.target.checked ? 'yes' : '' ) : e.target.value ),
	};

	let control;
	if ( field.type === 'textarea' ) {
		control = <textarea { ...common } rows={ 3 } placeholder={ field.placeholder || '' } />;
	} else if ( field.type === 'number' ) {
		control = <input type="number" { ...common } placeholder={ field.placeholder || '' } min={ field.min || undefined } max={ field.max || undefined } step={ field.step || undefined } />;
	} else if ( field.type === 'checkbox' ) {
		control = <label className="clcf-block-checkbox"><input type="checkbox" id={ id } required={ !! field.required } checked={ value === 'yes' } onChange={ common.onChange } /> { label }</label>;
	} else if ( field.type === 'select' ) {
		control = (
			<select { ...common }>
				<option value="">{ __( 'Choose an option', 'corelabs-checkout-fields' ) }</option>
				{ ( field.options || [] ).map( ( o, i ) => (
					<option key={ i } value={ optionLabel( o ) }>{ optionText( field, o ) }</option>
				) ) }
			</select>
		);
	} else if ( field.type === 'radio' ) {
		control = (
			<span className="clcf-block-radios">
				{ ( field.options || [] ).map( ( o, i ) => (
					<label key={ i }>
						<input
							type="radio"
							name={ id }
							value={ optionLabel( o ) }
							checked={ value === optionLabel( o ) }
							onChange={ () => onChange( optionLabel( o ) ) }
							required={ !! field.required }
						/> { optionText( field, o ) }
					</label>
				) ) }
			</span>
		);
	} else if ( field.type === 'email' || field.type === 'phone' || field.type === 'url' || field.type === 'time' ) {
		const htmlType = { email: 'email', phone: 'tel', url: 'url', time: 'time' }[ field.type ];
		control = <input type={ htmlType } { ...common } placeholder={ field.placeholder || '' } />;
	} else if ( field.type === 'date' ) {
		control = <input type="date" { ...common } min={ field.min || undefined } max={ field.max || undefined } />;
	} else if ( field.type === 'delivery' ) {
		// Value is the composite "date|slot"; the date options are the same
		// server-computed valid days the classic checkout uses (field.validDates).
		const [ curDate = '', curSlot = '' ] = String( value || '' ).split( '|' );
		const slots = field.timeSlots || [];
		const emit = ( d, s ) => onChange( `${ d }|${ s }` );
		control = (
			<span className="clcf-block-delivery">
				<select id={ id } required={ !! field.required } value={ curDate } onChange={ ( e ) => emit( e.target.value, curSlot ) }>
					<option value="">{ __( 'Choose a date', 'corelabs-checkout-fields' ) }</option>
					{ ( field.validDates || [] ).map( ( d ) => (
						<option key={ d.value } value={ d.value }>{ d.label }</option>
					) ) }
				</select>
				{ slots.length > 0 && (
					<select className="clcf-block-delivery__slot" required={ !! field.required } value={ curSlot } onChange={ ( e ) => emit( curDate, e.target.value ) }>
						<option value="">{ __( 'Choose a time', 'corelabs-checkout-fields' ) }</option>
						{ slots.map( ( s, i ) => (
							<option key={ i } value={ s }>{ s }</option>
						) ) }
					</select>
				) }
			</span>
		);
	} else if ( field.type === 'file' ) {
		const cfg = CONFIG.upload || {};
		const maxFiles = Math.min( 10, Math.max( 1, parseInt( field.maxFiles, 10 ) || 1 ) );
		// The value carries the token list; names ride alongside for display.
		const tokens = String( value || '' ).split( ',' ).map( ( t ) => t.trim() ).filter( Boolean );
		const names = FILE_NAMES[ field.id ] || {};

		const pick = async ( e ) => {
			let picked = Array.from( e.target.files || [] );
			if ( ! picked.length ) {
				return;
			}
			const current = maxFiles === 1 ? [] : tokens.slice();
			const room = Math.max( 0, maxFiles - current.length );
			const overflow = picked.length > room;
			picked = picked.slice( 0, room );

			const oversize = picked.filter( ( f ) => cfg.maxMb && f.size > cfg.maxMb * 1048576 );
			picked = picked.filter( ( f ) => ! cfg.maxMb || f.size <= cfg.maxMb * 1048576 );

			const added = {};
			for ( const file of picked ) {
				try {
					const form = new FormData();
					form.append( 'file', file );
					const headers = { 'X-CLCF-Nonce': cfg.nonce };
					if ( cfg.restNonce ) {
						headers[ 'X-WP-Nonce' ] = cfg.restNonce;
					}
					const resp = await fetch( cfg.url, { method: 'POST', headers, body: form } );
					const data = await resp.json();
					if ( ! resp.ok || ! data.token ) {
						throw new Error( ( data && data.message ) || 'upload failed' );
					}
					current.push( data.token );
					added[ data.token ] = data.name;
				} catch ( err ) {
					window.alert( err.message || 'Upload failed — please try again.' );
				}
			}

			e.target.value = '';
			if ( oversize.length ) {
				window.alert( `File is too large (max ${ cfg.maxMb } MB).` );
			} else if ( overflow ) {
				window.alert( `Only ${ maxFiles } files allowed.` );
			}
			FILE_NAMES[ field.id ] = { ...( FILE_NAMES[ field.id ] || {} ), ...added };
			onChange( current.join( ',' ) ); // re-renders, so the new names show
		};

		const removeToken = ( token ) => {
			onChange( tokens.filter( ( t ) => t !== token ).join( ',' ) );
		};

		control = (
			<>
				<input
					type="file"
					id={ id }
					accept={ cfg.accept || undefined }
					multiple={ maxFiles > 1 }
					required={ !! field.required && ! tokens.length }
					onChange={ pick }
				/>
				{ tokens.length > 0 && (
					<span className="clcf-file-list">
						{ tokens.map( ( t ) => (
							<span key={ t } className="clcf-file-item">
								<span className="clcf-file-item__name">{ names[ t ] || __( 'Attached file', 'corelabs-checkout-fields' ) }</span>
								<button
									type="button"
									className="clcf-file-remove"
									aria-label={ __( 'Remove file', 'corelabs-checkout-fields' ) }
									onClick={ () => removeToken( t ) }
								>
									×
								</button>
							</span>
						) ) }
					</span>
				) }
			</>
		);
	} else {
		control = <input type="text" { ...common } placeholder={ field.placeholder || '' } maxLength={ field.maxLength || undefined } />;
	}

	return (
		<div className={ `clcf-block-field clcf-w-${ field.width === 'half' ? 'half' : 'full' }` }>
			{ field.type !== 'checkbox' && (
				<label htmlFor={ id } className="clcf-block-field__label">
					{ label } { field.required ? <abbr title="required">*</abbr> : null }
				</label>
			) }
			{ field.description && field.type !== 'checkbox' ? <small className="clcf-block-field__desc">{ field.description }</small> : null }
			{ control }
		</div>
	);
}

function CheckoutFields() {
	const fields = CONFIG.fields || [];
	const [ values, setValues ] = useState( () => initialValues( fields ) );

	const live = useSelect( ( select ) => {
		const cart = select( 'wc/store/cart' );
		const payment = select( 'wc/store/payment' );
		const cartData = cart ? cart.getCartData() : {};
		const customer = cart ? cart.getCustomerData() : {};
		const ext = cartData && cartData.extensions && cartData.extensions.clcf ? cartData.extensions.clcf : {};
		return {
			serverCtx: ext.ctx || {},
			paymentMethod: payment && payment.getActivePaymentMethod ? payment.getActivePaymentMethod() : '',
			shippingMethod: selectedShippingRate( cartData ),
			billingCountry: ( customer && customer.billingAddress && customer.billingAddress.country ) || '',
			shippingCountry: ( customer && customer.shippingAddress && customer.shippingAddress.country ) || '',
		};
	}, [] );

	// Server context (role / cart categories / etc.) overlaid with the bits the
	// shopper changes live on the page (payment, shipping, address, field values).
	const ctx = {
		...live.serverCtx,
		paymentMethod: live.paymentMethod,
		shippingMethod: live.shippingMethod || live.serverCtx.shippingMethod || '',
		billingCountry: live.billingCountry || live.serverCtx.billingCountry || '',
		shippingCountry: live.shippingCountry || live.serverCtx.shippingCountry || '',
	};
	const tokenMap = buildValues( values, ctx );
	const map = activeMap( fields, tokenMap );

	// Push current values to the Store API whenever they change. When any field
	// carries a fee, also push a cart update so the fee total refreshes live.
	useEffect( () => {
		dispatch( 'wc/store/checkout' ).setExtensionData( 'clcf', { fields: values } );
		if ( HAS_FEES && typeof extensionCartUpdate === 'function' ) {
			extensionCartUpdate( { namespace: 'clcf', data: { fields: values } } );
		}
	}, [ values ] );

	const setValue = ( id, v ) => setValues( ( prev ) => ( { ...prev, [ id ]: v } ) );

	const visible = fields.filter( ( f ) => map[ f.id ] );
	if ( ! visible.length ) {
		return null;
	}

	return (
		<ExperimentalOrderMeta>
			<div className="clcf-block-fields clcf-grid">
				{ visible.map( ( f ) => (
					<Field key={ f.id } field={ f } value={ values[ f.id ] || '' } onChange={ ( v ) => setValue( f.id, v ) } />
				) ) }
			</div>
		</ExperimentalOrderMeta>
	);
}

registerPlugin( 'clcf-checkout-fields', {
	render: CheckoutFields,
	scope: 'woocommerce-checkout',
} );
