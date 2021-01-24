module.exports = async () => {
	const jsonld = await require('./jsonld.js')();

	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('image' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = [];
					}

					row.image.forEach((ImageObject) => {
						out[permalink].push(
							['og:image:url', ImageObject.contentUrl],
							['og:image:type', ImageObject.encodingFormat],
							['og:image:width', ImageObject.width.value],
							['og:image:height', ImageObject.height.value],
							['og:image:alt', ImageObject.name],
						);
					});
				}
			});

			return out;
		},
		{}
	);

	return out;
};
