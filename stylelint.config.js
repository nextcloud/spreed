/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.rules['at-rule-no-unknown'] = [
	true, {
		ignoreAtRules: ['include', 'mixin', 'use', 'for'],
	},
]

if (!stylelintConfig.plugins) {
	stylelintConfig.plugins = []
}

stylelintConfig.plugins.push('stylelint-use-logical')
stylelintConfig.rules['csstools/use-logical'] = [
	'always',
	{
		// Only lint LTR-RTL properties for now
		except: [
			// Position properties
			'top',
			'bottom',
			// Position properties with directional suffixes
			/-top$/,
			/-bottom$/,
			// Size properties
			'width',
			'max-width',
			'min-width',
			'height',
			'max-height',
			'min-height',
		],
	},
]

module.exports = stylelintConfig
