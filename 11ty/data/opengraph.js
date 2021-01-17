const jsonld = require('./jsonld.js');

module.exports = () => {
	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('image' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = [];
					}

					row.image.forEach((url) => {
						out[permalink].push(['og:image', url]);
					});
				}
			});

			return out;
		},
		{}
	);

	return out;
};
