const twitter_text = require('twitter-text');

module.exports = (config) => {
	config.addPlugin(require('eleventy-plugin-svg-contents'));

	return Object.assign(
		{},
		require('./.eleventy.js'),
		{
			dir: {
				data: '../data',
				input: './11ty/pages-alt-layouts/',
				layouts: '../layouts',
				includes: '../includes',
				output: './src',
			}
		}
	);
};
