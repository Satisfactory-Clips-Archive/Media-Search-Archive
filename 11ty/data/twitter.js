const twitter_card_defaults = [
	['twitter:card', 'summary'],
];
const twitter_url_regex = /^https\:\/\/(?:mobile\.|www\.)?twitter\.com\/([^?]+)$/;
const page_about_thing_regex = /^Satisfactory Livestream clips about (.+)$/;

module.exports = async () => {
	const jsonld = await require('./jsonld.js')();

	const out = Object.entries(jsonld).reduce(
		(out, e) => {
			const [permalink, data] = e;

			data.forEach((row) => {
				if ('description' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = Object.fromEntries(
							twitter_card_defaults
						);
					}

					if ( ! ('twitter:description' in out[permalink])) {
						out[permalink][
							'twitter:description'
						] = row.description;
					}

					if ( ! ('twitter:title' in out[permalink])) {
						out[permalink][
							'twitter:title'
						] = row.description;
					}
				}

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

				if (
					(permalink in out)
					&& ('twitter:title' in out[permalink])
					&& 'name' in row
					&& [
						'Person',
						'WebPage',
						'CreativeWork',
					].includes(row['@type'])
				) {
					let maybe_urls = [];

					if (
						'Person' === row['@type']
						&& 'url' in row
					) {
						maybe_urls.push(...row.url);
					} else if (
						['WebPage', 'CreativeWork'].includes(row['@type'])
						&& 'about' in row
						&& 1 === row.about.length
						&& 'Person' === row.about[0]['@type']
						&& 'name' in row.about[0]
						&& 'url' in row.about[0]
						&& page_about_thing_regex.test(
							out[permalink]['twitter:title']
						)
					) {
						maybe_urls.push(...row.about[0].url);
					}

					const maybe_twitter = maybe_urls.find((url) => {
						return twitter_url_regex.test(url);
					});

					const jsonld_name_check = page_about_thing_regex.exec(
						out[permalink]['twitter:title']
					);

					if (
						jsonld_name_check
						&& maybe_twitter
					) {
						out[permalink]['twitter:title'] =
							`Satisfactory Livestream clips about @${
								twitter_url_regex.exec(maybe_twitter)[1]
							}`
						;
					}
				}

				if ('image' in row) {
					if ( ! (permalink in out)) {
						out[permalink] = Object.fromEntries(
							twitter_card_defaults
						);
					}

					if ( ! ('twitter:image' in out[permalink])) {
						const ImageObject = row.image[0];
						out[permalink][
							'twitter:image'
						] = ImageObject.contentUrl;
						out[permalink]['twitter:image:alt'] = ImageObject.name;
					}
				}

				if (permalink in out) {
					if ( ! ('twitter:site' in out[permalink])) {
						out[permalink]['twitter:site'] = '@SignpostMarv';
					}

					if ( ! ('twitter:creator' in out[permalink])) {
						out[permalink]['twitter:creator'] = '@SatisfactoryAF';
					}
				}
			});

			return out;
		},
		{}
	);

	return out;
};
