/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const { resolve } = require('node:path')

// Listed packages will be transformed with babel-jest
// TODO: find a way to consolidate this in one place, with webpack.common.js
const ignorePatterns = [
	'@mdi/svg',
	'@ckpack',
	'bail',
	'ccount', // ESM dependency of remark-gfm
	'character-entities',
	'comma-separated-tokens',
	'decode-named-character-reference',
	'devlop',
	'emoji-mart-vue-fast',
	'escape-string-regexp',
	'estree-util-is-identifier-name',
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
		'<rootDir>/src/**/*.{js,ts,vue}',
	],

	testEnvironment: 'jest-environment-jsdom',
	testEnvironmentOptions: {
		customExportConditions: ['node', 'node-addons'],
	},
	moduleFileExtensions: [
		'js',
		'ts',
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
		'\\.vue$': '@vue/vue3-jest',
		'\\.tflite$': 'jest-transform-stub',
		'\\.(css|scss)$': 'jest-transform-stub',
	},
}
