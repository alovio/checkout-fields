import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './store';
import Palette from './Palette';
import SimulationBar from './SimulationBar';
import PreviewCanvas from './PreviewCanvas';
import SettingsPanel from './SettingsPanel';

const T = 'corelabs-checkout-fields';

export default function AppShell() {
	const { fields, canUndo } = useSelect( ( select ) => ( {
		fields: select( STORE ).getFields(),
		canUndo: select( STORE ).canUndo(),
	} ), [] );
	const { hydrate, undo } = useDispatch( STORE );
	const [ saving, setSaving ] = useState( false );
	const [ flash, setFlash ] = useState( null ); // 'saved' | 'error' | null
	const savedRef = useRef( null );

	useEffect( () => {
		apiFetch( { path: 'clcf/v1/fields' } )
			.then( ( group ) => {
				const f = ( group && group.fields ) || [];
				hydrate( f );
				savedRef.current = JSON.stringify( f );
			} )
			.catch( () => {
				savedRef.current = '[]';
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const dirty = savedRef.current !== null && JSON.stringify( fields ) !== savedRef.current;

	useEffect( () => {
		const handler = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );

	const save = useCallback( async () => {
		setSaving( true );
		try {
			const group = await apiFetch( { path: 'clcf/v1/fields', method: 'POST', data: { fields } } );
			const f = ( group && group.fields ) || [];
			hydrate( f );
			savedRef.current = JSON.stringify( f );
			setFlash( 'saved' );
			setTimeout( () => setFlash( null ), 2500 );
		} catch ( e ) {
			setFlash( 'error' );
		}
		setSaving( false );
	}, [ fields, hydrate ] );

	// ⌘S / Ctrl+S saves.
	useEffect( () => {
		const onKey = ( e ) => {
			if ( ( e.metaKey || e.ctrlKey ) && e.key.toLowerCase() === 's' ) {
				e.preventDefault();
				save();
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ save ] );

	let statusCls = 'clcf-status';
	let statusTxt = __( 'All changes saved', T );
	if ( flash === 'error' ) {
		statusCls += ' is-error';
		statusTxt = __( 'Save failed — try again', T );
	} else if ( dirty ) {
		statusCls += ' is-dirty';
		statusTxt = __( 'Unsaved changes', T );
	} else if ( flash === 'saved' ) {
		statusCls += ' is-saved';
		statusTxt = __( 'Saved', T );
	}

	return (
		<div className="clcf-app">
			<div className="clcf-hdr">
				<div className="clcf-logo">
					<span className="clcf-mark">▲</span>
					Alovio <span className="clcf-sub">{ __( 'Checkout Fields', T ) }</span>
				</div>
				<div className="clcf-grow"></div>
				{ /* Alovio family cross-promo — the Calculator is the flagship. */ }
				<a className="clcf-more" href="https://alovio.org/calculator" target="_blank" rel="noopener noreferrer">
					{ __( 'Try Alovio Calculator', T ) } ↗
				</a>
				<span className={ statusCls }><span className="clcf-dot"></span>{ statusTxt }</span>
				<button className="clcf-btn-ghost" disabled={ ! canUndo } onClick={ undo }>⟲ { __( 'Undo', T ) }</button>
				<button className="clcf-btn-primary" disabled={ saving } onClick={ save }>
					{ saving ? __( 'Saving…', T ) : __( 'Save & publish', T ) }
				</button>
			</div>
			<div className="clcf-work">
				<Palette />
				<div className="clcf-canvas-col">
					<SimulationBar />
					<PreviewCanvas />
				</div>
				<SettingsPanel />
			</div>
		</div>
	);
}
