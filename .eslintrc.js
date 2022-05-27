module.exports = {
	extends: [
		'@nextcloud',
		'plugin:@typescript-eslint/recommended',
	],
	overrides: [
		{
			files: ['**/*.spec.js'],
			rules: {
				'node/no-unpublished-import': 0,
			},
		},
	],
}
