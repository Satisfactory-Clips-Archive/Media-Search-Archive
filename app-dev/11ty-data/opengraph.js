const {
	readFileSync,
} = require('fs');
const {
	createHash,
} = require('crypto');

module.exports = async () => {
	const jsonld = await require('./jsonld.js')();

	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('image' in row) {
					if ( ! (permalink in out)) {
						const hash = createHash('sha512');
						hash.update(readFileSync(
							`${__dirname}/../../images/internal/content/${
								permalink.slice(0, -1)
							}.png`
						));

						out[permalink] = [
							[
								'og:image:url',
								`https://i.img.archive.satisfactory.video/content/${
									permalink.slice(0, -1)
								}.png?h=${
									encodeURIComponent(
										hash.digest('hex').slice(0, 4)
									)
								}`
							],
							['og:image:type', 'image/png'],
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
