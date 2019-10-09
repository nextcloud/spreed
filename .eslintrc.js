module.exports = {
	root: true,
	env: {
		browser: true,
		es6: true,
		node: true,
		jest: true
	},
	globals: {
		t: true,
		n: true,
		OC: true,
		OCA: true,
		Vue: true,
		VueRouter: true
	},
	parserOptions: {
		parser: 'babel-eslint',
		ecmaVersion: 6
	},
	extends: [
		'eslint:recommended',
		'plugin:import/errors',
		'plugin:import/warnings',
		'plugin:node/recommended',
		'plugin:vue/essential',
		'plugin:vue/recommended',
		'plugin:nextcloud/recommended',
		'standard'
	],
	settings: {
		'import/resolver': {
			webpack: {
				config: 'webpack.common.js'
			},
			node: {
				paths: ['src'],
				extensions: ['.js', '.vue']
			}
		}
	},
	plugins: ['vue', 'node'],
	rules: {
		// space before function ()
		'space-before-function-paren': ['error', 'never'],
		// curly braces always space
		'object-curly-spacing': ['error', 'always'],
		// stay consistent with array brackets
		'array-bracket-newline': ['error', 'consistent'],
		// 1tbs brace style
		'brace-style': 'error',
		// tabs only
		indent: ['error', 'tab'],
		'no-tabs': ['off'],
		'vue/html-indent': ['error', 'tab'],
		// only debug console
		'no-console': ['error', { allow: ['error', 'warn', 'info', 'debug'] }],
		// classes blocks
		'padded-blocks': ['error', { classes: 'always' }],
		// always have the operator in front
		'operator-linebreak': ['error', 'before'],
		// ternary on multiline
		'multiline-ternary': ['error', 'always-multiline'],
		// force proper JSDocs
		'valid-jsdoc': [2, {
			'prefer': {
				'return': 'returns'
			},
			'requireReturn': false,
			'requireReturnDescription': false
		}],
		// es6 import/export and require
		'node/no-unpublished-require': ['off'],
		'node/no-unsupported-features/es-syntax': ['off'],
		// PascalCase components names for vuejs
		// https://vuejs.org/v2/style-guide/#Single-file-component-filename-casing-strongly-recommended
		'vue/component-name-in-template-casing': ['error', 'PascalCase'],
		// force name
		'vue/match-component-file-name': ['error', {
			'extensions': ['jsx', 'vue', 'js'],
			'shouldMatchCase': true
		 }],
		// space before self-closing elements
		'vue/html-closing-bracket-spacing': 'error',
		// no ending html tag on a new line
		'vue/html-closing-bracket-newline': ['error', { multiline: 'never' }],
		// code spacing with attributes
		'vue/max-attributes-per-line': [
			'error',
			{
				singleline: 3,
				multiline: {
					max: 3,
					allowFirstLine: true
				}
			}
		]
	}
}
