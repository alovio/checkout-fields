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
	} else if ( field.type === 'file' ) {
		const cfg = CONFIG.upload || {};
		const pick = async ( e ) => {
			const file = e.target.files && e.target.files[ 0 ];
			if ( ! file ) {
				onChange( '' );
				return;
			}
			if ( cfg.maxMb && file.size > cfg.maxMb * 1048576 ) {
				e.target.value = '';
				onChange( '' );
				window.alert( `File is too large (max ${ cfg.maxMb } MB).` );
				return;
			}
			try {
				const form = new FormData();
				form.append( 'file', file );
				const resp = await fetch( cfg.url, { method: 'POST', headers: { 'X-CLCF-Nonce': cfg.nonce }, body: form } );
				const data = await resp.json();
				if ( ! resp.ok || ! data.token ) {
					throw new Error( ( data && data.message ) || 'upload failed' );
				}
				onChange( data.token );
			} catch ( err ) {
				e.target.value = '';
				onChange( '' );
				window.alert( err.message || 'Upload failed — please try again.' );
			}
		};
		control = (
			<>
				<input type="file" id={ id } accept={ cfg.accept || undefined } required={ !! field.required && ! value } onChange={ pick } />
				{ value ? <small className="clcf-block-field__desc">✓ { __( 'File attached', 'corelabs-checkout-fields' ) }</small> : null }
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
