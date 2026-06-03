/**
 * Three entry points: the admin builder (index), the classic-checkout runtime
 * (frontend), and the Block-checkout integration (block-checkout). Extends the
 * default @wordpress/scripts config so dependency extraction (per-entry
 * *.asset.php) and externals are preserved.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( process.cwd(), 'src', 'index.js' ),
		frontend: path.resolve( process.cwd(), 'src', 'frontend.js' ),
		'block-checkout': path.resolve( process.cwd(), 'src', 'block-checkout', 'index.js' ),
	},
};
