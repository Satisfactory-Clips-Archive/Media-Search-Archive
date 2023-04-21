const twitter_text = require('twitter-text');
const {common:commonConfig} = require('./.eleventy.common-config.js');

module.exports = (config) => {
	commonConfig(config);

	config.addFilter('tweet', (tweet) => {
		return twitter_text.autoLink(
			tweet.data.text,
			{
				urlEntities: tweet.data.entities?.urls ?? [],
			}
		).replace(/rel="nofollow"/g, 'rel="nofollow noopener"').replace('\n', '<br>\n');
	});

	config.addFilter('format_date', (date_str) => {
		const date = new Date(date_str);

		const ordinals = [
			'th', // 0th
			'st', // 1st
			'nd', // 2nd
			'rd', // 3rd
			'th', // 4th
			'th', // 5th
			'th', // 6th
			'th', // 7th
			'th', // 8th
			'th', // 9th
			'th', // 10th
			'th', // 11th
			'th', // 12th
			'th', // 13th
			'th', // 14th
			'th', // 15th
			'th', // 16th
			'th', // 17th
			'th', // 18th
			'th', // 19th
		];

		return (
			date.toLocaleDateString(
				'en-GB',
				{ month: 'long'}
			)
			+ ' '
			+ date.getDate()
			+ ordinals[date.getDate() % 20]
			+ ', '
			+ date.toLocaleDateString(
				'en-GB',
				{ year: 'numeric'}
			)
		);
	});

	return Object.assign(
		{},
		require('./.eleventy.js'),
		{
			dir: {
				data: '../data',
				input: './11ty/tweets/',
				layouts: '../layouts',
				includes: '../includes',
				output: './src',
			}
		}
	);
};
