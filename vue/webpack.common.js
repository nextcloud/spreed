const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	entry: {
		"collections": path.join(__dirname, 'src', 'collections.js'),
		"collectionsintegration": path.join(__dirname, 'src', 'collectionsintegration.js'),
		"admin/allowed-groups": path.join(__dirname, 'src', 'allowed-groups.js'),
		"admin/commands": path.join(__dirname, 'src', 'commands.js'),
		"admin/signaling-server": path.join(__dirname, 'src', 'signaling-server.js'),
		"admin/stun-server": path.join(__dirname, 'src', 'stun-server.js'),
		"admin/turn-server": path.join(__dirname, 'src', 'turn-server.js'),
		"vue": path.join(__dirname, 'src', 'frontpage.js'),
	},
	output: {
		path: path.resolve(__dirname, '../js'),
		publicPath: '/js/',
		filename: '[name].js'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				test: /\.scss$/,
				use: ['vue-style-loader', 'css-loader', 'sass-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
			{
				enforce: 'pre',
				test: /\.(js|vue)$/,
				loader: 'eslint-loader',
				exclude: /node_modules/
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			},
			{
				/**
				 * Fixes lodash registering globally and therefore replacing server's underscore
				 *
				 * https://github.com/lodash/lodash/issues/1798#issuecomment-233804586
				 * https://github.com/webpack/webpack/issues/3017#issuecomment-285954512
				 */
				parser: {
					amd: false
				}
			}
		]
	},
	plugins: [new VueLoaderPlugin()],
	resolve: {
		alias: {
			Components: path.resolve(__dirname, 'src/components/'),
			Views: path.resolve(__dirname, 'src/views/')
		},
		extensions: ['*', '.js', '.vue', '.json'],
		modules: [
			path.resolve(__dirname),
			path.resolve(__dirname, 'src'),
			path.join(__dirname, 'node_modules'),
			'node_modules'
		]

	}
};
