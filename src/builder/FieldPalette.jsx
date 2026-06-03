import { useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'corelabs-checkout-fields';
const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' ];

const LABELS = {
	text: __( 'Text', T ),
	textarea: __( 'Text area', T ),
	number: __( 'Number', T ),
	checkbox: __( 'Checkbox', T ),
	radio: __( 'Multiple choice', T ),
	select: __( 'Dropdown', T ),
	heading: __( 'Heading', T ),
	date: __( 'Date', T ),
	file: __( 'File upload', T ),
};

export default function FieldPalette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.CLCF_BUILDER && window.CLCF_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="clcf-palette" aria-label={ __( 'Field types', T ) }>
			<h3>{ __( 'Add field', T ) }</h3>
			{ types.map( ( type ) => (
				<Button key={ type } variant="secondary" className="clcf-palette__btn" onClick={ () => addField( type ) }>
					{ LABELS[ type ] || type }
				</Button>
			) ) }
		</div>
	);
}
