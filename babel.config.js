module.exports = {
	presets: [
		[
			'@babel/preset-env',
			{
				corejs: 3,
				useBuiltIns: 'usage',
				modules: false,
			},
		],
	],
}
