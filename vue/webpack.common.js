const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	entry: {
		// "collections" can not be bundled with other files, as it is used not
		// only by Talk but also by any other app that uses collections.
		"collections": path.join(__dirname, 'src', 'collections.js'),
		"main": [
			path.join(__dirname, 'src', 'collectionsintegration.js'),
			path.join(__dirname, 'src', 'lobbytimerpicker.js'),
		],
		"admin/allowed-groups": path.join(__dirname, 'src', 'AllowedGroupsSettings.js'),
		"admin/commands": path.join(__dirname, 'src', 'CommandsSettings.js'),
		"admin/general-settings": path.join(__dirname, 'src', 'GeneralSettings.js'),
		"admin/signaling-server": path.join(__dirname, 'src', 'SignalingServerSettings.js'),
		"admin/stun-server": path.join(__dirname, 'src', 'StunServerSettings.js'),
		"admin/turn-server": path.join(__dirname, 'src', 'TurnServerSettings.js'),
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
				loader: 'vue-loader',
				options: {
					hotReload: false // disables Hot Reload
				}
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
