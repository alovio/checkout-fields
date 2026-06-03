import { createReduxStore, register } from '@wordpress/data';
import { reducer, actions, selectors } from './reducer';

export const STORE = 'clcf/builder';

const store = createReduxStore( STORE, { reducer, actions, selectors } );
register( store );

export default store;
