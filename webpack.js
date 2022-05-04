const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')
const webpackRules = require('@nextcloud/webpack-vue-config/rules')
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')

webpackConfig.entry = {
	'admin-settings': path.join(__dirname, 'src', 'mainAdminSettings.js'),
	collections: path.join(__dirname, 'src', 'collections.js'),
	main: path.join(__dirname, 'src', 'main.js'),
	'files-sidebar': [
		path.join(__dirname, 'src', 'mainFilesSidebar.js'),
		path.join(__dirname, 'src', 'mainFilesSidebarLoader.js'),
	],
	'public-share-auth-sidebar': path.join(__dirname, 'src', 'mainPublicShareAuthSidebar.js'),
	'public-share-sidebar': path.join(__dirname, 'src', 'mainPublicShareSidebar.js'),
	flow: path.join(__dirname, 'src', 'flow.js'),
	dashboard: path.join(__dirname, 'src', 'dashboard.js'),
	deck: path.join(__dirname, 'src', 'deck.js'),
	maps: path.join(__dirname, 'src', 'maps.js'),
}

webpackConfig.output.assetModuleFilename = '[name][ext]?v=[contenthash]'

// Edit JS rule
webpackRules.RULE_JS.exclude = BabelLoaderExcludeNodeModulesExcept([
	'@juliushaertl/vue-richtext',
	'@nextcloud/event-bus',
	'@nextcloud/vue-dashboard',
	'ansi-regex',
	'color.js',
	'fast-xml-parser',
	'hot-patcher',
	'nextcloud-vue-collections',
	'semver',
	'strip-ansi',
	'tributejs',
	'vue-resize',
	'webdav',
])

// Replaces rules array
webpackConfig.module.rules = Object.values(webpackRules)

webpackConfig.module.rules.push({
	/**
	 * webrtc-adapter main module does no longer provide
	 * "module.exports", which is expected by some elements using it
	 * (like "attachmediastream"), so it needs to be added back with
	 * a plugin.
	 */
	test: /node_modules\/webrtc-adapter\/.*\.js$/,
	loader: 'babel-loader',
	options: {
		plugins: ['add-module-exports'],
		presets: [
			/**
			 * From "add-module-exports" documentation:
			 * "webpack doesn't perform commonjs transformation for
			 * codesplitting. Need to set commonjs conversion."
			 */
			['@babel/env', { modules: 'commonjs' }],
		],
	},
})

webpackConfig.module.rules.push({
	test: /\.wasm$/i,
	type: 'asset/resource',
})

webpackConfig.module.rules.push({
	test: /\.tflite$/i,
	type: 'asset/resource',
})

webpackConfig.module.rules.push({
	test: /\.worker\.js$/,
	use: { loader: 'worker-loader' },
})

// Add typescript rule
webpackConfig.module.rules.push({
	test: /\.tsx?$/,
	use: ['babel-loader', 'ts-loader'],
	exclude: /node_modules/,
})

// Add typescript extension resolver
webpackConfig.resolve.extensions.push('.tsx')

module.exports = webpackConfig
