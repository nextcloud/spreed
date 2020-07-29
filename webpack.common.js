const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')
const StyleLintPlugin = require('stylelint-webpack-plugin')
const babelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')

module.exports = {
	entry: {
		'admin-settings': path.join(__dirname, 'src', 'mainAdminSettings.js'),
		'collections': path.join(__dirname, 'src', 'collections.js'),
		'talk': path.join(__dirname, 'src', 'main.js'),
		'talk-files-sidebar': path.join(__dirname, 'src', 'mainFilesSidebar.js'),
		'talk-files-sidebar-loader': path.join(__dirname, 'src', 'mainFilesSidebarLoader.js'),
		'talk-public-share-auth-sidebar': path.join(__dirname, 'src', 'mainPublicShareAuthSidebar.js'),
		'talk-public-share-sidebar': path.join(__dirname, 'src', 'mainPublicShareSidebar.js'),
		'flow': path.join(__dirname, 'src', 'flow.js'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader'],
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader'],
			},
			{
				test: /\.(js|vue)$/,
				use: 'eslint-loader',
				exclude: /node_modules/,
				enforce: 'pre',
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader',
				exclude: babelLoaderExcludeNodeModulesExcept([
					'vue-material-design-icons',
				]),
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: babelLoaderExcludeNodeModulesExcept([
					'@juliushaertl/vue-richtext',
					'fast-xml-parser',
					'hot-patcher',
					'nextcloud-vue-collections',
					'webdav',
				]),
			},
			{
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
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]?[hash]',
				},
			},
		],
	},
	plugins: [
		new VueLoaderPlugin(),
		new StyleLintPlugin({
			files: ['**/*.vue'],
		}),
	],
	resolve: {
		extensions: ['*', '.js', '.vue'],
		symlinks: false,
	},
}
