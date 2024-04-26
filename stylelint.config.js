/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.rules['at-rule-no-unknown'] = [
	true, {
		ignoreAtRules: ['include', 'mixin', 'use'],
	},
]

module.exports = stylelintConfig
