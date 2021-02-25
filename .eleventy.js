module.exports = (e) => {
	const markdownIt = require('markdown-it');

	e.setLibrary('md', markdownIt({
		html: true,
		breaks: true,
		linkify: true,
	}));

	e.addFilter('json', (value) => {
		return JSON.stringify(value, null, "\t");
	});

	e.addFilter('jsonld_description', (value) => {
		let maybe = value.find((e) => {
			return 'description' in e;
		});

		if (maybe) {
			return maybe.description;
		}

		maybe = value.find((e) => {
			return (
				('@type' in e)
				&& ['Person', 'Article'].includes(e['@type'])
				&& ('name' in e)
			);
		});

		if (maybe) {
			return `Satisfactory Lviestream clips about ${maybe.name}.`;
		}

		return 'Serves as an unofficial archive for Q&A Clips for Coffee Stain Studio\'s Satisfactory-related livestreams';
	});

	return {
		dir: {
			data: '../../../11ty/data',
			input: './video-clip-notes/coffeestainstudiosdevs/satisfactory',
			layouts: '../../../11ty/layouts',
			includes: '../../../11ty/includes',
			output: './src',
		},
	};
};
