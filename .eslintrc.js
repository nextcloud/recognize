module.exports = {
	extends: [
		'@nextcloud'
	],
	parserOptions: {
		requireConfigFile: false,
	},
	rules: {
		"n/no-unpublished-import": "off",
		"n/no-process-exit": "off",
		"no-console": "off",
		"n/no-missing-require": "off",
	}
}
