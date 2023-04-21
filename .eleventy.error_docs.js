const {common:commonConfig} = require('./.eleventy.common-config.js');

module.exports = (e) => {
	commonConfig(e);

	return {
		dir: {
			data: '../11ty/data',
			input: './src-error_docs/',
			layouts: '../11ty/layouts',
			includes: '../11ty/includes',
			output: './tmp-error_docs/',
		},
	};
};
