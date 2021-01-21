module.exports = Object.assign(
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
