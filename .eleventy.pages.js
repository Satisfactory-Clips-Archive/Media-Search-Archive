const twitter_text = require('twitter-text');

module.exports = (config) => {
	config.addPlugin(require('eleventy-plugin-svg-contents'));

	config.addFilter('tweet', (tweet) => {
		return twitter_text.autoLink(
			tweet.data.text,
			{
				urlEntities: tweet.data.entities?.urls ?? [],
			}
		).replace(/rel="nofollow"/g, 'rel="nofollow noopener"');
	});

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
