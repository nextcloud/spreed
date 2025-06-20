/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const nextcloudWebpackRules = require('@nextcloud/webpack-vue-config/rules')
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')
const { mergeWithRules } = require('webpack-merge')

// Replace rules with the same modules
module.exports = mergeWithRules({
	module: {
		rules: {
			test: 'match',
			loader: 'replace',
			options: 'replace',
			use: 'replace',
		},
	},
})(
	{
		module: {
		// Reuse @nextcloud/webpack-vue-config/rules
			rules: Object.values(nextcloudWebpackRules),
		},
	},
	{
		module: {
			rules: [
				{
					test: /\.js$/,
					loader: 'esbuild-loader',
					options: {
					// Implicitly set as JS loader for only JS parts of Vue SFCs will be transpiled
						loader: 'js',
						target: 'es2020',
					},
					exclude: BabelLoaderExcludeNodeModulesExcept([
						'@nextcloud/event-bus',
						'ansi-regex',
						'fast-xml-parser',
						'hot-patcher',
						'nextcloud-vue-collections',
						'semver',
						'strip-ansi',
						'tributejs',
						'webdav',
					]),
				},
				{
					test: /\.tsx?$/,
					use: [{
						loader: 'esbuild-loader',
						options: {
						// Implicitly set as TS loader so only <script lang="ts"> Vue SFCs will be transpiled
							loader: 'ts',
							target: 'es2020',
						},
					}],
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
					test: /\.worker\.js$/,
					use: { loader: 'worker-loader' },
				},
				{
					resourceQuery: /raw/,
					type: 'asset/source',
				},
			],
		},
	},
)
