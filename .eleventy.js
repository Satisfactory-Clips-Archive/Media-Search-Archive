const {common:commonConfig} = require('./.eleventy.common-config.js');

module.exports = (e) => {
	commonConfig(e);

	return {
		dir: {
			data: '../../11ty/data',
			input: './video-clip-notes/docs',
			layouts: '../../11ty/layouts',
			includes: '../../11ty/includes',
			output: './src',
		},
	};
};
