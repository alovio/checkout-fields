import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, Snackbar, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './store';
import FieldPalette from './FieldPalette';
import Canvas from './Canvas';
import FieldSettings from './FieldSettings';

const T = 'corelabs-checkout-fields';

export default function App() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { hydrate } = useDispatch( STORE );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
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

	const save = async () => {
		setSaving( true );
		try {
			const group = await apiFetch( { path: 'clcf/v1/fields', method: 'POST', data: { fields } } );
			const f = ( group && group.fields ) || [];
			hydrate( f );
			savedRef.current = JSON.stringify( f );
			setNotice( { type: 'success', text: __( 'Checkout fields saved.', T ) } );
		} catch ( e ) {
			setNotice( { type: 'error', text: __( 'Save failed. Please try again.', T ) } );
		}
		setSaving( false );
	};

	return (
		<div className="clcf-app">
			{ notice && notice.type === 'error' && (
				<Notice status="error" onRemove={ () => setNotice( null ) }>{ notice.text }</Notice>
			) }
			<div className="clcf-build">
				<FieldPalette />
				<Canvas />
				<FieldSettings />
			</div>
			<div className="clcf-actions">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ dirty ? __( 'Save changes •', T ) : __( 'Save changes', T ) }
				</Button>
				{ dirty && <span className="clcf-unsaved">{ __( 'Unsaved changes', T ) }</span> }
			</div>
			{ notice && notice.type === 'success' && (
				<Snackbar onRemove={ () => setNotice( null ) }>{ notice.text }</Snackbar>
			) }
		</div>
	);
}
