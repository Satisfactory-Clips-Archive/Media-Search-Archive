module.exports = async () => {
	const jsonld = await require('./jsonld.js')();

	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('image' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = [
							[
								'og:image:url',
								`https://i.img.archive.satisfactory.video/content/${
									permalink.slice(0, -1)
								}.webp`
							],
							['og:image:type', 'image/webp'],
							['og:image:width', '1200'],
							['og:image:height', '628'],
							['og:image:alt', `Embed for ${row.image[0].name}`],
						];
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
