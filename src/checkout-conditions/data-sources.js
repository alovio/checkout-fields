/**
 * JS mirror of PHP CheckoutConditionData::from_values. Pure: maps submitted field
 * values + a plain context object to the flat token map ConditionalLogic consumes.
 * Kept in lockstep with the PHP side via tests/fixtures/condition-data-cases.json.
 *
 * Token scheme: submitted field values keyed by bare field id; context sources by
 * `@`-prefixed tokens (@user_role, @cart_has_category:<id>, @payment_method, …).
 *
 * The classic/Block runtimes assemble `ctx` from the DOM / wc/store/* and call this.
 */
export function buildValues( fieldValues, ctx = {} ) {
	const values = {};

	Object.keys( fieldValues || {} ).forEach( ( id ) => {
		const v = fieldValues[ id ];
		values[ String( id ) ] = v === null || v === undefined || typeof v === 'object' ? '' : String( v );
	} );

	const role = ctx.role ? String( ctx.role ) : '';
	values[ '@user_role' ] = role !== '' ? role : 'guest';

	( ctx.cartCategories || [] ).forEach( ( tid ) => {
		values[ '@cart_has_category:' + parseInt( tid, 10 ) ] = 'yes';
	} );
	( ctx.cartProducts || [] ).forEach( ( pid ) => {
		values[ '@cart_has_product:' + parseInt( pid, 10 ) ] = 'yes';
	} );

	values[ '@cart_qty' ] = String( parseInt( ctx.cartQty || 0, 10 ) );
	values[ '@cart_subtotal' ] = String( Number( ctx.cartSubtotal || 0 ) );
	values[ '@shipping_method' ] = String( ctx.shippingMethod || '' );
	values[ '@payment_method' ] = String( ctx.paymentMethod || '' );
	values[ '@billing_country' ] = String( ctx.billingCountry || '' );
	values[ '@shipping_country' ] = String( ctx.shippingCountry || '' );

	return values;
}
