module.exports = (config) => {
	config.addPlugin(require('eleventy-plugin-svg-contents'));

	return Object.assign(
		{},
		require('./.eleventy.js'),
		{
			dir: {
				data: '../data',
				input: './11ty/pages/',
				layouts: '../layouts',
				includes: '../includes',
				output: './src',
			}
		}
	);
};
