module.exports = {
	extends: [
		'@nextcloud',
	],
	rules: {
		'import/newline-after-import': 1,
		'import/order': [
			'warn',
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
	},
	overrides: [
		{
			files: ['**/*.spec.js'],
			rules: {
				'node/no-unpublished-import': 0,
			},
		},
	],
}
