/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * External dependencies
 */
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const path = require( 'path' );

/**
 * AllyWorker webpack configuration.
 *
 * Extends the default @wordpress/scripts webpack config with:
 *  - Admin UI entry point    (src/admin/index.js    → assets/admin/admin.js)
 *  - Frontend entry point    (src/frontend/index.js → assets/frontend/frontend.js)
 *
 * Source structure:
 *   src/
 *   ├── admin/
 *   │   ├── index.js        ← Admin UI entry (React JSX)
 *   │   ├── components/
 *   │   └── admin.scss      ← Admin styles (import inside index.js)
 *   └── frontend/
 *       ├── index.js        ← Frontend entry (vanilla JS)
 *       └── frontend.scss   ← Frontend styles (import inside index.js)
 *
 * Output structure:
 *   assets/
 *   ├── admin/
 *   │   ├── admin.js          ← Compiled + minified JS bundle
 *   │   ├── admin.css         ← Extracted + minified CSS
 *   │   └── admin.asset.php   ← { dependencies: [...], version: '...' }
 *   └── frontend/
 *       ├── frontend.js
 *       ├── frontend.css
 *       └── frontend.asset.php
 */

const rootDir = process.cwd();

module.exports = {
	...defaultConfig,

	devtool: false,

	entry: {
		...defaultConfig.entry(),
		'assets/admin/admin':       path.resolve( rootDir, 'src/admin',    'index.js' ),
		// 'assets/frontend/frontend': path.resolve( rootDir, 'src/frontend', 'index.js' ),
	},

	output: {
		...defaultConfig.output,
		path:  path.resolve( rootDir ),
		clean: false,
	},

	optimization: {
		...defaultConfig.optimization,
		splitChunks:   false,
		runtimeChunk:  false,
	},

	plugins: [
		...defaultConfig.plugins,

		// Removes the empty `.js` stubs webpack generates for CSS-only entry points.
		// Must run after @wordpress/scripts has written its *.asset.php files.
		new RemoveEmptyScriptsPlugin( {
			stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
		} ),
	],
};