const jsonld = require('./jsonld.js');
const twitter_card_defaults = [
	['twitter:card', 'summary'],
];

module.exports = () => {
	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('name' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = Object.fromEntries(
							twitter_card_defaults
						);
					}

					if ( ! ('twitter:title' in out[permalink])) {
						out[permalink]['twitter:title'] = row.name;
					}
				}
				if ('image' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = Object.fromEntries(
							twitter_card_defaults
						);
					}

					if ( ! ('twitter:image' in out[permalink])) {
						out[permalink]['twitter:image'] = row.image[0];
					}
				}
			});

			return out;
		},
		{}
	);

	return out;
};
