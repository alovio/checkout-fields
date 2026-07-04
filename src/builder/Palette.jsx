import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { TEMPLATES } from './templates';

const T = 'corelabs-checkout-fields';
const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'heading' ];

const META = {
	text: { label: __( 'Text', T ), ic: 'Aa' },
	textarea: { label: __( 'Text area', T ), ic: '¶' },
	number: { label: __( 'Number', T ), ic: '#' },
	checkbox: { label: __( 'Checkbox', T ), ic: '☑' },
	radio: { label: __( 'Radio', T ), ic: '◉' },
	select: { label: __( 'Dropdown', T ), ic: '▾' },
	heading: { label: __( 'Heading', T ), ic: 'H' },
	date: { label: __( 'Date', T ), ic: '📅' },
	file: { label: __( 'File', T ), ic: '📎' },
};

export default function Palette() {
	const { addField, insertFields } = useDispatch( STORE );
	const types = ( window.CLCF_BUILDER && window.CLCF_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="clcf-palette">
			<div className="clcf-sec-label">{ __( 'Add a field', T ) }</div>
			<div className="clcf-ptypes">
				{ types.map( ( type ) => {
					const m = META[ type ] || { label: type, ic: '·' };
					return (
						<button key={ type } className="clcf-ptype" onClick={ () => addField( type ) }>
							<span className="clcf-ic">{ m.ic }</span>
							{ m.label }
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
