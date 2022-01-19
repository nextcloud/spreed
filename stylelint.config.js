const stylelintConfig = require('@nextcloud/stylelint-config')

stylelintConfig.ignoreFiles = ['css/At.scss']

stylelintConfig.rules['at-rule-no-unknown'] = [
	true, {
		ignoreAtRules: ['include', 'mixin', 'use'],
	},
]

module.exports = stylelintConfig
