module.exports = {
	extends: [
		'@nextcloud',
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
