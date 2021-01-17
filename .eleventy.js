module.exports = (e) => {
	e.addFilter('json', (value) => {
		return JSON.stringify(value, null, "\t");
	});

	return {
	dir: {
		data: '../../../11ty/data',
		input: './twitch-clip-notes/coffeestainstudiosdevs/satisfactory',
		layouts: '../../../11ty/layouts',
		includes: '../../../11ty/includes',
		output: './src',
	},
};
};
