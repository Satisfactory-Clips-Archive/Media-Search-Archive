const topics = require('../../src/topics.json');

module.exports = async () => {
	const [
		{default:satisfactory},
		{default:data},
	] = await Promise.all([
		import(
			'../../Media-Archive-Metadata/src/common/satisfactory.js'
		),
		import(
			'../../Media-Archive-Metadata/index.js'
		),
	]);

	const out = Object.assign(
		{},
		data,
		{
			"/": [
				{
					"@context": "https://schema.org",
					"@type": "WebSite",
					"url": "https://archive.satisfactory.video/",
					"potentialAction": {
						"@type": "SearchAction",
						"target": "https://archive.satisfactory.video/search?q={search_term_string}",
						"query-input": "required name=search_term_string"
					}
				}
			],
		}
	);

	Object.entries(topics).forEach((e) => {
		const [slug, data] = e;
		const permalink = `/topics/${slug}/`;

		if ( ! (permalink in out)) {
			out[permalink] = [
				{
					"@context": "https://schema.org",
					"@type": "WebPage",
					"name": data[data.length - 1],
					"description": `Satisfactory Livestream clips about ${
						data[data.length - 1]
					}`,
					"about": [
						satisfactory,
					]
				}
			];
		}
	});

	Object.entries(out).forEach((e) => {
		const [permalink, data] = e;
		const subslugs = [];

		const breadcrumbs = [];

		const maybe_topic_slugs = /^\/topics\/(.+)\//.exec(permalink);

		if (maybe_topic_slugs) {
			breadcrumbs.push(...maybe_topic_slugs[1].split('/').map(
				(subslug) => {
					subslugs.push(subslug);

					const topic = topics[subslugs.join('/')];

					return [
						topic[topic.length - 1],
						`https://archive.satisfactory.video/topics/${
							subslugs.join('/')
						}/`
					];
				}
			));
		}

		if (
			('@type' in data[0])
			&& ['Person'].includes(data[0]['@type'])
		) {
			data[0] = {
				"@context": "https://schema.org",
				"@type": "CreativeWork",
				"name": data[0].name,
				"description": `Satisfactory Livestream clips about ${
					data[0].name
				}`,
				"about": [
					data[0],
				]
			};

			if ('image' in data[0].about[0]) {
				data[0].image = data[0].about[0].image;
			}
		}

		if (
			breadcrumbs.length > 0
			&& ('@type' in data[0])
			&& ['WebPage'].includes(data[0]['@type'])
			&& ! ('breadcrumb' in data[0])
		) {
			data[0]['breadcrumb'] = {
				'@type': 'BreadcrumbList',
				'itemListElement': breadcrumbs.map((breadcrumb, i) => {
					return {
						'@type': 'ListItem',
						'additionalType': 'WebPage',
						item: breadcrumb[1],
						url: breadcrumb[1],
						name: breadcrumb[0],
						position: i,
					};
				}),
			};
		}
	})

	return out;
};
