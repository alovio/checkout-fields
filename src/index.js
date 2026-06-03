import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './builder/store';
import App from './builder/App';
import '../assets/css/builder.css';

domReady( () => {
	const el = document.getElementById( 'clcf-builder-root' );
	if ( ! el ) {
		return;
	}

	if ( window.CLCF_BUILDER ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( window.CLCF_BUILDER.root ) );
		apiFetch.use( apiFetch.createNonceMiddleware( window.CLCF_BUILDER.nonce ) );
	}

	createRoot( el ).render( <App /> );
} );
