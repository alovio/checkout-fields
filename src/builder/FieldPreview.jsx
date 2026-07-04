import { __ } from '@wordpress/i18n';
import { describeCondition, conditionAction } from './describe';

const T = 'corelabs-checkout-fields';
const SOURCES = () => ( window.CLCF_BUILDER && window.CLCF_BUILDER.sources ) || [];

function Control( { field } ) {
	const ph = field.placeholder || '';
	switch ( field.type ) {
		case 'textarea':
			return <div className="clcf-input clcf-input--area">{ ph || '…' }</div>;
		case 'number':
			return <div className="clcf-input">{ ph || '0' }</div>;
		case 'checkbox':
			return <div className="clcf-input clcf-input--check">{ field.default === 'yes' ? '✓' : '' }</div>;
		case 'radio':
			return (
				<div className="clcf-opts">
					{ ( field.options || [] ).slice( 0, 4 ).map( ( o ) => (
						<span key={ o }><span className="clcf-radio"></span>{ o }</span>
					) ) }
				</div>
			);
		case 'select':
			return <div className="clcf-input">{ ( field.options && field.options[ 0 ] ) || __( 'Choose…', T ) } ▾</div>;
		default:
			return <div className="clcf-input">{ ph || '…' }</div>;
	}
}

/**
 * One field, rendered checkout-style inside the preview canvas.
 */
export default function FieldPreview( { field, fields, selected, ghost, reason, onSelect, onDuplicate, onRemove, dragHandlers = {} } ) {
	const cls = [
		'clcf-fld',
		`clcf-w-${ field.width === 'half' ? 'half' : 'full' }`,
		selected ? 'is-selected' : '',
		ghost ? 'is-ghost' : '',
		dragHandlers.isDragging ? 'is-dragging' : '',
		dragHandlers.isDropTarget ? 'is-drop-target' : '',
	].filter( Boolean ).join( ' ' );

	const summary = describeCondition( field, SOURCES(), fields );

	if ( field.type === 'heading' ) {
		return (
			<div
				className={ cls }
				role="button"
				tabIndex={ 0 }
				onClick={ onSelect }
				onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { onSelect(); } } }
				{ ...dragHandlers.props }
			>
				{ selected && <Ops onDuplicate={ onDuplicate } onRemove={ onRemove } /> }
				{ selected && <span className="clcf-grip" title={ __( 'Drag to reorder', T ) }>⠿</span> }
				<div className="clcf-heading-preview">{ field.label || __( 'Heading', T ) }</div>
				{ field.description ? <div className="clcf-fdesc">{ field.description }</div> : null }
				{ summary && ! ghost ? <span className="clcf-logic-flag">IF · { summary } → { conditionAction( field ) }</span> : null }
			</div>
		);
	}

	return (
		<div
			className={ cls }
			role="button"
			tabIndex={ 0 }
			onClick={ onSelect }
			onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { onSelect(); } } }
			{ ...dragHandlers.props }
		>
			{ selected && <Ops onDuplicate={ onDuplicate } onRemove={ onRemove } /> }
			{ selected && <span className="clcf-grip" title={ __( 'Drag to reorder', T ) }>⠿</span> }
			<div className="clcf-flabel">
				{ field.label || field.type }
				{ field.required ? <span className="clcf-req">*</span> : null }
			</div>
			{ field.description ? <div className="clcf-fdesc">{ field.description }</div> : null }
			<Control field={ field } />
			{ ghost && reason ? <div className="clcf-fdesc" style={ { marginTop: 7 } }>{ reason }</div> : null }
			{ summary && ! ghost ? <span className="clcf-logic-flag">IF · { summary } → { conditionAction( field ) }</span> : null }
		</div>
	);
}

function Ops( { onDuplicate, onRemove } ) {
	return (
		<span className="clcf-ops">
			<button className="clcf-op" aria-label={ __( 'Duplicate', T ) } onClick={ ( e ) => { e.stopPropagation(); onDuplicate(); } }>⧉</button>
			<button className="clcf-op" aria-label={ __( 'Delete', T ) } onClick={ ( e ) => { e.stopPropagation(); onRemove(); } }>✕</button>
		</span>
	);
}
