/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommendedVue2 } from '@nextcloud/eslint-config'

export default [
	...recommendedVue2,
	// Skip OpenAPI generated files
	{
		ignores: ['src/types/openapi/*'],
	},
	// Disabled rules from recommendedVue2 pack
	{
		rules: {
			'@nextcloud-l10n/non-breaking-space': 'off', // changes translation strings
			'@stylistic/array-bracket-newline': 'off', // changes array formatting
			'@stylistic/function-paren-newline': 'off', // weird formatting
			'@stylistic/implicit-arrow-linebreak': 'off', // weird formatting
			'@stylistic/max-statements-per-line': 'off', // non-fixable
			'@stylistic/member-delimiter-style': 'off', // removes commas from types
			'@typescript-eslint/no-unused-expressions': 'off', // non-fixable
			'@typescript-eslint/no-unused-vars': 'off', // non-fixable
			'@typescript-eslint/no-use-before-define': 'off', // non-fixable
			eqeqeq: 'off', // non-fixable
			'jsdoc/check-param-names': 'off', // need to respect JS
			'jsdoc/check-tag-names': 'off', // need to respect JS
			'jsdoc/check-types': 'off', // need to respect JS
			'jsdoc/no-defaults': 'off', // need to respect JS
			'jsdoc/no-types': 'off', // need to respect JS
			'jsdoc/require-param': 'off', // need to respect JS
			'jsdoc/require-param-description': 'off', // need to respect JS
			'jsdoc/tag-lines': 'off', // need to respect JS
			'no-console': 'off', // non-fixable
			'no-constant-binary-expression': 'off', // non-fixable
			'no-constant-condition': 'off', // non-fixable
			'no-empty': 'off', // non-fixable
			'no-redeclare': 'off', // non-fixable
			'no-restricted-imports': 'off', // non-fixable
			'no-undef': 'off', // non-fixable
			'no-unused-vars': 'off', // non-fixable
			'no-use-before-define': 'off', // non-fixable
			'no-useless-concat': 'off', // non-fixable
			'object-shorthand': 'off', // changes Vue watchers
			'perfectionist/sort-imports': 'off',
			'perfectionist/sort-named-exports': 'off',
			'perfectionist/sort-named-imports': 'off',
			'prefer-const': 'off', // non-fixable
			'prefer-object-has-own': 'off', // changes Objet.prototype.hasOwnProperty
			'prefer-object-spread': 'off', // changes Object.assign
			'vue/first-attribute-linebreak': 'off', // changes all Vue files
			'vue/multi-word-component-names': 'off', // non-fixable
			'vue/no-boolean-default': 'off', // non-fixable
			'vue/no-required-prop-with-default': 'off', // non-fixable
			'vue/no-unused-properties': 'off', // non-fixable
			'vue/no-unused-refs': 'off', // non-fixable
			'vue/no-useless-mustaches': 'off', // changes template
			'vue/object-curly-newline': 'off', // changes newlines
			'vue/order-in-components': 'off', // moves code
		},
	},
]
