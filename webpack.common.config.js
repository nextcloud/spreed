/*
 * @copyright Copyright (c) 2022 Grigorii Shartsev <me@shgk.me>
 *
 * @author Grigorii Shartsev <me@shgk.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')

const nextcloudWebpackRules = require('@nextcloud/webpack-vue-config/rules')

// Edit JS rule
nextcloudWebpackRules.RULE_JS.exclude = BabelLoaderExcludeNodeModulesExcept([
	'@nextcloud/event-bus',
	'ansi-regex',
	'fast-xml-parser',
	'hot-patcher',
	'nextcloud-vue-collections',
	'semver',
	'strip-ansi',
	'tributejs',
	'webdav',
])

module.exports = {
	module: {
		rules: [
			// Reuse @nextcloud/webpack-vue-config/rules
			...Object.values(nextcloudWebpackRules),

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
		],
	},
}
