/*
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license GNU AGPL version 3 or any later version
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
 *
 */

// TODO: find a way to consolidate this in one place, with webpack.common.js
const ignorePatterns = [
	'vue-material-design-icons',
	'@juliushaertl',
	'@nextcloud/vue-richtext',
	'tributejs',
	'@nextcloud/vue',
	'splitpanes',
	'string-length',
	'strip-ansi',
	'ansi-regex',
	'char-regex',
	'uuid',
]

module.exports = {

	// Allow tests in the src and in tests/unit folders
	testMatch: ['<rootDir>/src/**/*.(spec|test).(ts|js)'],
	transformIgnorePatterns: [
		'node_modules/(?!(' + ignorePatterns.join('|') + ')/)',
	],
	resetMocks: false,
	setupFiles: ['jest-localstorage-mock'],
	setupFilesAfterEnv: [
		'<rootDir>/src/test-setup.js',
		'jest-mock-console/dist/setupTestFramework.js',
	],
	collectCoverageFrom: [
		'<rootDir>/src/**/*.{js,vue}',
	],

	testEnvironment: 'jest-environment-jsdom',

	moduleFileExtensions: [
		'js',
		'vue',
	],

	transform: {
		// process `*.js` files with `babel-jest`
		'.*\\.(js)$': 'babel-jest',
		'^.+\\.vue$': '@vue/vue2-jest',
		'src/utils/media/effects/virtual-background/vendor/*': 'jest-transform-stub',
	},
}
