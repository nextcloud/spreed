/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const path = require('node:path')

const { EsbuildPlugin } = require('esbuild-loader')
const webpack = require('webpack')
const { mergeWithRules } = require('webpack-merge')

const nextcloudWebpackConfig = require('@nextcloud/webpack-vue-config')

const commonWebpackConfig = require('./webpack.common.config.js')

const IS_PROD = process.env.NODE_ENV === 'production'

module.exports = mergeWithRules({
	module: {
		// Rules from @nextcloud/webpack-vue-config/rules already added by commonWebpackConfig
		rules: 'replace',
	},
	optimization: {
		minimizer: 'replace',
	},
})(nextcloudWebpackConfig, commonWebpackConfig, {
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
	},

	output: {
		assetModuleFilename: '[name][ext]?v=[contenthash]',
	},

	optimization: {
		splitChunks: {
			cacheGroups: {
				defaultVendors: {
					reuseExistingChunk: true,
				},
			},
		},
		minimizer: [
			new EsbuildPlugin({
				target: 'es2020',
			}),
		],
	},

	plugins: [
		new webpack.DefinePlugin({ IS_DESKTOP: false }),
	],

	cache: true,

	devtool: IS_PROD
		// .js.map files with original file names and lines but without the source code
		// Tradeoff choice between dist size and features
		? 'nosources-source-map'
		// High-quality SourceMaps with faster rebuild for development
		: 'eval-source-map',
})
