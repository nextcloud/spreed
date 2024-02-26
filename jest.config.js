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

const { resolve } = require('node:path')

// Listed packages will be transformed with babel-jest
// TODO: find a way to consolidate this in one place, with webpack.common.js
const ignorePatterns = [
	'@mdi/svg',
	'bail',
	'ccount', // ESM dependency of remark-gfm
	'comma-separated-tokens',
	'decode-named-character-reference',
	'devlop',
	'escape-string-regexp',
	'hast-.*',
	'is-.*',
	'longest-streak', // ESM dependency of remark-gfm
	'markdown-table', // ESM dependency of remark-gfm
	'mdast-.*',
	'micromark',
	'property-information',
	'rehype-.*',
	'remark-.*',
	'space-separated-tokens',
	'trim-lines',
	'trough',
	'unified',
	'unist-.*',
	'vfile',
	'vue-material-design-icons',
	'web-namespaces',
	'zwitch', // ESM dependency of remark-gfm
]

module.exports = {

	// Allow tests in the src and in tests/unit folders
	testMatch: ['<rootDir>/src/**/*.(spec|test).(ts|js)'],
	// Transform packages from top-level and nested 'node_modules'
	transformIgnorePatterns: [
		`<rootDir>/node_modules/(?!(?:.*\\/node_modules\\/)?(?:${ignorePatterns.join('|')}))`,
	],
	resetMocks: false,
	setupFiles: ['jest-localstorage-mock'],
	setupFilesAfterEnv: [
		'<rootDir>/src/test-setup.js',
		'jest-mock-console/dist/setupTestFramework.js',
	],
	globalSetup: resolve(__dirname, 'jest.global.setup.js'),

	collectCoverageFrom: [
		'<rootDir>/src/**/*.{js,vue}',
	],

	testEnvironment: 'jest-environment-jsdom',

	moduleFileExtensions: [
		'js',
		'vue',
	],

	moduleNameMapper: {
		'\\.(css|scss)$': 'jest-transform-stub',
		'^.+\\.svg(\\?raw)?$': '<rootDir>/src/__mocks__/svg.js',
		'vendor/tflite/(.*).wasm$': '<rootDir>/src/utils/media/effects/virtual-background/vendor/tflite/$1.js',
	},

	transform: {
		'\\.ts$': ['ts-jest', {
			useESM: true,
			tsconfig: {
				verbatimModuleSyntax: false,
			},
		}],
		'\\.js$': 'babel-jest',
		'\\.vue$': '@vue/vue2-jest',
		'\\.tflite$': 'jest-transform-stub',
		'\\.(css|scss)$': 'jest-transform-stub',
	},
}
