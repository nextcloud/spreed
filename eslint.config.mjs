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
			'js/*',
			// Vendor code
			'src/utils/**/vendor/*',
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
		name: 'talk/disabled-during-migration',
		rules: {
			'@nextcloud-l10n/non-breaking-space': 'off', // changes translation strings
			'@nextcloud-l10n/non-breaking-space-vue': 'off', // changes translation strings
			'@typescript-eslint/no-unused-expressions': 'off', // non-fixable
			'@typescript-eslint/no-unused-vars': 'off', // non-fixable
			'@typescript-eslint/no-use-before-define': 'off', // non-fixable
			'jsdoc/require-param-type': 'off', // need to respect JS
			'jsdoc/require-param-description': 'off', // need to respect JS
			'no-console': 'off', // non-fixable
			'no-unused-vars': 'off', // non-fixable
			'no-use-before-define': 'off', // non-fixable
			'vue/no-boolean-default': 'off', // non-fixable
			'vue/no-required-prop-with-default': 'off', // non-fixable
			'vue/no-unused-properties': 'off', // non-fixable
			'vue/no-unused-refs': 'off', // non-fixable
		},
	},
]
