/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const browserslistConfig = require('@nextcloud/browserslist-config')
const { defineConfig } = require('@rspack/cli')
const { CopyRspackPlugin } = require('@rspack/core')
const { CssExtractRspackPlugin, LightningCssMinimizerRspackPlugin, DefinePlugin, ProgressPlugin, SwcJsMinimizerRspackPlugin } = require('@rspack/core')
const NodePolyfillPlugin = require('@rspack/plugin-node-polyfill')
const browserslist = require('browserslist')
const path = require('node:path')
const { VueLoaderPlugin } = require('vue-loader')

// browserslist-rs does not support baseline queries yet
// Manually resolving the browserslist config to the list of browsers with minimal versions
// See: https://github.com/browserslist/browserslist-rs/issues/40
const browsers = browserslist(browserslistConfig)
const minBrowserVersion = browsers
	.map((str) => str.split(' '))
	.reduce((minVersion, [browser, version]) => {
		minVersion[browser] = minVersion[browser] ? Math.min(minVersion[browser], parseFloat(version)) : parseFloat(version)
		return minVersion
	}, {})
const targets = Object.entries(minBrowserVersion).map(([browser, version]) => `${browser} >=${version}`).join(',')

module.exports = defineConfig((env) => {
	const appName = process.env.npm_package_name
	const appVersion = process.env.npm_package_version

	const mode = (env.development && 'development') || (env.production && 'production') || process.env.NODE_ENV || 'production'
	const isDev = mode === 'development'
	process.env.NODE_ENV = mode

	console.info('Building', appName, appVersion, '\n')

	return {
		target: 'web',
		mode,
		devtool: isDev ? 'cheap-source-map' : 'source-map',

		entry: {
			'admin-settings': path.join(__dirname, 'src', 'mainAdminSettings.js'),
			collections: path.join(__dirname, 'src', 'collections.js'),
			main: path.join(__dirname, 'src', 'main.js'),
			recording: path.join(__dirname, 'src', 'mainRecording.js'),
			'files-sidebar': [
				path.join(__dirname, 'src', 'mainFilesSidebar.js'),
				path.join(__dirname, 'src', 'mainFilesSidebarLoader.js'),
			],
			'public-share-auth-sidebar': path.join(__dirname, 'src', 'mainPublicShareAuthSidebar.js'),
			'public-share-sidebar': path.join(__dirname, 'src', 'mainPublicShareSidebar.js'),
			flow: path.join(__dirname, 'src', 'flow.js'),
			deck: path.join(__dirname, 'src', 'deck.js'),
			maps: path.join(__dirname, 'src', 'maps.js'),
			search: path.join(__dirname, 'src', 'search.js'),
			icons: path.join(__dirname, 'src', 'icons.css'),
		},

		output: {
			path: path.resolve('./js'),
			filename: `${appName}-[name].js?v=[contenthash]`,
			chunkFilename: `${appName}-[name].js?v=[contenthash]`,
			// Set publicPath via __webpack_public_path__
			publicPath: 'auto',
			// We are working with .wasm and .tflite files as resources on a public path: it must be with the original name in the output folder's root
			assetModuleFilename: '[name][ext]?v=[contenthash]',
			// We are working with .wasm files as resources on a public path: disabling default wasm loading as source
			wasmLoading: false,
			enabledWasmLoadingTypes: [],
			clean: true,
			devtoolNamespace: appName,
			// Make sure sourcemaps have a proper path and do not leak local paths
			// Source: @nextcloud/webpack-vue-config
			devtoolModuleFilenameTemplate(info) {
				const rootDir = process.cwd()
				const rel = path.relative(rootDir, info.absoluteResourcePath)
				return `webpack:///${appName}/${rel}`
			},
		},

		devServer: {
			hot: true,
			host: '127.0.0.1',
			port: 3000,
			client: {
				overlay: false,
			},
			devMiddleware: {
				writeToDisk: true,
			},
			headers: {
				'Access-Control-Allow-Origin': '*',
			},
		},

		optimization: {
			chunkIds: 'named',
			splitChunks: {
				automaticNameDelimiter: '-',
				cacheGroups: {
					defaultVendors: {
						reuseExistingChunk: true,
					},
				},
			},
			minimize: !isDev,
			minimizer: [
				new SwcJsMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
				new LightningCssMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
			],
		},

		module: {
			rules: [
				{
					test: /\.vue$/,
					loader: 'vue-loader',
					options: {
						experimentalInlineMatchResource: true,
					},
				},
				{
					test: /\.css$/,
					use: [
						{
							loader: CssExtractRspackPlugin.loader,
						},
						'css-loader',
					],
				},
				{
					test: /\.scss$/,
					use: [
						{
							loader: CssExtractRspackPlugin.loader,
						},
						'css-loader',
						'sass-loader',
					],
				},
				{
					test: /\.ts$/,
					exclude: [/node_modules/],
					loader: 'builtin:swc-loader',
					options: {
						jsc: {
							parser: {
								syntax: 'typescript',
							},
						},
						env: {
							targets,
						},
					},
					type: 'javascript/auto',
				},
				{
					test: /\.(png|jpe?g|gif|svg|webp)$/i,
					type: 'asset',
				},
				{
					test: /\.(woff2?|eot|ttf|otf)$/i,
					type: 'asset/resource',
				},
				{
					test: /\.wasm$/i,
					type: 'asset/resource',
				},
				{
					test: /\.tflite$/i,
					type: 'asset/resource',
				},
				{
					resourceQuery: /raw/,
					type: 'asset/source',
				},
			],
		},

		plugins: [
			new ProgressPlugin(),

			new VueLoaderPlugin(),

			new NodePolyfillPlugin(),

			new DefinePlugin({
				IS_DESKTOP: false,
				__IS_DESKTOP__: false,
				appName: JSON.stringify(appName),
				appVersion: JSON.stringify(appVersion),
			}),

			new CssExtractRspackPlugin({
				filename: '../css/talk-[name].css',
				chunkFilename: '../css/chunks/[id].chunk.css',
				ignoreOrder: true,
			}),

			// Bundle wasm and tflite files required for virtual background feature
			new CopyRspackPlugin({
				patterns: [
					{
						from: path.resolve(__dirname, 'node_modules', '@mediapipe/tasks-vision', 'wasm'),
						to: path.resolve(__dirname, 'js'),
					},
					{
						from: path.resolve(__dirname, 'src', 'utils', 'media', 'effects', 'virtual-background', 'vendor', 'models', 'selfie_segmenter.tflite'),
						to: path.resolve(__dirname, 'js'),
					},
				],
			}),
		],

		resolve: {
			extensions: ['*', '.ts', '.js', '.vue'],
			symlinks: false,
			fallback: {
				fs: false,
			},
		},

		cache: true,
	}
})
