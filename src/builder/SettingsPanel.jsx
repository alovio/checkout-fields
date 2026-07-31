import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import FieldIcon from '../shared/FieldIcon';
import General from './panels/General';
import Logic from './panels/Logic';
import Options, { HAS_OPTIONS_TYPES } from './panels/Options';
import Layout from './panels/Layout';
import Fee from './panels/Fee';

const T = 'corelabs-checkout-fields';


export default function SettingsPanel() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const [ tab, setTab ] = useState( 'general' );

	// Reset to General when switching fields.
	useEffect( () => {
		setTab( 'general' );
	}, [ field && field.id ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! field ) {
		return (
			<div className="clcf-settings">
				<div className="clcf-sp-empty">
					{ __( 'Select a field on the canvas to edit its settings — or add one from the left.', T ) }
				</div>
			</div>
		);
	}

	const meta = [ field.type, field.required ? __( 'required', T ) : null ].filter( Boolean ).join( ' · ' );

	return (
		<div className="clcf-settings">
			<div className="clcf-sp-head">
				<div className="clcf-sp-title">
					<span className="clcf-ic"><FieldIcon type={ field.type } /></span>
					<div>
						<h3>{ field.label || field.type }</h3>
						<small>{ meta }</small>
					</div>
				</div>
				<div className="clcf-tabs">
					<button className={ `clcf-tab${ tab === 'general' ? ' is-on' : '' }` } onClick={ () => setTab( 'general' ) }>{ __( 'General', T ) }</button>
					{ HAS_OPTIONS_TYPES.includes( field.type ) && (
						<button className={ `clcf-tab${ tab === 'options' ? ' is-on' : '' }` } onClick={ () => setTab( 'options' ) }>{ __( 'Options', T ) }</button>
					) }
					<button className={ `clcf-tab${ tab === 'logic' ? ' is-on' : '' }` } onClick={ () => setTab( 'logic' ) }>{ __( 'Logic', T ) }</button>
					<button className={ `clcf-tab${ tab === 'layout' ? ' is-on' : '' }` } onClick={ () => setTab( 'layout' ) }>{ __( 'Layout', T ) }</button>
					{ field.type !== 'heading' && (
						<button className={ `clcf-tab${ tab === 'fee' ? ' is-on' : '' }` } onClick={ () => setTab( 'fee' ) }>{ __( 'Fee', T ) }</button>
					) }
				</div>
			</div>
			<div className="clcf-sp-body">
				{ tab === 'general' && <General field={ field } /> }
				{ tab === 'options' && HAS_OPTIONS_TYPES.includes( field.type ) && <Options field={ field } /> }
				{ tab === 'logic' && <Logic field={ field } /> }
				{ tab === 'layout' && <Layout field={ field } /> }
				{ tab === 'fee' && field.type !== 'heading' && <Fee field={ field } /> }
			</div>
			<div className="clcf-sp-foot">
				{ __( 'Changes preview instantly', T ) } · <span className="clcf-kbd">⌘S</span> { __( 'saves', T ) }
			</div>
		</div>
	);
}
