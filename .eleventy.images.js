module.exports = Object.assign(
	{},
	require('./.eleventy.js'),
	{
		dir: {
			data: '../img-data',
			input: './11ty/img-pages/',
			layouts: '../layouts',
			includes: '../includes',
			output: './images-tmp/internal/',
		}
	}
);
