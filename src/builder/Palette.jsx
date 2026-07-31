import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import FieldIcon from '../shared/FieldIcon';
import { TEMPLATES } from './templates';

const T = 'corelabs-checkout-fields';
const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' ];


const LABELS = {
	text: __( 'Text', T ),
	textarea: __( 'Text area', T ),
	number: __( 'Number', T ),
	checkbox: __( 'Checkbox', T ),
	radio: __( 'Radio', T ),
	select: __( 'Dropdown', T ),
	heading: __( 'Heading', T ),
	email: __( 'Email', T ),
	phone: __( 'Phone', T ),
	url: __( 'URL', T ),
	date: __( 'Date', T ),
	time: __( 'Time', T ),
	file: __( 'File', T ),
};

export default function Palette() {
	const { addField, insertFields } = useDispatch( STORE );
	const types = ( window.CLCF_BUILDER && window.CLCF_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="clcf-palette">
			<div className="clcf-sec-label">{ __( 'Add a field', T ) }</div>
			<div className="clcf-ptypes">
				{ types.map( ( type ) => {
					return (
						<button key={ type } className="clcf-ptype" onClick={ () => addField( type ) }>
							<span className="clcf-ic"><FieldIcon type={ type } /></span>
							{ LABELS[ type ] || type }
						</button>
					);
				} ) }
			</div>

			<div className="clcf-sec-label">{ __( 'Start from a template', T ) }</div>
			<div className="clcf-tpl">
				<h4>{ __( 'One-click recipes', T ) }</h4>
				<p>{ __( 'Pre-built fields with conditional logic already wired.', T ) }</p>
				<div className="clcf-chips">
					{ TEMPLATES.map( ( tpl ) => (
						<button key={ tpl.key } className="clcf-chip" title={ tpl.description } onClick={ () => insertFields( tpl.fields ) }>
							{ tpl.title }
						</button>
					) ) }
				</div>
			</div>
		</div>
	);
}
