const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.rules['at-rule-no-unknown'] = [
	true, {
		ignoreAtRules: ['include', 'mixin', 'use'],
	},
]

module.exports = stylelintConfig
