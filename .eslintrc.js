module.exports = {
	extends: [
		'nextcloud'
	],
	/**
	 * Allow jest syntax in the src folder
	 */
	env: {
		jest: true
	},
	/**
	 * Allow shallow import of @vue/test-utils in order to be able to use it in 
	 * the src folder
	 */
	rules: {
		"node/no-unpublished-import": ["error", {
			"allowModules": ["@vue/test-utils"]
		}]
	}
}
