const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	plugins: defaultConfig.plugins.filter(
		(plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	),
entry: {
	autocomplete: './src/autocomplete.js',
	smartcompose: './src/smartcompose.js',
	'writing-tips': './src/writing-tips.js',
	'tags-categories': './src/tags-categories.js',
	},
	output: {
		path: __dirname + '/assets/admin/js/dist',
		filename: '[name].js',
	},
	externals: {
		'@wordpress/hooks': 'wp.hooks',
		'@wordpress/api-fetch': 'wp.apiFetch',
		'@wordpress/dom-ready': 'wp.domReady',
		'@wordpress/plugins': 'wp.plugins',
		'@wordpress/editor': 'wp.editor',
		'@wordpress/edit-post': 'wp.editPost',
		'@wordpress/components': 'wp.components',
		'@wordpress/data': 'wp.data',
		'@wordpress/element': 'wp.element',
		'@wordpress/i18n': 'wp.i18n',
		'@wordpress/block-editor': 'wp.blockEditor',
		'@wordpress/compose': 'wp.compose'
	},
};
