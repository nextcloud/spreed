/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import globals from 'globals'

export default [
	...recommended,

	{
		name: 'talk/ignores',
		ignores: [
			// Generated files
			'src/types/openapi/*',
			// Temporary ignore code in documentation examples
			'docs',
			// TODO: upstream
			'openapi-*.json',
		],
	},

	{
		name: 'talk/config',
		languageOptions: {
			globals: {
				...globals.browser,
				...globals.node,
				IS_DESKTOP: 'readonly',
				__webpack_public_path__: 'writable',
			},
		},
	},

	{
		name: 'talk/jest',
		files: ['src/__mocks__/*.js', '**/*.spec.js', 'src/test-setup.js'],
		languageOptions: {
			globals: {
				...globals.jest,
			},
		},
	},

	{
		name: 'talk/disabled-during-migration',
		rules: {
			'@nextcloud-l10n/non-breaking-space': 'off', // changes translation strings
			'@stylistic/array-bracket-newline': 'off', // changes array formatting
			'@stylistic/max-statements-per-line': 'off', // non-fixable
			'@typescript-eslint/no-unused-expressions': 'off', // non-fixable
			'@typescript-eslint/no-unused-vars': 'off', // non-fixable
			'@typescript-eslint/no-use-before-define': 'off', // non-fixable
			'antfu/top-level-function': 'off', // non-fixable
			'jsdoc/check-param-names': 'off', // need to respect JS
			'jsdoc/check-tag-names': 'off', // need to respect JS
			'jsdoc/check-types': 'off', // need to respect JS
			'jsdoc/no-defaults': 'off', // need to respect JS
			'jsdoc/no-types': 'off', // need to respect JS
			'jsdoc/require-param': 'off', // need to respect JS
			'jsdoc/require-param-type': 'off', // need to respect JS
			'jsdoc/require-param-description': 'off', // need to respect JS
			'jsdoc/tag-lines': 'off', // need to respect JS
			'no-console': 'off', // non-fixable
			'no-unused-vars': 'off', // non-fixable
			'no-use-before-define': 'off', // non-fixable
			'object-shorthand': 'off', // changes Vue watchers
			'prefer-object-has-own': 'off', // changes Objet.prototype.hasOwnProperty
			'prefer-object-spread': 'off', // changes Object.assign
			'vue/first-attribute-linebreak': 'off', // changes all Vue files
			'vue/multi-word-component-names': 'off', // non-fixable
			'vue/no-boolean-default': 'off', // non-fixable
			'vue/no-required-prop-with-default': 'off', // non-fixable
			'vue/no-unused-properties': 'off', // non-fixable
			'vue/no-unused-refs': 'off', // non-fixable
		},
	},

	{
		name: 'talk/disabled-during-vue3-migration',
		rules: {
			'vue/no-deprecated-v-bind-sync': 'off',
		},
	},
]
