import { useDispatch } from '@wordpress/data';
import { Button, TextControl, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { STORE } from '../store';
import { optionLabel, optionPrice } from '../../shared/options';

const T = 'corelabs-checkout-fields';

/** Field types whose value is one of a fixed list of options. */
export const HAS_OPTIONS_TYPES = [ 'select', 'radio' ];

/** Options are strings until they carry a price; edit them as objects either way. */
const asObject = ( o ) => ( o && typeof o === 'object' ? { ...o, label: optionLabel( o ) } : { label: optionLabel( o ) } );
const priceOf = ( o ) => {
	const p = optionPrice( o );
	return p > 0 ? String( p ) : '';
};

/** Labels that appear more than once — they are one value to the checkout. */
function duplicateLabels( opts ) {
	const seen = new Set();
	const dupes = new Set();
	opts.forEach( ( o ) => {
		const l = optionLabel( o ).trim();
		if ( ! l ) {
			return;
		}
		if ( seen.has( l ) ) {
			dupes.add( l );
		}
		seen.add( l );
	} );
	return [ ...dupes ];
}

/**
 * One row per choice: a label and its own optional fee. Leaving the price empty
 * falls back to the field price on the Fee tab.
 */
export default function Options( { field } ) {
	const { updateField } = useDispatch( STORE );
	const opts = field.options || [];
	const set = ( options ) => updateField( field.id, { options } );

	const row = ( i, patch ) => {
		const next = opts.map( ( o, j ) => {
			if ( j !== i ) {
				return o;
			}
			const merged = { ...asObject( o ), ...patch };
			const price = parseFloat( merged.price );
			if ( isNaN( price ) || price <= 0 ) {
				delete merged.price;
				return merged.label; // nothing extra to carry — back to a string.
			}
			merged.price = price;
			return merged;
		} );
		set( next );
	};

	const removeRow = ( i ) => set( opts.filter( ( _, j ) => j !== i ) );
	const addRow = () => set( [ ...opts, `${ __( 'Option', T ) } ${ opts.length + 1 }` ] );

	const dupes = duplicateLabels( opts );

	return (
		<>
			{ opts.map( ( o, i ) => (
				<div key={ i } className="clcf-optrow">
					<TextControl
						label={ 0 === i ? __( 'Options', T ) : undefined }
						value={ asObject( o ).label }
						onChange={ ( v ) => row( i, { label: v } ) }
					/>
					<div className="clcf-optprice">
						<TextControl
							type="number"
							min={ 0 }
							step="any"
							label={ 0 === i ? __( 'Fee', T ) : undefined }
							placeholder="0"
							value={ priceOf( o ) }
							aria-label={ __( 'Fee for this option', T ) }
							onChange={ ( v ) => row( i, { price: v } ) }
						/>
					</div>
					<Button
						isDestructive
						variant="tertiary"
						aria-label={ __( 'Remove option', T ) }
						onClick={ () => removeRow( i ) }
					>
						✕
					</Button>
				</div>
			) ) }

			<Button variant="secondary" onClick={ addRow }>＋ { __( 'Add option', T ) }</Button>

			{ ! opts.length && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }

			{ dupes.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: comma-separated option labels */
						__( 'Two options share the same label (%s). The checkout cannot tell them apart, so both charge the first one’s fee — rename one.', T ),
						dupes.join( ', ' )
					) }
				</Notice>
			) }

			{ !! opts.length && (
				<p className="clcf-opthelp">
					{ __( 'Give an option its own fee to charge a different amount per choice — e.g. Standard 0, Express 10. Leave it empty to fall back to the field fee on the Fee tab.', T ) }
				</p>
			) }
		</>
	);
}
