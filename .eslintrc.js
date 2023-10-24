module.exports = {
	extends: [
		'@nextcloud',
	],
	globals: {
		// @nextcloud/webpack-vue-config globals
		appName: 'readonly',
		appVersion: 'readonly',
		// Desktop build globals
		IS_DESKTOP: 'readonly',
	},
	rules: {
		'comma-dangle': 'off',
		'jsdoc/no-defaults': 'off',
		'import/newline-after-import': 'warn',
		'import/no-named-as-default-member': 'off',
		'import/order': [
			'off', // TODO disabled with #10622 as it breaks tests and changes many components
			{
				groups: ['builtin', 'external', 'internal', ['parent', 'sibling', 'index'], 'unknown'],
				pathGroups: [
					{
						// group all style imports at the end
						pattern: '{*.css,*.scss}',
						patternOptions: { matchBase: true },
						group: 'unknown',
						position: 'after',
					},
					{
						// group material design icons
						pattern: 'vue-material-design-icons/**',
						group: 'external',
						position: 'after',
					},
					{
						// group @nextcloud imports
						pattern: '@nextcloud/{!(vue),!(vue)/**}',
						group: 'external',
						position: 'after',
					},
					{
						// group @nextcloud/vue imports
						pattern: '{@nextcloud/vue,@nextcloud/vue/**}',
						group: 'external',
						position: 'after',
					},
					{
						// group project components
						pattern: '*.vue',
						patternOptions: { matchBase: true },
						group: 'external',
						position: 'after',
					},
				],
				pathGroupsExcludedImportTypes: ['@nextcloud', 'vue-material-design-icons'],
				'newlines-between': 'always',
				alphabetize: {
					order: 'asc',
					caseInsensitive: true,
				},
				warnOnUnassignedImports: true,
			},
		],
		'import/no-unresolved': ['error', {
			// Ignore Webpack query parameters, not supported by eslint-plugin-import
			// https://github.com/import-js/eslint-plugin-import/issues/2562
			ignore: ['\\?raw$'],
		}],
		// Prepare for Vue 3 Migration
		'vue/no-deprecated-data-object-declaration': 'warn',
		'vue/no-deprecated-events-api': 'warn',
		'vue/no-deprecated-filter': 'warn',
		'vue/no-deprecated-functional-template': 'warn',
		'vue/no-deprecated-html-element-is': 'warn',
		'vue/no-deprecated-props-default-this': 'warn',
		'vue/no-deprecated-router-link-tag-prop': 'warn',
		'vue/no-deprecated-scope-attribute': 'warn',
		'vue/no-deprecated-slot-attribute': 'warn',
		'vue/no-deprecated-slot-scope-attribute': 'warn',
		'vue/no-deprecated-v-is': 'warn',
		'vue/no-deprecated-v-on-number-modifiers': 'warn',
		'vue/require-explicit-emits': 'warn',
	},
	overrides: [
		{
			files: ['**/*.spec.js'],
			rules: {
				'node/no-unpublished-import': 'off',
			},
		},
	],
}
